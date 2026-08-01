<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * MedicalConsultRecorded — se despacha desde $dispatchesEvents['created'] de la
 * consulta médica. Transporta el modelo de la consulta. El listener filtra el
 * ruido: solo las consultas LIGADAS a un accidente se inyectan al DSR.
 */
class MedicalConsultRecorded
{
    use Dispatchable, SerializesModels;

    /** @var mixed la consulta médica recién creada */
    public $model;

    public function __construct($model)
    {
        $this->model = $model;
    }
}
