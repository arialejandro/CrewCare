<?php

namespace App\Http\Controllers;

use App\Models\DepartmentOut;
use App\Models\IndividualOut;
use App\Models\OutReporter;
use App\Support\CurrentProduction;
use App\Support\CurrentUnit;
use App\Support\DayRosterBuilder;
use App\Support\OutAuthority;
use App\Support\OutIngest;
use App\Support\OutRegistrar;
use App\Support\OutWindow;
use App\Support\ProductionCalendar;
use App\Support\Turnaround;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * OutController — SALIDAS (outs) + turnaround.
 *
 * MODELO (así se maneja en CINE, no en oficina):
 *   · El OUT es del DEPARTAMENTO: UNA persona DESIGNADA por depto reporta la hora y APLICA A TODOS
 *     ("salimos a tal hora" = todos). NADIE marca "lo suyo"; no hay auto-marcado.
 *   · La salida INDIVIDUAL es la EXCEPCIÓN (alguien que salió a otra hora que su equipo) y TAMBIÉN la
 *     ASIGNA el designado — gana sobre la del depto para el turnaround de esa persona.
 *   · Autoridad POR DESIGNACIÓN (OutReporter), no por puesto. Producción/coordinación ven y reportan
 *     todo. El reporte por WhatsApp (capa apagada) usa el mismo servicio (OutIngest).
 *
 * "OUT" aquí NO es la columna `out` del back (retirada) ni el estado ROSTER_OUT.
 */
