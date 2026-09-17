<?php

namespace Tests\Feature\Pae;

use App\Models\EmergencyActionPlan;
use App\Support\SealVerifier;
use Illuminate\Support\Facades\DB;

/**
 * VERIFICADOR PÚBLICO del PAE (GET /verificar/pae/{uuid}). SIN sesión: un tercero llega
 * escaneando el QR. Emisión INDEPENDIENTE (sin retiro) → dos estados: vigente / alterado.
 * La respuesta es un ACUSE de 5 campos, sin contenido ni identidad (ni la locación, ni los
 * contactos del organigrama). NO se usa actingAs: se prueba al INVITADO.
 */
class PublicVerifierTest extends PaeVerticalTestCase
{
    private function url(string $uuid): string
    {
        return '/verificar/pae/' . $uuid;
    }

    public function test_pae_integro_200_sin_sesion(): void
    {
        $plan = $this->sealPlan();

        $resp = $this->get($this->url($plan->uuid));
        $resp->assertOk();
        $resp->assertSee('Documento íntegro y vigente');
        $resp->assertSee('Plan de Atención a Emergencias'); // etiqueta genérica del tipo
        $resp->assertSee($plan->folio());                   // PAE-####
        $resp->assertSee($plan->uuid);
    }

    public function test_verificador_no_filtra_locacion_ni_contactos(): void
    {
        $sc   = $this->makeScouting(['location_name' => 'LOCACION-SECRETA-PAE']);
        $plan = $this->sealPlan([$sc->id], ['contact_name' => 'CONTACTO-CONFIDENCIAL-PAE']);

        $resp = $this->get($this->url($plan->uuid));
        $resp->assertOk();
        $resp->assertDontSee('LOCACION-SECRETA-PAE');
        $resp->assertDontSee('CONTACTO-CONFIDENCIAL-PAE');
    }

    public function test_pae_alterado_en_bd_se_muestra_alterado(): void
    {
        $plan = $this->sealPlan([], ['shoot_day' => 3]);

        // Baseline íntegro por el resolvedor.
        $this->assertSame('ok', SealVerifier::resolve('pae', $plan->uuid)['verdict']);

        DB::table('emergency_action_plans')->where('id', $plan->id)->update(['shoot_day' => 77]);

        $resp = $this->get($this->url($plan->uuid));
        $resp->assertOk();
        $resp->assertSee('Documento alterado');
        $resp->assertSee('NO coincide con el sello');
    }

    /**
     * Apagar el PAE del listado (is_active=0, p. ej. al superseder por una revisión) NO es
     * alteración: is_active está en $signatureExcludes. Debe seguir mostrándose ÍNTEGRO y
     * VIGENTE (el PAE no tiene concepto de "retirado" en el verificador — dos estados).
     */
    public function test_pae_apagado_del_listado_sigue_integro_no_alterado(): void
    {
        $plan = $this->sealPlan();
        DB::table('emergency_action_plans')->where('id', $plan->id)->update(['is_active' => 0]);

        $resp = $this->get($this->url($plan->uuid));
        $resp->assertOk();
        $resp->assertSee('Documento íntegro y vigente');
        $resp->assertDontSee('Documento alterado');
    }

    public function test_uuid_inexistente_404_generico(): void
    {
        $this->get($this->url('11111111-2222-3333-4444-555555555555'))
            ->assertStatus(404)
            ->assertSee('No se encontró el documento');
    }
}
