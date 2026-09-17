<?php

namespace Tests\Feature\Safety;

use App\Models\InjuryReport;
use Tests\QaTestCase;

/**
 * Injury — la validación migró a InjuryReportRequest (higiene). Mismo comportamiento:
 *  - estricto (progressive OFF) exige el set completo; Fase 1 solo what_happened;
 *  - REGISTRABLE sin aviso a la autoridad = 422 (la regla del MÓDULO 10 sigue viva);
 *  - update sigue gateado por autor/consolidación (403);
 *  - el SELLO no cambia: un injury creado+firmado VERIFICA.
 */
class InjuryReportRequestTest extends QaTestCase
{
    private function strictPayload(array $over = []): array
    {
        return array_merge([
            'production_title' => 'Producción X',
            'incident_date'    => '2026-08-20',
            'reported_date'    => '2026-08-21',
            'name'             => 'Juan Pérez',
            'injury_type'      => ['corte'],
            'what_happened'    => 'Se cortó con una herramienta.',
            'what_caused'      => 'Filo expuesto.',
            'preventions'      => 'Guarda en la herramienta.',
            'likelihood'       => 'C',
            'consequence'      => 3,
            'treatment_level'  => 'first_aid',
            // MÓDULO 11: en estricto, sin GPS hay que justificar la ubicación (required_without:latitude).
            'manual_location_justification' => 'Interior sin señal GPS.',
        ], $over);
    }

    public function test_strict_store_crea_injury_firmado_que_verifica(): void
    {
        config(['features.progressive_capture' => false]); // estricto
        $this->actingAsRole('safety-officer');

        $this->post(route('injury_reports.store'), $this->strictPayload())->assertSessionHasNoErrors();

        $r = InjuryReport::latest('id')->first();
        $this->assertNotNull($r);
        $this->assertTrue((bool) $r->verifyLatestSignature(), 'el injury firmado verifica (sello intacto)');
    }

    public function test_registrable_sin_aviso_a_autoridad_es_422(): void
    {
        config(['features.progressive_capture' => false]);
        $this->actingAsRole('safety-officer');

        // hospitalización → REGISTRABLE → exige al menos un aviso a la autoridad.
        $this->post(route('injury_reports.store'), $this->strictPayload(['treatment_level' => 'hospitalization']))
            ->assertSessionHasErrors('authority_notifications');
    }

    public function test_fase_1_solo_exige_what_happened(): void
    {
        config(['features.progressive_capture' => true]); // captura ágil
        $this->actingAsRole('safety-officer');

        $this->post(route('injury_reports.store'), ['what_happened' => 'Algo pasó.'])
            ->assertSessionHasNoErrors();
    }

    public function test_update_gateado_por_autor_o_consolidacion(): void
    {
        config(['features.progressive_capture' => false]);
        $this->actingAsRole('safety-officer');
        $this->post(route('injury_reports.store'), $this->strictPayload());
        $r = InjuryReport::latest('id')->first();

        // Otro safety (sin consolidación) NO es el autor → 403 antes de validar.
        $this->actingAs($this->makeUser('safety-officer'));
        $this->put(route('injury_reports.update', $r->id), $this->strictPayload())->assertForbidden();
    }
}
