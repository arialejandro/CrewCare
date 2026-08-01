<?php

namespace App\Support;

use App\Models\ActionItem;
use App\Models\IssuedPermit;
use App\Models\ScoutingReport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contexto de captura del DSR (captura fluida, Paso 5): "lo de hoy al frente".
 *
 * Reúne lo que el contexto SUGIERE al llenar un Daily Safety Report, para no recorrer
 * 38 casillas a ciegas: permisos emitidos ese día, acciones correctivas abiertas de la
 * producción, y los temas (categorías) que la LOCACIÓN ya evaluó en su scouting.
 *
 * NADA se marca solo: esto solo SUGIERE; marcar es un acto de quien firma. Todo va con
 * guardas defensivas (tablas/columnas/modelos que podrían no existir) y try/catch: si algo
 * falla, devuelve vacío y el formulario sigue funcionando.
 */
class DsrContext
{
    /** Permisos ABIERTOS emitidos para esta producción y jornada (día de rodaje). */
    public static function permitsToday($pid, $shootDay): Collection
    {
        if ($pid === null || $shootDay === null) {
            return collect();
        }
        if (!class_exists(IssuedPermit::class) || !Schema::hasTable('issued_permits')) {
            return collect();
        }
        try {
            return IssuedPermit::query()
                ->where('is_active', 1)
                ->whereNull('closed_at')
                ->whereNull('suspended_at')
                ->where('production_id', $pid)
                ->where('shoot_day', $shootDay)
                ->orderBy('id', 'desc')
                ->get(['id', 'permit_name', 'permit_code', 'site_label', 'activity_description', 'permit_family']);
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /**
     * Mapa nombre-de-locación (minúsculas) → temas (claves de categoría) que su scouting
     * ya evaluó. Lo consume el cliente: al elegir una locación reconocida, sugiere esos
     * temas (chips). Acotado a la producción vigente si la tiene; el más reciente gana.
     */
    public static function locationTopics($pid): array
    {
        if (!Schema::hasTable('scouting_reports')) {
            return [];
        }
        try {
            $q = ScoutingReport::query()
                ->whereNotNull('location_name')->where('location_name', '!=', '');
            if ($pid !== null && Schema::hasColumn('scouting_reports', 'production_id')) {
                if ((clone $q)->where('production_id', $pid)->exists()) {
                    $q->where('production_id', $pid);
                }
            }
            $rows = $q->orderBy('id', 'desc')->limit(100)->get(['id', 'location_name', 'risk_assessment']);
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $s) {
            $key = trim(mb_strtolower((string) $s->location_name));
            if ($key === '' || isset($out[$key])) {
                continue; // el más reciente ya ganó
            }
            $cats = [];
            $ra = $s->risk_assessment;
            if (is_array($ra)) {
                foreach ($ra as $h) {
                    if (is_array($h) && !empty($h['key']) && empty($h['unclassified'])) {
                        $cats[$h['key']] = true;
                    }
                }
            }
            if ($cats) {
                $out[$key] = array_keys($cats);
            }
        }
        return $out;
    }

    /**
     * Acciones correctivas ABIERTAS de la producción, vía los reportes padre que enlazan a
     * la producción (directo: scouting, permisos, inspecciones, DSR→logs; indirecto:
     * hazard/condición por su scouting). Injury no tiene enlace de producción → no entra.
     */
    public static function openActionItems($pid, $limit = 12): Collection
    {
        if ($pid === null || !class_exists(ActionItem::class) || !Schema::hasTable('action_items')) {
            return collect();
        }
        try {
            $map = [];

            $scoutIds = self::idsByProduction('scouting_reports', $pid);
            if ($scoutIds) {
                $map[ScoutingReport::class] = $scoutIds;
            }

            $dsrIds = self::idsByProduction('daily_reports', $pid);
            if ($dsrIds && Schema::hasTable('daily_logs') && class_exists(\App\Models\DailyLog::class)) {
                $logIds = \App\Models\DailyLog::whereIn('daily_report_id', $dsrIds)->pluck('id')->all();
                if ($logIds) {
                    $map[\App\Models\DailyLog::class] = $logIds;
                }
            }

            $permitIds = self::idsByProduction('issued_permits', $pid);
            if ($permitIds && class_exists(IssuedPermit::class)) {
                $map[IssuedPermit::class] = $permitIds;
            }

            if (class_exists(\App\Models\ToolInspection::class)) {
                $inspIds = self::idsByProduction('tool_inspections', $pid);
                if ($inspIds) {
                    $map[\App\Models\ToolInspection::class] = $inspIds;
                }
            }

            // Acto/condición insegura: cuelgan de un scouting de la producción.
            if ($scoutIds) {
                foreach ([['hazardnotifications', \App\Models\hazardnotification::class], ['unsafeconds', \App\Models\unsafecond::class]] as $pair) {
                    [$tbl, $cls] = $pair;
                    if (class_exists($cls) && Schema::hasTable($tbl) && Schema::hasColumn($tbl, 'scouting_report_id')) {
                        $ids = $cls::whereIn('scouting_report_id', $scoutIds)->pluck('id')->all();
                        if ($ids) {
                            $map[$cls] = $ids;
                        }
                    }
                }
            }

            if (!$map) {
                return collect();
            }

            return ActionItem::query()
                ->where('status', '!=', ActionItem::STATUS_CLOSED)
                ->whereNull('closed_at')
                ->where(function ($w) use ($map) {
                    foreach ($map as $type => $ids) {
                        $w->orWhere(function ($x) use ($type, $ids) {
                            $x->where('actionable_type', $type)->whereIn('actionable_id', $ids);
                        });
                    }
                })
                ->orderByRaw('due_date IS NULL, due_date ASC')
                ->limit($limit)
                ->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    private static function idsByProduction($table, $pid): array
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'production_id')) {
            return [];
        }
        try {
            return DB::table($table)->where('production_id', $pid)->pluck('id')->all();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
