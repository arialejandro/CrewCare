<?php

namespace App\Support;

use App\Models\CallDay;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * OutWindow — ¿a qué DÍA DE RODAJE pertenece una salida? (§2)
 *
 * REGLA (owner): se ancla a la HORA DEL LLAMADO GENERAL del back de la unidad y acepta hasta 20 HORAS
 * hacia delante como máximo. Un out de las 02:30 pertenece al rodaje que TERMINÓ (su general fue ayer),
 * no al que empieza. Nada de corte fijo de madrugada: SIEMPRE relativo al llamado. Cada unidad tiene su
 * propio general_call, así que su ventana es la suya (unit_id null = principal).
 *
 * FUERA de la ventana NO se asigna a ciegas: se devuelve `resolved=false` con el motivo, para que la
 * pantalla o el bot pregunten/rechacen. "Un out mal asignado es peor que un out no registrado."
 */
class OutWindow
{
    /** Máximo de horas después del llamado general que un out sigue perteneciendo a ese día. */
    const MAX_FORWARD_HOURS = 20;

    /**
     * general_call ('HH:MM:SS') del (producción, unidad, fecha), o null si no hay llamado ese día.
     */
    public static function generalCall(int $productionId, ?int $unitId, string $date): ?string
    {
        if (! Schema::hasTable('call_days')) {
            return null;
        }
        $q = CallDay::where('production_id', $productionId)->whereDate('call_date', $date);
        if (Schema::hasColumn('call_days', 'unit_id')) {
            $unitId === null ? $q->whereNull('unit_id') : $q->where('unit_id', $unitId);
        }
        $row = $q->first();

        return ($row && ! empty($row->general_call)) ? (string) $row->general_call : null;
    }

    /**
     * ¿A qué día de rodaje pertenece un out con timestamp real $outAt? (para el bot / timestamps crudos)
     * El ancla es el general_call de la unidad; gana el ANCLA MÁS RECIENTE ≤ out dentro de 20 h.
     *
     * @return array{shoot_date: ?string, resolved: bool, reason: ?string, anchor: ?string}
     */
    public static function resolveShootDate(Carbon $outAt, ?int $unitId, int $productionId): array
    {
        $best = null;   // ['date'=>string, 'anchor'=>Carbon]

        // Una ventana de 20 h no alcanza más atrás que el día anterior: basta mirar el día del out y el previo.
        for ($back = 0; $back <= 1; $back++) {
            $date = $outAt->copy()->subDays($back)->toDateString();
            $gc = self::generalCall($productionId, $unitId, $date);
            if ($gc === null) {
                continue;
            }
            $anchor = Carbon::parse($date . ' ' . substr($gc, 0, 8));
            if ($anchor->lte($outAt)) {
                $diffHours = $anchor->diffInMinutes($outAt) / 60;
                if ($diffHours <= self::MAX_FORWARD_HOURS) {
                    if ($best === null || $anchor->gt($best['anchor'])) {
                        $best = ['date' => $date, 'anchor' => $anchor];
                    }
                }
            }
        }

        if ($best !== null) {
            return [
                'shoot_date' => $best['date'],
                'resolved'   => true,
                'reason'     => null,
                'anchor'     => $best['anchor']->toDateTimeString(),
            ];
        }

        // ¿Había llamado (pero fuera de 20 h) o no había llamado en absoluto? El motivo cambia el mensaje.
        $hadAny = self::generalCall($productionId, $unitId, $outAt->toDateString()) !== null
               || self::generalCall($productionId, $unitId, $outAt->copy()->subDay()->toDateString()) !== null;

        return [
            'shoot_date' => null,
            'resolved'   => false,
            'reason'     => $hadAny ? 'outside_window' : 'no_general_call',
            'anchor'     => null,
        ];
    }

    /**
     * App: dado el DÍA DE RODAJE (de la pantalla) y una hora humana, calcula el datetime REAL del out.
     * Si la hora es ANTERIOR al general_call, es de madrugada → cae al día natural siguiente. Valida 20 h.
     *
     * @return array{out_at: ?Carbon, resolved: bool, reason: ?string}
     */
    public static function outAtForShootDay(string $shootDate, string $timeHHMM, ?int $unitId, int $productionId): array
    {
        $t = OutMessageParser::normalizeTime($timeHHMM);
        if ($t === null) {
            return ['out_at' => null, 'resolved' => false, 'reason' => 'bad_time'];
        }

        $sameDay = Carbon::parse($shootDate . ' ' . $t . ':00');
        $gc = self::generalCall($productionId, $unitId, $shootDate);

        if ($gc === null) {
            // Sin llamado ese día no podemos decidir "madrugada": se toma el mismo día natural y se avisa.
            return ['out_at' => $sameDay, 'resolved' => true, 'reason' => 'no_general_call'];
        }

        $anchor = Carbon::parse($shootDate . ' ' . substr($gc, 0, 8));
        $out = $sameDay->lt($anchor) ? $sameDay->copy()->addDay() : $sameDay;

        $diffHours = $anchor->diffInMinutes($out) / 60;
        if ($diffHours > self::MAX_FORWARD_HOURS) {
            return ['out_at' => $out, 'resolved' => false, 'reason' => 'outside_window'];
        }

        return ['out_at' => $out, 'resolved' => true, 'reason' => null];
    }
}
