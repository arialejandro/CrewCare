<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\MedicCredential;
use App\Support\SepRegistry;
use App\Support\CredentialTelemetry;
use App\Support\CedulaVerifier;

/**
 * MedicCredentialController — captura y validación de la CÉDULA PROFESIONAL (Paso B).
 *
 * Dos acciones, y la diferencia entre ellas es TODO el módulo:
 *   · save()   = CAPTURAR el dato. Es data entry. La cédula nace SIEMPRE pendiente.
 *   · verify() = VALIDAR el dato contra el registro oficial. Es un ACTO DE AUTORIDAD.
 *
 * ── POR QUÉ ESTO NO CALCA EL MOLDE DEL SDS (desviación deliberada) ─────────────────
 * En `ConsumableController::store` una ficha capturada por alguien con `sds.manage` NACE
 * VERIFICADA, "sin fricción para el admin". Aquí NO, y la diferencia no es de estilo:
 *
 *   1) NADIE VALIDA SU PROPIA CÉDULA. Un médico que se autoacredita convierte el badge en
 *      un sello de goma: la señal que el módulo existe para dar (alguien externo cotejó
 *      esta licencia) desaparece. Ver assertNotSelfVerification().
 *   2) NINGUNA CÉDULA NACE VERIFICADA, ni siquiera capturada por un super-admin. Escribir
 *      un número no es haberlo cotejado. En el SDS el que captura PUEDE avalar porque está
 *      viendo la sustancia; aquí el hecho a avalar vive en un registro EXTERNO que hay que
 *      ir a consultar. Teclear no es consultar.
 *   3) LA RE-VERIFICACIÓN AL EDITAR NO DEPENDE DEL PERMISO. `SafetyStandardController`
 *      omite el bloque de re-apertura razonando que "quien puede editar es, por
 *      construcción, quien puede avalar". Ese razonamiento NO aplica a un dato externo:
 *      cambiar el número cambia QUÉ LICENCIA se está afirmando, y el cotejo anterior ya no
 *      dice nada sobre la nueva. Se re-abre siempre. Ver save().
 *
 * PERMISOS: `medic.credential.manage` valida (lo reparte MedicCredentialPermissionsSeeder y
 * NO lo tiene el rol `medic`). Capturar puede hacerlo además el propio médico sobre su
 * ficha. Ver el docblock de MedicCredentialPermissionsSeeder.
 *
 * DEGRADACIÓN: sin la tabla (SQL no aplicado) las dos acciones avisan y no rompen nada.
 */
class MedicCredentialController extends Controller
{
    /** Fuentes admitidas al escribir. `sep_auto` lo emitirá el sub-paso diferido, no esta clase. */
    const ALLOWED_SOURCES = [MedicCredential::SOURCE_MANUAL, MedicCredential::SOURCE_SEP_AUTO];

    /**
     * Disponibilidad del módulo (NO es autorización). Espejo de ConsumableController::guard().
     *
     * @return \Illuminate\Http\RedirectResponse|null
     */
    protected function guard()
    {
        if (!MedicCredential::supportsCredentials()) {
            return redirect()->back()
                ->with('error', 'El módulo de cédula profesional aún no está disponible en esta instancia (falta aplicar la migración de base de datos).');
        }

        return null;
    }

    // ---------------------------------------------------------------------------
    // CAPTURA
    // ---------------------------------------------------------------------------

