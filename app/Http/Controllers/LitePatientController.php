<?php

namespace App\Http\Controllers;

use App\Models\cmedic;
use App\Models\LitePatient;
use Illuminate\Http\Request;

/**
 * LitePatientController — el REGISTRO de personas no-crew (extras, day players, visitantes,
 * proveedores). (2026-07-24 · módulo beta)
 *
 * Es la ÚNICA superficie de pacientes de medicbeta: reemplaza los cards de crew de /medicocrud
 * (que a un rol beta le queda bloqueado). Aquí se registra a la persona una vez, se la reencuentra
 * después (búsqueda aproximada, para no duplicar) y se atiende (la consulta vive en cmedicController).
 *
 * NO crea cuenta, contraseña, rol ni acceso: un lite_patient no puede iniciar sesión ni aparece en
 * selectores de asignación. Gate de ruta: medical.view (ver) / medical.create (alta, fusión).
 */
class LitePatientController extends Controller
{
    /**
     * (2026-07-31) La puerta separada /pacientes-lite se FUNDIÓ con /medicocrud (home médico
     * unificado): allí el buscador encuentra crew Y personas sin cuenta, y el alta de no-crew vive
     * en la misma pantalla. Esta ruta se conserva como ALIAS que redirige, para no romper enlaces
     * ni marcadores viejos. La lista/búsqueda/conteo que había aquí los da ahora
     * CrewListController@medicocrud. El alta (store) y la fusión (fusionar) siguen vivos abajo.
     */
    public function index(Request $request)
    {
        return redirect()->route('medicocrud');
    }

    /** Alta de una persona lite. */
    public function store(Request $request)
    {
        abort_unless(LitePatient::supported(), 404);
        abort_unless(auth()->user()->isClinician(), 403);

        $data = $request->validate([
            'full_name'         => 'required|string|max:255',
            'dob'               => 'nullable|date|before_or_equal:today',
            'age'               => 'nullable|integer|min:0|max:130',
            'sex'               => 'nullable|string|max:10',
            'phone'             => 'nullable|string|max:30',
            'emergency_contact' => 'nullable|string|max:255',
            'emergency_phone'   => 'nullable|string|max:30',
            'area'              => 'nullable|string|max:120',
            'origin'            => 'nullable|string|max:255',
        ]);

        $data['created_by_id'] = auth()->id();
        $paciente = LitePatient::create($data);

        // Se ofrece atender de inmediato: registrar y atender es un solo gesto en set.
        return redirect()->route('lite.consulta.create', $paciente->id)
            ->with('success', 'Paciente registrado: ' . $paciente->displayName() . '. Ya puedes atenderlo.');
    }

    /**
     * Fusión de duplicados. El paciente {id} (DUPLICADO) se marca como fundido en la SUPERVIVIENTE
     * (survivor_id). NO se reescribe ninguna consulta: cada `cmedic.lite_patient_id` conserva su
     * valor y su SELLO; la identidad se resuelve al grupo al leer (ver LitePatient::identityGroupIds).
     */
    public function fusionar(Request $request, $id)
    {
        abort_unless(LitePatient::supported(), 404);
        abort_unless(auth()->user()->isClinician(), 403);

        $duplicado = LitePatient::findOrFail($id);
        $request->validate(['survivor_id' => 'required|integer|different:' . $id]);

        $superviviente = LitePatient::findOrFail((int) $request->input('survivor_id'));
        // Si la superviviente elegida ya es un duplicado, se sube a SU superviviente (cadena plana).
        if ($superviviente->isMerged() && $superviviente->mergedInto) {
            $superviviente = $superviviente->mergedInto;
        }
        if ($superviviente->id === $duplicado->id) {
            return redirect()->route('medicocrud')->with('error', 'No se puede fusionar una persona consigo misma.');
        }

        // Aplanar: los que se fundieron EN el duplicado pasan a la superviviente (sin cadenas de 2+).
        LitePatient::where('merged_into_id', $duplicado->id)->update(['merged_into_id' => $superviviente->id]);
        $duplicado->merged_into_id = $superviviente->id;
        $duplicado->save();

        return redirect()->route('medicocrud')->with(
            'success',
            'Fusionado: «' . $duplicado->displayName() . '» quedó unido a «' . $superviviente->displayName()
            . '». Ambas historias se ven juntas y ninguna consulta se perdió.'
        );
    }
}
