<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Escalares de documentos por producción (PASO 2). Hoy: la FECHA DE CORTE de la 32-D
 * (`csf_cut_day`, día 1..28). Config del kick-off — el módulo NO decide cuál.
 */
class ProductionDocumentSetting extends Model
{
    protected $table = 'production_document_settings';

    protected $fillable = ['production_id', 'csf_cut_day', 'equipment_threshold', 'period_label_template'];

    protected $casts = [
        'csf_cut_day'         => 'integer',
        'equipment_threshold' => 'decimal:2',
    ];

    /** Plantilla por defecto de la nomenclatura de la semana (final de semana → SEM060926). */
    const DEFAULT_PERIOD_TEMPLATE = 'SEM{DD}{MM}{YY}';

    /** Plantilla de la etiqueta del periodo para una producción (default constante si no hay fila/valor). */
    public static function periodTemplateFor($productionId): string
    {
        $t = static::where('production_id', $productionId)->value('period_label_template');
        return trim((string) $t) !== '' ? (string) $t : self::DEFAULT_PERIOD_TEMPLATE;
    }

    /** Día de corte de la 32-D para una producción (default 1 si no hay fila). */
    public static function cutDayFor($productionId): int
    {
        $day = static::where('production_id', $productionId)->value('csf_cut_day');
        return $day ? (int) $day : 1;
    }

    /** Umbral del equipo declarable para una producción (default 6000 si no hay fila). */
    public static function equipmentThresholdFor($productionId): float
    {
        $t = static::where('production_id', $productionId)->value('equipment_threshold');
        return $t !== null ? (float) $t : 6000.0;
    }
}