    /**
     * Crea o actualiza la cédula de un médico. SIEMPRE deja la ficha en PENDIENTE cuando el
     * número cambia (o cuando es nueva). Validar es otra acción, con otra autoridad.
     */
    public function save(Request $request, $id)
    {
        if ($resp = $this->guard()) {
            return $resp;
        }

        $target = User::findOrFail($id);
        $actor  = $request->user();

        // INVARIANTE DEL MÓDULO: solo un CLÍNICO (médico o médico beta) tiene cédula. La BD no
        // puede expresarlo (no sabe de roles de Spatie), así que la puerta está aquí. Esto define
        // quién puede RECIBIR cédula (el target); quién puede ASIGNARLA/validarla es otra cosa
        // (canCapture + permiso de ruta + "no validas la tuya"), y NO se amplía aquí.
        if (!$target->isClinician()) {
            return redirect()->back()
                ->with('error', 'Solo un usuario con rol clínico (médico o médico beta) puede tener cédula profesional. Asígnale primero el rol.');
        }

        if (!$this->canCapture($actor, $target)) {
            abort(403);
        }

        $data = $request->validate([
            'cedula'           => 'required|string|max:40',
            'profession'       => 'nullable|string|max:150',
            'specialty'        => 'nullable|string|max:150',
            'registered_name'  => 'nullable|string|max:255',
            // (2026-07-24) `verification_url` DEJÓ DE CAPTURARSE: el registro de la SEP es un
            // buscador por POST y no publica una URL por cédula, así que el campo pedía algo
            // imposible. La regla se conserva por si una instancia vieja aún lo manda; la columna
            // sigue en la BD con los datos históricos y dentro de los snapshots ya firmados.
            'verification_url' => 'nullable|url|max:500',
        ], [
            'cedula.required'   => 'El número de cédula es obligatorio.',
            'verification_url.url' => 'El enlace de cotejo no es una URL válida.',
        ]);

        $data['cedula'] = trim($data['cedula']);

        $credential = MedicCredential::where('user_id', $target->id)->first();

        // UNIQUE(cedula) en la BD es la última línea de defensa; aquí se convierte en un
        // mensaje legible en vez de una excepción SQL cruda. Un duplicado NO es un typo
        // benigno: es exactamente la señal que el módulo busca.
        $clash = MedicCredential::where('cedula', $data['cedula'])
            ->where('user_id', '!=', $target->id)
            ->first();
        if ($clash !== null) {
            return redirect()->back()->withInput()
                ->with('error', 'Esa cédula ya está registrada a nombre de otra persona. Dos profesionistas no pueden compartir número: verifícalo antes de continuar.');
        }

        $reopened           = false;
        $numberChangedOrNew = false;

        if ($credential === null) {
            // NACE PENDIENTE, sin excepción. Ver el docblock de la clase (punto 2).
            $data['user_id']             = $target->id;
            $data['verified_at']         = null;
            $data['verified_by_id']      = null;
            $data['verification_source'] = null;
            $data['verified_snapshot']   = null;
            $credential = MedicCredential::create($data);
            $numberChangedOrNew = true;
        } else {
            // Se decide ANTES del update(): después, getOriginal() ya devolvería el valor
            // nuevo y la comparación siempre diría "sin cambios" (mismo motivo que
            // ConsumableController::update).
            $numberChanged      = trim((string) $credential->getOriginal('cedula')) !== $data['cedula'];
            $numberChangedOrNew = $numberChanged;

            if ($credential->isVerified() && $numberChanged) {
                $data['verified_at']         = null;
                $data['verified_by_id']      = null;
                $data['verification_source'] = null;
                $data['verified_snapshot']   = null;
                $reopened = true;
            }

            $credential->update($data);
        }

        // ── ENGANCHE AUTOMÁTICO (sub-paso sep_auto) ─────────────────────────────────
        // Solo se dispara al CAPTURAR o EDITAR EL NÚMERO. Esto ES el "no re-consultar": una
        // edición que no toca el número (profesión, enlace…) NO gasta una consulta a la
        // fuente, y una cédula ya verificada con el mismo número tampoco. Además va detrás
        // del flag CEDULA_AUTO_VERIFY (OFF por defecto). NUNCA bloquea: si algo falla,
        // attemptAutoVerify degrada a manual y devuelve una nota para el flash.
        $autoNote = null;
        if ($numberChangedOrNew) {
            $autoNote = $this->attemptAutoVerify($credential, $target);
            $credential->refresh();   // reflejar lo que el automático haya escrito.
        }

        // El mensaje se arma sobre el ESTADO FINAL real de la cédula. OJO: "VERIFICADA
        // automáticamente" solo es honesto si el enganche CORRIÓ EN ESTE request (número
        // nuevo/cambiado). Editar un campo que NO es el número sobre una cédula ya verificada
        // por sep_auto NO vuelve a consultar la fuente (invariante de "no re-consultar"), así
        // que afirmar que se cotejó ahora sería mentira. Por eso el primer branch va gateado
        // por $numberChangedOrNew. (Detectado en la revisión adversarial, 2026-07-20.)
        if ($numberChangedOrNew && $credential->isVerified() && $credential->verification_source === MedicCredential::SOURCE_SEP_AUTO) {
            $message = 'Cédula guardada y VERIFICADA automáticamente contra el registro público.';
        } elseif ($reopened) {
            $message = 'Cédula actualizada. Cambió el NÚMERO, así que volvió a PENDIENTE DE VERIFICACIÓN: hay que cotejarla de nuevo contra el registro oficial.';
        } elseif (!$numberChangedOrNew && $credential->isVerified()) {
            // Edición que no tocó el número sobre una cédula ya verificada: sigue verificada,
            // pero NO se re-consultó nada. Mensaje de estado neutro, sin afirmar un cotejo.
            $message = 'Cédula guardada. Sigue verificada (no cambió el número, no se volvió a consultar el registro).';
        } else {
            $message = 'Cédula guardada.';
        }
        if (is_string($autoNote) && $autoNote !== '') {
            $message .= ' ' . $autoNote;
        }

        $redirect = redirect()->back()->with('success', $message);

        // Guardar una URL de dominio ajeno no es un error (el dato se conserva), pero NO se
        // pintará como enlace. Avisar evita que alguien crea que el enlace quedó puesto.
        if (!empty($data['verification_url']) && SepRegistry::safeHref($data['verification_url']) === null) {
            $redirect->with('warning', 'El enlace guardado NO apunta al registro oficial de la SEP, así que no se mostrará como enlace en la ficha. Revísalo.');
        }

        return $redirect;
    }