class OutController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // ---------------------------------------------------------------------------------------
    // Tablero del designado / producción
    // ---------------------------------------------------------------------------------------

    public function index(Request $request)
    {
        $viewer = $request->user();
        abort_unless(OutAuthority::canUseScreen($viewer), 403, 'No tienes departamento asignado para reportar salidas.');

        $pid = CurrentProduction::id();
        abort_unless($pid, 404, 'Sin producción vigente.');
        $unitId = CurrentUnit::id();

        $day = $request->query('date')
            ? Carbon::parse($request->query('date'))->toDateString()
            : Carbon::today()->toDateString();

        $visibleDeptIds = OutAuthority::visibleDepartmentIds($viewer);   // null = todos

        // Departamentos con llamado ese día (reusa el roster; ya scopeado por el viewer).
        $roster = DayRosterBuilder::build($viewer, $day);
        $deptRows = [];
        $rosterPeople = [];   // para el selector de excepción individual (acotado por autoridad)
        foreach ($roster['groups'] as $g) {
            $deptId = null;
            foreach ($g['people'] as $p) { if (! empty($p['dept_id'])) { $deptId = (int) $p['dept_id']; break; } }
            if ($deptId === null) { continue; }
            if ($visibleDeptIds !== null && ! in_array($deptId, $visibleDeptIds, true)) { continue; }
            $deptRows[$deptId] = ['id' => $deptId, 'label' => $g['label'], 'called' => count($g['people'])];
            foreach ($g['people'] as $p) {
                $rosterPeople[] = ['user_id' => $p['user_id'], 'name' => $p['name'], 'dept' => $g['label']];
            }
        }

        // Salidas de departamento del día.
        $deptOuts = [];
        $dq = DepartmentOut::where('production_id', $pid)->whereDate('shoot_date', $day);
        $unitId === null ? $dq->whereNull('unit_id') : $dq->where('unit_id', $unitId);
        foreach ($dq->get() as $o) { $deptOuts[(int) $o->department_id] = $o; }

        // Excepciones individuales del día (acotadas a los deptos visibles).
        $iq = IndividualOut::where('production_id', $pid)->whereDate('shoot_date', $day);
        $unitId === null ? $iq->whereNull('unit_id') : $iq->where('unit_id', $unitId);
        if ($visibleDeptIds !== null) { $iq->whereIn('department_id', $visibleDeptIds ?: [-1]); }
        $individualOuts = $iq->orderBy('out_at')->get();
        $indNames = [];
        foreach (DB::table('users')->whereIn('id', $individualOuts->pluck('user_id')->all() ?: [-1])
                    ->get(['id', 'name', 'lname', 'lname2', 'ncreditos']) as $u) {
            $indNames[$u->id] = \App\Models\User::displayName($u);
        }

        // "Reportaron vs faltan" es POR DEPARTAMENTO (uno reporta por todos).
        $totalDepts = count($deptRows);
        $reported = 0;
        foreach ($deptRows as $id => $r) { if (isset($deptOuts[$id])) { $reported++; } }

        return view('admin.outs.index', [
            'day'            => $day,
            'dayLabel'       => ProductionCalendar::labelFor($day, $unitId),
            'deptRows'       => array_values($deptRows),
            'rosterPeople'   => $rosterPeople,
            'deptOuts'       => $deptOuts,
            'individualOuts' => $individualOuts,
            'indNames'       => $indNames,
            'reported'       => $reported,
            'pending'        => max(0, $totalDepts - $reported),
            'totalDepts'     => $totalDepts,
            'generalCall'    => ($gc = OutWindow::generalCall($pid, $unitId, $day)) ? substr($gc, 0, 5) : null,
            'canDesignate'   => OutAuthority::seesAll($viewer) || ! empty(OutAuthority::authorityDepartmentIds($viewer)),
        ]);
    }

    // ---------------------------------------------------------------------------------------
    // Registrar / corregir (SOLO el designado o producción)
    // ---------------------------------------------------------------------------------------

    /** Salida del DEPARTAMENTO (aplica a todos). Corregir = volver a guardar (updateOrCreate). */
    public function storeDepartment(Request $request)
    {
        $viewer = $request->user();
        $data = $request->validate([
            'department_id' => ['required', 'integer'],
            'shoot_date'    => ['required', 'date'],
            'time'          => ['required', 'string', 'max:10'],
            'note'          => ['nullable', 'string', 'max:191'],
        ]);

        $pid = CurrentProduction::id();
        abort_unless($pid, 404);
        $unitId = CurrentUnit::id();
        $deptId = (int) $data['department_id'];

        abort_unless(OutAuthority::canRegisterFor($viewer, $deptId), 403, 'No estás designado para ese departamento.');

        $shootDate = Carbon::parse($data['shoot_date'])->toDateString();
        $res = OutWindow::outAtForShootDay($shootDate, $data['time'], $unitId, $pid);
        if ($res['reason'] === 'bad_time') {
            return back()->with('error', 'La hora «' . $data['time'] . '» no es válida.');
        }
        if (! $res['resolved'] && $res['reason'] === 'outside_window') {
            return back()->with('error', 'Esa hora no cae en la ventana de este día (más de 20 h del llamado). '
                . 'Regístrala en el día que corresponde.');
        }

        OutRegistrar::departmentOut($pid, $unitId, $shootDate, $deptId, $res['out_at'],
            DepartmentOut::SOURCE_APP, $viewer->id, $data['note'] ?? null);

        $msg = 'Salida del departamento registrada.';
        if ($res['reason'] === 'no_general_call') {
            $msg .= ' (Aviso: este día no tiene llamado general configurado; se tomó la fecha tal cual.)';
        }

        return back()->with('success', $msg);
    }

    /** EXCEPCIÓN individual: alguien que salió a otra hora — la ASIGNA el designado, no la persona. */
    public function storeIndividual(Request $request)
    {
        $viewer = $request->user();
        $data = $request->validate([
            'user_id'    => ['required', 'integer'],
            'shoot_date' => ['required', 'date'],
            'time'       => ['required', 'string', 'max:10'],
            'note'       => ['nullable', 'string', 'max:191'],
        ]);

        $pid = CurrentProduction::id();
        abort_unless($pid, 404);
        $unitId = CurrentUnit::id();
        $userId = (int) $data['user_id'];

        $deptId = DB::table('production_user')->where('production_id', $pid)->where('user_id', $userId)
            ->value('department_id');
        $deptId = $deptId ? (int) $deptId : null;

        abort_unless(
            OutAuthority::seesAll($viewer) || ($deptId !== null && OutAuthority::canRegisterFor($viewer, $deptId)),
            403, 'No estás designado para el departamento de esa persona.'
        );

        $shootDate = Carbon::parse($data['shoot_date'])->toDateString();
        $res = OutWindow::outAtForShootDay($shootDate, $data['time'], $unitId, $pid);
        if ($res['reason'] === 'bad_time') {
            return back()->with('error', 'La hora «' . $data['time'] . '» no es válida.');
        }
        if (! $res['resolved'] && $res['reason'] === 'outside_window') {
            return back()->with('error', 'Esa hora no cae en la ventana de este día. Regístrala en el día correcto.');
        }

        OutRegistrar::individualOut($pid, $unitId, $shootDate, $userId, $deptId, $res['out_at'],
            IndividualOut::SOURCE_APP, $viewer->id, $data['note'] ?? null);

        return back()->with('success', 'Excepción individual registrada.');
    }

    /** Pegar un mensaje de grupo → reporta la salida del DEPARTAMENTO (mismo servicio que el bot). */
    public function ingest(Request $request)
    {
        $viewer = $request->user();
        abort_unless(OutAuthority::canUseScreen($viewer), 403);
        $data = $request->validate(['message' => ['required', 'string', 'max:1000']]);

        $pid = CurrentProduction::id();
        abort_unless($pid, 404);
        $unitId = CurrentUnit::id();

        $report = OutIngest::ingestText(
            $data['message'], $pid, $unitId, DepartmentOut::SOURCE_APP, $viewer->id,
            Carbon::now(), OutAuthority::visibleDepartmentIds($viewer)
        );

        return back()->with(empty($report['registered']) ? 'error' : 'success', $report['reply']);
    }

    public function destroyDepartment(Request $request, int $id)
    {
        $viewer = $request->user();
        $out = DepartmentOut::findOrFail($id);
        abort_unless(OutAuthority::canRegisterFor($viewer, (int) $out->department_id), 403);
        $out->delete();

        return back()->with('success', 'Salida de departamento eliminada.');
    }

    public function destroyIndividual(Request $request, int $id)
    {
        $viewer = $request->user();
        $out = IndividualOut::findOrFail($id);
        abort_unless(
            OutAuthority::seesAll($viewer) || ($out->department_id && OutAuthority::canRegisterFor($viewer, (int) $out->department_id)),
            403
        );
        $out->delete();

        return back()->with('success', 'Excepción individual eliminada.');
    }

    // ---------------------------------------------------------------------------------------
    // Designados (una persona por depto — autoridad por designación, no por puesto)
    // ---------------------------------------------------------------------------------------

    public function designations(Request $request)
    {
        $viewer = $request->user();
        abort_unless(OutAuthority::canUseScreen($viewer), 403);
        $pid = CurrentProduction::id();
        abort_unless($pid, 404);

        $visibleDeptIds = OutAuthority::visibleDepartmentIds($viewer);   // null = todos

        $deptNames = DB::table('departments')->where('active', 1)->orderBy('sort_order')->orderBy('name')
            ->pluck('name', 'id')->all();
        if ($visibleDeptIds !== null) {
            $deptNames = array_intersect_key($deptNames, array_flip($visibleDeptIds));
        }

        $reporters = [];
        $rows = OutReporter::where('production_id', $pid)->get();
        $userNames = [];
        foreach (DB::table('users')->whereIn('id', $rows->pluck('user_id')->all() ?: [-1])
                    ->get(['id', 'name', 'lname', 'lname2', 'ncreditos']) as $u) {
            $userNames[$u->id] = \App\Models\User::displayName($u);
        }
        foreach ($rows as $r) {
            $reporters[(int) $r->department_id][] = [
                'row_id'  => $r->id,
                'user_id' => (int) $r->user_id,
                'name'    => $userNames[$r->user_id] ?? ('#' . $r->user_id),
            ];
        }

        $crew = [];
        $cq = DB::table('production_user as pu')->join('users', 'users.id', '=', 'pu.user_id')
            ->where('pu.production_id', $pid)->where('users.activo', 1)
            ->get(['users.id as user_id', 'users.name', 'users.lname', 'users.lname2', 'users.ncreditos', 'pu.department_id']);
        foreach ($cq as $c) {
            if ($visibleDeptIds !== null && ($c->department_id === null || ! in_array((int) $c->department_id, $visibleDeptIds, true))) {
                continue;
            }
            $crew[] = ['user_id' => (int) $c->user_id, 'name' => \App\Models\User::displayName($c),
                       'department_id' => $c->department_id ? (int) $c->department_id : null];
        }

        return view('admin.outs.designations', [
            'departments' => $deptNames,
            'reporters'   => $reporters,
            'crew'        => $crew,
        ]);
    }

    public function storeDesignation(Request $request)
    {
        $viewer = $request->user();
        $data = $request->validate([
            'department_id' => ['required', 'integer'],
            'user_id'       => ['required', 'integer'],
        ]);
        $pid = CurrentProduction::id();
        abort_unless($pid, 404);
        $deptId = (int) $data['department_id'];
        abort_unless(OutAuthority::canDesignateFor($viewer, $deptId), 403, 'Sin autoridad para designar en ese departamento.');

        OutReporter::firstOrCreate(
            ['production_id' => $pid, 'department_id' => $deptId, 'user_id' => (int) $data['user_id']],
            ['designated_by_id' => $viewer->id]
        );

        return back()->with('success', 'Designado agregado.');
    }

    public function destroyDesignation(Request $request, int $id)
    {
        $viewer = $request->user();
        $row = OutReporter::findOrFail($id);
        abort_unless(OutAuthority::canDesignateFor($viewer, (int) $row->department_id), 403);
        $row->delete();

        return back()->with('success', 'Designado retirado.');
    }

    // ---------------------------------------------------------------------------------------
    // Turnaround (consulta + export). Silencioso: no bloquea, no alerta.
    // ---------------------------------------------------------------------------------------

    public function turnaround(Request $request)
    {
        $viewer = $request->user();
        abort_unless(OutAuthority::canUseScreen($viewer), 403);
        $pid = CurrentProduction::id();
        abort_unless($pid, 404);
        $unitId = CurrentUnit::id();

        $day = $request->query('date')
            ? Carbon::parse($request->query('date'))->toDateString()
            : Carbon::today()->toDateString();

        $rows = Turnaround::rowsForDay($pid, $unitId, $day, OutAuthority::visibleDepartmentIds($viewer));

        return view('admin.outs.turnaround', [
            'day'      => $day,
            'dayLabel' => ProductionCalendar::labelFor($day, $unitId),
            'rows'     => $rows,
        ]);
    }

    public function turnaroundExport(Request $request)
    {
        $viewer = $request->user();
        abort_unless(OutAuthority::canUseScreen($viewer), 403);
        $pid = CurrentProduction::id();
        abort_unless($pid, 404);
        $unitId = CurrentUnit::id();
        $day = $request->query('date') ? Carbon::parse($request->query('date'))->toDateString() : Carbon::today()->toDateString();

        $rows = Turnaround::rowsForDay($pid, $unitId, $day, OutAuthority::visibleDepartmentIds($viewer));

        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['Tipo', 'Departamento', 'Persona', 'Salida', 'Siguiente llamado', 'Turnaround']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['scope'] === 'dept' ? 'Departamento' : 'Individual',
                $r['department'],
                $r['name'] ?? '',
                $r['out_at'] ? $r['out_at']->format('Y-m-d H:i') : '',
                $r['next_call_at'] ? $r['next_call_at']->format('Y-m-d H:i') : '',
                Turnaround::humanize($r['minutes']),
            ]);
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="turnaround-' . $day . '.csv"',
        ]);
    }
}
