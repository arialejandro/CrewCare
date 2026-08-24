<?php

namespace App\Support;

use App\Models\ContractEnvelope;
use App\Models\PayeeContract;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * DayRosterBuilder — "¿QUIÉN TRABAJA HOY?" agrupado por departamento (2026-08-22).
 *
 * Responde el estado del roster de TODO el crew de una FECHA en un número CONSTANTE de consultas
 * (no 2 por persona como iterar PayeeContract::rosterStateOn). El estado por-día vive en la
 * consulta agregada; el "sobre completado" viaja como columna, de donde sale el 4º estado.
 *
 * REUSA, no reinventa:
 *  - `User::applyDepartmentScope` → el HOD ve su departamento; producción/coordinación ven todo
 *    (mismos bypass que el resto). Funciona tal cual porque `users` va JOINeado (correlaciona por users.id).
 *  - `User::applyRosterOrder` → orden depto→puesto con el HOD arriba (idéntico a las 3 pantallas).
 *  - El agrupado por departamento de CrewRosterBuilder (pivote production_user + catálogo, fallback zone).
 *    NO se reusa CrewRosterBuilder entero: filtra crewlist_visible y no tiene dimensión por día.
 *
 * LOS 4 ESTADOS (ver PayeeContract::ROSTER_*) — el mapeo vive en PayeeContract::resolveRosterState
 * (FUENTE ÚNICA; rosterStateOn delega en la misma):
 *  - CALLED             activo, con fecha ese día, SOBRE COMPLETADO.
 *  - PENDING_SIGNATURE  activo, con fecha ese día, pero SIN sobre completado (accionable, no invisible).
 *  - NOT_CALLED         activo, sin fecha ese día.
 *  - OUT                inactivo (contrato), o DAY PLAYER vencido. El crew FIJO no cae a OUT por
 *                       vencer (PARTE C): se queda con N/C hasta el wrap.
 * La persona DADA DE BAJA (users.activo=0) no aparece en ningún estado: se filtra en la consulta
 * (PARTE G). La PUERTA no cambia: sin sobre completado nadie cuenta como CALLED.
 */
class DayRosterBuilder
{
    /** Prioridad para deduplicar (una persona con >1 contrato crew_work): gana el estado más "presente". */
    private const STATE_PRIORITY = [
        PayeeContract::ROSTER_CALLED            => 3,
        PayeeContract::ROSTER_PENDING_SIGNATURE => 2,
        PayeeContract::ROSTER_NOT_CALLED        => 1,
        PayeeContract::ROSTER_OUT               => 0,
    ];

