<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHealthAddendumRequest;
use App\Models\formulario;
use App\Models\HealthRecordAddendum;
use App\Models\MedicCredential;
use App\Models\User;

/**
 * ANEXOS AL EXPEDIENTE CLÍNICO — sólo el médico, previa valoración.
 * (2026-07-24 · PIEZA 3, corrida 2/2)
 *
 * El expediente es inmutable desde que se declara. Este controlador es la ÚNICA vía por la que
 * su contenido puede cambiar de significado, y lo hace sin reescribir nada: agrega un documento
 * nuevo, fechado y sellado, que dice qué cambia y por qué.
 *
 * QUIÉN: rol `medic` (fuente única `User::isMedic()`), como en cmedicController@create. **El
 * titular NO puede** — ni siquiera sobre su propio expediente, y ésa es la regla que sostiene
 * todo: si el dueño pudiera corregirse, el documento dejaría de probar qué declaró.
 *
 * ⚠ No basta el permiso de la ruta: `medical.create` lo tienen super-admin y medic, y un
 * super-admin NO médico quedaría como AUTOR CLÍNICO de una valoración que no hizo. La identidad
 * médica es un ROL, no un permiso. Mismo criterio, mismo lugar: el controlador.
 */
class HealthRecordAddendumController extends Controller
{
    /**
     * Formulario del anexo. Muestra el estado VIGENTE de cada campo anexable para que el médico
     * corrija sobre lo que realmente está viendo el resto de la app, no sobre lo declarado.
     */
    public function create($idUser)
    {
        $bloqueo = $this->guardas($idUser);
        if ($bloqueo !== null) {
            return $bloqueo;
        }

        $paciente   = User::findOrFail($idUser);
        $expediente = formulario::vigenteDe($idUser);

        return view('admin.expediente-anexo', [
            'paciente'   => $paciente,
            'expediente' => $expediente,
            'valores'    => $expediente->aplicados(),
            'motivos'    => HealthRecordAddendum::MOTIVOS,
            'campos'     => formulario::CAMPOS_ANEXABLES,
        ]);
    }

    /**
     * Crea el anexo. NUNCA toca la fila del expediente: el original conserva su hash, su folio
     * y su QR, y sigue verificando. Lo que cambia es lo que la app LEE (formulario::aplicados()).
     */
    public function store(StoreHealthAddendumRequest $request, $idUser)
    {
        $bloqueo = $this->guardas($idUser);
        if ($bloqueo !== null) {
            return $bloqueo;
        }

        $expediente = formulario::vigenteDe($idUser);
        $cambios = $request->cambiosRespectoA($expediente);

        // Un anexo que no cambia nada sería ruido firmado dentro de un expediente clínico: se
        // rechaza con los datos de vuelta, no se guarda un documento vacío.
        if (empty($cambios)) {
            return redirect()->back()->withInput()->with('error', __('health.addendum_no_changes'));
        }

        $medico = auth()->user();

        // SNAPSHOT DE CÉDULA congelado, igual que en la consulta: quién anexó y si su cédula
        // estaba verificada EN ESE MOMENTO. Si mañana pierde la verificación, el anexo de hoy
        // sigue diciendo la verdad de hoy — y el sello de abajo lo cubre.
        $credencial = MedicCredential::supportsCredentials() ? $medico->medicCredential : null;

        $anexo = HealthRecordAddendum::create([
            'formulario_id'         => $expediente->id_formulario,
            'reason'                => $request->input('reason'),
            // `changed_fields`, NO `changes`: ver HealthRecordAddendum::camposCambiados().
            'changed_fields'        => $cambios,
            'notes'                 => $request->input('notes'),
            'created_by_id'         => $medico->id,
            'medic_name'            => method_exists($medico, 'fullName') ? $medico->fullName() : $medico->name,
            'medic_cedula'          => $credencial ? $credencial->cedula : null,
            // 1 = verificada al momento · 0 = pendiente al momento · null = sin credencial.
            'medic_cedula_verified' => $credencial ? ($credencial->isVerified() ? 1 : 0) : null,
        ]);

        // Sello propio del anexo. refresh() antes de firmar (uuid y casts ya persistidos), o el
        // hash se firma sobre un objeto que ya no existe y sale "ALTERADO" a la primera lectura.
        $anexo->refresh();
        $anexo->signDocument($medico, $request);

        return redirect('/historialWR/' . $idUser)->with('success', __('health.addendum_saved', [
            'folio' => $anexo->folio(),
        ]));
    }

    /**
     * Guardas compartidas por el formulario y el guardado. Van en los DOS: ocultar el botón no
     * es una guarda, y un POST directo no pasa por la vista.
     *
     * @return \Illuminate\Http\RedirectResponse|null  null = puede seguir
     */
    private function guardas($idUser)
    {
        if (! HealthRecordAddendum::supported()) {
            return redirect('/medicocrud')->with('error', __('health.addendum_unavailable'));
        }

        if (! auth()->user()->isMedic()) {
            return redirect('/medicocrud')->with('error', __('health.addendum_only_medic'));
        }

        // Alcance por departamento: la misma fuente única que protege el expediente individual
        // (historialWR). Sin esto, un médico-HOD podría anexar por URL fuera de su área.
        $paciente = User::findOrFail($idUser);
        abort_unless(auth()->user()->canManageCrewMember($paciente), 403);

        if (! formulario::vigenteDe($idUser)) {
            // No se anexa a lo que no existe. Si la persona nunca declaró su expediente, lo que
            // corresponde es que lo declare —o que la consulta quede marcada `without_record`—,
            // no que el médico lo invente por ella.
            return redirect('/historialWR/' . $idUser)->with('error', __('health.addendum_no_record'));
        }

        return null;
    }
}
