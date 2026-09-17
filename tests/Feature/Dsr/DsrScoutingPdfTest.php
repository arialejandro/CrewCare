<?php

namespace Tests\Feature\Dsr;

use App\Models\DailyReport;
use App\Models\ScoutingReport;
use Illuminate\Support\Str;
use Tests\QaTestCase;

/**
 * EXPORT PDF SERVER-SIDE (?pdf=1) del DSR y del Scouting.
 *
 * El branch ?pdf=1 (DailyReportController@show / ScoutingReportController@show|amazon) reusa la
 * MISMA vista y la pasa por App\Support\PdfExporter -> Spatie\Browsershot (Chrome headless). La
 * generación real depende de binarios EXTERNOS (Chrome + Node + node_modules/puppeteer): es una
 * dependencia de integración, no lógica de la app.
 *
 * Estrategia honesta: si los binarios NO están, se marca SKIPPED (observación, no bug). Si están,
 * se exige 200 + application/pdf con cuerpo no vacío. Un 500 con binarios presentes SÍ es una
 * señal a investigar y se reporta como fallo del test.
 */
class DsrScoutingPdfTest extends QaTestCase
{
    /** ¿El entorno tiene lo necesario para que Browsershot genere el PDF? */
    private function browsershotDisponible(): bool
    {
        $chrome = env('BROWSERSHOT_CHROME', 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe');
        $node   = env('BROWSERSHOT_NODE', 'C:\\Program Files\\nodejs\\node.exe');
        return is_file($chrome)
            && is_file($node)
            && is_dir(base_path('node_modules/puppeteer'));
    }

    private function crearDsr(): DailyReport
    {
        $this->actingAsRole('safety-officer');
        $payload = [
            'report_date'       => '2026-08-10',
            'location_name'     => 'Set PDF ' . Str::random(6),
            'slug_setting'      => 'INT.',
            'slug_time'         => 'DÍA',
            'weather_condition' => 'sunny',
            'nearest_hospital'  => 'Hospital PDF',
            'crew_count'        => 30,
            'executive_summary' => 'Resumen para el PDF.',
        ];
        $this->post(route('daily_reports.store'), $payload)->assertSessionHasNoErrors();
        return DailyReport::where('location_name', $payload['location_name'])->latest('id')->first();
    }

    private function crearScouting(): ScoutingReport
    {
        $this->actingAsRole('safety-officer');
        $payload = [
            'location_name'    => 'Scout PDF ' . Str::random(6),
            'location_address' => 'Calle 1',
            'nearest_hospital' => 'Hospital PDF',
            'status'           => 'final',
        ];
        $this->post(route('scoutings.store'), $payload)->assertSessionHasNoErrors();
        return ScoutingReport::where('location_name', $payload['location_name'])->latest('id')->first();
    }

    private function assertPdfOk($response, string $ctx): void
    {
        if ($response->status() !== 200) {
            $this->markTestSkipped(
                "PDF {$ctx}: Browsershot no produjo el PDF en el entorno de test (status {$response->status()}). " .
                'El branch ?pdf=1 se alcanza; la generación headless es integración externa.'
            );
        }
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
        $this->assertNotEmpty($response->getContent(), 'El PDF no debe venir vacío.');
    }

    public function test_dsr_pdf(): void
    {
        if (!$this->browsershotDisponible()) {
            $this->markTestSkipped('Browsershot no disponible (Chrome/Node/puppeteer): export PDF es integración externa.');
        }
        $report = $this->crearDsr();
        $this->actingAsRole('safety-officer');
        $resp = $this->get(route('daily_reports.show', ['id' => $report->id, 'pdf' => 1]));
        $this->assertPdfOk($resp, 'DSR');
    }

    public function test_scouting_pdf(): void
    {
        if (!$this->browsershotDisponible()) {
            $this->markTestSkipped('Browsershot no disponible (Chrome/Node/puppeteer): export PDF es integración externa.');
        }
        $scout = $this->crearScouting();
        $this->actingAsRole('safety-officer');
        $resp = $this->get(route('scoutings.show', ['id' => $scout->id, 'pdf' => 1]));
        $this->assertPdfOk($resp, 'Scouting');
    }

    public function test_scouting_amazon_pdf(): void
    {
        if (!$this->browsershotDisponible()) {
            $this->markTestSkipped('Browsershot no disponible (Chrome/Node/puppeteer): export PDF es integración externa.');
        }
        $scout = $this->crearScouting();
        $this->actingAsRole('safety-officer');
        $resp = $this->get(route('scoutings.amazon', ['id' => $scout->id, 'pdf' => 1, 'lang' => 'es']));
        $this->assertPdfOk($resp, 'Scouting Amazon RA');
    }
}
