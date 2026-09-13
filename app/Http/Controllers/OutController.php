<?php

namespace App\Http\Controllers;

use App\Models\DepartmentOut;
use App\Models\IndividualOut;
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
 * OutController — SALIDAS (outs) del crew (§3) + consulta/export del TURNAROUND (§4).
 *
 * NO confundir con la columna `out` del back ni con el estado ROSTER_OUT. Autoridad POR PUESTO
 * (OutAuthority): producción/coordinación ven todo; el jefe (is_hod) registra su depto. La pantalla
 * es la del DÍA: departamentos que tuvieron llamado, con quién ya reportó y quién no; registrar en
 * dos toques (hora + guardar); salidas individuales; corregir = volver a guardar (updateOrCreate).
 *
 * Todo aditivo: no toca el llamado ni el roster (los LEE). El registro pasa por OutRegistrar (servicio
 * compartido con el bot). A qué día pertenece una hora lo decide OutWindow (nunca se asigna a ciegas).
 */
class OutController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // ---------------------------------------------------------------------------------------
    // Pantalla del día
    // ---------------------------------------------------------------------------------------

    public function index(Request $request)
    {
        $viewer = $request->user();
        abort_unless(OutAuthority::canUseScreen($viewer), 403, 'No tienes autoridad para registrar salidas.');

        $pid = CurrentProduction::id();
        abort_unless($pid, 404, 'Sin producción vigente.');
        $unitId = CurrentUnit::id();

        $day = $request->query('date')
            ? Carbon::parse($request->query('date'))->toDateString()
            : Carbon::today()->toDateString();

        $visibleDeptIds = OutAuthority::visibleDepartmentIds($viewer);   // null = todos

        // Departamentos que tuvieron llamado ese día (reusa el roster; ya scopeado por el viewer).
        $roster = DayRosterBuilder::build($viewer, $day);
        $deptRows = [];
        $rosterPeople = [];   // lista plana para el selector de salida individual (acotada por autoridad)
        foreach ($roster['groups'] as $g) {
            $deptId = null;
            foreach ($g['people'] as $p) {
                if (! empty($p['dept_id'])) { $deptId = (int) $p['dept_id']; break; }
            }
            if ($deptId === null) {
                continue;   // grupo sin depto de catálogo (legacy zone): no se puede registrar un out por él
            }
            if ($visibleDeptIds !== null && ! in_array($deptId, $visibleDeptIds, true)) {
                continue;
            }
            $deptRows[$deptId] = ['id' => $deptId, 'label' => $g['label'], 'called' => count($g['people'])];
            foreach ($g['people'] as $p) {
                $rosterPeople[] = ['user_id' => $p['user_id'], 'name' => $p['name'], 'dept' => $g['label']];
            }
        }

        // Salidas ya registradas ese día (unidad vigente).
        $deptOuts = [];
        $dq = DepartmentOut::where('production_id', $pid)->whereDate('shoot_date', $day);
        $unitId === null ? $dq->whereNull('unit_id') : $dq->where('unit_id', $unitId);
        foreach ($dq->get() as $o) {
            $deptOuts[(int) $o->department_id] = $o;
        }

        $iq = IndividualOut::where('production_id', $pid)->whereDate('shoot_date', $day);
        $unitId === null ? $iq->whereNull('unit_id') : $iq->where('unit_id', $unitId);
        if ($visibleDeptIds !== null) {
            $iq->whereIn('department_id', $visibleDeptIds ?: [-1]);
        }
        $individualOuts = $iq->orderBy('out_at')->get();
        $indNames = [];
        foreach (DB::table('users')->whereIn('id', $individualOuts->pluck('user_id')->all() ?: [-1])
                    ->get(['id', 'name', 'lname', 'lname2', 'ncreditos']) as $u) {
            $indNames[$u->id] = \App\Models\User::displayName($u);
        }

        // Conteo "reportaron vs faltan" (el uso real a las 11 de la noche).
        $totalDepts = count($deptRows);
        $reported = 0;
        foreach ($deptRows as $id => $r) {
            if (isset($deptOuts[$id])) { $reported++; }
        }

        $general = OutWindow::generalCall($pid, $unitId, $day);

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
            'generalCall'    => $general ? substr($general, 0, 5) : null,
            'canPasteAll'    => OutAuthority::seesAll($viewer),
        ]);
    }

    // ---------------------------------------------------------------------------------------
    // Registrar / corregir
    // ---------------------------------------------------------------------------------------

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

        abort_unless(OutAuthority::canRegisterFor($viewer, $deptId), 403, 'Sin autoridad sobre ese departamento.');

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

        $msg = 'Salida registrada.';
        if ($res['reason'] === 'no_general_call') {
            $msg .= ' (Aviso: este día no tiene llamado general configurado; se tomó la fecha tal cual.)';
        }

        return back()->with('success', $msg);
    }

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

        // Depto de la persona (para scoping + autoridad).
        $deptId = DB::table('production_user')->where('production_id', $pid)->where('user_id', $userId)
            ->value('department_id');
        $deptId = $deptId ? (int) $deptId : null;

        // Autoridad: producción ve todo; el jefe sólo su depto (y necesita conocer el depto de la persona).
        abort_unless(
            OutAuthority::seesAll($viewer) || ($deptId !== null && OutAuthority::canRegisterFor($viewer, $deptId)),
            403, 'Sin autoridad sobre esa persona.'
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

        return back()->with('success', 'Salida individual registrada.');
    }

    public function destroyDepartment(Request $request, int $id)
    {
        $viewer = $request->user();
        $out = DepartmentOut::findOrFail($id);
        abort_unless(OutAuthority::canRegisterFor($viewer, (int) $out->department_id), 403);
        $out->delete();

        return back()->with('success', 'Salida eliminada.');
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

        return back()->with('success', 'Salida individual eliminada.');
    }

    /** Pegar un mensaje de grupo y registrarlo (usa el MISMO servicio que el bot). */
    public function ingest(Request $request)
    {
        $viewer = $request->user();
        abort_unless(OutAuthority::canUseScreen($viewer), 403);
        $data = $request->validate([
            'message'    => ['required', 'string', 'max:1000'],
            'shoot_date' => ['nullable', 'date'],
        ]);

        $pid = CurrentProduction::id();
        abort_unless($pid, 404);
        $unitId = CurrentUnit::id();

        $report = OutIngest::ingestText(
            $data['message'], $pid, $unitId, DepartmentOut::SOURCE_APP, $viewer->id,
            Carbon::now(), OutAuthority::visibleDepartmentIds($viewer)
        );

        return back()->with(empty($report['registered']) ? 'error' : 'success', $report['reply']);
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
