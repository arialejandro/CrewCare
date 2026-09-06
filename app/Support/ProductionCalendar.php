<?php

namespace App\Support;

use App\Models\DailyReport;
use App\Models\ShootDay;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * ProductionCalendar — el CONTADOR DE DÍAS de la producción, derivado y no tecleable.
 *
 * ============================ POR QUÉ EXISTE ============================
 * `daily_reports.shoot_day` era un entero que se escribía a mano en el formulario, y los datos
 * de esta misma base lo prueban: 0, 9, 10, 11, 8, 1, 1, 1 — desordenado, repetido, y un DSR con
 * 150 personas de crew marcado como DÍA 0. Razón del owner, textual: "llegas a locación a las
 * 4AM y en lugar de declarar día 12 pones 13 u 11; eso rompe la lógica más fácil de lo que
 * parece". Un contador que se teclea a las 4 de la mañana no es un contador.
 *
 * ============================ LAS REGLAS ============================
 * 1) DÍA DE RODAJE = POSITIVO, SECUENCIAL y DERIVADO. El primer día con DSR es 1, el siguiente
 *    día DISTINTO es 2, y así. No hay forma de equivocarse porque nadie lo escribe.
 *
 * 2) PREP = NEGATIVO, contando LUNES A SÁBADO. EL DOMINGO NO CUENTA — no recibe número, y por
 *    eso dayNumber() devuelve null en domingo de prep en lugar de 0: un 0 se confundiría con
 *    "sin dato" y además volvería a meter el bug del "día 0" por la puerta de atrás.
 *
 * 3) REPRESENTACIÓN según la distancia. El número interno SIEMPRE son días; lo que cambia es
 *    cómo se dice. A más de una semana se dice en SEMANAS (una semana de prep son 6 días
 *    trabajados, lunes a sábado), y a partir de la semana -1 se dice en DÍAS (-6 … -1).
 *    Comprobado contra el ejemplo del owner: del 23-jul-2026 al lunes 5-oct-2026 hay 62 días
 *    lunes-a-sábado → ceil(62/6) = 11 → "semana -11". ✔
 *
 * 4) ANCLA. El día 1 es `productions.start_date`. Si la producción todavía no la tiene, el ancla
 *    es el primer día con DSR — así el contador funciona desde el primer reporte, sin esperar a
 *    que alguien configure nada, y se vuelve exacto en cuanto se fije la fecha.
 *
 * 5) WRAP. `productions.end_date` es la fecha de wrap preestablecida y es AJUSTABLE a propósito:
 *    entre el 3 % y el 10 % de las producciones se extienden días o semanas. Mover el wrap es un
 *    UPDATE de una columna, no una migración.
 *
 * ============================ LO QUE NO HACE ============================
 * NO escribe en la base y NO toca ninguna columna que entre en el hash de un documento firmado.
 * `shoot_day` se sigue guardando (para que un DSR ya sellado siga casando con su sello y para
 * que producción conserve su etiqueta), pero ya no se TECLEA: el store lo rellena con lo que
 * dice esta clase. Los documentos históricos se muestran con el número DERIVADO, que es una
 * decisión de PANTALLA — no reescribe nada, así que ningún sello queda marcado "ALTERADO".
 */
class ProductionCalendar
{
    /** Días trabajados por semana de prep (lunes a sábado). El domingo no cuenta. */
    const PREP_WEEK_DAYS = 6;

    /** Caché por petición, POR UNIDAD (clave: 'n' = principal/null; o el id de la unidad). */
    private static $datesCache = [];
    private static $anchorCache = [];

    /** Clave de caché por unidad. NULL (principal) → 'n'. */
    private static function unitKey($unitId): string
    {
        return $unitId === null ? 'n' : (string) (int) $unitId;
    }

    // ---------------------------------------------------------------------------------------
    // Cimientos
    // ---------------------------------------------------------------------------------------

