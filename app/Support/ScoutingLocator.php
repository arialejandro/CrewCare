<?php

namespace App\Support;

use App\Models\ScoutingReport;

/**
 * ScoutingLocator — resuelve el Scouting (locación autoritativa) más apropiado para un hallazgo
 * capturado con GPS, para PERSISTIR el vínculo en el store/update de los reportes gemelos.
 *
 * (2026-07-24) Es un MIRROR server-side del ranking de ScoutingReportController@nearby (que NO se
 * toca: lo sigue usando el JS silencioso de los formularios para SUGERIR el nombre). Misma lógica:
 *   1) caja delimitadora en grados (filtro grueso en MySQL),
 *   2) haversine real (descarta lo que cae en la caja pero fuera del radio),
 *   3) date score: 0 = HOY cae en la ventana prep→wrap (o es el shoot); si no, días a la ventana;
 *      null = scouting sin fechas.
 * Orden: primero por cercanía de FECHA (el scouting cuyo shoot es hoy gana), luego por DISTANCIA.
 *
 * ⚠ Si se edita el algoritmo de nearby(), reflejarlo aquí (y viceversa). Se mantienen separados a
 *   propósito: nearby() devuelve JSON para el cliente; esto devuelve un id para persistir.
 */
class ScoutingLocator
{
    /**
     * Id del Scouting mejor rankeado dentro del radio, o null si no hay match.
     *
     * @param  float|null $lat
     * @param  float|null $lng
     * @param  int        $radius  metros (default 500)
     * @return int|null
     */
    public static function nearestId($lat, $lng, $radius = 500)
    {
        if ($lat === null || $lng === null || !is_numeric($lat) || !is_numeric($lng)) {
            return null;
        }
        $lat = (float) $lat;
        $lng = (float) $lng;
        $radius = (int) $radius;

        try {
            $latDelta = $radius / 111320;
            $lngDelta = $radius / (111320 * max(cos(deg2rad($lat)), 0.01));

            $candidates = ScoutingReport::whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->whereBetween('latitude',  [$lat - $latDelta, $lat + $latDelta])
                ->whereBetween('longitude', [$lng - $lngDelta, $lng + $lngDelta])
                ->orderBy('id', 'desc')
                ->limit(50)
                ->get(['id', 'latitude', 'longitude', 'date_prep', 'date_shoot', 'date_wrap']);

            $today   = now()->startOfDay();
            $best    = null;

            foreach ($candidates as $c) {
                $dist = self::haversineMeters($lat, $lng, (float) $c->latitude, (float) $c->longitude);
                if ($dist > $radius) {
                    continue;
                }

                $dateScore = null;
                $start = $c->date_prep ?: $c->date_shoot;
                $end   = $c->date_wrap ?: $c->date_shoot;
                if ($start && $end) {
                    $startDay = $start->copy()->startOfDay();
                    $endDay   = $end->copy()->endOfDay();
                    if ($today->between($startDay, $endDay)) {
                        $dateScore = 0;
                    } else {
                        $dateScore = min($today->diffInDays($startDay), $today->diffInDays($endDay));
                    }
                }

                $cand = ['id' => $c->id, 'dist' => $dist, 'date' => $dateScore];
                if ($best === null || self::better($cand, $best)) {
                    $best = $cand;
                }
            }

            return $best ? (int) $best['id'] : null;
        } catch (\Throwable $e) {
            return null; // nunca romper el guardado por el vínculo
        }
    }

    /**
     * (2026-07-25) Id del Scouting cuya LOCACIÓN coincide por NOMBRE con el texto dado, o null.
     *
     * Es el hermano por-nombre de nearestId(): los gemelos resuelven el vínculo server-side desde
     * el GPS del hallazgo, pero el DSR es el único reporte de campo SIN GPS propio, así que resuelve
     * la MISMA idea —el servidor reconoce la locación y amarra el scouting— desde el nombre que el
     * safety escribe o elige en el campo único de locación. Mismo contrato: devuelve un id para
     * PERSISTIR y nunca rompe el guardado (todo dentro de try/catch).
     *
     * La comparación es por nombre EXACTO ignorando espacios de sobra; la mayúscula/acento la maneja
     * la colación de la columna (utf8/ci). Acota a la producción vigente si ésta tiene una locación
     * con ese nombre —mismo criterio que el datalist de DailyReportController@create—, y si no, cae a
     * cualquiera. El scouting más reciente gana (id desc).
     *
     * @param  string|null $name          nombre de la locación tal como se tecleó/eligió
     * @param  int|null    $productionId  producción a preferir (null = sin preferencia)
     * @return int|null
     */
    public static function idByLocationName($name, $productionId = null)
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        try {
            $base = ScoutingReport::whereRaw('TRIM(location_name) = ?', [$name]);

            // Producción vigente primero: si tiene una locación con ese nombre, se prefiere; si no,
            // se acepta cualquiera (mismo fallback que el selector del formulario en arranque en frío).
            if ($productionId !== null
                && \Illuminate\Support\Facades\Schema::hasColumn('scouting_reports', 'production_id')
                && (clone $base)->where('production_id', $productionId)->exists()) {
                $base->where('production_id', $productionId);
            }

            $id = $base->orderBy('id', 'desc')->value('id');

            return $id !== null ? (int) $id : null;
        } catch (\Throwable $e) {
            return null; // nunca romper el guardado por el vínculo
        }
    }

    /** ¿$a rankea mejor que $b? (fecha primero — null al final; luego distancia). */
    private static function better(array $a, array $b): bool
    {
        $da = $a['date'] === null ? PHP_INT_MAX : $a['date'];
        $db = $b['date'] === null ? PHP_INT_MAX : $b['date'];
        if ($da !== $db) {
            return $da < $db;
        }
        return $a['dist'] < $b['dist'];
    }

    /** Distancia en metros (Haversine) — idéntica a ScoutingReportController@haversineMeters. */
    private static function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2)
    {
        $r    = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2)
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) * sin($dLng / 2);
        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
