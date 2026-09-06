<?php

namespace App\Support;

use App\Models\ShootDay;
use Illuminate\Support\Facades\DB;

/**
 * Persiste el plan generado por {@see ShootCalendarBuilder} en `shoot_days`, con la regla de oro:
 * 🔑 LA EXCEPCIÓN A MANO (is_manual=1) GANA SOBRE LA REGLA. Regenerar reemplaza solo lo GENERADO;
 * lo que producción tocó a mano (un festivo, un día extra, una luz distinta) sobrevive intacto.
 */
class ShootCalendarService
{
    /**
     * Aplica el plan (salida de ShootCalendarBuilder::build) a la producción. Reemplaza los días
     * GENERADOS en el rango del plan; conserva los MANUALES.
     *
     * @param  int   $productionId
     * @param  array $plan  [['date','week_no','slug','is_last'], ...]
     * @param  int|null $userId
     * @return void
     */
    public static function applyPlan(int $productionId, array $plan, $userId = null): void
    {
        if (empty($plan)) {
            return;
        }
        $dates = array_column($plan, 'date');
        $min   = min($dates);
        $max   = max($dates);

        DB::transaction(function () use ($productionId, $plan, $userId, $min, $max) {
            // 1) Borra SOLO los días generados (no manuales) del rango: se van a regenerar. Los MANUALES
            //    quedan — son las excepciones que ganan sobre la regla.
            ShootDay::where('production_id', $productionId)
                ->where('is_manual', 0)
                ->whereBetween('shoot_date', [$min, $max])
                ->delete();

            // 2) Escribe el plan. Si ya hay una fila MANUAL en esa fecha, NO la toca (la excepción manda).
            foreach ($plan as $d) {
                $manual = ShootDay::where('production_id', $productionId)
                    ->whereDate('shoot_date', $d['date'])
                    ->where('is_manual', 1)
                    ->exists();
                if ($manual) {
                    continue;
                }
                ShootDay::updateOrCreate(
                    ['production_id' => $productionId, 'shoot_date' => $d['date']],
                    [
                        'is_shoot_day' => true,
                        'slug_time'    => $d['slug'] === ShootDay::SLUG_DIA ? null : $d['slug'],
                        'week_no'      => $d['week_no'],
                        'is_manual'    => false,
                        'created_by_id' => $userId,
                    ]
                );
            }
        });
    }

    /**
     * EXCEPCIÓN A MANO: fija si una fecha es día de rodaje o descanso (festivo/día extra). Marca is_manual
     * para que la regeneración la respete. La regla propone, la persona manda.
     */
    public static function markException(int $productionId, string $date, bool $isShoot, $userId = null, ?string $note = null): ShootDay
    {
        return ShootDay::updateOrCreate(
            ['production_id' => $productionId, 'shoot_date' => $date],
            ['is_shoot_day' => $isShoot, 'is_manual' => true, 'note' => $note, 'created_by_id' => $userId]
        );
    }

    /**
     * EXCEPCIÓN A MANO: fija la LUZ de un día (DÍA/NOCHE/AMANECER/ATARDECER/MIXTO) en el calendario, sin
     * leer el DSR. Marca is_manual. NOCHE/MIXTO en el último día de una semana dispara la madrugada al
     * regenerar.
     */
    public static function setLight(int $productionId, string $date, string $slug, $userId = null): ShootDay
    {
        $slug = strtoupper(trim($slug));
        if (! in_array($slug, ShootDay::SLUGS, true)) {
            $slug = ShootDay::SLUG_DIA;
        }

        return ShootDay::updateOrCreate(
            ['production_id' => $productionId, 'shoot_date' => $date],
            ['slug_time' => $slug === ShootDay::SLUG_DIA ? null : $slug, 'is_manual' => true, 'created_by_id' => $userId]
        );
    }
}
