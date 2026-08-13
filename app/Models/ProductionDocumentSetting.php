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

    protected $fillable = ['production_id', 'csf_cut_day'];

    protected $casts = ['csf_cut_day' => 'integer'];

    /** Día de corte de la 32-D para una producción (default 1 si no hay fila). */
    public static function cutDayFor($productionId): int
    {
        $day = static::where('production_id', $productionId)->value('csf_cut_day');
        return $day ? (int) $day : 1;
    }
}