    // ---------------------------------------------------------------------------
    // ENGANCHE AUTOMÁTICO (sub-paso sep_auto)
    // ---------------------------------------------------------------------------

    /**
     * Intenta encender el badge AUTOMÁTICAMENTE consultando la fuente pública (BúhoLegal) con
     * la receta del PoC: GET (csrf+cookies) → POST → parsear tabla. Ver App\Support\CedulaVerifier.
     *
     * NUNCA BLOQUEA NI LANZA. Cualquier fallo degrada al modo MANUAL: la cédula queda pendiente
     * y el botón "Validar" sigue disponible. El automático es una comodidad, no una barrera.
     *
     * Debe llamarse SOLO cuando el número es nuevo o cambió — el llamador lo garantiza (es la
     * cortesía con la fuente: no re-consultar por ediciones que no tocan el número).
     *
     * @return string|null  Nota corta para el flash, o null si la feature está apagada / verificó
     *                       en silencio (el mensaje base ya lo dice) / no había nada que hacer.
     */
    protected function attemptAutoVerify(MedicCredential $credential, User $target)
    {
        // Interruptor maestro (OFF por defecto). Apagado = todo sigue en modo manual.
        if (!CedulaVerifier::enabled()) {
            return null;
        }
        if (!MedicCredential::supportsCredentials()) {
            return null;
        }
        // Defensa extra del "no re-consultar": si por lo que sea ya llega verificada, no se
        // gasta una consulta a la fuente.
        if ($credential->isVerified()) {
            return null;
        }

        // BLINDAJE TOTAL. lookup() ya es no-lanzante, pero el WRITE-BACK de éxito (el
        // $credential->update de más abajo) es una escritura a BD que SÍ puede lanzar
        // (deadlock, lock-wait, caída de conexión, rechazo del JSON). Si eso subiera sin
        // atrapar, save() respondería 500 y el "automático nunca bloquea" se rompería: la
        // ficha YA está persistida como pendiente (save la escribió antes de llamar aquí),
        // así que ante cualquier \Throwable degradamos a manual y avisamos al dev — el mismo
        // patrón que usa verify(). (Detectado en la revisión adversarial, 2026-07-20.)
        try {
            $result = CedulaVerifier::lookup($credential->cedula);

            // (1) Fuente caída / HTML cambió → alerta TÉCNICA (correo al dev) + manual.
            if ($result->isError()) {
                CredentialTelemetry::report(CredentialTelemetry::REASON_SOURCE_DOWN, [
                    // Solo diagnóstico técnico: ID interno + código de error. Ni nombre ni
                    // cédula salen de la app (CredentialTelemetry los filtra igual, por si acaso).
                    'user_id'       => $target->id,
                    'credential_id' => $credential->id,
                    'reason'        => $result->reason,
                    'http'          => $result->httpStatus,
                    'where'         => 'CedulaVerifier::lookup',
                ]);
                return 'No se pudo consultar el registro automáticamente ahora; queda PENDIENTE para validar a mano (ya se avisó al equipo).';
            }

            // (2) La fuente respondió pero el número no existe → señal de NEGOCIO, sin correo.
            if ($result->isEmpty()) {
                Log::info('CedulaVerifier: número no encontrado en el registro público (user #' . $target->id . ', credencial #' . $credential->id . ').');
                return 'El número no aparece en el registro público. Revísalo; queda PENDIENTE.';
            }

            // (3) Hay resultado. Cotejo antisuplantación: nombre del REGISTRO vs nombre en la app.
            $fetchedName = (string) $result->name;
            if (!SepRegistry::namesMatch($fetchedName, $target->fullName())) {
                // Alerta de NEGOCIO (posible suplantación) → sin correo al dev. La ausencia de
                // badge ES la señal, igual que en el flujo manual.
                // Señal de NEGOCIO, log-only. Registrar QUÉ nombres difieren dejaría datos
                // personales en laravel.log; el diff exacto ya se muestra en pantalla a quien
                // puede actuar (más abajo, en el flujo manual). Aquí solo el ID interno.
                CredentialTelemetry::report(CredentialTelemetry::REASON_NAME_MISMATCH, [
                    'user_id'       => $target->id,
                    'credential_id' => $credential->id,
                    'source'        => 'sep_auto',
                ]);
                return 'El registro devolvió el nombre «' . $fetchedName . '», que NO coincide con el del titular. NO se validó; queda PENDIENTE.';
            }

            // (4) Coincide → badge AUTOMÁTICO. verified_by_id = null es el "segundo NULL" que la
            // tabla ya contemplaba: validada por la fuente, sin autor humano. source = sep_auto.
            $credential->update([
                'verified_at'         => now(),
                'verified_by_id'      => null,
                'verification_source' => MedicCredential::SOURCE_SEP_AUTO,
                // Backfill solo si el campo venía vacío: no se pisa lo que un humano ya capturó.
                'registered_name'     => $credential->registered_name ?: $fetchedName,
                'profession'          => $credential->profession ?: ($result->profession ?: null),
                'specialty'           => $credential->specialty ?: ($result->specialty ?: null),
                'verified_snapshot'   => [
                    'source'           => MedicCredential::SOURCE_SEP_AUTO,
                    'cedula'           => $credential->cedula,
                    'registered_name'  => $fetchedName,
                    'compared_against' => $target->fullName(),
                    'level'            => $result->level,
                    'is_specialist'    => $result->isSpecialist(),
                    'rows'             => $result->rows,   // lo que devolvió la tabla, íntegro.
                    'checked_at'       => now()->toDateTimeString(),
                ],
            ]);

            return null;   // el mensaje base ya dirá "VERIFICADA automáticamente".
        } catch (\Throwable $e) {
            Log::error('MedicCredentialController::attemptAutoVerify — ' . $e->getMessage());
            CredentialTelemetry::report(CredentialTelemetry::REASON_TECHNICAL, [
                'user_id'       => $target->id,
                'credential_id' => $credential->id,
                'error'         => $e->getMessage(),
                'where'         => 'MedicCredentialController::attemptAutoVerify',
            ]);
            return 'No se pudo completar la verificación automática por un problema técnico; queda PENDIENTE para validar a mano (ya se avisó al equipo).';
        }
    }

