<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Vínculo de norma PENDIENTE de resolver. Bandeja polimórfica: guarda la cadena
 * cruda de norma ("29 CFR 1926.300(c)") que un ítem del catálogo (Tool /
 * ToolCheckPoint / Permit) cita pero que HOY no existe como fila en
 * `safety_standards`. NO inventa la norma: la parquea con su jurisdicción de origen
 * para que el owner decida darla de alta. Cuando exista la fila, re-sembrar el
 * catálogo la resuelve al pivote real y esta fila deja de crearse.
 *
 * NO es "una segunda tabla de normas en texto libre": no tiene definición, badge ni
 * estado normativo — es solo un apunte de "esto falta unir".
 */
class CatalogPendingStandard extends Model
{
    protected $table = 'catalog_pending_standards';

    protected $guarded = ['id'];

    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }
}
