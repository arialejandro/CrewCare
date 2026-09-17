<?php

namespace App\Http\Requests;

use App\Models\InjuryReport;
use App\Support\Features;
use App\Support\ReportVisibility;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;

/**
 * Injury — validación en Form Request (higiene; el documento de MAYOR exposición legal se valida
 * como el resto de los reportes). Comportamiento IDÉNTICO al que vivía en el controlador:
 *
 *  - ESTRICTO ⇔ update SIEMPRE, o store con la captura progresiva APAGADA (Pilar 1, Fase 2 estricta).
 *  - Aislamiento por autor (auditoría #1) en authorize() → conserva el 403 ANTES de validar (update).
 *  - MÓDULO 10 (aviso a la autoridad si es REGISTRABLE) como after-hook, sólo en flujo estricto.
 *
 * NO toca el sello: las reglas validan los MISMOS campos; el hash lo calcula signDocument() sobre el
 * estado del registro, ajeno a esta capa.
 */
class InjuryReportRequest extends FormRequest
{
    private function isUpdate(): bool
    {
        return $this->isMethod('PUT') || $this->isMethod('PATCH');
    }

    /** update SIEMPRE estricto; store estricto sólo si la captura progresiva está apagada. */
    private function isStrict(): bool
    {
        return $this->isUpdate() || ! Features::enabled('progressive_capture');
    }

    public function authorize(): bool
    {
        if (! $this->isUpdate()) {
            return true; // store: gateado por la ruta/permiso (injury.create)
        }
        // update: sólo el autor o la consolidación de seguridad. Inexistente → deja el 404 al controlador.
        $report = InjuryReport::find($this->route('id'));

        return ! $report || ReportVisibility::canMutate($this->user(), $report);
    }

    protected function failedAuthorization()
    {
        abort(403, 'Solo el autor o la consolidación de seguridad pueden editar este reporte.');
    }

    public function rules(): array
    {
        $strict = $this->isStrict();

        // MÓDULO 11: la justificación manual sólo se vuelve obligatoria (sin GPS) en estricto.
        $manualLocationRule = 'nullable|string|max:1000';
        if ($strict && Schema::hasColumn('injury_reports', 'manual_location_justification')) {
            $manualLocationRule .= '|required_without:latitude';
        }

        $req = $strict ? 'required' : 'nullable';

        return [
            'production_title' => "{$req}|string|max:255",
            'production_dates' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'incident_date' => "{$req}|date|before_or_equal:today",
            'reported_date' => $strict
                ? 'required|date|after_or_equal:incident_date|before_or_equal:today'
                : 'nullable|date|before_or_equal:today',
            'time' => 'nullable|date_format:H:i',
            'incident_location' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'gps_address' => 'nullable|string|max:500',
            'name' => "{$req}|string|max:255",
            'position' => 'nullable|string|max:255',
            'dob' => 'nullable|date|before:today',
            'phone' => 'nullable|string|max:20',
            'other' => 'nullable|string|max:255',
            'body_part' => 'nullable|string|max:255',
            'injury_type' => "{$req}|array",
            'injury_type.*' => 'string|max:255',
            'treatment_type' => 'nullable|string|max:255',
            'treatment_by' => 'nullable|string|max:255',
            'hospital' => 'nullable|string|max:255',
            'treatment_comments' => 'nullable|string|max:1000',
            'what_happened' => 'required|string|max:2000',
            'what_caused' => "{$req}|string|max:2000",
            'preventions' => "{$req}|string|max:1000",
            'further_comments' => 'nullable|string|max:1000',
            'user_id' => 'nullable|exists:users,id',
            'main_image' => 'nullable|mimes:jpeg,png,jpg,gif,heic,heif|heic_ok|max:12288',
            'additional_images.*' => 'nullable|mimes:jpeg,png,jpg,gif,heic,heif|heic_ok|max:12288',
            'category_name' => 'nullable|string',
            'hazard_event_id' => 'nullable|integer',
            'employer_name' => 'nullable|string|max:255',
            'call_time' => 'nullable|date_format:H:i',
            'treatment_level' => 'nullable|in:first_aid,medical_treatment,hospitalization,fatality',
            'days_away_from_work' => 'nullable|integer|min:0|max:9999',
            'days_restricted_work' => 'nullable|integer|min:0|max:9999',
            'likelihood' => $strict ? 'required|in:A,B,C,D,E' : 'nullable|in:A,B,C,D,E',
            'consequence' => $strict ? 'required|integer|between:1,5' : 'nullable|integer|between:1,5',
            'root_cause_analysis' => 'nullable|array',
            'root_cause_analysis.immediate' => 'nullable|string|max:2000',
            'root_cause_analysis.contributing' => 'nullable|string|max:2000',
            'root_cause_analysis.root' => 'nullable|string|max:2000',
            'root_cause_analysis.mechanism' => 'nullable|string|max:2000',
            'root_cause_analysis.categories' => 'nullable|array',
            'root_cause_analysis.categories.*' => 'string|max:255',
            'ppe_details' => 'nullable|array',
            'ppe_details.worn' => 'nullable|in:si,no,na',
            'ppe_details.types' => 'nullable|array',
            'ppe_details.types.*' => 'nullable|string|max:100',
            'ppe_details.condition' => 'nullable|string|max:255',
            'standards' => 'nullable|array',
            'standards.*' => 'integer|exists:safety_standards,id',
            'witnesses' => 'nullable|array',
            'witnesses.*.name' => 'required_with:witnesses|string|max:255',
            'witnesses.*.phone' => 'nullable|string|max:50',
            'witnesses.*.statement' => 'nullable|string|max:2000',
            'authority_notifications' => 'nullable|array',
            'authority_notifications.*.authority' => 'nullable|string|max:100',
            'authority_notifications.*.notified_at' => 'nullable|date',
            'authority_notifications.*.notified_by' => 'nullable|string|max:255',
            'authority_notifications.*.folio_number' => 'nullable|string|max:100',
            'manual_location_justification' => $manualLocationRule,
        ];
    }

    /**
     * MÓDULO 10: si el incidente es REGISTRABLE (nivel médico/hospitalización/fatalidad o días
     * perdidos/restringidos), exige al menos UN aviso a la autoridad con autoridad y folio no vacíos.
     * Sólo en flujo estricto (igual que en el controlador: store !progressive, update siempre).
     * No-op si la columna no existe (defensivo prod).
     */
    public function withValidator(Validator $validator): void
    {
        if (! $this->isStrict() || ! Schema::hasColumn('injury_reports', 'authority_notifications')) {
            return;
        }

        $validator->after(function ($v) {
            $treatmentLevel = $this->input('treatment_level');
            $daysAway       = (int) $this->input('days_away_from_work', 0);
            $daysRestricted = (int) $this->input('days_restricted_work', 0);
            $isRecordable   = in_array($treatmentLevel, ['medical_treatment', 'hospitalization', 'fatality'], true)
                || $daysAway > 0 || $daysRestricted > 0;

            if (! $isRecordable) {
                return;
            }

            $hasValidNotification = false;
            foreach ((array) $this->input('authority_notifications', []) as $note) {
                if (is_array($note)
                    && trim((string) ($note['authority'] ?? '')) !== ''
                    && trim((string) ($note['folio_number'] ?? '')) !== '') {
                    $hasValidNotification = true;
                    break;
                }
            }

            if (! $hasValidNotification) {
                $v->errors()->add(
                    'authority_notifications',
                    'Este incidente es REGISTRABLE: registra al menos una notificación a la autoridad con autoridad y número de folio.'
                );
            }
        });
    }
}
