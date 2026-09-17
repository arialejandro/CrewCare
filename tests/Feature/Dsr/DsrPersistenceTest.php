<?php

namespace Tests\Feature\Dsr;

use App\Models\DailyReport;
use App\Models\HazardEvent;
use App\Models\ScoutingReport;
use App\Support\ProductionCalendar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\QaTestCase;

/**
 * PERSISTENCIA + SELLADO + LÓGICA DE NEGOCIO del DSR (endpoints REALES).
 *
 * Cubre el flujo del safety: crear el encabezado del día (store), agregar un hallazgo
 * (storeLog), cerrar el día (update). Verifica que persiste con los campos clave, que el
 * documento nace SELLADO (autofirma SHA-256) y que verifyLatestSignature() recomputa VÁLIDO,
 * y las reglas de negocio: hospital designado obligatorio en el alta, herencia del hospital
 * desde el scouting reconocido por nombre, y shoot_day respetado/derivado.
 */
class DsrPersistenceTest extends QaTestCase
{
    /** Encabezado válido MÍNIMO: clima 'sunny' -> NO dispara EPP obligatorio; sin factores de altura. */
    private function dsrPayload(array $overrides = []): array
    {
        return array_merge([
            'report_date'       => '2026-08-10',
            'location_name'     => 'Set QA ' . Str::random(6),
            'slug_setting'      => 'INT.',
            'slug_time'         => 'DÍA',
            'weather_condition' => 'sunny',
            'nearest_hospital'  => 'Hospital QA Central',
            'crew_count'        => 42,
            'executive_summary' => 'Jornada sin incidentes.',
        ], $overrides);
    }

    private function firstHazardEventId(): int
    {
        return (int) HazardEvent::query()->orderBy('id')->value('id');
    }

    // =====================================================================
    //  STORE — alta del encabezado
    // =====================================================================

    public function test_safety_crea_dsr_y_persiste_con_campos_clave(): void
    {
        $user = $this->actingAsRole('safety-officer');
        $payload = $this->dsrPayload(['crew_count' => 57]);

        $resp = $this->post(route('daily_reports.store'), $payload);
        $resp->assertSessionHasNoErrors();
        $resp->assertStatus(302);

        $this->assertDatabaseHas('daily_reports', [
            'location_name' => $payload['location_name'],
            'crew_count'    => 57,
            // AUTOFIRMA: el autor lo fija el servidor con el usuario logueado (no del form).
            'author_name'   => $user->name,
            'created_by_id' => $user->id,
        ]);
    }

    public function test_dsr_nace_sellado_y_verifica_integro(): void
    {
        $this->actingAsRole('line-producer');
        $payload = $this->dsrPayload();

        $this->post(route('daily_reports.store'), $payload)->assertSessionHasNoErrors();

        $report = DailyReport::where('location_name', $payload['location_name'])->latest('id')->first();
        $this->assertNotNull($report, 'El DSR debe existir tras el store.');
        $this->assertTrue($report->signatures()->exists(), 'El DSR debe nacer con firma (autofirma en store).');
        $this->assertNotEmpty($report->uuid, 'El DSR debe recibir uuid al crearse.');

        // Recarga FRESCA desde BD (como el verificador) y recomputa el hash.
        $fresh = DailyReport::where('uuid', $report->uuid)->first();
        $this->assertTrue(
            $fresh->verifyLatestSignature(),
            'FALSO POSITIVO/INTEGRIDAD: un DSR recién creado e intacto debe verificar VÁLIDO.'
        );
    }

    // =====================================================================
    //  BUSINESS RULE — hospital designado obligatorio en el alta
    // =====================================================================

    public function test_alta_sin_hospital_designado_es_rechazada(): void
    {
        $this->actingAsRole('safety-officer');
        // Locación con nombre que NO existe como scouting -> no hay herencia -> la regla required aplica.
        $payload = $this->dsrPayload(['location_name' => 'Locación Inexistente ' . Str::random(8)]);
        unset($payload['nearest_hospital']);

        $this->post(route('daily_reports.store'), $payload)->assertSessionHasErrors('nearest_hospital');
        $this->assertDatabaseMissing('daily_reports', ['location_name' => $payload['location_name']]);
    }

    // =====================================================================
    //  BUSINESS RULE — herencia del hospital desde el scouting por NOMBRE
    // =====================================================================

