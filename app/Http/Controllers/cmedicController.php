<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\cmedic;
use App\Models\formulario;
use App\Models\InjuryReport;
use App\Models\LitePatient;
use App\Models\Medication;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class cmedicController extends Controller
{
  
    // --- index($id_user) ELIMINADO (2026-06-28) — CÓDIGO MUERTO: ninguna ruta ni vista lo
    //     referenciaba (verificado route:list + grep). Duplicaba historialWR() (ambos rendían
    //     componentes.historiamr) con un contrato de variables distinto/confuso. El historial
    //     médico vivo lo sirve historialWR(); el listado, CrewListController@medicocrud.

    /**
     * Mostrar el formulario para crear una nueva consulta médica para un usuario.
     */
    public function create($id_user)
    {
        // (2026-07-24) GUARD DE MÉDICO (item 2): registrar una consulta es un ACTO CLÍNICO.
        // El gate de la ruta (permission:medical.create) NO basta: lo tienen super-admin y medic,
        // y un super-admin NO médico quedaría como AUTOR CLÍNICO. La identidad clínica es un ROL,
        // no un permiso → se exige isClinician() (médico o médico beta). Bloquea incluso al super-admin.
        if (! auth()->user()->isClinician()) {
            return redirect('/medicocrud')->with('error', 'Solo un médico puede registrar consultas. Este acceso queda en el expediente clínico como quien atendió.');
        }

        // (2026-07-24 · PIEZA 3) EXPEDIENTE VIGENTE, vía fuente única. Antes esto era un
        // `->first()` sin ORDER BY: con dos filas devolvía la MÁS ANTIGUA, mientras la tarjeta
        // de crew mostraba la más reciente. El médico y el badge podían contradecirse en las
        // alergias. Ver formulario::vigenteDe().
        list($datos, $formulario, $intakeState, $expedienteModelo) = $this->expedienteVigente($id_user);

        // (2026-07-24 · PASO 3/3, item 1) NUNCA SE NIEGA ATENCIÓN POR UN TRÁMITE.
        // Antes, sin fila en `formularios` esto REDIRIGÍA: día 1, o alguien que entró de apoyo,
        // no alcanzaba a llenar el cuestionario y el sistema convertía un problema administrativo
        // en uno clínico. Ahora la consulta se abre igual; lo que cambia es que el médico VE el
        // aviso y la consulta DEJA CONSTANCIA (without_record en store()).
        // Mismo principio ya aplicado 3 veces: el GPS no bloquea el reporte, el robot de cédula
        // no bloquea el alta, lo automático nunca bloquea.
        //
        // $intakeState: 'ok' | 'missing' (sin expediente) | 'incomplete' (hay expediente pero sin
        // alergias registradas — una fila existente NO garantiza que esté completa). Lo resuelve
        // expedienteVigente() de arriba, con el MISMO testigo que la tarjeta de crew.

        // Verificar si el usuario existe
        $usuario = User::findOrFail($id_user);

        // Catálogo de medicamentos (variantes) para autocompletar + lista de presentaciones.
        $catalog = Medication::where('active', 1)->orderBy('name')->orderBy('dosage')->get();
        $presentations = Medication::PRESENTATIONS;

        // (Ola A) Accidentes recientes para LIGAR opcionalmente la consulta a un reporte de lesión
        // (habilita la inyección de la consulta al DSR del día). DEFENSIVO: sólo si existe la tabla.
        $recentInjuries = Schema::hasTable('injury_reports')
            ? InjuryReport::orderByDesc('id')->limit(20)->get(['id', 'production_title', 'incident_date'])
            : collect();

        // (2026-07-24 · PASO 2/3, item 3) CINTILLO DE TRATAMIENTO PREVIO — RECONCILIACIÓN DE
        // MEDICAMENTOS. Es la contraparte clínica del aislamiento del item 1: si el médico A no ve
        // las consultas de B, podría recetar a ciegas (dar otro corticoide sin saber del anterior =
        // sobredosis, no control del síntoma). Por eso este cintillo NO se filtra por autor.
        //
        // PRINCIPIO DE LO MÍNIMO NECESARIO: viajan SÓLO tres datos — fecha · diagnóstico ·
        // medicamento. NUNCA observations, aditional ni el nombre del médico que atendió: eso es
        // la nota del otro médico y sigue aislada.
        // (2026-07-25) CINTILLO — SÓLO la ÚLTIMA consulta (antes recorría la lista entera). Query
        // ÚNICA compartida con el camino lite (cmedic::lastForPatient), cross-médico A PROPÓSITO: el
        // cintillo existe para NO recetar a ciegas sobre lo que dio otro médico. El componente
        // compartido _cintillo-previo lo pinta legible y sin recorte, o dice "sin atenciones previas".
        $prevConsult = cmedic::lastForPatient((int) $id_user);

        $managementOptions = cmedic::MANAGEMENT_OPTIONS;

        return view('admin.cmedica', compact('usuario', 'formulario', 'datos', 'catalog', 'presentations',
            'recentInjuries', 'prevConsult', 'intakeState', 'managementOptions'));
    }

    /**
     * (2026-07-24 · PIEZA 3) El EXPEDIENTE VIGENTE del paciente, en las tres formas que piden
     * las vistas de este controlador. Un solo punto de resolución para create() e historialWR():
     * antes cada uno preguntaba por su cuenta y podían diferir.
     *
     * Devuelve, en este orden:
     *   0. $datos       fila UNIDA a `users` (identidad + contacto + expediente). Es lo que las
     *                   vistas usan para la cabecera, así que conserva exactamente su forma.
     *                   ⚠ Por la unión, `$datos->created_at` es el del USUARIO, no el del
     *                   expediente: para la fecha de llenado usar la colección de abajo.
     *   1. $filas       colección con UNA sola fila (la vigente). Se conserva como colección
     *                   porque las vistas la recorren; con un elemento, el `@foreach` de
     *                   historiamr deja de repetir la ficha y de duplicar ids HTML.
     *   2. $estado      'missing' | 'incomplete' | 'ok' (testigo `alergy`, ver formulario::estadoDe).
     *   3. $modelo      el modelo Eloquent SIN anexos aplicados. Lo necesitan el sello (que
     *                   recomputa el hash sobre los atributos ORIGINALES) y la traza de anexos.
     *
     * (2026-07-24 · corrida 2/2) Los valores clínicos que salen en 0 y 1 vienen con los ANEXOS
     * MÉDICOS YA APLICADOS: la app tiene que leer el estado vigente, no lo declarado hace tres
     * meses. Pero el modelo de 3 va intacto — si le aplicáramos los anexos encima, su hash
     * dejaría de casar con la firma y el expediente se declararía "ALTERADO" justo por
     * funcionar bien (ver formulario::aplicados()).
     *
     * @param  int  $idUser
     * @return array
     */
    private function expedienteVigente($idUser)
    {
        $vigente = formulario::vigenteDe($idUser);

        if (! $vigente) {
            return [null, collect(), 'missing', null];
        }

        $aplicados = $vigente->aplicados();

        // La fila UNIDA conserva su forma (identidad + contacto desde `users`), con los valores
        // clínicos pisados por los anexos.
        $datos = DB::table('formularios')
            ->join('users', 'formularios.id_user', '=', 'users.id')
            ->where('formularios.id_formulario', '=', $vigente->id_formulario)
            ->first();

        if ($datos) {
            foreach ((array) $aplicados as $campo => $valor) {
                // Sólo campos del expediente: `users` aporta name/phone/email/imgperfil y no
                // debe pisarse con nada de aquí.
                if (property_exists($datos, $campo) && $campo !== 'id') {
                    $datos->$campo = $valor;
                }
            }
        }

        return [$datos, collect([$aplicados]), formulario::estadoDe($vigente), $vigente];
    }

    // (2026-07-25) medsShort() se MUDÓ al modelo como cmedic::medsLine() — fuente única del cintillo
    // y del historial. cintilloLite() (abajo) se retiró: el cintillo lo arma cmedic::lastForPatient().

    /**
     * Guardar una nueva consulta médica en la base de datos.
     */
    public function store(Request $request, $id_user)
    {
        // (2026-07-24) GUARD DE MÉDICO (item 2): mismo candado que create(), aquí como defensa
        // en profundidad (el POST no depende de que la UI ocultara el botón). Solo un clínico
        // (médico o médico beta) registra una consulta y queda como autor clínico.
        abort_unless(auth()->user()->isClinician(), 403);

        // Validar el objetivo (2026-06-28): antes se creaba la consulta sin verificar que el
        // usuario existiera → 404 limpio si el {id_user} es inválido (evita consultas huérfanas).
        User::findOrFail($id_user);

        $request->validate([
            'consultation_date'  => 'nullable|date',
            'diagnosis'          => 'required|string|max:500',
            'observations'       => 'nullable|string',
            'aditional'          => 'nullable|string',
            'injury_report_id'   => 'nullable|integer|exists:injury_reports,id',
            // (2026-07-24 · PASO 3/3) Manejo/conducta: claves del catálogo cerrado del modelo.
            'management'         => 'nullable|array',
            'management.*'       => 'string|in:' . implode(',', array_keys(cmedic::MANAGEMENT_OPTIONS)),
            'med_name.*'         => 'nullable|string|max:150',
            'med_qty.*'          => 'nullable|numeric|min:0',
            'med_dosage.*'       => 'nullable|string|max:60',
            'med_presentation.*' => 'nullable|string|max:50',
        ]);

        // Armar los medicamentos ESTRUCTURADOS a partir de los renglones dinámicos.
        // Cada variante (nombre × dosis × presentación) se registra/reutiliza en el catálogo
        // (modo híbrido) para poder contabilizarla después. Guardamos un snapshot en la consulta.
        $names         = $request->input('med_name', []);
        $qtys          = $request->input('med_qty', []);
        $dosages       = $request->input('med_dosage', []);
        $presentations = $request->input('med_presentation', []);

        $items = [];
        $medText = [];
        foreach ($names as $i => $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue; // renglón vacío → se ignora
            }
            $dosage       = trim((string) ($dosages[$i] ?? ''));
            $presentation = trim((string) ($presentations[$i] ?? ''));
            $qty          = (float) ($qtys[$i] ?? 1);
            if ($qty <= 0) {
                $qty = 1;
            }

            $med = Medication::firstOrCreate(
                ['name' => $name, 'dosage' => $dosage, 'presentation' => $presentation],
                ['active' => 1]
            );

            $items[] = [
                'medication_id' => $med->id,
                'name'          => $name,
                'dosage'        => $dosage,
                'presentation'  => $presentation,
                'quantity'      => $qty,
            ];
            // Texto legible (compatibilidad con el historial viejo): "2 Paracetamol 500mg Tableta".
            $medText[] = trim(preg_replace('/\s+/', ' ', ($qty == (int) $qty ? (int) $qty : $qty)
                . ' ' . $name . ' ' . $dosage . ' ' . $presentation));
        }

        $payload = [
            'id_user'           => $id_user,
            'created_by_id'     => auth()->id(),                                   // autofirma / auditoría
            'consultation_date' => $request->consultation_date ?: Carbon::now()->toDateString(),
            'diagnosis'         => $request->diagnosis,
            // (2026-07-24) `medication` y `observations` son NOT NULL SIN DEFAULT en cmedic y MySQL
            // corre en modo estricto: mandarles null revienta el INSERT (1364). Pasaba de verdad —
            // una consulta sin medicamento, o con el textarea de observaciones vacío (Laravel
            // convierte "" en null con ConvertEmptyStringsToNull), tiraba 500 al guardar. Se
            // normalizan a cadena vacía; las vistas ya muestran «Ninguno»/«Ninguna» con `?:`.
            'medication'        => $medText ? implode('; ', $medText) : '',        // compat historial
            'medication_items'  => $items,                                         // estructurado (cast array)
            'observations'      => (string) $request->observations,
            'aditional'         => $request->aditional,
            'created_at'        => Carbon::now(),
        ];

        // (Ola A) Liga OPCIONAL a un accidente → habilita la inyección de la consulta al DSR.
        // DECISIÓN (2026-07-25): si el médico ELIGIÓ un accidente pero la columna no existe (el
        // ALTER owner-apply aún no corrió), NO se descarta callado: se TRUENA con un mensaje claro.
        // Se falla ruidoso sólo cuando el dato se perdería; si no se ligó accidente no hay nada que
        // perder y el guard deja pasar el store como siempre (prod sin el ALTER sigue funcionando).
        if ($request->filled('injury_report_id')) {
            abort_unless(
                Schema::hasColumn('cmedic', 'injury_report_id'),
                500,
                'No se puede ligar la consulta al accidente: falta la columna cmedic.injury_report_id. '
                .'Aplica la migración owner-apply de consultas médicas antes de usar esta función.'
            );
            $payload['injury_report_id'] = (int) $request->input('injury_report_id');
        }

        // (2026-07-24 · PASO 3/3, item 2) MANEJO / CONDUCTA CLÍNICA. Se guardan las CLAVES del
        // catálogo cerrado (la validación de arriba ya rechazó cualquier otra). Un array vacío se
        // guarda como null para no ensuciar el payload sellado con "[]".
        if (Schema::hasColumn('cmedic', 'management')) {
            $mgmt = array_values(array_unique((array) $request->input('management', [])));
            $payload['management'] = $mgmt ? $mgmt : null;
        }

        // (2026-07-24 · PASO 3/3, item 1) CONSTANCIA de que se atendió SIN expediente. Se resuelve
        // en el SERVIDOR (no desde el formulario): es el dato que protege al médico si algo sale
        // mal después, así que no puede depender de un campo que el navegador podría no mandar.
        if (Schema::hasColumn('cmedic', 'without_record')) {
            // (2026-07-24 · PIEZA 3) Misma fuente única que el aviso que vio el médico en
            // create(): si aquí preguntáramos distinto, la consulta podría decir "había
            // expediente" mientras el médico atendió leyendo el aviso de que no lo había.
            $payload['without_record'] = formulario::vigenteDe($id_user) ? 0 : 1;
        }

        // (2026-07-24 · PIEZA 3, corrida 2/2) QUÉ VERSIÓN DEL EXPEDIENTE VIO EL MÉDICO.
        // Se resuelve en el SERVIDOR, no desde el formulario: es la prueba que protege al médico
        // si el expediente se anexa DESPUÉS. Anexar más tarde no cambia lo que esta consulta
        // demuestra — que es exactamente el punto.
        if (Schema::hasColumn('cmedic', 'intake_hash')) {
            $expedienteVisto = formulario::vigenteDe($id_user);
            if ($expedienteVisto) {
                $ultimo = $expedienteVisto->ultimoAnexo();
                $payload['intake_formulario_id'] = $expedienteVisto->id_formulario;
                // null = se atendió con el expediente ORIGINAL, sin anexos todavía.
                $payload['intake_addendum_id']   = $ultimo ? $ultimo->id : null;
                $payload['intake_hash']          = $expedienteVisto->hashVigente();
            }
        }

        // (2026-07-24) SNAPSHOT DE CÉDULA (item 4): se CONGELA quién atendió al momento de crear
        // la consulta, para que el sello SHA (abajo) cubra su identidad. Después el badge en vivo
        // puede cambiar en el perfil del médico; la consulta guarda lo que era EN ESE MOMENTO.
        // DEFENSIVO: sólo si las columnas existen (prod sin el ALTER no truena).
        if (Schema::hasColumn('cmedic', 'medic_cedula')) {
            $author = auth()->user();
            $cred   = ($author && \App\Models\MedicCredential::supportsCredentials())
                ? $author->medicCredential : null;
            $payload['medic_name']            = $author ? $author->fullName() : null;
            $payload['medic_cedula']          = $cred ? $cred->cedula : null;
            // 1 = verificada al momento · 0 = pendiente al momento · null = sin credencial.
            $payload['medic_cedula_verified'] = $cred ? ($cred->isVerified() ? 1 : 0) : null;
        }

        $consulta = cmedic::create($payload);

        // (2026-07-24) SELLO CFDI (item 5): se sella al CREARSE, homologado a los 5 reportes de
        // seguridad. refresh() carga los valores canónicos ya persistidos (uuid + snapshot) para
        // que el hash coincida con lo almacenado. No-op seguro si digital_signatures no existe.
        // No hay flujo de edición de consultas → no hace falta re-sellar (el sello queda fijo).
        $consulta->refresh();
        $consulta->signDocument(auth()->user(), $request);

        return redirect('/medicocrud')->with('success', 'Consulta médica registrada correctamente.');
    }

    // =========================================================================================
    //  CONSULTA DE PACIENTE LITE (no-crew: extras/visitantes/proveedores) — 2026-07-24.
    //  (2026-07-25) Es funcionalidad CORE del rol `medic` real (isClinician() == isMedic()).
    //  Reusa el sello (v2), los medicamentos y el cintillo. Diferencias con la de crew:
    //   · el paciente es un LitePatient, no un User (id_user va NULL, lite_patient_id apunta a él);
    //   · el cintillo agrupa por GRUPO DE IDENTIDAD (superviviente + duplicados fundidos);
    //   · SIEMPRE without_record = 1 (un paciente lite no tiene expediente por definición);
    //   · CÉDULA OBLIGATORIA para emitir (un documento clínico firmado con cédula en blanco miente);
    //   · sin intake_* (no hay expediente) y sin liga a accidente (no inyecta al DSR).
    // =========================================================================================

    /** Formulario de consulta para un paciente lite. */
    public function createLite($liteId)
    {
        if (! auth()->user()->isClinician()) {
            return redirect()->route('medicocrud')->with('error', 'Solo un médico puede registrar consultas.');
        }
        abort_unless(LitePatient::supported(), 404);

        $paciente = LitePatient::findOrFail($liteId);
        // Si abrieron un duplicado ya fundido, se atiende sobre la superviviente.
        if ($paciente->isMerged() && $paciente->mergedInto) {
            return redirect()->route('lite.consulta.create', $paciente->mergedInto->id);
        }

        $catalog       = Medication::where('active', 1)->orderBy('name')->orderBy('dosage')->get();
        $presentations = Medication::PRESENTATIONS;
        $managementOptions = cmedic::MANAGEMENT_OPTIONS;
        $tieneCedula   = $this->clinicoTieneCedula(auth()->user());
        // (2026-07-25) CINTILLO — última consulta, MISMA query que el crew (cmedic::lastForPatient),
        // agrupando por el GRUPO DE IDENTIDAD (superviviente + fusionados) para que la fusión una las
        // historias sin reescribir ninguna consulta sellada. Se pinta con el componente compartido.
        $prevConsult = cmedic::lastForPatient(null, $paciente->identityGroupIds());

        return view('admin.lite.consulta', compact(
            'paciente', 'catalog', 'presentations', 'managementOptions', 'tieneCedula', 'prevConsult'
        ));
    }

    /** Guarda y SELLA (v2) una consulta de paciente lite. */
    public function storeLite(Request $request, $liteId)
    {
        abort_unless(auth()->user()->isClinician(), 403);   // defensa en profundidad
        abort_unless(LitePatient::supported(), 404);

        $paciente = LitePatient::findOrFail($liteId);
        if ($paciente->isMerged() && $paciente->mergedInto) {
            $paciente = $paciente->mergedInto;   // nunca colgar una consulta de un duplicado
        }

        // CÉDULA OBLIGATORIA PARA EMITIR. El documento se sella con la cédula CONGELADA del médico;
        // sin cédula registrada, el sello mentiría por omisión. No se atora la ATENCIÓN (el médico
        // ya atendió), se atora la EMISIÓN del documento hasta que registre su cédula.
        if (! $this->clinicoTieneCedula(auth()->user())) {
            return redirect()->back()->withInput()->with(
                'error',
                'Antes de emitir la consulta necesitas registrar tu cédula profesional en tu perfil: el documento clínico se sella con ella.'
            );
        }

        $request->validate([
            'consultation_date'  => 'nullable|date',
            'diagnosis'          => 'required|string|max:500',
            'observations'       => 'nullable|string',
            'aditional'          => 'nullable|string',
            'management'         => 'nullable|array',
            'management.*'       => 'string|in:' . implode(',', array_keys(cmedic::MANAGEMENT_OPTIONS)),
            'med_name.*'         => 'nullable|string|max:150',
            'med_qty.*'          => 'nullable|numeric|min:0',
            'med_dosage.*'       => 'nullable|string|max:60',
            'med_presentation.*' => 'nullable|string|max:50',
        ]);

        list($items, $medText) = $this->construirMedicamentos($request);

        $author = auth()->user();
        $cred   = \App\Models\MedicCredential::supportsCredentials() ? $author->medicCredential : null;

        $payload = [
            'id_user'           => null,                 // XOR: es lite, no crew
            'lite_patient_id'   => $paciente->id,
            'seal_version'      => cmedic::SEAL_VERSION_CURRENT,   // sella en v2 (identidad en el hash)
            'created_by_id'     => $author->id,
            'consultation_date' => $request->consultation_date ?: Carbon::now()->toDateString(),
            'diagnosis'         => $request->diagnosis,
            'medication'        => $medText ? implode('; ', $medText) : '',
            'medication_items'  => $items,
            'observations'      => (string) $request->observations,
            'aditional'         => $request->aditional,
            // Un paciente lite NO tiene expediente por definición: la constancia es SIEMPRE 1.
            'without_record'    => 1,
            // Snapshot congelado de la cédula (misma doctrina que la consulta de crew).
            'medic_name'            => $author->fullName(),
            'medic_cedula'          => $cred ? $cred->cedula : null,
            'medic_cedula_verified' => $cred ? ($cred->isVerified() ? 1 : 0) : null,
            'created_at'        => Carbon::now(),
        ];

        if (Schema::hasColumn('cmedic', 'management')) {
            $mgmt = array_values(array_unique((array) $request->input('management', [])));
            $payload['management'] = $mgmt ? $mgmt : null;
        }

        $consulta = cmedic::create($payload);
        $consulta->refresh();
        $consulta->signDocument($author, $request);

        return redirect()->route('medicocrud')->with('success', 'Consulta registrada y sellada para ' . $paciente->displayName() . '.');
    }

    /**
     * Construye los medicamentos ESTRUCTURADOS desde los renglones dinámicos (compartido por la
     * consulta de crew y la lite). Devuelve [items, textoLegible].
     *
     * @return array{0: array, 1: array}
     */
    private function construirMedicamentos(Request $request): array
    {
        $names         = $request->input('med_name', []);
        $qtys          = $request->input('med_qty', []);
        $dosages       = $request->input('med_dosage', []);
        $presentations = $request->input('med_presentation', []);

        $items = [];
        $medText = [];
        foreach ($names as $i => $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $dosage       = trim((string) ($dosages[$i] ?? ''));
            $presentation = trim((string) ($presentations[$i] ?? ''));
            $qty          = (float) ($qtys[$i] ?? 1);
            if ($qty <= 0) {
                $qty = 1;
            }

            $med = Medication::firstOrCreate(
                ['name' => $name, 'dosage' => $dosage, 'presentation' => $presentation],
                ['active' => 1]
            );

            $items[] = [
                'medication_id' => $med->id,
                'name'          => $name,
                'dosage'        => $dosage,
                'presentation'  => $presentation,
                'quantity'      => $qty,
            ];
            $medText[] = trim(preg_replace('/\s+/', ' ', ($qty == (int) $qty ? (int) $qty : $qty)
                . ' ' . $name . ' ' . $dosage . ' ' . $presentation));
        }

        return [$items, $medText];
    }

    /** ¿El clínico tiene una cédula profesional registrada (número no vacío)? */
    private function clinicoTieneCedula($user): bool
    {
        if (! $user || ! \App\Models\MedicCredential::supportsCredentials()) {
            return false;
        }
        $cred = $user->medicCredential;
        return $cred && trim((string) $cred->cedula) !== '';
    }

    /**
     * Historial WR + consultas médicas de un usuario (CORTE #7, 2026-06-28: movido VERBATIM
     * desde AdminController@historialWR a su dominio médico). Distinto de index(): este alimenta
     * `componentes.historiamr` con los formularios (`$usuario`/`$datos`) además de `$consultas`.
     * Sigue gateado bajo el grupo `admin` (ruta repuntada, sin cambio de gate).
     */
    public function historialWR($id)
    {
        // (2026-07-25) ACCESO POR DEPARTAMENTO (canManageCrewMember) — el owner CONFIRMÓ que PRODUCCIÓN
        // ve el expediente clínico del crew POR DISEÑO: se REVIRTIÓ el gate doctor-only que se probó y
        // se deja el candado original. super-admin/medic/coordinator/line-producer tienen all-departments
        // → pasan; el HOD queda acotado a SU área. (La LISTA y el buscador AJAX ya filtran por depto;
        // esto cierra la 2ª puerta del expediente individual.)
        $target = User::findOrFail($id);
        abort_unless(auth()->user()->canManageCrewMember($target), 403);

        // (2026-07-24 · PIEZA 3) EXPEDIENTE VIGENTE, misma fuente única que usa create() —
        // antes cada método resolvía "el expediente" por su cuenta. `$usuario` trae UNA sola
        // fila: el `@foreach` de historiamr pintaba una ficha COMPLETA por cada fila, con los
        // mismos ids HTML repetidos (#hm-personales…) y sin decir a qué fecha correspondía
        // ninguna. Con más de un expediente eso ya se veía roto hoy.
        list($datos, $usuario, $intakeState, $expedienteModelo) = $this->expedienteVigente($id);

        // (2026-07-24 · PASO 3/3, item 1) COHERENCIA CON EL DESBLOQUEO: si la consulta ya se puede
        // registrar sin expediente, el HISTORIAL no puede seguir redirigiendo — quedarían consultas
        // guardadas que nadie puede abrir. En vez de eso se arma un $datos MÍNIMO desde `users`
        // (identidad y contacto, nada clínico) para que la cabecera funcione; el bloque de ficha y
        // antecedentes simplemente no se pinta, porque itera $usuario (los formularios) y viene vacío.
        if (! $datos) {
            $datos = DB::table('users')->where('id', $id)
                ->first(['id', 'name', 'lname', 'lname2', 'phone', 'email', 'imgperfil']);
            if (! $datos) {
                return redirect('/medicocrud')->with('error', 'Ese usuario no existe.');
            }
        }

        // Consultas como MODELOS Eloquent (el sello _seal-cfdi necesita el modelo). AISLAMIENTO POR
        // MÉDICO vía visibleTo(): el médico común ve SÓLO sus consultas de este paciente; los roles que
        // OBSERVAN (coordinador/line-producer/HOD/super-admin) y el key-medic ven TODAS. (2026-07-25) Se
        // revirtió el cambio a historyForPatient para que los ROLES CLÍNICOS sigan viendo igual que antes
        // (historyForPatient queda en uso sólo por el historial lite). Cubre la tabla, los sellos y la
        // impresión de una vez (es la misma colección).
        $consultas = cmedic::visibleTo(auth()->user())
            ->where('id_user', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        // PASO B (2026-07-19) — QUIÉN ATENDIÓ, con su cédula. Fallback EN VIVO para las consultas
        // anteriores al snapshot congelado (las nuevas ya traen medic_name/medic_cedula). Fuente
        // única compartida con el historial lite (authorMap).
        $medicos = $this->authorMap($consultas);

        // (2026-07-24 · corrida 2/2) `expedienteModelo` viaja APARTE de los valores: la vista
        // pinta el estado vigente (con anexos) pero el SELLO tiene que recomputarse sobre el
        // documento original, que es lo que se firmó. Es null si la persona no tiene expediente.
        $anexosExpediente = ($expedienteModelo && \App\Models\HealthRecordAddendum::supported())
            ? $expedienteModelo->anexos : collect();

        // (2026-07-25) $target es el User real del crew (ya resuelto para el gate). La cabecera de
        // historiamr mostraba el PUESTO leyendo la columna legacy $datos->puestodepartamento (fila
        // cruda, a veces sin ese campo); ahora lo lee del FK vía $target->positionName().
        $viewData = compact(
            'usuario', 'target', 'datos', 'consultas', 'medicos', 'intakeState',
            'expedienteModelo', 'anexosExpediente'
        );

        // (2026-08-10) IMPRESIÓN LIMPIA — ?print=1 devuelve el DOCUMENTO dedicado (componentes/
        // historiamr-print): HTML autocontenido, SIN nada del shell de la app, así ningún elemento de
        // GUI (la hamburguesa `position:fixed`) se cuela al papel y el layout clínico de 2 columnas se
        // controla por entero. La pantalla (historiamr) queda igual. Mismo candado, mismos datos.
        // Reemplaza el window.print() sobre la vista de pantalla, que salía desordenado. Ver
        // [[health-record-module]].
        // (2026-08-11) EXPORT PDF SERVER-SIDE (?pdf=1) — ADITIVO, antes del print/return normal.
        // Reusa EXACTAMENTE el mismo documento de impresión (historiamr-print) y lo pasa por
        // Browsershot (Chrome headless) → descarga de un clic, idéntica a window.print(). Ver
        // [[browsershot-pdf-pipeline]].
        if (request()->boolean('pdf')) {
            $html = view('componentes.historiamr-print', $viewData)->render();
            return \App\Support\PdfExporter::download($html, 'HISTORIAL-' . ($target->id ?? ($datos->id ?? 0)), [13, 12, 13, 12]);
        }

        if (request()->boolean('print')) {
            return view('componentes.historiamr-print', $viewData);
        }

        return view('componentes.historiamr', $viewData);
    }

    // (2026-08-09) historialImprimir() + componentes/historiamr-print (vista standalone chrome-v2)
    // se RETIRARON: el owner pidió que el historial médico NO se imprimiera como los demás
    // documentos, sino conservando el formato de su propia pantalla (componentes/historiamr),
    // sólo ajustado para verse bien en papel. La impresión ahora es window.print() sobre esa
    // vista, cuyo @media print aísla el reporte y lo deja paginar. Ver [[health-record-module]].

    /**
     * (2026-07-25) HISTORIAL COMPLETO del paciente SIN CUENTA (lite). No existía: sólo había el
     * cintillo al atender. UN SOLO CAMINO con el crew — misma query (cmedic::historyForPatient), mismo
     * documento por consulta, mismo GATE doctor-only. Une la persona y sus duplicados fundidos
     * (identityGroupIds) sin reescribir ninguna consulta sellada; orden y contenido idénticos al crew.
     */
    public function historialLite($liteId)
    {
        abort_unless(auth()->user()->isClinician(), 403);   // DOCTOR-ONLY (dato clínico)
        abort_unless(LitePatient::supported(), 404);

        $paciente = LitePatient::findOrFail($liteId);
        // Si abrieron un duplicado ya fundido, el historial vive en la superviviente.
        if ($paciente->isMerged() && $paciente->mergedInto) {
            return redirect()->route('lite.historial', $paciente->mergedInto->id);
        }

        $consultas = cmedic::historyForPatient(null, $paciente->identityGroupIds());
        $medicos   = $this->authorMap($consultas);

        return view('admin.lite.historial', compact('paciente', 'consultas', 'medicos'));
    }

    /**
     * (2026-07-25) DOCUMENTO SELLADO de UNA consulta (crew o lite) + PDF (window.print). GATE
     * doctor-only. La reconciliación (fecha/dx/medicamento/manejo) y el SELLO se muestran a cualquier
     * médico; la NOTA privada (observations/aditional) SÓLO al dueño o al key medic (cmedic::visibleTo),
     * para no romper el aislamiento médico-a-médico que gobierna la nota clínica.
     */
    public function consultaDoc($id)
    {
        abort_unless(auth()->user()->isClinician(), 403);

        $consulta    = cmedic::findOrFail($id);   // {id} = id_cmedic (clave primaria)
        $canSeeNotes = cmedic::visibleTo(auth()->user())
            ->where('id_cmedic', $consulta->id_cmedic)->exists();

        $autor = $consulta->created_by_id ? User::find($consulta->created_by_id) : null;
        if ($autor && \App\Models\MedicCredential::supportsCredentials()) {
            $autor->load('medicCredential');
        }
        $paciente     = $consulta->id_user ? User::find($consulta->id_user) : null;
        $litePaciente = ($consulta->lite_patient_id && LitePatient::supported())
            ? LitePatient::find($consulta->lite_patient_id) : null;

        return view('admin.consulta-documento', compact('consulta', 'canSeeNotes', 'autor', 'paciente', 'litePaciente'));
    }

    /**
     * Mapa id_médico → User (con cédula si la tabla existe) para el fallback EN VIVO de las consultas
     * anteriores al snapshot congelado. Compartido por el historial de crew y el de lite.
     */
    private function authorMap($consultas)
    {
        $ids = $consultas->pluck('created_by_id')->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }
        $q = User::whereIn('id', $ids);
        if (\App\Models\MedicCredential::supportsCredentials()) {
            $q->with('medicCredential');
        }
        return $q->get()->keyBy('id');
    }
}