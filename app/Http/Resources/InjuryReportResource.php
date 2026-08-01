<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * InjuryReportResource — representación API de un reporte de lesión (MÓDULO 13).
 *
 * USO FUTURO: aún no está montado en ninguna ruta/API; se deja listo para cuando
 * se exponga una API de reportes.
 *
 * Expone los campos NO sensibles del reporte y ENMASCARA (null) la información
 * médica salvo que el usuario autenticado tenga acceso médico (policy
 * viewMedical): nivel de atención, tratamiento/hospital, causa raíz y EPP. Los
 * testigos se serializan con WitnessResource, que a su vez enmascara teléfono y
 * declaración. Así el mismo recurso sirve para listados generales y para el silo
 * médico H&S sin fugas de datos clínicos.
 */
class InjuryReportResource extends JsonResource
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $canMedical = Gate::allows('viewMedical', $this->resource);

        return [
            'id'               => $this->id,
            'uuid'             => $this->uuid,
            'production_title' => $this->production_title,
            'location'         => $this->location,
            'department'       => $this->department,
            'incident_date'    => $this->incident_date,
            'reported_date'    => $this->reported_date,
            'incident_location'=> $this->incident_location,
            'name'             => $this->name,
            'position'         => $this->position,
            'body_part'        => $this->body_part,
            'injury_type'      => $this->injury_type,
            'seriousness'      => $this->seriousness,
            'frequency'        => $this->frequency,
            'is_recordable'    => $this->is_recordable,
            'risk_level'       => $this->risk_level,
            'days_away_from_work'  => $this->days_away_from_work,
            'days_restricted_work' => $this->days_restricted_work,
            'what_happened'    => $this->what_happened,
            'what_caused'      => $this->what_caused,
            'preventions'      => $this->preventions,
            'further_comments' => $this->further_comments,
            // Aviso a autoridades: registro de cumplimiento, no dato clínico → visible.
            'authority_notifications' => $this->authority_notifications,
            'make_by'          => $this->make_by,
            'make_date'        => $this->make_date,

            // === SILO MÉDICO: solo con acceso viewMedical (autor / H&S / médico) ===
            'treatment_level'     => $canMedical ? $this->treatment_level : null,
            'treatment_type'      => $canMedical ? $this->treatment_type : null,
            'treatment_by'        => $canMedical ? $this->treatment_by : null,
            'hospital'            => $canMedical ? $this->hospital : null,
            'treatment_comments'  => $canMedical ? $this->treatment_comments : null,
            'root_cause_analysis' => $canMedical ? $this->root_cause_analysis : null,
            'ppe_details'         => $canMedical ? $this->ppe_details : null,

            // Testigos: WitnessResource enmascara teléfono/declaración sin acceso médico.
            'witnesses' => WitnessResource::collection($this->whenLoaded('witnesses')),

            // Bandera de conveniencia para el consumidor de la API.
            'medical_access' => $canMedical,
        ];
    }
}