    /**
     * Fechas DISTINTAS con reporte diario, ascendentes, desde el ancla en adelante.
     *
     * Se acota a la producción vigente SÓLO si esa producción tiene DSRs propios; si la columna
     * `production_id` sigue vacía (el caso de hoy), se cuentan todos. Así el contador no se queda
     * mudo mientras se termina de poblar el vínculo.
     *
     * @return array  ['Y-m-d', ...]
     */
    public static function shootDates(?int $unitId = null)
    {
        $k = self::unitKey($unitId);
        if (array_key_exists($k, self::$datesCache)) {
            return self::$datesCache[$k];
        }

        // EL CALENDARIO MANDA, EL DSR CONFIRMA. Si hay días marcados en shoot_days para ESTA unidad,
        // ésos son la verdad y el contador avanza AUNQUE NO EXISTA UN DSR. Sin calendario poblado la
        // PRINCIPAL cae al comportamiento anterior (derivar de daily_reports); una unidad ADICIONAL no
        // usa el DSR del principal → su lista vacía es su verdad. (2026-09-06)
        $marcados = self::calendarShootDates($unitId);
        if ($marcados !== null) {
            return self::$datesCache[$k] = $marcados;
        }

        if (! Schema::hasTable('daily_reports')) {
            return self::$datesCache[$k] = [];
        }

        try {
            $q = DailyReport::whereNotNull('report_date');

            $pid = CurrentProduction::id();
            if ($pid && Schema::hasColumn('daily_reports', 'production_id')
                && DailyReport::where('production_id', $pid)->exists()) {
                $q->where('production_id', $pid);
            }

            $fechas = $q->orderBy('report_date')->pluck('report_date')->all();
        } catch (\Throwable $e) {
            return self::$datesCache[$k] = [];
        }

        $out = [];
        foreach ($fechas as $f) {
            $d = self::toDay($f);
            if ($d !== null) {
                $out[$d] = true;
            }
        }
        $out = array_keys($out);
        sort($out);

        // Un DSR anterior al ancla es PREP, no rodaje: se queda fuera de la numeración positiva.
        $ancla = self::anchorDate($unitId);
        if ($ancla !== null) {
            $a = $ancla->toDateString();
            $out = array_values(array_filter($out, function ($d) use ($a) {
                return $d >= $a;
            }));
        }

        return self::$datesCache[$k] = $out;
    }

    /**
     * Días de rodaje MARCADOS en el calendario (shoot_days, is_shoot_day=1) de la producción vigente,
     * desde el ancla en adelante. Devuelve null cuando NO hay calendario poblado, para que shootDates()
     * caiga al comportamiento anterior (derivar de daily_reports) y nada cambie hasta que se use.
     *
     * @return array|null  ['Y-m-d', ...] o null
     */
    private static function calendarShootDates(?int $unitId)
    {
        if (! Schema::hasTable('shoot_days')) {
            return null;
        }
        $hasUnitCol = Schema::hasColumn('shoot_days', 'unit_id');

        try {
            // ¿Acotar a la producción vigente? Sólo si esta unidad tiene días propios en ella.
            $pid = CurrentProduction::id();
            $scopeProd = false;
            if ($pid) {
                $probe = ShootDay::where('is_shoot_day', 1)->where('production_id', $pid);
                if ($hasUnitCol) {
                    $unitId === null ? $probe->whereNull('unit_id') : $probe->where('unit_id', $unitId);
                }
                $scopeProd = $probe->exists();
            }

            $q = ShootDay::query()->where('is_shoot_day', 1)->whereNotNull('shoot_date');
            if ($hasUnitCol) {
                $unitId === null ? $q->whereNull('unit_id') : $q->where('unit_id', $unitId);
            }
            if ($scopeProd) {
                $q->where('production_id', $pid);
            }
            $fechas = $q->orderBy('shoot_date')->pluck('shoot_date')->all();
        } catch (\Throwable $e) {
            return null;
        }

        $out = [];
        foreach ($fechas as $f) {
            $d = self::toDay($f);
            if ($d !== null) {
                $out[$d] = true;
            }
        }
        if (empty($out)) {
            // PRINCIPAL sin días marcados → null (fallback al DSR, como hoy). Unidad ADICIONAL sin días
            // marcados → [] (esa unidad simplemente no tiene días; NO hereda el DSR del principal).
            return $unitId === null ? null : [];
        }
        $out = array_keys($out);
        sort($out);

        // Igual que el fallback: un día marcado antes del ancla es prep, fuera de la numeración positiva.
        $ancla = self::anchorDate($unitId);
        if ($ancla !== null) {
            $a = $ancla->toDateString();
            $out = array_values(array_filter($out, function ($d) use ($a) {
                return $d >= $a;
            }));
        }

        return $out;
    }

