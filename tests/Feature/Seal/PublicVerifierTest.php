<?php

namespace Tests\Feature\Seal;

use App\Models\InjuryReport;
use App\Models\IssuedPermit;
use Illuminate\Support\Facades\DB;
use Tests\QaTestCase;

/**
 * QA — VERIFICADOR PÚBLICO de sellos: GET /verificar/{tipo}/{uuid}.
 *
 * Ruta SIN sesión (throttle:20,1). Un tercero (auditoría, estudio) llega escaneando el QR
 * impreso. La respuesta jamás debe mostrar contenido/identidad: sólo el ACUSE de integridad.
 *
 * NO se usa actingAs: se prueba precisamente que responde a un INVITADO.
 */
class PublicVerifierTest extends QaTestCase
{
    private function url(string $tipo, string $uuid): string
    {
        return '/verificar/' . $tipo . '/' . $uuid;
    }

    private function sellarInjury(array $attrs = []): InjuryReport
    {
        $user = $this->makeUser('safety-officer');
        $injury = InjuryReport::create(array_merge([
            'name'          => 'Persona Lesionada',
            'phone'         => '5544332211',
            'what_happened' => 'Caída desde escalera',
        ], $attrs));
        $injury->refresh();
        $injury->signDocument($user);

        return $injury;
    }

    // ------------------------------------------------------------------
    // Documento íntegro: 200 SIN sesión + veredicto positivo.
    // ------------------------------------------------------------------

    public function test_documento_integro_responde_200_sin_sesion(): void
    {
        $injury = $this->sellarInjury();

        $resp = $this->get($this->url('injury', $injury->uuid));

        $resp->assertOk();
        $resp->assertSee('Documento íntegro y vigente');
        $resp->assertSee($injury->uuid); // el UUID (no PII) sí se muestra
    }

    public function test_verificador_no_filtra_pii_del_lesionado(): void
    {
        $injury = $this->sellarInjury(['name' => 'Secreto Absoluto', 'phone' => '5500001111']);

        $resp = $this->get($this->url('injury', $injury->uuid));

        $resp->assertOk();
        $resp->assertDontSee('Secreto Absoluto');
        $resp->assertDontSee('5500001111');
        $resp->assertDontSee('Caída desde escalera');
    }

    // ------------------------------------------------------------------
    // ⭐ Alteración: la página pública lo grita en rojo.
    // ------------------------------------------------------------------

    public function test_documento_alterado_se_muestra_como_alterado(): void
    {
        $injury = $this->sellarInjury(['name' => 'Original']);

        DB::table('injury_reports')->where('id', $injury->id)->update(['name' => 'Manipulado']);

        $resp = $this->get($this->url('injury', $injury->uuid));

        $resp->assertOk();
        $resp->assertSee('Documento alterado');
        $resp->assertSee('NO coincide con el sello');
    }

    // ------------------------------------------------------------------
    // Sin sello / inexistente / tipo desconocido.
    // ------------------------------------------------------------------

    public function test_documento_sin_sello_se_muestra_como_sin_sello(): void
    {
        $injury = InjuryReport::create(['name' => 'Sin sellar', 'what_happened' => 'x']);

        $resp = $this->get($this->url('injury', $injury->uuid));

        $resp->assertOk();
        $resp->assertSee('Documento sin sello');
    }

    public function test_uuid_inexistente_responde_404_generico(): void
    {
        $resp = $this->get($this->url('injury', '11111111-2222-3333-4444-555555555555'));

        $resp->assertStatus(404);
        $resp->assertSee('No se encontró el documento');
    }

    public function test_tipo_desconocido_responde_404_generico(): void
    {
        // 'zzz' respeta el patrón de ruta [a-z]{3,6} pero no existe en TYPES.
        $resp = $this->get($this->url('zzz', '11111111-2222-3333-4444-555555555555'));

        $resp->assertStatus(404);
        $resp->assertSee('No se encontró el documento');
    }

    // ------------------------------------------------------------------
    // Tres estados: permiso cerrado => "Válido, pero cerrado" (no rojo).
    // ------------------------------------------------------------------

    public function test_permiso_cerrado_se_muestra_valido_pero_no_vigente(): void
    {
        $user = $this->makeUser('safety-officer');
        $permit = IssuedPermit::create([
            'permit_name'          => 'Trabajo en caliente',
            'activity_description' => 'Soldadura',
            'issuer_name'          => 'Safety',
            'acceptor_name'        => 'Ejecutante',
        ]);
        $permit->refresh();
        $permit->signDocument($user);

        DB::table('issued_permits')->where('id', $permit->id)
            ->update(['closed_at' => now(), 'is_active' => 0]);

        $resp = $this->get($this->url('perm', $permit->uuid));

        $resp->assertOk();
        $resp->assertSee('El sello es auténtico');
        $resp->assertDontSee('Documento alterado');
    }
}
