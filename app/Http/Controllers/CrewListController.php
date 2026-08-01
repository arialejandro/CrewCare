<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\BadgeTemplate;
use App\Models\LitePatient;

/**
 * CrewListController — PRIMER CORTE del God Object AdminController (strangler, 2026-06-27).
 *
 * SOLO LECTURA: listados de crew (Crew List / coordinación / médico / gafetes) + ficha de gafete.
 * Es un MOVIMIENTO PURO desde AdminController: misma lógica, mismas vistas, mismos nombres de
 * método y de ruta → cero cambio de comportamiento (verificable). El scope por departamento ya
 * estaba aplicado en los listados (User::applyDepartmentScope, misma fuente de verdad que
 * SearchController). El endurecimiento de scope de idcard($id) (H2, defensa en profundidad — hoy
 * NO explotable) se hará en la fase de seguridad dedicada, no en este corte estructural.
 */
class CrewListController extends Controller
{
    public function usuarioscrud()
    {
        // Scope por departamento (#2): un HOD (sin crew.view.all-departments) solo ve a su
        // propio departamento; super-admin/coordinador/line-producer (con all-departments)
        // ven a todos. Mismo helper que usa SearchController → una sola fuente de verdad.
        $query = DB::table('users')->where('activo', '=', 1);
        $query = User::applyDepartmentScope($query, auth()->user());
        $usuarios = $query->orderBy('users.id', 'desc')->paginate(50);

        return view ('admin/usuarioscrud', compact('usuarios'));
    }

    // RETIRADO (2026-07-19): comcrud(). Servía la pantalla huérfana /comcrud, redundante con
    // usuarioscrud (consulta IDÉNTICA: activo=1 + applyDepartmentScope + paginate(50)) y con los
    // botones de grupo rotos desde hacía tiempo (posteaban a url('/putga/…'), que es un NOMBRE
    // de ruta y no una URI → 404). usuarioscrud muestra más columnas, trae el mismo export
    // /nophoto y usa el parcial componentes/_group-toggles, que sí funciona.
    // Vista y justificación en _legacy_backup/cleanup-2026-07-19/ (ver NOTA.md).

    /**
     * (2026-07-31) HOME MÉDICO UNIFICADO. Antes había DOS puertas: /medicocrud (crew) y
     * /pacientes-lite (personas sin cuenta). Se fundieron en UNA sola para no re-registrar a un
     * integrante del crew como paciente lite (identidad partida). Esta pantalla muestra el crew Y
     * los pacientes sin cuenta, el buscador (AJAX /searchmedico) encuentra a ambos, y trae el alta
     * "registrar persona no-crew" para el caso "no aparece". El registro/fusión sigue viviendo en
     * LitePatientController; la consulta, en cmedicController.
     */
    public function medicocrud()
    {
        // Scope por departamento (#2). (medic tiene all-departments → ve a todos; aditivo/defensivo.)
        $query = User::applyDepartmentScope(DB::table('users')->where('activo', '=', 1), auth()->user());
        // (2026-07-24 · PASO 3/3) Proyección EXPLÍCITA, igual que la del buscador AJAX
        // (SearchController::PRESET_MEDICAL): nunca SELECT * — no arrastra el hash de contraseña —
        // y las dos rutas alimentan el MISMO parcial de cards con las mismas columnas. Sin
        // teléfono ni email: la lista médica no los muestra.
        $usuarios = $query->orderBy('users.id', 'desc')
            ->paginate(50, ['users.id', 'users.name', 'users.lname', 'users.lname2',
                'users.puestodepartamento', 'users.zone', 'users.imgperfil',
                'users.borndate', 'users.sex']);

        // PACIENTES SIN CUENTA (lite): supervivientes recientes + conteo de consultas por grupo de
        // identidad. Silencioso si el módulo no está disponible en la instancia.
        if (LitePatient::supported()) {
            $litePatients = LitePatient::whereNull('merged_into_id')->orderByDesc('id')->limit(50)->get();
            $liteCounts   = LitePatient::consultCountsFor($litePatients->pluck('id')->all());
        } else {
            $litePatients = collect();
            $liteCounts   = [];
        }

        return view('admin/medicocrud', compact('usuarios', 'litePatients', 'liteCounts'));
    }

    public function idcardscrud()
    {
        // Scope por departamento (#2): el HOD ve solo los gafetes de su área.
        $base = User::applyDepartmentScope(User::query()->where('activo', 1), auth()->user());

        // "Con foto" = imgperfil real (no vacío/nofoto). "Listo" = con foto y NO impreso
        // (sin fila en badge_prints).
        $photoFilter = function ($q) {
            $q->whereNotNull('imgperfil')->where('imgperfil', '!=', '')->where('imgperfil', '!=', 'nofoto');
        };
        $counts = [
            'total'     => (clone $base)->count(),
            'withPhoto' => (clone $base)->where($photoFilter)->count(),
            'ready'     => (clone $base)->where($photoFilter)->whereDoesntHave('badgePrint')->count(),
        ];

        // withCount('badgePrint') → cada fila expone badge_print_count (0/1 = impreso).
        $usuarios = (clone $base)->withCount('badgePrint')->orderBy('id', 'desc')->paginate(50);

        return view('admin/idcardscrud', compact('usuarios', 'counts'));
    }

    public function idcard($id){
        // Scope (2026-06-28): findOrFail + guarda por depto, igual que el resto del dominio crew
        // (useredit/CrewStatusController). La LISTA de gafetes ya está acotada; esto evita que un
        // HOD abra por URL el gafete de otro departamento. Cierra el último pendiente H2 de crew.
        $users = User::findOrFail($id);
        abort_unless(auth()->user()->canManageCrewMember($users), 403);

        // Plantilla de gafete activa (DEFAULTS + config guardada). Reemplaza los reads
        // muertos $puestos/$puestoactual (la vista usa users.puestodepartamento).
        $tpl = BadgeTemplate::activeConfig();

        return view("admin/idcard", compact('users', 'tpl'));
    }
}