    /**
     * Ancla = día 1. `productions.start_date` manda; si no está, el primer día con DSR.
     *
     * @return \Carbon\Carbon|null
     */
    public static function anchorDate(?int $unitId = null)
    {
        $k = self::unitKey($unitId);
        if (array_key_exists($k, self::$anchorCache)) {
            return self::$anchorCache[$k];
        }

        $hasUnitCol = Schema::hasTable('shoot_days') && Schema::hasColumn('shoot_days', 'unit_id');

        // UNIDAD ADICIONAL: su día 1 es su PRIMER día marcado — SIEMPRE arranca en día 1, nazca cuando
        // nazca (semana 6 o la que sea). No hereda start_date ni DSR del principal.
        if ($unitId !== null) {
            $min = null;
            if (Schema::hasTable('shoot_days')) {
                try {
                    $min = ShootDay::where('is_shoot_day', 1)->where('unit_id', $unitId)->min('shoot_date');
                } catch (\Throwable $e) {
                    // sin días marcados → sin ancla
                }
            }

            return self::$anchorCache[$k] = $min ? Carbon::parse($min)->startOfDay() : null;
        }

        // PRINCIPAL: `productions.start_date` manda; si no, el primer día marcado de la principal; si
        // tampoco, el primer día con DSR (comportamiento anterior).
        $prod = CurrentProduction::get();
        if ($prod && ! empty($prod->start_date)) {
            return self::$anchorCache[$k] = Carbon::parse($prod->start_date)->startOfDay();
        }
        if (Schema::hasTable('shoot_days')) {
            try {
                $q = ShootDay::where('is_shoot_day', 1);
                if ($hasUnitCol) {
                    $q->whereNull('unit_id');
                }
                $minCal = $q->min('shoot_date');
                if ($minCal) {
                    return self::$anchorCache[$k] = Carbon::parse($minCal)->startOfDay();
                }
            } catch (\Throwable $e) {
                // sigue al fallback del DSR
            }
        }
        if (! Schema::hasTable('daily_reports')) {
            return self::$anchorCache[$k] = null;
        }
        try {
            $min = DailyReport::whereNotNull('report_date')->min('report_date');
        } catch (\Throwable $e) {
            $min = null;
        }

        return self::$anchorCache[$k] = $min ? Carbon::parse($min)->startOfDay() : null;
    }

    /**
     * Fecha de wrap preestablecida (`productions.end_date`), o null si no se ha fijado.
     *
     * @return \Carbon\Carbon|null
     */
    public static function wrapDate()
    {
        $prod = CurrentProduction::get();

        return ($prod && ! empty($prod->end_date)) ? Carbon::parse($prod->end_date)->startOfDay() : null;
    }

    // ---------------------------------------------------------------------------------------
    // Calendario PLANEADO (PARTE A) — la "M" de "Día N de M" y el wrap estimado
    // ---------------------------------------------------------------------------------------
    //
    // DOS FUENTES QUE NO SE PISAN:
    //   · PLANEADO  → shoot_weeks × shoot_days_per_week da la M y el wrap estimado (esta sección).
    //   · REAL      → los DSR declaran el día que OCURRIÓ (shootDates/shootDaysCount, más abajo).
    // Si divergen (día de lluvia, company move), la divergencia se MUESTRA, no se resuelve sola
    // (ver scheduleSummary + el panel de configuración). Decisión del owner.

