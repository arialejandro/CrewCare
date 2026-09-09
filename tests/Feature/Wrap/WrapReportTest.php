<?php

namespace Tests\Feature\Wrap;

use App\Models\WrapReport;
use App\Support\CurrentProduction;
use App\Support\SealVerifier;
use App\Support\WrapReportBuilder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\QaTestCase;

/**
 * QA — VERTICAL REPORTE FINAL DE WRAP (2026-07-24).
 *
 * El wrap es el único documento que describe TODA la producción: contraste predicho-vs-real (SB
 * 132). Se PREVISUALIZA vivo y se EMITE una sola vez (congela el payload y lo sella). Dos reglas
 * lo blindan y esta suite las verifica:
 *
 *   LOCK 1 · QUIÉN (WrapReport::issuableBy): emitir queda para el SAFETY MANAGER (rol safety-officer)
 *            o el safety asignado a la producción; el super-admin pasa como operador de plataforma.
 *            Ni line-producer (que sí exporta reportes) lo puede.
 *   LOCK 2 · CUÁNDO (WrapReport::windowBlockedReason): ABSOLUTO — nadie emite antes de PASADA la
 *            fecha de finalización, ni siquiera el super-admin. Protege contra el clic accidental.
 *
 * Además: el builder es de SOLO LECTURA (no muta estado), el documento nace sellado y un tamper lo
 * marca ALTERADO en el verificador público 'wrap'.
 *
 * Rutas gateadas por `permission:dsr.view` (ruta) + issuableBy (controlador). dsr.view lo tienen
 * super-admin, line-producer, coordinator, hod, safety-officer, auditor; NO medic ni crew.
 */
