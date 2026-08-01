<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHealthRecordRequest;
use App\Models\formulario;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Mail;

/**
 * Alta del EXPEDIENTE CLÍNICO del crew.
 *
 * Sólo tiene un método vivo: `newformulario()`. Todo lo demás se retiró el 2026-07-24:
 *
 *  · `registrarformulario()` — recibía 49 parámetros POSICIONALES y su ruta
 *    (`POST /formularios/medicos`) no declaraba ninguno, así que cualquier llamada habría
 *    reventado con ArgumentCountError antes de ejecutar una sola línea. Su único "cliente"
 *    era un `fetch('/registrarformulario/…')` dentro de formulario.blade.php, hacia una ruta
 *    que nunca existió. Nunca funcionó, y no podía.
 *  · Los stubs vacíos del scaffold (index/create/store/show/edit/update/destroy). `edit()` y
 *    `update()` se van además por DECISIÓN, no por limpieza: el expediente NO se edita
 *    (corrida 2/2 lo sella). Dejarlos vacíos era una invitación a implementarlos.
 *
 * Respaldo del método retirado: _legacy_backup/ (git conserva el historial completo).
 */
class FormulariosController extends Controller
{
    /**
     * Guarda el expediente clínico de quien está en sesión.
     *
     * TRES COSAS QUE ANTES NO PASABAN:
     *
     * 1. VALIDA EN EL SERVIDOR. Antes era `request()->all()` directo a `create()`: los cuatro
     *    campos obligatorios sólo tenían `required` en HTML. Ahora StoreHealthRecordRequest
     *    decide qué entra, y si algo falta el usuario vuelve al formulario CON SUS DATOS
     *    (`withInput`) — perder 40 campos por un dedazo es la forma más segura de que la
     *    siguiente persona escriba cualquier cosa con tal de terminar.
     *
     * 2. NO SIEMBRA UN SEGUNDO EXPEDIENTE POR ACCIDENTE. Si la persona ya lo entregó
     *    (`encuestadiaria = 1`) y existe una fila, el alta se rechaza: eso cubre el doble
     *    clic, el botón "atrás" y el reenvío del formulario.
     *
     *    ⚠ NO es un bloqueo absoluto, y la diferencia importa: el toggle admin "Activar
     *    encuesta" (POST /activarencuesta/{id}) pone `encuestadiaria = 0` a propósito, y ésa
     *    es la ÚNICA reapertura que queda viva desde que se retiró el cron diario. Si aquí
     *    bloqueáramos por "ya existe una fila", ese botón quedaría muerto. Con el flag en 0 se
     *    permite una nueva declaración; la anterior NO se borra (queda el histórico) y
     *    `vigenteDe()` sirve la más reciente. Corregir un dato SIN reapertura es acto del
     *    médico (anexos, corrida 2/2), no un segundo POST del titular.
     *
     * 3. `id_user` SIGUE SALIENDO DE LA SESIÓN. Era lo único que el método viejo hacía bien:
     *    se asigna DESPUÉS de tomar los datos validados, así que un `id_user` posteado no
     *    tiene efecto. No se toca.
     */
    public function newformulario(StoreHealthRecordRequest $request)
    {
        $usuario = auth()->user();
        $idUser = $usuario->id;

        // (2) Ya entregado + no reabierto = no se vuelve a sembrar. `vigenteDe()` es la misma
        // fuente que leen el médico y la tarjeta de crew, así que aquí no puede haber una idea
        // distinta de "ya tiene".
        if ($usuario->encuestadiaria && formulario::vigenteDe($idUser)) {
            return redirect('/profile')->with('error', __('health.already_filed'));
        }

        $datos = $request->validated();

        // Las 24 casillas: el navegador manda "on" o no manda nada. Antes eran 24 `if`
        // copiados; ahora la lista vive en el modelo y esto recorre esa lista — agregar una
        // casilla al formulario ya no obliga a acordarse de agregar un `if` aquí.
        foreach (formulario::CHECKBOXES as $casilla) {
            $datos[$casilla] = $request->input($casilla) ? 1 : 0;
        }

        // Pares excluyentes (vivo/fallecido). El formulario ya usa radios, pero un POST
        // directo no pasa por el formulario y la BD aceptaría "vivo Y fallecido".
        foreach (formulario::EXCLUSIVOS as $par) {
            if (! empty($datos[$par[0]]) && ! empty($datos[$par[1]])) {
                $datos[$par[1]] = 0;
            }
        }

        // Fecha de la influenza: sólo tiene sentido si la casilla está marcada. Si alguien
        // captura la fecha y luego desmarca, la fecha se descarta (si no, el expediente
        // guardaría la fecha de una vacuna que la persona dice no tener).
        if (formulario::soportaFechaInfluenza()) {
            $datos['vacci2_date'] = empty($datos['vacci2']) ? null : ($request->input('vacci2_date') ?: null);
        } else {
            unset($datos['vacci2_date']);
        }

        $datos['id_user'] = $idUser;

        $expediente = formulario::create($datos);

        // (4) SE SELLA Y QUEDA INMUTABLE. Firma la persona que declara: el expediente es SU
        // declaración, no un acto del sistema (por eso no es signDocumentAsSystem, como sí lo
        // es el cierre automático del DSR). A partir de aquí nadie lo edita —tampoco su
        // titular—; corregir es acto del médico, vía anexo.
        //
        // ⚠ `refresh()` ANTES de firmar, no después. El hash se calcula sobre
        // attributesToArray(), y el modelo recién creado no trae lo que puso la BD (defaults
        // de columna, casts resueltos). Sin esto el hash se firma sobre un objeto que ya no
        // vuelve a existir y el sello sale "ALTERADO" en la primera lectura. Es la misma
        // trampa documentada en el Scouting y en el Wrap.
        $expediente->refresh();
        $expediente->signDocument($usuario, $request);

        // `encuestadiaria` = "ya entregó su expediente" (nombre heredado del COVID). Sigue
        // siendo lo que apaga el CTA del home y del perfil. `lastwr` se conserva por
        // compatibilidad, pero la fecha REAL del expediente es su propio created_at:
        // CrewController pone lastwr en now() al dar de alta, así que no distingue.
        $registro = User::find($idUser);
        if ($registro) {
            $registro->encuestadiaria = 1;
            $registro->lastwr = Carbon::now();
            $registro->save();
        }

        $enviado = $this->enviarCopiaAlTitular($expediente, $usuario);

        return redirect('/profile')->with('success', $enviado
            ? __('health.saved_mailed', ['email' => $usuario->email])
            : __('health.saved'));
    }