    /**
     * DÍAS DE RODAJE POR SEMANA planeados (5 o 6). Default 6 (semana lunes-a-sábado, consistente con
     * la prep). Es lo que separa una semana de 5 de una de 6 al derivar el wrap.
     */
    public static function shootDaysPerWeek(): int
    {
        $prod = CurrentProduction::get();
        $d = $prod ? (int) ($prod->shoot_days_per_week ?? 0) : 0;

        return ($d === 5 || $d === 6) ? $d : self::PREP_WEEK_DAYS;
    }

    /**
     * LA "M": TOTAL de días de rodaje PLANEADOS = semanas × días/semana. null si la producción no
     * configuró su calendario (entonces el encabezado dice sólo "Día N"). Distinto de
     * shootDaysCount(), que cuenta los días REALES con DSR.
     *
     * @return int|null
     */
    public static function plannedShootDays(?int $unitId = null)
    {
        // UNIDAD ADICIONAL: no tiene weeks×days de producción — su M es su PROPIO calendario (los días
        // que marcó). Una 2ª unidad que nace en la semana 6 tiene su propia M, no la de la producción.
        if ($unitId !== null) {
            if (Schema::hasTable('shoot_days') && Schema::hasColumn('shoot_days', 'unit_id')) {
                try {
                    $q = ShootDay::where('is_shoot_day', 1)->where('unit_id', $unitId);
                    $pid = CurrentProduction::id();
                    if ($pid && ShootDay::where('is_shoot_day', 1)->where('unit_id', $unitId)->where('production_id', $pid)->exists()) {
                        $q->where('production_id', $pid);
                    }
                    $n = $q->count();

                    return $n > 0 ? $n : null;
                } catch (\Throwable $e) {
                    return null;
                }
            }

            return null;
        }

        // PRINCIPAL: la M PLANEADA de la producción (semanas × días/semana). Comportamiento de hoy.
        $prod = CurrentProduction::get();
        if (! $prod) {
            return null;
        }
        $weeks   = (int) ($prod->shoot_weeks ?? 0);
        $perWeek = (int) ($prod->shoot_days_per_week ?? 0);
        if ($weeks <= 0 || ($perWeek !== 5 && $perWeek !== 6)) {
            return null;
        }

        return $weeks * $perWeek;
    }

    /**
     * WRAP ESTIMADO derivado: la fecha del día de rodaje número M contando desde el ancla (día 1),
     * saltando los NO laborables de la semana configurada (domingo si 6/sem; sábado+domingo si
     * 5/sem). null si falta ancla o calendario. Es el DEFAULT del wrap; `end_date` lo sobrescribe.
     *
     * @return \Carbon\Carbon|null
     */
    public static function plannedWrapDate()
    {
        $m     = self::plannedShootDays();
        $ancla = self::anchorDate();
        if ($m === null || $ancla === null) {
            return null;
        }

        return self::advanceWorkingDays($ancla->copy(), $m - 1, self::shootDaysPerWeek());
    }

    /**
     * RESUMEN calendario PLANEADO vs REAL para el panel de configuración y el tablero. NO resuelve la
     * divergencia — la EXPONE: días planeados vs días con DSR, y cuántos días de rodaje "deberían"
     * llevar contra los que llevan. `divergence` > 0 = por detrás del plan (lluvia/company move);
     * = 0 al día; null si aún no hay con qué comparar.
     *
     * @return array
     */
    public static function scheduleSummary()
    {
        $prod     = CurrentProduction::get();
        $start    = self::anchorDate();
        $m        = self::plannedShootDays();
        $perWeek  = self::shootDaysPerWeek();
        $realDays = self::shootDaysCount();
        $dates    = self::shootDates();
        $lastReal = ! empty($dates) ? Carbon::parse(end($dates))->startOfDay() : null;

        // "Deberían" = días laborables (según la semana configurada) del ancla a HOY, tope en M.
        // Se compara contra los días REALES con DSR. La diferencia es la señal de atraso.
        $divergence = null;
        if ($start !== null) {
            $hoy = Carbon::now()->startOfDay();
            if ($hoy->gte($start)) {
                $expected = self::countWorkingDays($start, $hoy, $perWeek);
                if ($m !== null) {
                    $expected = min($expected, $m);
                }
                $divergence = $expected - $realDays;
            }
        }

        return [
            'configured'    => $m !== null,
            'start'         => $start,
            'weeks'         => $prod && $prod->shoot_weeks !== null ? (int) $prod->shoot_weeks : null,
            'days_per_week' => $perWeek,
            'planned_total' => $m,                        // la M
            'planned_wrap'  => self::plannedWrapDate(),   // wrap derivado
            'set_wrap'      => self::wrapDate(),          // wrap ajustado (end_date)
            'real_days'     => $realDays,                 // días con DSR
            'last_real'     => $lastReal,
            'today_n'       => self::dayNumber(Carbon::now()),
            'divergence'    => $divergence,
        ];
    }

