<?php

namespace App\Support;

use App\Models\DailyReport;
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

    /** Caché por petición. */
    private static $dates = null;
    private static $anchor = false;

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
    public static function shootDates()
    {
        if (self::$dates !== null) {
            return self::$dates;
        }

        if (! Schema::hasTable('daily_reports')) {
            return self::$dates = [];
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
            return self::$dates = [];
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
        $ancla = self::anchorDate();
        if ($ancla !== null) {
            $a = $ancla->toDateString();
            $out = array_values(array_filter($out, function ($d) use ($a) {
                return $d >= $a;
            }));
        }

        return self::$dates = $out;
    }

    /**
     * Ancla = día 1. `productions.start_date` manda; si no está, el primer día con DSR.
     *
     * @return \Carbon\Carbon|null
     */
    public static function anchorDate()
    {
        if (self::$anchor !== false) {
            return self::$anchor;
        }

        $prod = CurrentProduction::get();
        if ($prod && ! empty($prod->start_date)) {
            return self::$anchor = Carbon::parse($prod->start_date)->startOfDay();
        }

        if (! Schema::hasTable('daily_reports')) {
            return self::$anchor = null;
        }

        try {
            $min = DailyReport::whereNotNull('report_date')->min('report_date');
        } catch (\Throwable $e) {
            $min = null;
        }

        return self::$anchor = $min ? Carbon::parse($min)->startOfDay() : null;
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
    // El contador
    // ---------------------------------------------------------------------------------------

    /**
     * Número de día de una fecha. Positivo = rodaje. Negativo = prep. null = indeterminable
     * (sin ancla) o DOMINGO de prep (el domingo no cuenta y por eso no tiene número).
     *
     * @param  mixed $fecha
     * @return int|null
     */
    public static function dayNumber($fecha)
    {
        $d = self::toDay($fecha);
        if ($d === null) {
            return null;
        }

        $ancla = self::anchorDate();
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

        // ---- RODAJE: posición dentro de las fechas con DSR ---------------------------------
        $dates = self::shootDates();
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
     * Número que le corresponde a un DSR que se está guardando. Es lo que el store persiste en
     * `shoot_day` en vez de pedírselo al usuario.
     *
     * @param  mixed $fecha
     * @return int|null
     */
    public static function shootDayFor($fecha)
    {
        return self::dayNumber($fecha);
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
    public static function labelFor($fecha)
    {
        $n = self::dayNumber($fecha);
        if ($n !== null) {
            return self::label($n);
        }

        $d = self::toDay($fecha);
        if ($d !== null) {
            $c = Carbon::parse($d);
            $ancla = self::anchorDate();
            if ($ancla !== null && $c->lt($ancla) && $c->dayOfWeek === Carbon::SUNDAY) {
                return 'Domingo';
            }
        }

        return '—';
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

        $pid = CurrentProduction::id();
        if ($pid !== null && isset($reporte->production_id)) {
            // Sólo se descarta si la producción vigente YA tiene reportes propios: mientras la
            // columna esté vacía en todos, acotar dejaría el listado entero sin número.
            $suyo = (int) $reporte->production_id === (int) $pid;
            if (! $suyo && ! empty(self::shootDates())) {
                try {
                    if (DailyReport::where('production_id', $pid)->exists()) {
                        return '—';
                    }
                } catch (\Throwable $e) {
                    // Sin base, se cae al comportamiento por fecha.
                }
            }
        }

        return self::labelFor(isset($reporte->report_date) ? $reporte->report_date : null);
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
    public static function shootDaysCount()
    {
        return count(self::shootDates());
    }

    /**
     * DÍAS TRABAJADOS transcurridos: de la prep (o del primer día con actividad) al último día
     * con actividad, contando lunes a sábado. Decisión del owner: los días NO son naturales.
     *
     * @param  \Carbon\Carbon|null $desde  arranque real de la operación (p. ej. el primer scouting)
     * @return int
     */
    public static function workedDaysElapsed($desde = null)
    {
        $dates = self::shootDates();
        $ultimo = ! empty($dates) ? Carbon::parse(end($dates)) : null;

        $inicio = $desde ? $desde->copy()->startOfDay() : null;
        $ancla  = self::anchorDate();
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
        self::$dates = null;
        self::$anchor = false;
        CurrentProduction::forget();
    }
}