    public function test_alta_reconoce_scouting_por_nombre_y_hereda_hospital(): void
    {
        // Scouting de origen con hospital y ambulancia (sin ETA para heredar el nombre limpio).
        $locName = 'Bodega Reconocida ' . Str::random(6);
        $scout = ScoutingReport::create([
            'location_name'     => $locName,
            'nearest_hospital'  => 'Hospital Ángeles Sur',
            'ambulance_company' => 'Cruz Roja QA',
            'production_id'     => \App\Support\CurrentProduction::id(),
        ]);

        $this->actingAsRole('safety-officer');
        // El safety escribe la MISMA locación y deja hospital/ambulancia vacíos: el servidor
        // reconoce el scouting (prepareForValidation) y hereda en silencio -> pasa la regla required.
        $payload = $this->dsrPayload(['location_name' => $locName]);
        unset($payload['nearest_hospital']);

        $resp = $this->post(route('daily_reports.store'), $payload);
        $resp->assertSessionHasNoErrors();
        $resp->assertStatus(302);

        $report = DailyReport::where('location_name', $locName)->latest('id')->first();
        $this->assertNotNull($report);
        $this->assertSame($scout->id, (int) $report->scouting_report_id, 'Debe amarrarse el vínculo con el scouting reconocido.');
        $this->assertSame('Hospital Ángeles Sur', $report->nearest_hospital, 'Debe heredar el hospital del scouting.');
        $this->assertSame('Cruz Roja QA', $report->ambulance_company, 'Debe heredar la ambulancia del scouting.');
    }

    public function test_lo_escrito_por_el_safety_manda_sobre_la_herencia(): void
    {
        $locName = 'Locación Con Scouting ' . Str::random(6);
        ScoutingReport::create([
            'location_name'    => $locName,
            'nearest_hospital' => 'Hospital Del Scouting',
            'production_id'    => \App\Support\CurrentProduction::id(),
        ]);

        $this->actingAsRole('safety-officer');
        // El safety SÍ escribe un hospital: no debe pisarse por la herencia (sólo hereda en vacíos).
        $payload = $this->dsrPayload([
            'location_name'    => $locName,
            'nearest_hospital' => 'Hospital Escrito A Mano',
        ]);

        $this->post(route('daily_reports.store'), $payload)->assertSessionHasNoErrors();

        $report = DailyReport::where('location_name', $locName)->latest('id')->first();
        $this->assertSame('Hospital Escrito A Mano', $report->nearest_hospital);
    }

    // =====================================================================
    //  BUSINESS RULE — shoot_day derivado / respetado
    // =====================================================================

    public function test_shoot_day_tecleado_se_respeta(): void
    {
        $this->actingAsRole('safety-officer');
        $payload = $this->dsrPayload(['shoot_day' => 9]);

        $this->post(route('daily_reports.store'), $payload)->assertSessionHasNoErrors();

        $report = DailyReport::where('location_name', $payload['location_name'])->latest('id')->first();
        $this->assertSame(9, (int) $report->shoot_day, 'resolveShootDay debe RESPETAR el número tecleado.');
    }

    public function test_shoot_day_se_escribe_siempre_aunque_no_se_teclee(): void
    {
        $this->actingAsRole('safety-officer');
        // Sin shoot_day: el store lo DERIVA de la fecha (nunca lo deja null; la columna se escribe siempre).
        $payload = $this->dsrPayload();
        unset($payload['shoot_day']);

        $this->post(route('daily_reports.store'), $payload)->assertSessionHasNoErrors();

        $report = DailyReport::where('location_name', $payload['location_name'])->latest('id')->first();
        $this->assertNotNull($report->shoot_day, 'shoot_day nunca debe quedar null (se deriva).');
        $this->assertIsNumeric($report->shoot_day);
    }

    // =====================================================================
    //  BUSINESS RULE — EPP obligatorio por clima extremo (módulo 8)
    // =====================================================================

    public function test_epp_obligatorio_por_clima_extremo_sin_epp_es_rechazado(): void
    {
        $this->actingAsRole('safety-officer');
        // Clima lluvioso dispara la obligatoriedad de EPP; sin required_ppe -> error.
        $payload = $this->dsrPayload(['weather_condition' => 'rainy']);

        $this->post(route('daily_reports.store'), $payload)->assertSessionHasErrors('required_ppe');
        $this->assertDatabaseMissing('daily_reports', ['location_name' => $payload['location_name']]);
    }