    // ---------------------------------------------------------------------------------------
    // El contador
    // ---------------------------------------------------------------------------------------

    /**
     * Número de día de una fecha. Positivo = rodaje. Negativo = prep. null = indeterminable
     * (sin ancla) o DOMINGO de prep (el domingo no cuenta y por eso no tiene número).
     *
     * @param  mixed $fecha
     * @return int|null
     */
    public static function dayNumber($fecha, ?int $unitId = null)
    {
        $d = self::toDay($fecha);
        if ($d === null) {
            return null;
        }

        $ancla = self::anchorDate($unitId);
        if ($ancla === null) {
            return null;
        }

        $a = $ancla->toDateString();

        // ---- PREP: hacia atrás desde el día 1, saltando domingos --------------------------
        if ($d < $a) {
            $c = Carbon::parse($d);
            if ($c->dayOfWeek === Carbon::SUNDAY) {
                return null; // el domingo no cuenta
            }

            return -self::workingDaysBetween($c, $ancla);
        }

        // ---- RODAJE: posición dentro de las fechas de ESA UNIDAD ---------------------------
        $dates = self::shootDates($unitId);
        $idx = array_search($d, $dates, true);
        if ($idx !== false) {
            return $idx + 1;
        }

        // Fecha sin DSR (típicamente uno que se está creando): le toca el número siguiente al
        // último día anterior a ella. Crear un DSR fuera de orden RENUMERA a los posteriores,
        // que es justo lo que "secuencial derivado" significa.
        $previos = 0;
        foreach ($dates as $x) {
            if ($x < $d) {
                $previos++;
            }
        }

        return $previos + 1;
    }

    /**
     * Días LUNES-A-SÁBADO en el intervalo [$desde, $hasta) — el domingo no suma.
     *
     * Medio abierto a propósito: el día inmediatamente anterior al ancla debe dar 1 (= día -1).
     *
     * @return int
     */
    public static function workingDaysBetween(Carbon $desde, Carbon $hasta)
    {
        $ini = $desde->copy()->startOfDay();
        $fin = $hasta->copy()->startOfDay();
        if ($ini->gte($fin)) {
            return 0;
        }

        // Guardarraíl: nadie planea prep de 10 años; un rango absurdo sería un dato corrupto,
        // no una producción larga. Sin esto, una fecha tecleada como 1900 congela la petición.
        $span = $ini->diffInDays($fin);
        if ($span > 3650) {
            return 0;
        }

        $n = 0;
        $cur = $ini->copy();
        while ($cur->lt($fin)) {
            if ($cur->dayOfWeek !== Carbon::SUNDAY) {
                $n++;
            }
            $cur->addDay();
        }

        return $n;
    }

    /**
     * Avanza $steps días LABORABLES desde $from (que cuenta como día 1). Con 6/semana salta domingos;
     * con 5/semana salta sábado y domingo. Guardarraíl contra rangos absurdos (fecha corrupta).
     */
    private static function advanceWorkingDays(Carbon $from, int $steps, int $daysPerWeek): Carbon
    {
        $cur = $from->copy()->startOfDay();
        if ($steps <= 0) {
            return $cur;
        }
        $counted = 0;
        $guard   = 0;
        while ($counted < $steps && $guard < 3650) {
            $cur->addDay();
            $guard++;
            $isOff = ($cur->dayOfWeek === Carbon::SUNDAY)
                || ($daysPerWeek <= 5 && $cur->dayOfWeek === Carbon::SATURDAY);
            if (! $isOff) {
                $counted++;
            }
        }

        return $cur;
    }