    /**
     * @return array{date: Carbon, groups: array, unordered: array, counts: array, production: ?string}
     */
    public static function build(User $viewer, $date): array
    {
        $day = ($date instanceof Carbon ? $date->copy() : Carbon::parse($date))->startOfDay();

        $productionId = CurrentProduction::id();
        $production   = CurrentProduction::get();

        // Sin producción vigente no hay roster (los contratos crew_work cuelgan de una producción).
        if (! $productionId) {
            return self::emptyResult($day, $production);
        }

        // --- Catálogos + pivote (nº FIJO de consultas, igual que CrewRosterBuilder) ---
        $deptNameById = [];
        $deptSortById = [];
        $deptSortByName = [];
        foreach (DB::table('departments')->get(['id', 'name', 'sort_order']) as $d) {
            $deptNameById[$d->id] = $d->name;
            $deptSortById[$d->id] = $d->sort_order;
            if (! array_key_exists($d->name, $deptSortByName)) {
                $deptSortByName[$d->name] = $d->sort_order;
            }
        }
        $posNameById = [];
        foreach (DB::table('positions')->get(['id', 'name']) as $p) {
            $posNameById[$p->id] = $p->name;
        }
        $pivot = [];
        foreach (DB::table('production_user')->where('production_id', $productionId)
                    ->get(['user_id', 'department_id', 'position_id']) as $r) {
            $pivot[$r->user_id] = $r;
        }

        // --- LA CONSULTA AGREGADA (1 query) ---
        $query = DB::table('payee_contracts as pc')
            ->join('payees as p', 'p.id', '=', 'pc.payee_id')
            ->join('users', 'users.id', '=', 'p.user_id')
            ->where('pc.concept', PayeeContract::CONCEPT_CREW)
            ->where('pc.production_id', $productionId)
            // PARTE G: la persona DADA DE BAJA (users.activo=0) desaparece del roster/back/búsquedas.
            // No se borra: se desactiva (CrewStatusController::desactivarusuario). Antes NO se filtraba
            // → un desactivado seguía apareciendo; ahora se excluye como en los demás listados.
            ->where('users.activo', 1)
            ->selectRaw(
                'users.id as user_id, users.name, users.lname, users.lname2, users.ncreditos, '
                . 'users.zone, users.puestodepartamento, '
                . 'pc.id as contract_id, pc.is_active, pc.definitive_end_date, pc.payment_frequency, '
                . 'EXISTS(SELECT 1 FROM payee_contract_work_dates wd '
                . '       WHERE wd.payee_contract_id = pc.id AND wd.work_date = ?) as called, '
                . 'EXISTS(SELECT 1 FROM contract_envelopes ce '
                . '       WHERE ce.payee_contract_id = pc.id AND ce.status = ?) as envelope_completed',
                [$day->toDateString(), ContractEnvelope::STATUS_COMPLETED]
            );

        // El HOD ve su depto; producción/coordinación ven todo. `users` joineado → correlaciona bien.
        $query = User::applyDepartmentScope($query, $viewer);
        // Orden depto→puesto, HOD arriba (idéntico a las pantallas de crew).
        $query = User::applyRosterOrder($query, $productionId);

        $rows = $query->get();

        // --- Dedup por persona (una fila por user_id, con el mejor estado); orden preservado. ---
        $best = [];   // user_id => ['row'=>obj, 'state'=>str]
        $order = [];  // orden de aparición (ya viene por applyRosterOrder)
        foreach ($rows as $row) {
            $state = self::stateOf($row, $day);
            $uid = $row->user_id;
            if (! isset($best[$uid])) {
                $best[$uid] = ['row' => $row, 'state' => $state];
                $order[] = $uid;
            } elseif (self::STATE_PRIORITY[$state] > self::STATE_PRIORITY[$best[$uid]['state']]) {
                $best[$uid]['state'] = $state;   // conserva la posición, sube el estado
            }
        }

        // --- Agrupa por departamento (pivote → catálogo; fallback etiqueta legacy zone). ---
        $groups = [];  // deptKey => ['label','sort','people'=>[],'counts'=>[...]]
        $totals = self::zeroCounts();
        foreach ($order as $uid) {
            $row   = $best[$uid]['row'];
            $state = $best[$uid]['state'];
            $pv    = $pivot[$uid] ?? null;

            if ($pv && $pv->department_id && isset($deptNameById[$pv->department_id])) {
                $deptName = $deptNameById[$pv->department_id];
                $deptSort = $deptSortById[$pv->department_id];
            } else {
                $deptName = ($row->zone !== null && $row->zone !== '') ? $row->zone : null;
                $deptSort = ($deptName !== null && isset($deptSortByName[$deptName]))
                    ? $deptSortByName[$deptName] : null;
            }
            $deptKey   = $deptName === null ? '__none__' : $deptName;
            $deptLabel = $deptName === null ? 'Sin departamento' : $deptName;

            $posName = null;
            if ($pv && $pv->position_id && isset($posNameById[$pv->position_id])) {
                $posName = $posNameById[$pv->position_id];
            } elseif ($row->puestodepartamento !== null && $row->puestodepartamento !== '') {
                $posName = $row->puestodepartamento;
            }

            if (! isset($groups[$deptKey])) {
                $groups[$deptKey] = ['label' => $deptLabel, 'sort' => $deptSort, 'people' => [], 'counts' => self::zeroCounts()];
            }
            $groups[$deptKey]['people'][] = [
                // user_id/dept_id los usa CallSheetEngine para LAYERAR horario/pick up/comida sobre
                // esta misma salida (no un builder paralelo). La vista de roster los ignora.
                'user_id'   => (int) $uid,
                'dept_id'   => ($pv && $pv->department_id) ? (int) $pv->department_id : null,
                'frequency' => $row->payment_frequency,   // day_player va al bucket Crew Adicional del back
                'name'      => User::displayName($row),
                'cargo'     => $posName ?? '',
                'state'     => $state,
            ];
            $groups[$deptKey]['counts'][$state]++;
            $groups[$deptKey]['counts']['total']++;
            $totals[$state]++;
            $totals['total']++;
        }

        // --- Ordena los grupos: canónicos por sort_order; los sin orden al final (como el roster export). ---
        $canonical = [];
        $trailing  = [];
        foreach ($groups as $key => $g) {
            if ($g['sort'] === null) {
                $trailing[$key] = $g;
            } else {
                $canonical[$key] = $g;
            }
        }
        uasort($canonical, function ($a, $b) {
            return [$a['sort'], $a['label']] <=> [$b['sort'], $b['label']];
        });
        uasort($trailing, function ($a, $b) {
            return strcmp($a['label'], $b['label']);
        });
        $ordered = $canonical + $trailing;

        $unordered = array_values(array_map(function ($g) {
            return ['label' => $g['label'], 'count' => count($g['people'])];
        }, $trailing));

        return [
            'date'       => $day,
            'groups'     => array_values($ordered),
            'unordered'  => $unordered,
            'counts'     => $totals,
            'production' => $production ? ($production->name ?? null) : null,
        ];
    }

    /**
     * Estado del roster de una fila de la consulta agregada — delega en la FUENTE ÚNICA
     * {@see PayeeContract::resolveRosterState}. El vencimiento sólo saca a los DAY PLAYERS; el crew
     * fijo (weekly/biweekly/NULL) se queda hasta el wrap (PARTE C).
     */
    private static function stateOf($row, Carbon $day): string
    {
        return PayeeContract::resolveRosterState(
            (bool) (int) $row->is_active,
            $row->payment_frequency,
            $row->definitive_end_date,
            (int) $row->called === 1,
            (int) $row->envelope_completed === 1,
            $day
        );
    }

    private static function zeroCounts(): array
    {
        return [
            PayeeContract::ROSTER_CALLED            => 0,
            PayeeContract::ROSTER_PENDING_SIGNATURE => 0,
            PayeeContract::ROSTER_NOT_CALLED        => 0,
            PayeeContract::ROSTER_OUT               => 0,
            'total'                                 => 0,
        ];
    }

    private static function emptyResult(Carbon $day, $production): array
    {
        return [
            'date'       => $day,
            'groups'     => [],
            'unordered'  => [],
            'counts'     => self::zeroCounts(),
            'production' => $production ? ($production->name ?? null) : null,
        ];
    }
}
