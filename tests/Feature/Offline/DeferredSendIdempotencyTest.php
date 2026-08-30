<?php

namespace Tests\Feature\Offline;

use App\Models\InjuryReport;
use Illuminate\Support\Facades\DB;
use Tests\QaTestCase;

/**
 * ENVÍO DIFERIDO offline (Camino A) — el borrador se reproduce por la RUTA NORMAL store()
 * (valida + sella una sola vez) y el middleware 'idempotent' (IdempotentReplay) garantiza
 * que un reintento con la misma llave NO duplique. Se ejerce sobre injury_reports.store.
 *
 *  - reenvío con la misma X-Idempotency-Key → duplicado limpio (200), sin segundo documento,
 *    y el primero queda SELLADO (verifyLatestSignature);
 *  - un envío que falla validación (422) LIBERA la llave → un reintento corregido pasa;
 *  - sin cabecera, el flujo interactivo normal no cambia;
 *  - POST /api/sync/up quedó retirado (404).
 */
class DeferredSendIdempotencyTest extends QaTestCase
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
            'manual_location_justification' => 'Interior sin señal GPS.',
        ], $over);
    }

    /** Cabeceras del reenvío diferido: llave de idempotencia + JSON (para que la validación sea 422). */
    private function idemHeaders(string $key): array
    {
        return [
            'X-Idempotency-Key' => $key,
            'Accept'            => 'application/json',
            'X-Requested-With'  => 'XMLHttpRequest',
        ];
    }

    public function test_reenvio_con_misma_llave_no_duplica_y_queda_sellado(): void
    {
        config(['features.progressive_capture' => false]); // estricto
        $this->actingAsRole('safety-officer');
        $before = InjuryReport::count();
        $key = 'injury-report-abc-123';

        $this->post(route('injury_reports.store'), $this->strictPayload(), $this->idemHeaders($key))
            ->assertSessionHasNoErrors();

        $this->assertSame($before + 1, InjuryReport::count());
        $r = InjuryReport::latest('id')->first();
        $this->assertTrue((bool) $r->verifyLatestSignature(), 'el injury enviado por la ruta normal queda SELLADO');
        $this->assertNotNull(
            DB::table('idempotency_keys')->where('idem_key', $key)->value('response_status'),
            'la llave quedó sellada tras el éxito'
        );

        // Reintento con la MISMA llave → duplicado limpio (200), sin segundo documento.
        $this->post(route('injury_reports.store'), $this->strictPayload(), $this->idemHeaders($key))
            ->assertStatus(200);
        $this->assertSame($before + 1, InjuryReport::count(), 'un reintento con la misma llave NO duplica');
    }

    public function test_validacion_fallida_libera_la_llave(): void
    {
        config(['features.progressive_capture' => false]);
        $this->actingAsRole('safety-officer');
        $before = InjuryReport::count();
        $key = 'injury-report-def-456';

        // Estricto sin los campos requeridos → 422, sin crear nada.
        $this->post(route('injury_reports.store'), ['production_title' => 'X'], $this->idemHeaders($key))
            ->assertStatus(422);
        $this->assertSame($before, InjuryReport::count());
        $this->assertNull(
            DB::table('idempotency_keys')->where('idem_key', $key)->value('response_status'),
            'la llave se liberó (no quedó sellada) tras el fallo de validación'
        );

        // Reintento corregido con la MISMA llave → pasa.
        $this->post(route('injury_reports.store'), $this->strictPayload(), $this->idemHeaders($key))
            ->assertSessionHasNoErrors();
        $this->assertSame($before + 1, InjuryReport::count(), 'la llave liberada permite el reintento corregido');
    }

    public function test_sin_cabecera_el_flujo_normal_no_cambia(): void
    {
        config(['features.progressive_capture' => false]);
        $this->actingAsRole('safety-officer');
        $before = InjuryReport::count();

        $this->post(route('injury_reports.store'), $this->strictPayload())
            ->assertSessionHasNoErrors();

        $this->assertSame($before + 1, InjuryReport::count());
    }

    public function test_sync_up_esta_retirado(): void
    {
        $this->actingAsRole('safety-officer');
        // La ruta se eliminó → 404 (nada la llama; el controlador quedó como stub 410 por si se re-cablea).
        $this->post('/api/sync/up', ['reports' => []])->assertStatus(404);
    }
}