    // ---------------------------------------------------------------------------
    // VALIDACIÓN
    // ---------------------------------------------------------------------------

    /**
     * Enciende el badge verificado: cotejo MANUAL con autovalidación de nombre.
     *
     * La autovalidación es el corazón antisuplantación: no basta con que alguien pulse
     * "Validar". El nombre que devuelve el registro (`registered_name`) tiene que coincidir
     * con el del titular en la app. Si no coincide, el badge NO se enciende — y esa AUSENCIA
     * es la señal. No se inventa un estado "rechazado": la cédula simplemente sigue pendiente.
     */
    public function verify(Request $request, $id)
    {
        if ($resp = $this->guard()) {
            return $resp;
        }

        $target = User::findOrFail($id);
        $actor  = $request->user();

        if (!$target->isClinician()) {
            return redirect()->back()
                ->with('error', 'Solo un usuario con rol clínico (médico o médico beta) puede tener cédula profesional.');
        }

        // Validar SÍ exige el permiso: capturar es data entry, avalar es autoridad.
        if ($actor === null || !$actor->can('medic.credential.manage')) {
            abort(403);
        }

        // (2026-07-24) DECLARACIÓN DE COTEJO obligatoria. La app NO puede comprobar que alguien
        // abrió de verdad el registro de la SEP: no hay API pública ni URL por cédula. Lo único
        // honesto es exigir que lo AFIRME de forma explícita y dejar constancia de quién lo hizo.
        // El acto acredita a esta persona para ejercer y firmar actos médicos en la producción,
        // así que se trata como una declaración, no como un clic de sistema.
        if (! $request->boolean('attestation')) {
            return redirect()->back()
                ->with('error', 'Para validar la cédula tienes que marcar la declaración de cotejo: la validación se registra a tu nombre y te hace responsable de ella.');
        }

        // NADIE VALIDA SU PROPIA CÉDULA. Ver docblock de la clase (punto 1).
        if ((int) $actor->id === (int) $target->id) {
            return redirect()->back()
                ->with('error', 'No puedes validar tu propia cédula. La validación tiene que hacerla otra persona: un sello puesto por uno mismo no acredita nada.');
        }

        $credential = MedicCredential::where('user_id', $target->id)->first();
        if ($credential === null) {
            return redirect()->back()
                ->with('error', 'Este médico todavía no tiene cédula capturada.');
        }

        // Idempotente: re-validar una cédula ya validada no es un error ni repisa el rastro.
        if ($credential->isVerified()) {
            return redirect()->back()
                ->with('success', 'Esta cédula ya estaba verificada.');
        }

        try {
            $registered = trim((string) $credential->registered_name);
            $ownerName  = $target->fullName();

            // Sin nombre del registro no hay nada contra qué cotejar. Es una OMISIÓN DE
            // CAPTURA (negocio), no un fallo técnico: no dispara telemetría al dev.
            if ($registered === '') {
                return redirect()->back()
                    ->with('error', 'Falta el «nombre registrado» que devuelve la SEP. Sin él no hay nada que cotejar: captúralo y vuelve a intentar.');
            }

            if (!SepRegistry::namesMatch($registered, $ownerName)) {
                // ALERTA DE NEGOCIO → se queda en la operación. NO va correo al dev: el dev
                // no puede hacer nada con esto y el ruido le haría ignorar los que sí importan.
                // Señal de NEGOCIO, log-only. El diff exacto de los nombres se muestra en
                // pantalla (abajo, $pista); aquí NO se registran para no dejar el nombre del
                // titular en laravel.log. Solo IDs internos.
                CredentialTelemetry::report(CredentialTelemetry::REASON_NAME_MISMATCH, [
                    'user_id'       => $target->id,
                    'credential_id' => $credential->id,
                    'checked_by'    => $actor->id,
                ]);

                // (2026-07-24) Señalar la palabra EXACTA que difiere. Antes el mensaje mostraba
                // los dos nombres completos y ya: con «Giovani» vs «Giovany» —una letra— nadie
                // ve la diferencia, se concluye que la validación está rota y se insiste.
                $diff  = SepRegistry::nameDiff($registered, $ownerName);
                $pista = '';
                if (! empty($diff['solo_en_registro'])) {
                    $pista .= ' En el registro aparece «' . implode('», «', $diff['solo_en_registro']) . '» y en la app no.';
                }
                if (! empty($diff['solo_en_app'])) {
                    $pista .= ' En la app aparece «' . implode('», «', $diff['solo_en_app']) . '» y en el registro no.';
                }
                if ($pista !== '') {
                    $pista .= ' Si es un error de captura, corrige el que esté mal (el nombre del crew se edita arriba, en esta misma pantalla) y vuelve a intentar.';
                }

                return redirect()->back()->with(
                    'error',
                    'NO se validó la cédula: el nombre del registro («' . $registered . '») no coincide con el del titular en la app («' . $ownerName . '»).'
                    . $pista
                    . ' La cédula sigue PENDIENTE.'
                );
            }

            // Coincide → se enciende el badge, con evidencia de contra qué se coteja.
            $credential->update([
                'verified_at'         => now(),
                'verified_by_id'      => $actor->id,
                'verification_source' => MedicCredential::SOURCE_MANUAL,
                'verified_snapshot'   => [
                    'source'           => MedicCredential::SOURCE_MANUAL,
                    'cedula'           => $credential->cedula,
                    'registered_name'  => $registered,
                    'compared_against' => $ownerName,
                    'verification_url' => (string) $credential->verification_url,
                    'verified_by'      => $actor->fullName(),
                    'verified_by_id'   => $actor->id,
                    'checked_at'       => now()->toDateTimeString(),
                    // (2026-07-24) Rastro de la DECLARACIÓN: quién afirmó haber cotejado, con qué
                    // rol y desde dónde. Es lo que permite responder, meses después, «¿quién avaló
                    // a este médico?» sin depender de la memoria de nadie. Mismo criterio que la
                    // firma digital de los reportes (ip/user_agent en digital_signatures).
                    'attested'         => true,
                    'attested_role'    => $actor->getRoleNames()->implode(', '),
                    'attested_ip'      => $request->ip(),
                ],
            ]);

            return redirect()->back()
                ->with('success', 'Cédula VERIFICADA: el nombre del registro coincide con el del titular.');
        } catch (\Throwable $e) {
            // ALERTA TÉCNICA → esto sí es del dev: algo del flujo de validación se rompió.
            Log::error('MedicCredentialController::verify — ' . $e->getMessage());
            CredentialTelemetry::report(CredentialTelemetry::REASON_TECHNICAL, [
                'user_id' => $target->id,
                'error'   => $e->getMessage(),
                'where'   => 'MedicCredentialController::verify',
            ]);

            return redirect()->back()
                ->with('error', 'No se pudo completar la validación por un problema técnico. Ya se avisó al equipo; la cédula sigue pendiente.');
        }
    }

    // ---------------------------------------------------------------------------
    // Autorización de la captura
    // ---------------------------------------------------------------------------

    /**
     * ¿Puede este actor CAPTURAR la cédula de este médico?
     *
     * Dos vías, a propósito más abiertas que las de verify():
     *   · quien tiene `medic.credential.manage` (y alcance sobre ese miembro de crew), o
     *   · el propio médico sobre su ficha — puede escribir su número, pero al nacer
     *     pendiente y no poder autovalidarse, eso no le acredita nada.
     */
    protected function canCapture($actor, User $target)
    {
        if ($actor === null) {
            return false;
        }

        if ((int) $actor->id === (int) $target->id) {
            return true;
        }

        return $actor->can('medic.credential.manage') && $actor->canManageCrewMember($target);
    }
}
