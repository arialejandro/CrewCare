<?php

namespace Tests\Feature\Permit;

use App\Models\IssuedPermit;
use App\Support\SealVerifier;

/**
 * EMISIÓN DE PERMISOS (ciclo real, vía controlador): emitir con compuerta + doble firma
 * congelada + autorización externa DECLARADA → cerrar / suspender.
 *
 * Doctrina: "no hay permiso a medias" — un punto en 'no_cumple' NO emite. La autorización
 * externa obligatoria se DECLARA (folio/autoridad/vigencia/declarante); sin ella no se emite.
 * Cerrar/suspender es cambio de ESTADO, nunca de integridad del sello.
 */
class PermitIssuanceTest extends PermitVerticalTestCase
{
    // ---------------------------- EMISIÓN + SELLADO ----------------------------

    public function test_emision_limpia_persiste_y_queda_sellada(): void
    {
        $permit = $this->makePermitCatalog();
        $this->actingAsRole('safety-officer');

        $resp = $this->post(route('permits.store', $permit->id), $this->permitStorePayload($permit));
        $resp->assertStatus(302);
        $resp->assertSessionHas('success');

        $issued = IssuedPermit::latest('id')->first();
        $this->assertNotNull($issued, 'El permiso emitido debe persistir.');
        $this->assertSame($permit->code, $issued->permit_code);
        $this->assertSame('Ejecutante Designado', $issued->acceptor_name);
        // Doble firma congelada: el emisor (safety) queda dentro del documento.
        $this->assertNotNull($issued->issuer_user_id);
        $this->assertNotNull($issued->accepted_at);

        // SELLADO e íntegro.
        $this->assertTrue($issued->signatures()->exists(), 'El permiso debe quedar sellado al emitir.');
        $this->assertTrue($issued->fresh()->verifyLatestSignature(), 'El sello debe verificar íntegro.');
        $this->assertSame('ok', SealVerifier::resolve('perm', $issued->uuid)['verdict']);
    }

    /** Redirige a la vista del documento (por uuid), no a un id secuencial. */
    public function test_emision_redirige_al_documento_por_uuid(): void
    {
        $permit = $this->makePermitCatalog();
        $this->actingAsRole('safety-officer');

        $resp = $this->post(route('permits.store', $permit->id), $this->permitStorePayload($permit));
        $issued = IssuedPermit::latest('id')->first();
        $resp->assertRedirect(route('permits.show', $issued->uuid));
    }

    // ---------------------------- COMPUERTA: no hay permiso a medias ----------------------------

    public function test_un_punto_en_no_cumple_no_emite(): void
    {
        $permit = $this->makePermitCatalog(['name' => 'Permiso con compuerta'], 2);
        $codes  = $this->permitPointCodes($permit);
        $this->actingAsRole('safety-officer');

        $payload = $this->permitStorePayload($permit, [
            'answers' => [$codes[0] => 'cumple', $codes[1] => 'no_cumple'],
        ]);
        $resp = $this->post(route('permits.store', $permit->id), $payload);
        $resp->assertStatus(302);
        $resp->assertSessionHas('error');

        $this->assertSame(0, IssuedPermit::count(), 'Un punto en no_cumple NO debe emitir permiso.');
    }

    public function test_punto_sin_responder_no_emite(): void
    {
        $permit = $this->makePermitCatalog([], 2);
        $codes  = $this->permitPointCodes($permit);
        $this->actingAsRole('safety-officer');

        // Sólo responde el primero.
        $payload = $this->permitStorePayload($permit, ['answers' => [$codes[0] => 'cumple']]);
        $this->post(route('permits.store', $permit->id), $payload)->assertSessionHas('error');

        $this->assertSame(0, IssuedPermit::count());
    }

    // ---------------------------- AUTORIZACIÓN EXTERNA DECLARADA ----------------------------

    public function test_autorizacion_externa_obligatoria_falta_no_emite(): void
    {
        $permit = $this->makePermitCatalog(['ext_auth_mandatory' => 1]);
        $this->actingAsRole('safety-officer');

        // Sin ningún campo ext_auth_*.
        $resp = $this->post(route('permits.store', $permit->id), $this->permitStorePayload($permit));
        $resp->assertStatus(302);
        $resp->assertSessionHas('error');
        $this->assertSame(0, IssuedPermit::count(), 'Sin autorización externa declarada NO se emite.');
    }

