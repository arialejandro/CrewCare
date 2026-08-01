<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * HazardReported — se despacha desde $dispatchesEvents['created'] del
 * hazardnotification (ACTO INSEGURO / peligro). Espejo de AccidentReported y
 * UnsafeConditionReported: completa la terna para que el aviso de seguridad de
 * riesgo Alto/Extremo cubra los TRES tipos de reporte. Transporta el modelo.
 *
 * (2026-07-19) Creado junto con el listener SendHighRiskSafetyAlert.
 */
class HazardReported
{
    use Dispatchable, SerializesModels;

    /** @var mixed el hazardnotification recién creado */
    public $model;

    public function __construct($model)
    {
        $this->model = $model;
    }
}
