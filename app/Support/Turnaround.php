<?php

namespace App\Support;

use App\Models\CallDay;
use App\Models\DepartmentOut;
use App\Models\IndividualOut;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turnaround — ESTADÍSTICA SILENCIOSA (§4). El descanso entre la SALIDA y el SIGUIENTE LLAMADO.
 *
 * 🔴 NO bloquea llamados, NO calcula penalizaciones, NO alerta. Se calcula, se consulta y se exporta.
 * Se DERIVA (no se guarda en columna): un turnaround congelado quedaría stale al mover el llamado
 * siguiente. SIN dato de salida NO hay turnaround (nunca se inventa; no se usa el del departamento para
 * alguien que salió distinto sin haberlo reportado — para eso está la salida individual, que gana).
 *
 * "Siguiente llamado" = el general_call del PRÓXIMO call_day de la unidad tras el día de la salida,
 * más el offset del depto (o de la persona), reusando App\Support\CallSheetEngine.
 */
class Turnaround
{
    /** Próximo call_day (con general_call) de la unidad, en fecha estrictamente posterior a $afterDate. */
    public static function nextCallDay(int $productionId, ?int $unitId, string $afterDate): ?CallDay
    {
        if (! Schema::hasTable('call_days')) {
            return null;
        }
        $q = CallDay::where('production_id', $productionId)
            ->whereNotNull('general_call')
            ->whereDate('call_date', '>', $afterDate);
        if (Schema::hasColumn('call_days', 'unit_id')) {
            $unitId === null ? $q->whereNull('unit_id') : $q->where('unit_id', $unitId);
        }

        return $q->orderBy('call_date')->first();
    }

    /** Offset numérico (min) del depto en la unidad, o null (sin fila o literal no-hora). */
    public static function deptOffsetMinutes(int $productionId, ?int $unitId, int $departmentId): ?int
    {
        if (! Schema::hasTable('call_dept_offsets')) {
            return null;
        }
        $q = DB::table('call_dept_offsets')
            ->where('production_id', $productionId)
            ->where('department_id', $departmentId);
        if (Schema::hasColumn('call_dept_offsets', 'unit_id')) {
            $unitId === null ? $q->whereNull('unit_id') : $q->where('unit_id', $unitId);
        }
        $row = $q->first();

        return ($row && $row->offset_minutes !== null) ? (int) $row->offset_minutes : null;
    }

    /** Offset numérico (min) de la persona en la unidad, o null. */
    public static function personOffsetMinutes(int $productionId, ?int $unitId, int $userId): ?int
    {
        if (! Schema::hasTable('call_person_schedules')) {
            return null;
        }
        $q = DB::table('call_person_schedules')
            ->where('production_id', $productionId)
            ->where('user_id', $userId);
        if (Schema::hasColumn('call_person_schedules', 'unit_id')) {
            $unitId === null ? $q->whereNull('unit_id') : $q->where('unit_id', $unitId);
        }
        $row = $q->first();

        return ($row && $row->schedule_offset_minutes !== null) ? (int) $row->schedule_offset_minutes : null;
    }

    /** Datetime del siguiente llamado aplicando un offset (min) sobre el general del próximo call_day. */
    private static function nextCallAt(?CallDay $next, ?int $offsetMinutes): ?Carbon
    {
        if ($next === null || empty($next->general_call)) {
            return null;
        }
        $general = substr((string) $next->general_call, 0, 5);            // 'HH:MM'
        $time = CallSheetEngine::addMinutes($general, (int) ($offsetMinutes ?? 0)); // 'HH:MM'
        if ($time === null) {
            return null;
        }
        $date = $next->call_date instanceof Carbon
            ? $next->call_date->toDateString()
            : Carbon::parse($next->call_date)->toDateString();

        return Carbon::parse($date . ' ' . $time . ':00');
    }

    /** Minutos de descanso entre la salida y el siguiente llamado (con signo). */
    public static function minutes(Carbon $outAt, Carbon $nextCallAt): int
    {
        return $outAt->diffInMinutes($nextCallAt, false);
    }

