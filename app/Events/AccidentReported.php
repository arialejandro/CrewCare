<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * AccidentReported — se despacha desde $dispatchesEvents['created'] del
 * InjuryReport (lo cablea el orquestador). Transporta el modelo del accidente.
 */
class AccidentReported
{
    use Dispatchable, SerializesModels;

    /** @var mixed el InjuryReport recién creado */
    public $model;

    public function __construct($model)
    {
        $this->model = $model;
    }
}