    /**
     * (2026-07-24 · corrida 2/2) COPIA EN PDF AL TITULAR, para que REVISE.
     *
     * Cierra el circuito de la inmutabilidad. Si el expediente queda congelado y sólo un médico
     * puede anexar, la persona tiene derecho a ver exactamente qué quedó registrado a su nombre
     * — y ése es el único momento en que un error de captura es barato de detectar. El correo
     * dice explícitamente que revise y que, si algo no corresponde, lo plantee al servicio
     * médico, que es quien puede ajustarlo previa valoración.
     *
     * BLINDADO CON \Throwable A PROPÓSITO: el expediente YA está guardado y sellado cuando esto
     * corre. Un servidor de correo caído no puede tumbar la petición ni, mucho menos, hacer que
     * la persona crea que su expediente no se registró. Si falla, queda en el log y el mensaje
     * de éxito omite la mención al correo, en vez de prometer algo que no ocurrió.
     *
     * @return bool  true si el correo salió
     */
    private function enviarCopiaAlTitular($expediente, $usuario)
    {
        if (empty($usuario->email)) {
            return false;
        }

        try {
            $pdf = Pdf::loadView('correos.expediente-pdf', [
                'expediente' => $expediente,
                'valores'    => $expediente->aplicados(),
                'titular'    => $usuario,
                'anexos'     => collect(),
            ])->setPaper('a4', 'portrait');

            $datos = [
                'nombre' => trim($usuario->name . ' ' . $usuario->lname),
                'folio'  => $expediente->folio(),
                'fecha'  => $expediente->fechaLlenado(),
            ];
            $para    = $usuario->email;
            $asunto  = __('health.mail_subject');
            $archivo = 'expediente-' . $expediente->folio() . '.pdf';
            $binario = $pdf->output();

            Mail::send('correos.expediente-entregado', $datos, function ($msj) use ($para, $asunto, $archivo, $binario) {
                $msj->subject($asunto);
                $msj->to($para);
                // Adjunto en memoria: el expediente NO se escribe a disco. Un PDF con alergias
                // y antecedentes familiares en storage sería una copia más que proteger.
                $msj->attachData($binario, $archivo, ['mime' => 'application/pdf']);
            });

            return true;
        } catch (\Throwable $e) {
            Log::warning('Expediente clínico: no se pudo enviar la copia al titular.', [
                'user_id' => $usuario->id,
                'error'   => $e->getMessage(),
            ]);
            return false;
        }
    }
}
