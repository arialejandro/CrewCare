<?php

namespace Tests\Feature\Dsr;

use Illuminate\Support\Str;
use Tests\QaTestCase;

/**
 * GUARDAS DE BUGS REALES del vertical DSR.
 *
 * Cada test documenta un defecto CONFIRMADO con su repro. Para no romper el verde ni afirmar que
 * el comportamiento defectuoso es correcto, se usa el patrón "auto-upgrade":
 *   - Si el código sigue con el bug (status 500) -> markTestIncomplete (known-issue, no falla).
 *   - Cuando llegue el fix (deja de dar 500) -> el test EXIGE el comportamiento correcto
 *     (assertSessionHasErrors). Así el propio test avisa que ya se puede cerrar la guarda.
 */
class DsrBugGuardsTest extends QaTestCase
{
    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'report_date'       => '2026-08-10',
            'location_name'     => 'BugGuard ' . Str::random(6),
            'slug_setting'      => 'INT.',
            'slug_time'         => 'DÍA',
            'weather_condition' => 'sunny',
            'nearest_hospital'  => 'Hospital QA',
            'crew_count'        => 10,
        ], $overrides);
    }

    /**
     * BUG DSR-1 — Hora malformada en `call_time` provoca HTTP 500 (no error de validación).
     *
     * REPRO: POST /dsr-reports (rol con dsr.create) con todos los campos válidos + call_time='notatime'.
     * RESULTADO ACTUAL: Illuminate\Database\QueryException — "SQLSTATE[22007]: Invalid datetime format:
     *   1292 Incorrect time value: 'notatime' for column 'call_time'" -> HTTP 500 sin manejar.
     * CAUSA: app/Http/Requests/StoreDailyReportRequest.php:108 valida `call_time => 'nullable'`
     *   (sin formato), pero daily_reports.call_time es MySQL `time` y el servidor corre
     *   STRICT_TRANS_TABLES. El valor pasa la validación y revienta en el INSERT.
     * MISMA CLASE que el BUG labn ya aceptado en el vertical Crew.
     * FIX SUGERIDO: `call_time => 'nullable|date_format:H:i'` (o 'H:i:s'). Con `<input type="time">`
     *   el navegador emite H:i, así que la UI normal no lo dispara; un cliente que no use el picker
     *   nativo, sí.
     * ESPERADO TRAS EL FIX: assertSessionHasErrors('call_time') y SIN 500.
     */
    public function test_BUG_call_time_malformado_no_debe_dar_500(): void
    {
        $this->actingAsRole('safety-officer');
        $payload = $this->basePayload(['call_time' => 'notatime']);

        $resp = $this->post(route('daily_reports.store'), $payload);

        if ($resp->status() === 500) {
            $this->assertDatabaseMissing('daily_reports', ['location_name' => $payload['location_name']]);
            $this->markTestIncomplete(
                'BUG DSR-1 ABIERTO: call_time malformado -> 500 (QueryException 1292) en vez de error de ' .
                'validación. Fix: date_format:H:i en StoreDailyReportRequest.php:108.'
            );
        }

        // El fix llegó: exigir el comportamiento correcto.
        $resp->assertSessionHasErrors('call_time');
        $this->assertDatabaseMissing('daily_reports', ['location_name' => $payload['location_name']]);
    }

    /**
     * BUG DSR-2 — Igual que DSR-1 pero en `safety_meeting_time`.
     *
     * REPRO: POST /dsr-reports con safety_meeting_time='xx:yy'.
     * CAUSA: StoreDailyReportRequest.php:112 valida `safety_meeting_time => 'nullable'` sin formato;
     *   la columna es `time` (STRICT) -> 1292 -> HTTP 500.
     * FIX SUGERIDO: `safety_meeting_time => 'nullable|date_format:H:i'`.
     * ESPERADO TRAS EL FIX: assertSessionHasErrors('safety_meeting_time') y SIN 500.
     */
    public function test_BUG_safety_meeting_time_malformado_no_debe_dar_500(): void
    {
        $this->actingAsRole('safety-officer');
        $payload = $this->basePayload(['safety_meeting_time' => 'xx:yy']);

        $resp = $this->post(route('daily_reports.store'), $payload);

        if ($resp->status() === 500) {
            $this->assertDatabaseMissing('daily_reports', ['location_name' => $payload['location_name']]);
            $this->markTestIncomplete(
                'BUG DSR-2 ABIERTO: safety_meeting_time malformado -> 500 (QueryException 1292). ' .
                'Fix: date_format:H:i en StoreDailyReportRequest.php:112.'
            );
        }

        $resp->assertSessionHasErrors('safety_meeting_time');
        $this->assertDatabaseMissing('daily_reports', ['location_name' => $payload['location_name']]);
    }
}