    /** "10 h 30 min" a partir de minutos; '—' si null. */
    public static function humanize(?int $minutes): string
    {
        if ($minutes === null) {
            return '—';
        }
        $sign = $minutes < 0 ? '-' : '';
        $abs = abs($minutes);

        return $sign . intdiv($abs, 60) . ' h ' . str_pad((string) ($abs % 60), 2, '0', STR_PAD_LEFT) . ' min';
    }

    /**
     * Filas de turnaround de un día de rodaje, para consultar y exportar. Una por salida de depto
     * (turnaround del depto) y una por salida individual (gana sobre la del depto para esa persona).
     * `visibleDeptIds` null = todos; si no, filtra a esos deptos (autoridad por puesto).
     *
     * @param  int[]|null  $visibleDeptIds
     * @return array  [ ['scope'=>'dept'|'individual','department_id'=>,'department'=>,'user_id'=>?,'name'=>?,
     *                    'out_at'=>Carbon,'next_call_at'=>?Carbon,'minutes'=>?int], ... ]
     */
    public static function rowsForDay(int $productionId, ?int $unitId, string $shootDate, ?array $visibleDeptIds = null): array
    {
        $deptNames = DB::table('departments')->pluck('name', 'id')->all();
        $next = self::nextCallDay($productionId, $unitId, $shootDate);

        $rows = [];

        // --- Salidas de departamento ---
        $dq = DepartmentOut::where('production_id', $productionId)->whereDate('shoot_date', $shootDate);
        $unitId === null ? $dq->whereNull('unit_id') : $dq->where('unit_id', $unitId);
        if ($visibleDeptIds !== null) {
            $dq->whereIn('department_id', $visibleDeptIds ?: [-1]);
        }
        foreach ($dq->get() as $d) {
            $outAt = $d->out_at instanceof Carbon ? $d->out_at : Carbon::parse($d->out_at);
            $nextAt = self::nextCallAt($next, self::deptOffsetMinutes($productionId, $unitId, (int) $d->department_id));
            $rows[] = [
                'scope'         => 'dept',
                'department_id' => (int) $d->department_id,
                'department'    => $deptNames[$d->department_id] ?? ('#' . $d->department_id),
                'user_id'       => null,
                'name'          => null,
                'out_at'        => $outAt,
                'next_call_at'  => $nextAt,
                'minutes'       => $nextAt ? self::minutes($outAt, $nextAt) : null,
                'note'          => $d->note,
            ];
        }

        // --- Salidas individuales ---
        $iq = IndividualOut::where('production_id', $productionId)->whereDate('shoot_date', $shootDate);
        $unitId === null ? $iq->whereNull('unit_id') : $iq->where('unit_id', $unitId);
        if ($visibleDeptIds !== null) {
            // Individual sin depto conocido: se muestra sólo a quien ve todo (visibleDeptIds null).
            $iq->whereIn('department_id', $visibleDeptIds ?: [-1]);
        }
        $userNames = [];
        $userIds = $iq->pluck('user_id')->all();
        if (! empty($userIds)) {
            foreach (DB::table('users')->whereIn('id', $userIds)->get(['id', 'name', 'lname', 'lname2', 'ncreditos']) as $u) {
                $userNames[$u->id] = \App\Models\User::displayName($u);
            }
        }
        foreach ($iq->get() as $it) {
            $outAt = $it->out_at instanceof Carbon ? $it->out_at : Carbon::parse($it->out_at);
            $nextAt = self::nextCallAt($next, self::personOffsetMinutes($productionId, $unitId, (int) $it->user_id));
            $rows[] = [
                'scope'         => 'individual',
                'department_id' => $it->department_id ? (int) $it->department_id : null,
                'department'    => $it->department_id ? ($deptNames[$it->department_id] ?? '') : '',
                'user_id'       => (int) $it->user_id,
                'name'          => $userNames[$it->user_id] ?? ('#' . $it->user_id),
                'out_at'        => $outAt,
                'next_call_at'  => $nextAt,
                'minutes'       => $nextAt ? self::minutes($outAt, $nextAt) : null,
                'note'          => $it->note,
            ];
        }

        return $rows;
    }
}
