<?php

namespace App\Support;

use App\Models\CallDay;
use App\Models\CallDeptOffset;
use App\Models\CallDayMeal;
use App\Models\CallPersonSchedule;
use App\Models\Department;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * CallSheetEngine — EL MOTOR DE HORARIOS (PARTE D). Layerea horario / pick up / marca de comida sobre
 * la MISMA salida de {@see DayRosterBuilder} (no un builder paralelo): DayRosterBuilder dice quién
 * trabaja y en qué estado; este motor le pone las horas.
 *
 * REGLA CENTRAL: nada se guarda como hora absoluta, todo como OFFSET (minutos) respecto al general.
 *   hora resultante = general_del_día + offset.
 * Los offsets de depto (N2) y persona (N3) son SINGLETONS por producción → persisten día con día; al
 * mover el general, todas las horas se recalculan y el patrón (el offset) se conserva.
 *
 * PRECEDENCIA del horario de una persona: literal de persona → offset de persona → literal de depto →
 * offset de depto → general (offset 0). Quien entra a media producción sin fila propia hereda el
 * offset de SU depto (o el general si su depto no tiene offset).
 *
 * VALORES QUE NO SON HORA (no se recalculan): en horario O/C, D/C, texto libre; en pick up N/A, SD,
 * W/N. Se guardan como LITERAL y se muestran tal cual.
 */
class CallSheetEngine
{
    /**
     * SETS de comida auto-establecidos según la hora del general. Cada servicio = [label, offset_min].
     * Café/Craft SIEMPRE (offset 0, es continuo). Los offsets son el DEFAULT del primer día; a partir
     * de ahí se HEREDAN del día anterior y sólo se mueven al mover el general (ejemplo del owner:
     * general 07:00 → Desayuno 06:00 (-60); al pasar el general a 09:00, Desayuno queda en 08:00).
     */
    public const MEAL_SETS = [
        // Rodaje de día (general antes del mediodía).
        'day' => [
            ['Café/Craft', 0], ['Desayuno', -60], ['Snack', 180], ['Comida', 360], ['Snack', 600],
        ],
        // Rodaje de tarde/noche (general al mediodía o después).
        'night' => [
            ['Café/Craft', 0], ['Snack', 180], ['Cena', 360], ['Snack fuerte', 600],
        ],
    ];

    /** El set de comidas que corresponde a una hora de general ('HH:MM'). Sin general → set de día. */
    public static function autoMealSet(?string $general): array
    {
        $hour = 7;
        if ($general && strlen($general) >= 2) {
            $hour = (int) substr($general, 0, 2);
        }

        return self::MEAL_SETS[$hour >= 12 ? 'night' : 'day'];
    }

    // ---- Aritmética de tiempo -----------------------------------------------------------------

    /** general 'HH:MM' + offset (min, con signo) → 'HH:MM' (envuelve en 24 h). null si no hay general. */
    public static function addMinutes(?string $general, ?int $offset): ?string
    {
        if ($general === null || $general === '') {
            return null;
        }
        [$h, $m] = array_map('intval', array_pad(explode(':', $general), 2, 0));
        $total = (($h * 60 + $m + (int) $offset) % 1440 + 1440) % 1440;

        return sprintf('%02d:%02d', intdiv($total, 60), $total % 60);
    }

    /** Diferencia en minutos entre una hora 'HH:MM' y el general (para editar por hora en vez de offset). */
    public static function minutesFrom(?string $general, ?string $time): ?int
    {
        if (! $general || ! $time) {
            return null;
        }
        [$gh, $gm] = array_map('intval', array_pad(explode(':', $general), 2, 0));
        [$th, $tm] = array_map('intval', array_pad(explode(':', $time), 2, 0));
        $diff = ($th * 60 + $tm) - ($gh * 60 + $gm);
        // Normaliza a la ventana [-720, 720] para que un precall grande no salga como +22 h.
        if ($diff > 720)  { $diff -= 1440; }
        if ($diff < -720) { $diff += 1440; }

        return $diff;
    }

    // ---- Cargar la configuración del día ------------------------------------------------------

    /** El CallDay de (producción, fecha), o null si no se ha configurado. */
    public static function callDay(int $productionId, $date): ?CallDay
    {
        $d = $date instanceof Carbon ? $date->toDateString() : Carbon::parse($date)->toDateString();

        return CallDay::where('production_id', $productionId)->whereDate('call_date', $d)->first();
    }

