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
 * MODELO (corregido 2026-09-13):
 *   · CADA PERSONA marca SU PROPIA salida (individual), en un toque, desde su HOME. NADIE captura por
 *     todos y marcar lo propio NO requiere autoridad (myOut).
 *   · El REPORTE POR DEPARTAMENTO lo hace un DESIGNADO (OutReporter), por WhatsApp (capa apagada) o
 *     pegando el mensaje aquí (ingest). Autoridad por designación, no por puesto.
 *   · La individual GANA sobre la del depto; si difieren, se VE (no se resuelve sola).
 *   · Este tablero (/salidas) es de PRODUCCIÓN/DESIGNADOS: consulta (quién marcó / quién falta),
 *     reporte por depto (pegar), corrección/retiro, turnaround y export.
 *
 * "OUT" aquí NO es la columna `out` del back ni el estado ROSTER_OUT.
 */
class OutController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // ---------------------------------------------------------------------------------------
    // Auto-marcado de la PROPIA salida (cualquier persona, SIN autoridad)
    // ---------------------------------------------------------------------------------------

    public function myOut(Request $request)
    {
        $user = $request->user();
        $pid = CurrentProduction::id();
        abort_unless($pid, 404, 'Sin producción vigente.');
        $unitId = CurrentUnit::id();

        $data = $request->validate(['time' => ['nullable', 'string', 'max:10']]);

        $shootDate = OutWindow::shootDateForNow($pid, $unitId);

        if (! empty($data['time'])) {
            $res = OutWindow::outAtForShootDay($shootDate, $data['time'], $unitId, $pid);
            if ($res['reason'] === 'bad_time') {
                return back()->with('error', 'La hora «' . $data['time'] . '» no es válida.');
            }
            $outAt = $res['out_at'];
        } else {
            $outAt = Carbon::now();   // un toque = "salí ahora"
        }

        $deptId = DB::table('production_user')->where('production_id', $pid)->where('user_id', $user->id)
            ->value('department_id');

        OutRegistrar::individualOut($pid, $unitId, $shootDate, $user->id, $deptId ? (int) $deptId : null,
            $outAt, IndividualOut::SOURCE_APP, $user->id);

        return back()->with('success', 'Marcaste tu salida a las ' . $outAt->format('H:i') . '.');
    }

    public function myOutDestroy(Request $request)
    {
        $user = $request->user();
        $pid = CurrentProduction::id();
        abort_unless($pid, 404);
        $unitId = CurrentUnit::id();
        $shootDate = OutWindow::shootDateForNow($pid, $unitId);

        $q = IndividualOut::where('production_id', $pid)->where('user_id', $user->id)->whereDate('shoot_date', $shootDate);
        $unitId === null ? $q->whereNull('unit_id') : $q->where('unit_id', $unitId);
        $q->delete();

        return back()->with('success', 'Quitaste tu marca de salida.');
    }

    // ---------------------------------------------------------------------------------------
    // Tablero de PRODUCCIÓN / DESIGNADOS
    // ---------------------------------------------------------------------------------------

    public function index(Request $request)
    {
        $viewer = $request->user();
        abort_unless(OutAuthority::canUseScreen($viewer), 403, 'No tienes autoridad para el tablero de salidas.');

        $pid = CurrentProduction::id();
        abort_unless($pid, 404, 'Sin producción vigente.');
        $unitId = CurrentUnit::id();

        $day = $request->query('date')
            ? Carbon::parse($request->query('date'))->toDateString()
            : Carbon::today()->toDateString();

        $visibleDeptIds = OutAuthority::visibleDepartmentIds($viewer);   // null = todos

        // Salidas del día (unidad vigente).
        $deptOuts = [];   // department_id => DepartmentOut
        $dq = DepartmentOut::where('production_id', $pid)->whereDate('shoot_date', $day);
        $unitId === null ? $dq->whereNull('unit_id') : $dq->where('unit_id', $unitId);
        if ($visibleDeptIds !== null) { $dq->whereIn('department_id', $visibleDeptIds ?: [-1]); }
        foreach ($dq->get() as $o) { $deptOuts[(int) $o->department_id] = $o; }

        $indByUser = [];   // user_id => IndividualOut
        $iq = IndividualOut::where('production_id', $pid)->whereDate('shoot_date', $day);
        $unitId === null ? $iq->whereNull('unit_id') : $iq->where('unit_id', $unitId);
        foreach ($iq->get() as $io) { $indByUser[(int) $io->user_id] = $io; }

        // Roster del día por depto (reusa DayRosterBuilder, ya scopeado por el viewer).
        $roster = DayRosterBuilder::build($viewer, $day);
        $groups = [];
        $marked = 0; $pending = 0;
        foreach ($roster['groups'] as $g) {
            $deptId = null;
            foreach ($g['people'] as $p) { if (! empty($p['dept_id'])) { $deptId = (int) $p['dept_id']; break; } }
            if ($deptId === null) { continue; }
            if ($visibleDeptIds !== null && ! in_array($deptId, $visibleDeptIds, true)) { continue; }

            $deptOut = $deptOuts[$deptId] ?? null;
            $deptTime = $deptOut ? Carbon::parse($deptOut->out_at)->format('H:i') : null;

            $people = [];
            foreach ($g['people'] as $p) {
                $io = $indByUser[$p['user_id']] ?? null;
                $myTime = $io ? Carbon::parse($io->out_at)->format('H:i') : null;
                $discrepa = ($myTime !== null && $deptTime !== null && $myTime !== $deptTime);
                if ($myTime !== null) { $marked++; } else { $pending++; }
                $people[] = [
                    'user_id'   => $p['user_id'],
                    'name'      => $p['name'],
                    'cargo'     => $p['cargo'] ?? '',
                    'my_time'   => $myTime,
                    'my_out_id' => $io ? $io->id : null,
                    'discrepa'  => $discrepa,
                ];
            }
            $groups[] = [
                'id'        => $deptId,
                'label'     => $g['label'],
                'dept_time' => $deptTime,
                'dept_out'  => $deptOut,
                'people'    => $people,
            ];
        }

        return view('admin.outs.index', [
            'day'          => $day,
            'dayLabel'     => ProductionCalendar::labelFor($day, $unitId),
            'groups'       => $groups,
            'marked'       => $marked,
            'pending'      => $pending,
            'generalCall'  => ($gc = OutWindow::generalCall($pid, $unitId, $day)) ? substr($gc, 0, 5) : null,
            'canDesignate' => OutAuthority::seesAll($viewer) || ! empty(OutAuthority::authorityDepartmentIds($viewer)),
        ]);
    }

    /** Pegar un mensaje de grupo → reporta la salida del DEPARTAMENTO (mismo servicio que el bot). */
    public function ingest(Request $request)
    {
        $viewer = $request->user();
        abort_unless(OutAuthority::canUseScreen($viewer), 403);
        $data = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
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

        return back()->with('success', 'Salida individual eliminada.');
    }

    // ---------------------------------------------------------------------------------------
    // Designados (autoridad por designación, no por puesto)
    // ---------------------------------------------------------------------------------------

    public function designations(Request $request)
    {
        $viewer = $request->user();
        abort_unless(OutAuthority::canUseScreen($viewer), 403);
        $pid = CurrentProduction::id();
        abort_unless($pid, 404);

        $visibleDeptIds = OutAuthority::visibleDepartmentIds($viewer);   // null = todos

        // Departamentos gestionables + sus designados actuales.
        $deptNames = DB::table('departments')->where('active', 1)->orderBy('sort_order')->orderBy('name')
            ->pluck('name', 'id')->all();
        if ($visibleDeptIds !== null) {
            $deptNames = array_intersect_key($deptNames, array_flip($visibleDeptIds));
        }

        $reporters = [];   // department_id => [ [user_id,name,row_id], ... ]
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

        // Crew de la producción para el selector (por depto), acotado a lo gestionable.
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
