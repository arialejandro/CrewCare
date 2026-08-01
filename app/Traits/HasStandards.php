<?php

namespace App\Traits;

use App\Models\SafetyStandard;
use Illuminate\Support\Facades\Schema;

/**
 * Trait HasStandards — vincula un reporte a MÚLTIPLES normas (N:M polimórfico).
 *
 * Complementa el snapshot único regulation_badge/code que ya existe: permite
 * citar varias normas (CSATF/OSHA/STPS) aplicables a un mismo hallazgo, vía la
 * tabla pivote `standardables`.
 *
 * DEFENSIVO: si el pivote aún no existe, syncStandards() es no-op y standards()
 * solo se consulta cuando la app carga la relación (Schema::hasTable en el
 * controlador antes de load()).
 */
trait HasStandards
{
    /**
     * Normas aplicables (many-to-many polimórfica sobre safety_standards).
     */
    public function standards()
    {
        return $this->morphToMany(
            SafetyStandard::class,
            'standardable',
            'standardables',
            'standardable_id',
            'safety_standard_id'
        );
    }

    /**
     * Sincroniza el arreglo de IDs de norma recibido del request (idempotente).
     * No-op seguro si el owner aún no aplicó el pivote.
     *
     * @param  array|mixed $ids
     * @return void
     */
    public function syncStandards($ids)
    {
        if (!Schema::hasTable('standardables')) {
            return;
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids))));
        $this->standards()->sync($ids);
    }
}