    /** Espacios en blanco NO cuentan como declaración (normalización trim → null). */
    public function test_autorizacion_externa_en_blanco_no_cuenta(): void
    {
        $permit = $this->makePermitCatalog(['ext_auth_mandatory' => 1]);
        $this->actingAsRole('safety-officer');

        $resp = $this->post(route('permits.store', $permit->id), $this->permitStorePayload($permit, [
            'ext_auth_authority'   => '   ',
            'ext_auth_folio'       => '   ',
            'ext_auth_valid_until' => now()->addDays(10)->toDateString(),
            'ext_auth_declared_by' => '   ',
        ]));
        $resp->assertSessionHas('error');
        $this->assertSame(0, IssuedPermit::count());
    }

    public function test_autorizacion_externa_completa_si_emite(): void
    {
        $permit = $this->makePermitCatalog(['ext_auth_mandatory' => 1]);
        $this->actingAsRole('safety-officer');

        $resp = $this->post(route('permits.store', $permit->id), $this->permitStorePayload($permit, [
            'ext_auth_authority'   => 'STPS',
            'ext_auth_folio'       => 'FOLIO-2026-0001',
            'ext_auth_valid_until' => now()->addDays(30)->toDateString(),
            'ext_auth_declared_by' => 'Responsable Legal',
        ]));
        $resp->assertStatus(302);
        $resp->assertSessionHas('success');

        $issued = IssuedPermit::latest('id')->first();
        $this->assertNotNull($issued);
        $this->assertTrue((bool) $issued->ext_auth_mandatory);
        $this->assertSame('STPS', $issued->ext_auth_authority);
        $this->assertTrue($issued->fresh()->verifyLatestSignature());
    }

    // ---------------------------- CIERRE ----------------------------

    public function test_cerrar_cambia_estado_pero_no_altera_el_sello(): void
    {
        $permit = $this->makePermitCatalog();
        $this->actingAsRole('safety-officer');
        $this->post(route('permits.store', $permit->id), $this->permitStorePayload($permit));
        $issued = IssuedPermit::latest('id')->first();

        // Íntegro y vigente antes de cerrar.
        $acuse = SealVerifier::resolve('perm', $issued->uuid);
        $this->assertSame('ok', $acuse['verdict']);
        $this->assertFalse($acuse['retired']);

        $resp = $this->post(route('permits.close', $issued->uuid), ['close_notes' => 'Fin de jornada']);
        $resp->assertStatus(302);
        $resp->assertSessionHas('success');

        $issued->refresh();
        $this->assertTrue($issued->isClosed());
        $this->assertNotNull($issued->closed_by_id);

        // El sello SIGUE íntegro: cerrar no altera. La vigencia sí cambia a 'Cerrado'.
        $acuse = SealVerifier::resolve('perm', $issued->uuid);
        $this->assertSame('ok', $acuse['verdict'], 'Cerrar NO debe leerse como ALTERADO.');
        $this->assertTrue($acuse['retired']);
        $this->assertSame('Cerrado', $acuse['retired_label']);
    }

    public function test_no_se_puede_cerrar_dos_veces(): void
    {
        $permit = $this->makePermitCatalog();
        $this->actingAsRole('safety-officer');
        $this->post(route('permits.store', $permit->id), $this->permitStorePayload($permit));
        $issued = IssuedPermit::latest('id')->first();

        $this->post(route('permits.close', $issued->uuid), [])->assertSessionHas('success');
        $this->post(route('permits.close', $issued->uuid), [])->assertSessionHas('error');
    }

    // ---------------------------- TRABAJO EN CALIENTE (fire watch) ----------------------------

    public function test_trabajo_en_caliente_exige_vigilancia_para_cerrar(): void
    {
        // El nombre dispara deriveFireWatch (contiene 'caliente'/'soldad').
        $permit = $this->makePermitCatalog([
            'name'   => 'Trabajo en caliente',
            'family' => 'Soldadura y corte',
        ]);
        $this->actingAsRole('safety-officer');
        $this->post(route('permits.store', $permit->id), $this->permitStorePayload($permit));
        $issued = IssuedPermit::latest('id')->first();
        $this->assertTrue($issued->requires_fire_watch, 'Debe derivar trabajo en caliente por el nombre/familia.');

        // Cerrar SIN declarar la vigilancia posterior → rebota.
        $resp = $this->post(route('permits.close', $issued->uuid), []);
        $resp->assertSessionHas('error');
        $this->assertFalse($issued->fresh()->isClosed());

        // Con la vigilancia declarada → cierra.
        $this->post(route('permits.close', $issued->uuid), ['fire_watch_confirmed' => 1])
            ->assertSessionHas('success');
        $this->assertTrue($issued->fresh()->isClosed());
    }