    /**
     * Cuenta los días LABORABLES en [$desde, $hasta] (AMBOS inclusive), según la semana configurada
     * (6/sem salta domingo; 5/sem salta sábado+domingo). Lo usa scheduleSummary para los "esperados".
     */
    private static function countWorkingDays(Carbon $desde, Carbon $hasta, int $daysPerWeek): int
    {
        $ini = $desde->copy()->startOfDay();
        $fin = $hasta->copy()->startOfDay();
        if ($ini->gt($fin)) {
            return 0;
        }
        if ($ini->diffInDays($fin) > 3650) {
            return 0;
        }
        $n   = 0;
        $cur = $ini->copy();
        while ($cur->lte($fin)) {
            $isOff = ($cur->dayOfWeek === Carbon::SUNDAY)
                || ($daysPerWeek <= 5 && $cur->dayOfWeek === Carbon::SATURDAY);
            if (! $isOff) {
                $n++;
            }
            $cur->addDay();
        }

        return $n;
    }

    /**
     * Número que le corresponde a un DSR que se está guardando. Es lo que el store persiste en
     * `shoot_day` en vez de pedírselo al usuario.
     *
     * @param  mixed $fecha
     * @return int|null
     */
    public static function shootDayFor($fecha, ?int $unitId = null)
    {
        return self::dayNumber($fecha, $unitId);
    }

    // ---------------------------------------------------------------------------------------
    // Representación
    // ---------------------------------------------------------------------------------------

    /**
     * Cómo se DICE un número de día.
     *   12   → "Día 12"
     *   -3   → "Prep -3"        (a una semana o menos: se dice en días)
     *   -62  → "Semana -11"     (a más de una semana: se dice en semanas)
     *   null → "—"
     *
     * @param  int|null $n
     * @return string
     */
    public static function label($n)
    {
        if ($n === null || $n === '') {
            return '—';
        }
        $n = (int) $n;

        if ($n > 0) {
            return 'Día ' . $n;
        }
        if ($n === 0) {
            return '—';
        }

        $abs = -$n;
        if ($abs <= self::PREP_WEEK_DAYS) {
            return 'Prep -' . $abs;
        }

        return 'Semana -' . (int) ceil($abs / self::PREP_WEEK_DAYS);
    }

    /**
     * Etiqueta directa de una fecha. Domingo de prep se nombra como lo que es.
     *
     * @param  mixed $fecha
     * @return string
     */
    public static function labelFor($fecha, ?int $unitId = null)
    {
        $n = self::dayNumber($fecha, $unitId);
        if ($n !== null) {
            return self::label($n);
        }

        $d = self::toDay($fecha);
        if ($d !== null) {
            $c = Carbon::parse($d);
            $ancla = self::anchorDate($unitId);
            if ($ancla !== null && $c->lt($ancla) && $c->dayOfWeek === Carbon::SUNDAY) {
                return 'Domingo';
            }
        }

        return '—';
    }

    /**
     * Etiqueta con TOTAL para el encabezado del back/roster: "Día 12 de 72" cuando es día de rodaje
     * (N>0) y la M planeada está configurada; "Día 12" si no hay total; para prep u otros cae a
     * labelFor(). El total es la M PLANEADA, no los días reales con DSR.
     */
    public static function dayLabelWithTotal($fecha, ?int $unitId = null): string
    {
        $n = self::dayNumber($fecha, $unitId);
        if ($n !== null && $n > 0) {
            $m = self::plannedShootDays($unitId);

            return $m !== null ? ('Día ' . $n . ' de ' . $m) : ('Día ' . $n);
        }

        return self::labelFor($fecha, $unitId);
    }

