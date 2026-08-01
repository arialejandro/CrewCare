<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * WitnessResource — representación API de un testigo de lesión (MÓDULO 13).
 *
 * USO FUTURO: aún no está montado en ninguna ruta/API; se deja listo para cuando
 * se exponga una API de reportes.
 *
 * El NOMBRE del testigo es visible siempre. El TELÉFONO y la DECLARACIÓN son
 * datos sensibles (silo médico/H&S): se enmascaran (null) salvo que el usuario
 * autenticado tenga acceso médico al reporte PADRE (policy viewMedical). Como el
 * testigo no tiene policy propia, la autorización se resuelve contra su
 * InjuryReport padre.
 */
class WitnessResource extends JsonResource
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        // El padre puede venir ya cargado (whenLoaded) o resolverse por la relación.
        $parent = $this->injuryReport;
        $canMedical = $parent ? Gate::allows('viewMedical', $parent) : false;

        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'phone'     => $canMedical ? $this->phone : null,
            'statement' => $canMedical ? $this->statement : null,
        ];
    }
}
