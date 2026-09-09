<?php

namespace Tests\Feature\Permit;

use App\Models\IssuedPermit;
use App\Models\ToolInspection;
use Illuminate\Support\Facades\DB;

/**
 * VERIFICADOR PÚBLICO de sellos (GET /verificar/{tipo}/{uuid}) para PERMISO ('perm') y ACTA de
 * inspección ('insp'). SIN sesión: un tercero llega escaneando el QR. Tres estados por eje:
 * INTEGRIDAD (íntegro / alterado / sin sello) y VIGENCIA (vigente / cerrado-suspendido-retirado).
 *
 * Retirar/cerrar/suspender NUNCA se lee como ALTERADO. Alterar una columna sellada en BD SÍ.
 * NO se usa actingAs: se prueba precisamente el acceso de un INVITADO.
 */
class PublicVerifierTest extends PermitVerticalTestCase
{
    private function url(string $tipo, string $uuid): string
    {
        return '/verificar/' . $tipo . '/' . $uuid;
    }

    /** Emite un permiso sellado a nivel modelo (sin controlador → sin sesión). */
    private function sealPermit(array $attrs = []): IssuedPermit
    {
        $user = $this->makeUser('safety-officer');
        $permit = IssuedPermit::create(array_merge([
            'permit_name'          => 'Trabajo en caliente',
            'activity_description' => 'Soldadura de estructura',
            'issuer_name'          => 'Safety Officer',
            'acceptor_name'        => 'Ejecutante Designado',
            'permit_site_scope'    => IssuedPermit::SCOPE_INDIFFERENT,
        ], $attrs));
        $permit->refresh();
        $permit->signDocument($user);

        return $permit;
    }

    // =================== PERMISO ===================

    public function test_permiso_integro_200_sin_sesion(): void
    {
        $permit = $this->sealPermit();
        $resp = $this->get($this->url('perm', $permit->uuid));

        $resp->assertOk();
        $resp->assertSee('Documento íntegro y vigente');
        $resp->assertSee($permit->uuid);
        $resp->assertSee($permit->folio()); // PERM-####
    }

    public function test_permiso_verificador_no_filtra_contenido(): void
    {
        $permit = $this->sealPermit([
            'activity_description' => 'SECRETO montaje pirotecnia',
            'acceptor_name'        => 'Nombre Confidencial',
        ]);
        $resp = $this->get($this->url('perm', $permit->uuid));

        $resp->assertOk();
        $resp->assertDontSee('SECRETO montaje pirotecnia');
        $resp->assertDontSee('Nombre Confidencial');
    }

    public function test_permiso_alterado_en_bd_se_muestra_alterado(): void
    {
        $permit = $this->sealPermit(['activity_description' => 'Actividad autorizada']);

        DB::table('issued_permits')->where('id', $permit->id)
            ->update(['activity_description' => 'Actividad DISTINTA a la autorizada']);

        $resp = $this->get($this->url('perm', $permit->uuid));
        $resp->assertOk();
        $resp->assertSee('Documento alterado');
        $resp->assertSee('NO coincide con el sello');
    }

    public function test_permiso_cerrado_valido_pero_no_vigente(): void
    {
        $permit = $this->sealPermit();
        DB::table('issued_permits')->where('id', $permit->id)
            ->update(['closed_at' => now(), 'is_active' => 0]);

        $resp = $this->get($this->url('perm', $permit->uuid));
        $resp->assertOk();
        $resp->assertSee('El sello es auténtico');
        $resp->assertSee('cerrado'); // "Válido, pero cerrado"
        $resp->assertDontSee('Documento alterado');
    }

    public function test_permiso_suspendido_valido_pero_no_vigente(): void
    {
        $permit = $this->sealPermit();
        DB::table('issued_permits')->where('id', $permit->id)
            ->update(['suspended_at' => now()]);

        $resp = $this->get($this->url('perm', $permit->uuid));
        $resp->assertOk();
        $resp->assertSee('El sello es auténtico');
        $resp->assertSee('suspendido');
        $resp->assertDontSee('Documento alterado');
    }