    /**
     * [department_id => CallDeptOffset] de la UNIDAD VIGENTE (2b). El motor no cambió: sólo lee de otro
     * conjunto. Con una sola unidad no filtra → los mismos singletons de la producción de hoy.
     */
    public static function deptOffsets(int $productionId): array
    {
        return CurrentUnit::applyTo(CallDeptOffset::where('production_id', $productionId))
            ->get()->keyBy('department_id')->all();
    }

    /** [user_id => CallPersonSchedule] de la UNIDAD VIGENTE (2b). Con una sola unidad, idéntico a hoy. */
    public static function personSchedules(int $productionId): array
    {
        return CurrentUnit::applyTo(CallPersonSchedule::where('production_id', $productionId))
            ->get()->keyBy('user_id')->all();
    }

    // ---- Resolver el horario / pick up de una persona -----------------------------------------

    /**
     * Horario resuelto de una persona. Devuelve ['time'=>?, 'literal'=>?, 'source'=>str, 'suggested'=>?].
     * `suggested` es la hora que le tocaría SÓLO por su depto (para comparar en la pantalla de personas).
     */
    public static function resolveSchedule(?string $general, ?CallDeptOffset $dept, ?CallPersonSchedule $person): array
    {
        $suggested = self::deptTime($general, $dept);

        if ($person) {
            if ($person->schedule_literal !== null && $person->schedule_literal !== '') {
                return ['time' => null, 'literal' => $person->schedule_literal, 'source' => 'person', 'suggested' => $suggested['time'] ?? $suggested['literal']];
            }
            if ($person->schedule_offset_minutes !== null) {
                return ['time' => self::addMinutes($general, $person->schedule_offset_minutes), 'literal' => null, 'source' => 'person', 'suggested' => $suggested['time'] ?? $suggested['literal']];
            }
        }

        // Sin fila propia → hereda el depto (o el general).
        return $suggested + ['suggested' => $suggested['time'] ?? $suggested['literal']];
    }

    /** Hora (o literal) que impone el DEPARTAMENTO; general si el depto no tiene offset. */
    private static function deptTime(?string $general, ?CallDeptOffset $dept): array
    {
        if ($dept) {
            if ($dept->literal_value !== null && $dept->literal_value !== '') {
                return ['time' => null, 'literal' => $dept->literal_value, 'source' => 'dept'];
            }
            if ($dept->offset_minutes !== null) {
                return ['time' => self::addMinutes($general, $dept->offset_minutes), 'literal' => null, 'source' => 'dept'];
            }
        }

        return ['time' => $general, 'literal' => null, 'source' => 'general'];
    }

    /** Pick up resuelto: ['time'=>?, 'literal'=>?, 'place'=>?]. Sin dato propio → todo null (columna vacía). */
    public static function resolvePickup(?string $general, ?CallPersonSchedule $person, array $placeById = []): array
    {
        if (! $person) {
            return ['time' => null, 'literal' => null, 'place' => null];
        }
        $place = null;
        if ($person->pickup_place_id && isset($placeById[$person->pickup_place_id])) {
            $place = $placeById[$person->pickup_place_id];
        } elseif ($person->pickup_place_text) {
            $place = $person->pickup_place_text;
        }
        if ($person->pickup_literal !== null && $person->pickup_literal !== '') {
            return ['time' => null, 'literal' => $person->pickup_literal, 'place' => $place];
        }
        if ($person->pickup_offset_minutes !== null) {
            return ['time' => self::addMinutes($general, $person->pickup_offset_minutes), 'literal' => null, 'place' => $place];
        }

        return ['time' => null, 'literal' => null, 'place' => $place];
    }

    // ---- Comidas + wrap -----------------------------------------------------------------------

    /** Hora de un servicio de comida: hora fija si la tiene, si no general + offset. */
    public static function mealTime(CallDayMeal $meal, ?string $general): ?string
    {
        if (! empty($meal->explicit_time)) {
            return substr((string) $meal->explicit_time, 0, 5);
        }

        return self::addMinutes($general, $meal->offset_minutes);
    }

    /** Wrap estimado del día = general + jornada, sólo si está encendido. */
    public static function wrapEstimate(?CallDay $callDay): ?string
    {
        if (! $callDay || ! $callDay->wrap_estimate_enabled || ! $callDay->journey_minutes) {
            return null;
        }

        return self::addMinutes($callDay->generalHHMM(), (int) $callDay->journey_minutes);
    }

    // ---- Ensamblado para la pantalla de personas / el back ------------------------------------