    public function test_epp_declarado_satisface_la_regla_de_clima_extremo(): void
    {
        $this->actingAsRole('safety-officer');
        $payload = $this->dsrPayload([
            'weather_condition' => 'rainy',
            'required_ppe'      => ['Impermeable', 'Botas antiderrapantes'],
        ]);

        $this->post(route('daily_reports.store'), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('daily_reports', ['location_name' => $payload['location_name']]);
    }

    // =====================================================================
    //  BUSINESS RULE — candado de cumplimiento a 24 h (sellado)
    // =====================================================================

    public function test_dsr_sellado_a_24h_rechaza_nuevos_hallazgos(): void
    {
        $this->actingAsRole('safety-officer');
        $payload = $this->dsrPayload();
        $this->post(route('daily_reports.store'), $payload)->assertSessionHasNoErrors();
        $report = DailyReport::where('location_name', $payload['location_name'])->latest('id')->first();

        // Envejecer el reporte más allá de la ventana editable de 24 h.
        DB::table('daily_reports')->where('id', $report->id)->update(['created_at' => now()->subHours(25)]);

        $resp = $this->post(route('daily_logs.store', ['id' => $report->id]), [
            'log_time'        => '18:00',
            'description'     => 'Intento tardío de agregar un hallazgo.',
            'hazard_event_id' => $this->firstHazardEventId(),
        ]);
        $resp->assertStatus(302);
        $resp->assertSessionHas('error');

        $this->assertDatabaseMissing('daily_logs', [
            'daily_report_id' => $report->id,
            'description'     => 'Intento tardío de agregar un hallazgo.',
        ]);
    }

    // =====================================================================
    //  STORE LOG — hallazgo del día
    // =====================================================================

    public function test_safety_agrega_hallazgo_al_dsr_y_persiste(): void
    {
        $this->actingAsRole('safety-officer');
        $payload = $this->dsrPayload();
        $this->post(route('daily_reports.store'), $payload)->assertSessionHasNoErrors();
        $report = DailyReport::where('location_name', $payload['location_name'])->latest('id')->first();

        $resp = $this->post(route('daily_logs.store', ['id' => $report->id]), [
            'log_time'        => '14:30',
            'description'     => 'Cable de extensión cruzando el paso peatonal.',
            'action_taken'    => 'Se reubicó y se cubrió con canaleta.',
            'hazard_event_id' => $this->firstHazardEventId(),
        ]);
        $resp->assertSessionHasNoErrors();
        $resp->assertStatus(302);

        $this->assertDatabaseHas('daily_logs', [
            'daily_report_id' => $report->id,
            'description'     => 'Cable de extensión cruzando el paso peatonal.',
        ]);
    }

    public function test_log_exige_evento_de_peligro(): void
    {
        $this->actingAsRole('safety-officer');
        $payload = $this->dsrPayload();
        $this->post(route('daily_reports.store'), $payload)->assertSessionHasNoErrors();
        $report = DailyReport::where('location_name', $payload['location_name'])->latest('id')->first();

        // Sin hazard_event_id (required|integer) -> error de validación, sin persistir el log.
        $this->post(route('daily_logs.store', ['id' => $report->id]), [
            'log_time'    => '15:00',
            'description' => 'Observación sin evento.',
        ])->assertSessionHasErrors('hazard_event_id');

        $this->assertDatabaseMissing('daily_logs', [
            'daily_report_id' => $report->id,
            'description'     => 'Observación sin evento.',
        ]);
    }

    // =====================================================================
    //  UPDATE — cierre de día
    // =====================================================================

    public function test_cierre_de_dia_actualiza_resumen_y_re_sella(): void
    {
        $this->actingAsRole('safety-officer');
        $payload = $this->dsrPayload(['executive_summary' => 'Borrador inicial.']);
        $this->post(route('daily_reports.store'), $payload)->assertSessionHasNoErrors();
        $report = DailyReport::where('location_name', $payload['location_name'])->latest('id')->first();
        $firmasIniciales = $report->signatures()->count();

        $resp = $this->post(route('daily_reports.update', ['id' => $report->id]), [
            'executive_summary' => 'Cierre del día: sin lesiones reportadas, un hallazgo corregido.',
        ]);
        $resp->assertSessionHasNoErrors();
        $resp->assertStatus(302);

        $this->assertDatabaseHas('daily_reports', [
            'id'                => $report->id,
            'executive_summary' => 'Cierre del día: sin lesiones reportadas, un hallazgo corregido.',
        ]);

        // El contenido sellado cambió -> se re-firma (la firma sigue al contenido mientras es editable).
        $report->refresh();
        $this->assertGreaterThan($firmasIniciales, $report->signatures()->count(), 'El cierre debe re-sellar el DSR.');
        $this->assertTrue($report->fresh()->verifyLatestSignature(), 'Tras el cierre el sello debe casar con el contenido.');
    }

    // =====================================================================
    //  SHOW — la vista del reporte responde 200 para dsr.view
    // =====================================================================

    public function test_show_del_dsr_responde_ok(): void
    {
        $this->actingAsRole('safety-officer');
        $payload = $this->dsrPayload();
        $this->post(route('daily_reports.store'), $payload)->assertSessionHasNoErrors();
        $report = DailyReport::where('location_name', $payload['location_name'])->latest('id')->first();

        $this->get(route('daily_reports.show', ['id' => $report->id]))->assertOk();
    }
}