    // ---------------------------- SUSPENSIÓN ----------------------------

    public function test_suspender_no_altera_el_sello(): void
    {
        $permit = $this->makePermitCatalog();
        $this->actingAsRole('safety-officer');
        $this->post(route('permits.store', $permit->id), $this->permitStorePayload($permit));
        $issued = IssuedPermit::latest('id')->first();

        $resp = $this->post(route('permits.suspend', $issued->uuid), ['suspended_reason' => 'Cambió el clima']);
        $resp->assertStatus(302);
        $resp->assertSessionHas('success');

        $issued->refresh();
        $this->assertTrue($issued->isSuspended());

        $acuse = SealVerifier::resolve('perm', $issued->uuid);
        $this->assertSame('ok', $acuse['verdict'], 'Suspender NO debe leerse como ALTERADO.');
        $this->assertSame('Suspendido', $acuse['retired_label']);
    }

    public function test_suspender_exige_motivo(): void
    {
        $permit = $this->makePermitCatalog();
        $this->actingAsRole('safety-officer');
        $this->post(route('permits.store', $permit->id), $this->permitStorePayload($permit));
        $issued = IssuedPermit::latest('id')->first();

        $this->post(route('permits.suspend', $issued->uuid), [])->assertSessionHasErrors('suspended_reason');
        $this->assertFalse($issued->fresh()->isSuspended());
    }

    // ---------------------------- REVERIFICACIÓN EN SITIO ----------------------------

    /**
     * Un permiso de alcance 'reverificacion' se re-corre en OTRO sitio SOLO en sus puntos
     * sensibles al sitio, SIN re-sellar la emisión original (columnas hash-excluidas).
     */
    public function test_reverificacion_ok_no_altera_el_sello_original(): void
    {
        $permit = $this->makePermitCatalog(
            ['site_scope' => IssuedPermit::SCOPE_REVERIFY],
            2,
            true // puntos sensibles al sitio
        );
        $this->actingAsRole('safety-officer');
        $this->post(route('permits.store', $permit->id), $this->permitStorePayload($permit));
        $issued = IssuedPermit::latest('id')->first();
        $hashAntes = $issued->fresh()->computeDocumentHash();

        // Reverificar en un sitio nuevo, todos los puntos sensibles OK.
        $sensCodes = collect($issued->points_snapshot)->where('site_sensitive', true)->pluck('code')->all();
        $this->assertNotEmpty($sensCodes, 'El permiso debe tener puntos sensibles al sitio.');
        $answers = [];
        foreach ($sensCodes as $c) { $answers[$c] = 'cumple'; }

        $resp = $this->post(route('permits.reverify', $issued->uuid), [
            'new_site_label' => 'Locación B',
            'answers'        => $answers,
        ]);
        $resp->assertStatus(302);
        $resp->assertSessionHas('success');

        $issued->refresh();
        $this->assertNotEmpty($issued->reverifications, 'La reverificación debe registrarse.');
        // El hash del contenido sellado no cambió (reverificar es columna hash-excluida).
        $this->assertSame($hashAntes, $issued->computeDocumentHash(), 'Reverificar NO debe alterar el sello.');
        $this->assertSame('ok', SealVerifier::resolve('perm', $issued->uuid)['verdict']);
        $this->assertTrue($issued->coversSite('Locación B'), 'Tras reverificar OK, cubre el sitio nuevo.');
    }

    /** Un permiso 'indiferente' no se reverifica (no aplica). */
    public function test_permiso_indiferente_no_se_reverifica(): void
    {
        $permit = $this->makePermitCatalog(['site_scope' => IssuedPermit::SCOPE_INDIFFERENT]);
        $this->actingAsRole('safety-officer');
        $this->post(route('permits.store', $permit->id), $this->permitStorePayload($permit));
        $issued = IssuedPermit::latest('id')->first();

        $this->post(route('permits.reverify', $issued->uuid), [
            'new_site_label' => 'Otra', 'answers' => ['x' => 'cumple'],
        ])->assertSessionHas('error');
    }
}