class WrapReportTest extends QaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CurrentProduction::forget();
    }

    /** Fija end_date de la producción vigente y limpia la caché para que el controlador la relea. */
    private function setProductionEnd(?string $endDate): void
    {
        $pid = CurrentProduction::id();
        $this->assertNotNull($pid, 'La fábrica debe tener una producción vigente.');
        DB::table('productions')->where('id', $pid)->update(['end_date' => $endDate]);
        CurrentProduction::forget();
    }

    // =====================================================================================
    //  1. RBAC — QUIÉN VE / QUIÉN EMITE
    // =====================================================================================

    /** @dataProvider emisoresProvider */
    public function test_el_borrador_lo_abre_solo_el_safety_o_el_super_admin(string $role): void
    {
        $this->actingAsRole($role);
        $this->get(route('wrap.preview'))->assertOk();
    }

    public static function emisoresProvider(): array
    {
        return [['safety-officer'], ['super-admin']];
    }

    /** @dataProvider noEmisoresProvider */
    public function test_el_borrador_esta_vedado_a_quien_no_emite(string $role): void
    {
        // line-producer/coordinator/hod/auditor pasan la ruta (dsr.view) pero issuableBy los corta;
        // medic/crew ni siquiera tienen dsr.view. Ambos caminos terminan en 403.
        $this->actingAsRole($role);
        $this->get(route('wrap.preview'))->assertForbidden();
    }

    public static function noEmisoresProvider(): array
    {
        return [['line-producer'], ['coordinator'], ['hod'], ['auditor'], ['medic'], ['crew']];
    }

    public function test_el_indice_lo_ve_cualquier_dsr_view_pero_no_medic_ni_crew(): void
    {
        // El índice NO lleva candado de emisión: solo dsr.view (ver reportes de cierre emitidos).
        $this->actingAsRole('auditor');
        $this->get(route('wrap.index'))->assertOk();

        $this->actingAsRole('safety-officer');
        $this->get(route('wrap.index'))->assertOk();

        $this->actingAsRole('medic');   // sin dsr.view
        $this->get(route('wrap.index'))->assertForbidden();
    }

    public function test_invitado_es_redirigido_a_login(): void
    {
        $this->get(route('wrap.preview'))->assertRedirect(route('login'));
    }

    // =====================================================================================
    //  2. LOCK 1 · QUIÉN EMITE (issuableBy) — server-side, no solo el botón
    // =====================================================================================

    public function test_lock_quien_un_no_emisor_no_puede_emitir_por_post_directo(): void
    {
        // end_date en el pasado (la ventana NO es lo que bloquea aquí; es el candado de QUIÉN).
        $this->setProductionEnd(Carbon::yesterday()->toDateString());

        // line-producer tiene dsr.view y hasta dsr.export, pero NO es emisor de wrap.
        $this->actingAsRole('line-producer');
        $resp = $this->post(route('wrap.store'), []);
        $resp->assertForbidden();
        $this->assertSame(0, WrapReport::count(), 'Un no-emisor no debe poder emitir el wrap.');
    }

    // =====================================================================================
    //  3. LOCK 2 · CUÁNDO (window) — ABSOLUTO, incluso para el super-admin
    // =====================================================================================

    public function test_lock_cuando_no_se_emite_antes_de_la_fecha_de_finalizacion(): void
    {
        // Fecha de finalización en el FUTURO → la ventana está cerrada.
        $this->setProductionEnd(Carbon::now()->addMonth()->toDateString());

        $this->actingAsRole('safety-officer');
        $resp = $this->post(route('wrap.store'), []);
        $resp->assertRedirect(route('wrap.index'));
        $resp->assertSessionHas('error');
        $this->assertSame(0, WrapReport::count(), 'No se emite antes de la fecha de finalización.');
    }

    public function test_lock_cuando_sin_fecha_de_finalizacion_tampoco_se_emite(): void
    {
        $this->setProductionEnd(null);

        $this->actingAsRole('safety-officer');
        $resp = $this->post(route('wrap.store'), []);
        $resp->assertRedirect(route('wrap.index'));
        $resp->assertSessionHas('error');
        $this->assertSame(0, WrapReport::count());
    }

    public function test_lock_cuando_es_absoluto_incluso_para_super_admin(): void
    {
        // El super-admin PUEDE por QUIÉN, pero el CUÁNDO no lo salta nadie.
        $this->setProductionEnd(Carbon::now()->addWeek()->toDateString());

        $this->actingAsRole('super-admin');
        $resp = $this->post(route('wrap.store'), []);
        $resp->assertRedirect(route('wrap.index'));
        $resp->assertSessionHas('error');
        $this->assertSame(0, WrapReport::count(), 'La ventana bloquea también al super-admin.');
    }

    // =====================================================================================
    //  4. EMISIÓN LIMPIA — persiste, sella, verificador 'wrap' íntegro; tamper → ALTERADO
    // =====================================================================================

    public function test_emision_con_ventana_abierta_persiste_y_queda_sellada(): void
    {
        $this->setProductionEnd(Carbon::yesterday()->toDateString());

        $safety = $this->actingAsRole('safety-officer');
        $resp = $this->post(route('wrap.store'), []);

        $wrap = WrapReport::latest('id')->first();
        $this->assertNotNull($wrap, 'La emisión debe crear el documento de cierre.');
        $resp->assertRedirect(route('wrap.show', $wrap->id));
        $resp->assertSessionHas('status');

        $this->assertSame(WrapReport::KIND_FINAL, $wrap->kind);
        $this->assertSame($safety->id, (int) $wrap->issued_by_id);
        $this->assertNotNull($wrap->issued_at);
        $this->assertIsArray($wrap->payload);

        // Sellado COMO SISTEMA (user_id NULL) e íntegro.
        $firma = $wrap->signatures()->latest('id')->first();
        $this->assertNotNull($firma, 'El wrap nace sellado.');
        $this->assertNull($firma->user_id, 'El wrap se sella como sistema (sin firmante).');
        $this->assertTrue($wrap->fresh()->verifyLatestSignature());

        $acuse = SealVerifier::resolve('wrap', $wrap->uuid);
        $this->assertSame('ok', $acuse['verdict']);
        $this->assertSame('Reporte final de wrap', $acuse['type_label']);
        $this->assertSame($wrap->folio(), $acuse['folio']); // WRAP-####
    }

    public function test_tamper_sobre_el_wrap_lo_marca_alterado(): void
    {
        $this->setProductionEnd(Carbon::yesterday()->toDateString());
        $this->actingAsRole('safety-officer');
        $this->post(route('wrap.store'), []);
        $wrap = WrapReport::latest('id')->first();
        $this->assertSame('ok', SealVerifier::resolve('wrap', $wrap->uuid)['verdict']);

        // Cambiar un atributo FIRMADO (period_end) por fuera rompe el hash → ALTERADO.
        DB::table('wrap_reports')->where('id', $wrap->id)->update(['period_end' => '2099-12-31']);

        $this->assertSame('altered', SealVerifier::resolve('wrap', $wrap->uuid)['verdict']);
    }

    // =====================================================================================
    //  5. BUILDER DE SOLO LECTURA — no muta estado; devuelve las 8 secciones
    // =====================================================================================

    public function test_el_builder_no_muta_estado_ni_emite_nada(): void
    {
        $produccion = CurrentProduction::get();

        $antes = [
            'daily_reports'    => DB::table('daily_reports')->count(),
            'scouting_reports' => DB::table('scouting_reports')->count(),
            'cmedic'           => DB::table('cmedic')->count(),
            'wrap_reports'     => DB::table('wrap_reports')->count(),
        ];

        $payload = WrapReportBuilder::build($produccion);

        // Las 8 secciones + meta + avisos, ya calculadas.
        foreach (['meta', 's1_alcance', 's2_anticipado', 's3_ocurrido', 's4_contraste',
                  's5_cronologia', 's6_cumplimiento', 's7_tendencias', 's8_continuidad', 'avisos'] as $k) {
            $this->assertArrayHasKey($k, $payload, "El payload debe traer '{$k}'.");
        }
        $this->assertSame(WrapReportBuilder::VERSION, $payload['meta']['version']);

        // NADA cambió en la base: el builder solo LEE.
        $despues = [
            'daily_reports'    => DB::table('daily_reports')->count(),
            'scouting_reports' => DB::table('scouting_reports')->count(),
            'cmedic'           => DB::table('cmedic')->count(),
            'wrap_reports'     => DB::table('wrap_reports')->count(),
        ];
        $this->assertSame($antes, $despues, 'WrapReportBuilder::build() no debe escribir en la base.');
        $this->assertSame(0, WrapReport::count(), 'Construir el payload NO emite ningún documento.');
    }

    public function test_el_borrador_no_congela_ni_emite_nada(): void
    {
        $this->setProductionEnd(Carbon::now()->addMonth()->toDateString()); // ventana cerrada: da igual, el borrador no emite

        $this->actingAsRole('safety-officer');
        $this->get(route('wrap.preview'))->assertOk();

        $this->assertSame(0, WrapReport::count(), 'Previsualizar no debe persistir ningún wrap.');
    }
}