    /**
     * Etiqueta de un DOCUMENTO que lleva `shoot_day` CONGELADO (sellado). Muestra LO SUYO y, SOLO si el
     * calendario dice otra cosa, anexa la divergencia — NUNCA reescribe el shoot_day sellado. Es el mismo
     * criterio que planeado-vs-real: el documento conserva lo suyo, el calendario dice lo suyo, y la
     * persona ve las dos. En una producción bien armada desde el inicio, no se ve nunca.
     *   coinciden → "Día 12"
     *   difieren  → "Día 12 · el calendario dice 14"
     * Sin shoot_day propio cae a labelForReport() (comportamiento anterior).
     *
     * @param  object $report  algo con ->shoot_day, ->report_date y ->production_id
     * @return string
     */
    public static function documentDayLabel($report): string
    {
        if (! is_object($report)) {
            return '—';
        }
        $frozen = (isset($report->shoot_day) && $report->shoot_day !== null && $report->shoot_day !== '')
            ? (int) $report->shoot_day
            : null;
        if ($frozen === null) {
            return self::labelForReport($report);   // sin sellado propio: comportamiento anterior
        }

        $propio = self::label($frozen);

        // 🔴 Se compara contra el calendario DE LA UNIDAD DEL DOCUMENTO (su propio unit_id), NO contra el
        // de la producción: comparar contra el equivocado inventaría divergencias FALSAS en todos los
        // documentos de la 2ª unidad. NULL = principal. labelForReport ya lee el mismo unit_id del doc.
        $unitId = (isset($report->unit_id) && $report->unit_id !== null && $report->unit_id !== '')
            ? (int) $report->unit_id
            : null;

        $calLabel = self::labelForReport($report);
        $calN     = self::dayNumber(isset($report->report_date) ? $report->report_date : null, $unitId);

        if ($calLabel !== '—' && $calN !== null && $calN > 0 && $calN !== $frozen) {
            return $propio . ' · el calendario dice ' . $calN;
        }

        return $propio;
    }

    /**
     * Etiqueta de un REPORTE, no de una fecha suelta.
     *
     * La diferencia importa: un reporte que pertenece a OTRA producción (o a ninguna) no tiene
     * número de día en ESTE calendario, y ponerle uno derivado de su fecha le presta un día que
     * no es suyo. En esta base pasaba con tres filas de prueba sueltas: salían con "Día 12",
     * duplicando el día de un reporte real. Fuera de la producción vigente se dice "—", que es
     * la verdad.
     *
     * @param  object $reporte  cualquier cosa con ->report_date y ->production_id
     * @return string
     */
    public static function labelForReport($reporte)
    {
        if (! is_object($reporte)) {
            return '—';
        }

        // La unidad del DOCUMENTO (NULL = principal): todo el conteo se hace contra su propio calendario.
        $unitId = (isset($reporte->unit_id) && $reporte->unit_id !== null && $reporte->unit_id !== '')
            ? (int) $reporte->unit_id
            : null;

        $pid = CurrentProduction::id();
        if ($pid !== null && isset($reporte->production_id)) {
            // Sólo se descarta si la producción vigente YA tiene reportes propios: mientras la
            // columna esté vacía en todos, acotar dejaría el listado entero sin número.
            $suyo = (int) $reporte->production_id === (int) $pid;
            if (! $suyo && ! empty(self::shootDates($unitId))) {
                try {
                    if (DailyReport::where('production_id', $pid)->exists()) {
                        return '—';
                    }
                } catch (\Throwable $e) {
                    // Sin base, se cae al comportamiento por fecha.
                }
            }
        }

        return self::labelFor(isset($reporte->report_date) ? $reporte->report_date : null, $unitId);
    }

    /** Etiqueta de HOY. @return string */
    public static function todayLabel()
    {
        return self::labelFor(Carbon::now());
    }

    /** ¿Hoy es día de rodaje (número positivo)? @return bool */
    public static function inShoot()
    {
        $n = self::dayNumber(Carbon::now());

        return $n !== null && $n > 0;
    }

    // ---------------------------------------------------------------------------------------
    // Countdown al wrap
    // ---------------------------------------------------------------------------------------