    public function test_permiso_sin_sello_se_muestra_sin_sello(): void
    {
        $permit = IssuedPermit::create([
            'permit_name'          => 'Sin sellar',
            'activity_description' => 'x',
            'issuer_name'          => 'Y',
            'acceptor_name'        => 'Z',
        ]);

        $resp = $this->get($this->url('perm', $permit->uuid));
        $resp->assertOk();
        $resp->assertSee('Documento sin sello');
    }

    // =================== ACTA DE INSPECCIÓN ===================

    public function test_acta_integra_200_sin_sesion(): void
    {
        $insp = $this->sealInspection(['verdict' => ToolInspection::VERDICT_APTA]);
        $resp = $this->get($this->url('insp', $insp->uuid));

        $resp->assertOk();
        $resp->assertSee('Documento íntegro y vigente');
        $resp->assertSee($insp->folio()); // INSP-####
    }

    /**
     * ⭐ BUG GUARD: el VEREDICTO está dentro del hash. Si un tercero cambia 'paro' → 'apta'
     * en la BD, el verificador DEBE gritar ALTERADO (un acta con veredicto falsificado no puede
     * pasar por válida).
     */
    public function test_bug_guard_veredicto_falsificado_en_bd_se_detecta(): void
    {
        $insp = $this->sealInspection(['verdict' => ToolInspection::VERDICT_PARO, 'resolution_path' => ToolInspection::PATH_REPLACE]);

        // Baseline íntegro.
        $this->assertSame('ok', \App\Support\SealVerifier::resolve('insp', $insp->uuid)['verdict']);

        // Falsificar el veredicto a APTA directamente en la BD.
        DB::table('tool_inspections')->where('id', $insp->id)->update(['verdict' => ToolInspection::VERDICT_APTA]);

        $resp = $this->get($this->url('insp', $insp->uuid));
        $resp->assertOk();
        $resp->assertSee('Documento alterado');
    }

    public function test_acta_con_checklist_alterado_se_detecta(): void
    {
        $insp = $this->sealInspection();

        DB::table('tool_inspections')->where('id', $insp->id)
            ->update(['checklist_snapshot' => json_encode([['code' => 'X', 'answer' => 'ok', 'is_gate' => true]])]);

        $resp = $this->get($this->url('insp', $insp->uuid));
        $resp->assertOk();
        $resp->assertSee('Documento alterado');
    }

    public function test_acta_retirada_valida_pero_no_vigente(): void
    {
        $insp = $this->sealInspection();

        // Retiro = cambio de ESTADO (columnas hash-excluidas), no de contenido.
        DB::table('tool_inspections')->where('id', $insp->id)
            ->update(['is_active' => 0, 'retired_at' => now(), 'retired_reason' => 'Reinspección posterior']);

        $resp = $this->get($this->url('insp', $insp->uuid));
        $resp->assertOk();
        $resp->assertSee('El sello es auténtico');
        $resp->assertSee('retirado'); // "Válido, pero retirado"
        $resp->assertDontSee('Documento alterado');
    }

    /** El motivo de retiro (texto libre) NUNCA llega al verificador público. */
    public function test_acta_retirada_no_filtra_motivo(): void
    {
        $insp = $this->sealInspection();
        DB::table('tool_inspections')->where('id', $insp->id)
            ->update(['is_active' => 0, 'retired_at' => now(), 'retired_reason' => 'MOTIVO-CONFIDENCIAL-XYZ']);

        $resp = $this->get($this->url('insp', $insp->uuid));
        $resp->assertOk();
        $resp->assertDontSee('MOTIVO-CONFIDENCIAL-XYZ');
    }

    public function test_acta_sin_sello_se_muestra_sin_sello(): void
    {
        $insp = ToolInspection::create([
            'tool_name' => 'Sin sellar', 'verdict' => ToolInspection::VERDICT_APTA, 'is_active' => 1,
        ]);
        $resp = $this->get($this->url('insp', $insp->uuid));
        $resp->assertOk();
        $resp->assertSee('Documento sin sello');
    }

    // =================== RESPUESTA GENÉRICA ===================

    public function test_uuid_inexistente_404_generico(): void
    {
        $this->get($this->url('perm', '11111111-2222-3333-4444-555555555555'))
            ->assertStatus(404)
            ->assertSee('No se encontró el documento');
    }
}