    /**
     * Estructura enriquecida del día: los grupos del roster con horario/pick up/comida por persona.
     * `$allDepartments=true` (para el back) incluye TODOS los deptos del catálogo, con o sin gente
     * (los vacíos salen como cabecera sin filas → N/C en el back). Reusa DayRosterBuilder tal cual.
     *
     * @return array{date:Carbon, general:?string, wrap:?string, call_day:?CallDay, groups:array, meals:array, cast_count:?int, bg_count:?int}
     */
    public static function forDay(User $viewer, $date, bool $allDepartments = false): array
    {
        $day          = $date instanceof Carbon ? $date->copy()->startOfDay() : Carbon::parse($date)->startOfDay();
        $productionId = CurrentProduction::id();

        $roster  = DayRosterBuilder::build($viewer, $day);
        $callDay = $productionId ? self::callDay($productionId, $day) : null;
        $general = $callDay ? $callDay->generalHHMM() : null;

        $deptOffsets = $productionId ? self::deptOffsets($productionId) : [];
        $persons     = $productionId ? self::personSchedules($productionId) : [];
        $placeById   = $productionId
            ? DB::table('call_places')->where('production_id', $productionId)->pluck('code', 'id')->all()
            : [];

        // Enriquecer cada persona de los grupos del roster.
        $groups = [];
        foreach ($roster['groups'] as $g) {
            $people = [];
            foreach ($g['people'] as $p) {
                $deptOff = ($p['dept_id'] && isset($deptOffsets[$p['dept_id']])) ? $deptOffsets[$p['dept_id']] : null;
                $person  = $persons[$p['user_id']] ?? null;
                $sched   = self::resolveSchedule($general, $deptOff, $person);
                $pickup  = self::resolvePickup($general, $person, $placeById);

                $people[] = $p + [
                    'schedule'      => $sched,
                    'pickup'        => $pickup,
                    'hotel'         => $person ? (string) $person->hotel_code : '',
                    'meal_mark'     => $person ? (bool) $person->meal_mark : true,
                    'has_own'       => $person !== null && ($person->schedule_offset_minutes !== null || $person->schedule_literal !== null),
                ];
            }
            // array_merge (no el operador +) para SOBRESCRIBIR 'people' con la versión enriquecida.
            $groups[] = array_merge($g, ['people' => $people]);
        }

        if ($allDepartments) {
            $groups = self::padWithEmptyDepartments($groups, $deptOffsets, $general);
        }

        // Comidas del día.
        $meals = [];
        if ($callDay) {
            foreach ($callDay->meals as $meal) {
                $meals[] = [
                    'id'      => $meal->id,
                    'label'   => $meal->label,
                    'enabled' => (bool) $meal->enabled,
                    'time'    => self::mealTime($meal, $general),
                    'place'   => $meal->place_id && isset($placeById[$meal->place_id]) ? $placeById[$meal->place_id] : $meal->place_text,
                    'cast'    => $meal->cast_override ?? $callDay->cast_count,
                    'bg'      => $meal->bg_override ?? $callDay->bg_count,
                ];
            }
        }

        return [
            'date'       => $day,
            'general'    => $general,
            'wrap'       => self::wrapEstimate($callDay),
            'call_day'   => $callDay,
            'groups'     => $groups,
            'meals'      => $meals,
            'cast_count' => $callDay ? $callDay->cast_count : null,
            'bg_count'   => $callDay ? $callDay->bg_count : null,
        ];
    }

    /**
     * Rellena los grupos con los departamentos del catálogo que NO tienen gente ese día, en el orden
     * canónico, para que el BACK imprima TODOS los deptos siempre (los vacíos → N/C, sin filas).
     */
    private static function padWithEmptyDepartments(array $groups, array $deptOffsets, ?string $general): array
    {
        $present = [];
        foreach ($groups as $g) {
            $present[$g['label']] = true;
        }
        $catalog = Department::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'name_en', 'sort_order']);
        $extra = [];
        foreach ($catalog as $d) {
            if (isset($present[$d->name])) {
                continue;
            }
            $extra[] = [
                'label'    => $d->name,
                'label_en' => $d->name_en,
                'sort'     => $d->sort_order,
                'people'   => [],
                'counts'   => null,
            ];
        }
        $all = array_merge($groups, $extra);
        usort($all, fn ($a, $b) => [$a['sort'] ?? PHP_INT_MAX, $a['label']] <=> [$b['sort'] ?? PHP_INT_MAX, $b['label']]);

        return $all;
    }
}