    /**
     * Cuánto falta para el wrap preestablecido.
     *
     * @return array|null  null si no hay `end_date`. Si la hay:
     *                     ['date' => Carbon, 'days' => int, 'over' => bool, 'label' => string]
     *                     `days` son días LUNES-A-SÁBADO (los mismos que cuenta la prep) y `over`
     *                     marca la producción que ya rebasó su fecha — que es lo NORMAL en el
     *                     3-10 % de los casos, no un error: por eso se dice, no se oculta.
     */
    public static function countdown()
    {
        $wrap = self::wrapDate();
        if ($wrap === null) {
            return null;
        }

        $hoy = Carbon::now()->startOfDay();

        if ($hoy->lt($wrap)) {
            // (hoy, wrap] — hoy no se cuenta como "falta"; el propio wrap sí.
            $n = self::workingDaysBetween($hoy->copy()->addDay(), $wrap->copy()->addDay());

            return [
                'date'  => $wrap,
                'days'  => $n,
                'over'  => false,
                'label' => $n === 1 ? 'Falta 1 día para wrap' : 'Faltan ' . $n . ' días para wrap',
            ];
        }

        if ($hoy->isSameDay($wrap)) {
            return ['date' => $wrap, 'days' => 0, 'over' => false, 'label' => 'Hoy es el wrap'];
        }

        $n = self::workingDaysBetween($wrap->copy()->addDay(), $hoy->copy()->addDay());

        return [
            'date'  => $wrap,
            'days'  => $n,
            'over'  => true,
            'label' => $n === 1 ? 'Wrap rebasado por 1 día' : 'Wrap rebasado por ' . $n . ' días',
        ];
    }

    // ---------------------------------------------------------------------------------------
    // Agregados para el tablero
    // ---------------------------------------------------------------------------------------

    /**
     * DÍAS DE RODAJE = cuántos días distintos se ha rodado. Cuenta fechas, no la columna
     * `shoot_day`: el filtro viejo (`shoot_day > 0`) se comía el día que alguien dejó en 0.
     *
     * @return int
     */
    public static function shootDaysCount(?int $unitId = null)
    {
        return count(self::shootDates($unitId));
    }

    /**
     * DÍAS TRABAJADOS transcurridos: de la prep (o del primer día con actividad) al último día
     * con actividad, contando lunes a sábado. Decisión del owner: los días NO son naturales.
     *
     * @param  \Carbon\Carbon|null $desde  arranque real de la operación (p. ej. el primer scouting)
     * @return int
     */
    public static function workedDaysElapsed($desde = null, ?int $unitId = null)
    {
        $dates = self::shootDates($unitId);
        $ultimo = ! empty($dates) ? Carbon::parse(end($dates)) : null;

        $inicio = $desde ? $desde->copy()->startOfDay() : null;
        $ancla  = self::anchorDate($unitId);
        if ($inicio === null || ($ancla !== null && $ancla->lt($inicio))) {
            $inicio = $ancla;
        }
        if ($inicio === null) {
            return 0;
        }

        $fin = $ultimo && $ultimo->gt($inicio) ? $ultimo : $inicio;

        // +1 día en el extremo porque el intervalo es medio abierto y el último día SÍ se trabajó.
        return self::workingDaysBetween($inicio, $fin->copy()->addDay());
    }

    // ---------------------------------------------------------------------------------------
    // Utilidades
    // ---------------------------------------------------------------------------------------

    /**
     * Normaliza cualquier cosa que parezca fecha a 'Y-m-d', o null.
     *
     * @return string|null
     */
    private static function toDay($fecha)
    {
        if ($fecha === null || $fecha === '') {
            return null;
        }
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('Y-m-d');
        }
        try {
            return Carbon::parse($fecha)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Olvida la caché. La necesitan los seeders y el arnés, que mueven fechas dentro del mismo
     * proceso y volverían a leer un calendario viejo.
     *
     * @return void
     */
    public static function forget()
    {
        self::$datesCache = [];
        self::$anchorCache = [];
        CurrentProduction::forget();
    }
}
