<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * UnsafeConditionReported — se despacha desde $dispatchesEvents['created'] del
 * reporte de condición insegura. Transporta el modelo de la condición.
 */
class UnsafeConditionReported
{
    use Dispatchable, SerializesModels;

    /** @var mixed el reporte de condición insegura recién creado */
    public $model;

    public function __construct($model)
    {
        $this->model = $model;
    }
}
