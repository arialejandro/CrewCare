<?php

namespace App\Support;

use App\Models\ShootDay;
use Carbon\Carbon;

/**
 * CALENDARIO DE RODAJE DINÁMICO · generación de días desde los FINES DE SEMANA marcados (2026-09-06).
 *
 * PURO (no toca la BD): recibe los marcadores de fin de semana y devuelve la lista explícita de días.
 * Persistir es de {@see \App\Http\Controllers\ShootCalendarController}; así esta lógica se prueba sola.
 *
 * MECÁNICA (decisión owner): se marca el FIN de cada semana y sus días se DERIVAN HACIA ATRÁS usando
 * `shoot_days_per_week` como default. El domingo no cuenta (consistente con el resto del calendario).
 *
 * REGLA DE LA MADRUGADA (🔴 ACOTADA): SOLO el ÚLTIMO día de una semana dispara la regla. Si ese día es
 * NOCHE o MIXTO, consume un día de descanso extra y el arranque de la semana SIGUIENTE se recorre un día
 * hábil (un viernes nocturno → el siguiente día de rodaje no es el sábado sino el lunes). NUNCA entre
 * semana: un martes nocturno no empuja nada, porque la regla solo mira el último día de la semana.
 */
class ShootCalendarBuilder
{
    /**
     * Genera la lista ORDENADA de días de rodaje a partir de los fines de semana marcados.
     *
     * @param  array $weeks   ordenado, una entrada por semana: [
     *                          'end'       => 'Y-m-d'  (último día de rodaje de la semana; el marcador),
     *                          'days'      => int|null  (cuántos días; null → $default),
     *                          'last_slug' => string|null (luz del último día; null → DÍA — insumo de la madrugada),
     *                        ]
     * @param  int   $default shoot_days_per_week (5 o 6): default para proponer.
     * @return array lista de ['date'=>'Y-m-d','week_no'=>int,'slug'=>string,'is_last'=>bool], ascendente por fecha.
     */
    public static function build(array $weeks, int $default): array
    {
        $default = ($default === 5 || $default === 6) ? $default : 6;

        $out         = [];
        $prevLast    = null;   // Carbon del último día de la semana previa
        $prevCrosses = false;  // ¿ese último día cruza la madrugada?
        $weekNo      = 0;

        foreach ($weeks as $w) {
            if (empty($w['end'])) {
                continue;
            }
            $weekNo++;
            $end      = Carbon::parse($w['end'])->startOfDay();
            $days     = ! empty($w['days']) ? max(1, (int) $w['days']) : $default;
            $lastSlug = self::normSlug($w['last_slug'] ?? null);

            // Arranque MÍNIMO permitido por la madrugada de la semana previa: 1 día hábil después del
            // último día previo; +1 más si ese último día cruzó la madrugada (consume el descanso).
            $earliest = null;
            if ($prevLast !== null) {
                $earliest = self::addWorkingDays($prevLast, $prevCrosses ? 2 : 1);
            }

            // Deriva $days fechas HACIA ATRÁS desde $end saltando domingos, sin cruzar el arranque mínimo.
            $dates = [];
            $c     = $end->copy();
            $guard = 0;
            while (count($dates) < $days && $guard < 60) {
                $guard++;
                if ($c->dayOfWeek !== Carbon::SUNDAY) {
                    if ($earliest !== null && $c->lt($earliest)) {
                        break; // lo que quedaba de la semana lo consumió el descanso de la madrugada
                    }
                    $dates[] = $c->toDateString();
                }
                $c->subDay();
            }
            sort($dates);

            foreach ($dates as $d) {
                $isLast = ($d === $end->toDateString());
                $out[]  = [
                    'date'    => $d,
                    'week_no' => $weekNo,
                    'slug'    => $isLast ? $lastSlug : ShootDay::SLUG_DIA,
                    'is_last' => $isLast,
                ];
            }

            $prevLast    = $end;
            $prevCrosses = in_array($lastSlug, ShootDay::CROSSES_MIDNIGHT, true);
        }

        usort($out, fn ($a, $b) => strcmp($a['date'], $b['date']));

        return $out;
    }

    /** Avanza $n días HÁBILES desde $from (el domingo no cuenta). Guardarraíl contra rangos absurdos. */
    private static function addWorkingDays(Carbon $from, int $n): Carbon
    {
        $c       = $from->copy()->startOfDay();
        $counted = 0;
        $guard   = 0;
        while ($counted < $n && $guard < 60) {
            $guard++;
            $c->addDay();
            if ($c->dayOfWeek !== Carbon::SUNDAY) {
                $counted++;
            }
        }

        return $c;
    }

    /** Normaliza una luz al vocabulario del DSR; cualquier cosa fuera de lista cae a DÍA. */
    private static function normSlug($slug): string
    {
        $s = strtoupper(trim((string) $slug));

        return in_array($s, ShootDay::SLUGS, true) ? $s : ShootDay::SLUG_DIA;
    }
}
