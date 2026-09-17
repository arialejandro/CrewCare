<?php

namespace Tests\Feature\RiskMap;

use App\Models\RiskMap;
use App\Support\SealVerifier;
use Illuminate\Support\Facades\DB;

/**
 * VERIFICADOR PÚBLICO del MAPEO (GET /verificar/rmap/{uuid}). SIN sesión: un tercero llega
 * escaneando el QR impreso. El mapeo es una emisión INDEPENDIENTE (sin cadena/retiro) → dos
 * estados: vigente / alterado. La respuesta es un ACUSE de 5 campos, sin contenido ni identidad
 * (ni el título, ni la locación, ni las narrativas). NO se usa actingAs: se prueba al INVITADO.
 */
class PublicVerifierTest extends RiskMapVerticalTestCase
{
    private function url(string $uuid): string
    {
        return '/verificar/rmap/' . $uuid;
    }

    public function test_mapeo_integro_200_sin_sesion(): void
    {
        $map = $this->sealMap();

        $resp = $this->get($this->url($map->uuid));
        $resp->assertOk();
        $resp->assertSee('Documento íntegro y vigente');
        $resp->assertSee('Mapeo de riesgos y recursos'); // etiqueta genérica del tipo
        $resp->assertSee($map->folio());                 // RMAP-####
        $resp->assertSee($map->uuid);
    }

    public function test_verificador_no_filtra_titulo_ni_locacion(): void
    {
        $sc  = $this->makeScouting(false, ['location_name' => 'LOCACION-SECRETA-XYZ']);
        $map = $this->sealMap([
            'scouting_id' => $sc->id,
            'title'       => 'TITULO-CONFIDENCIAL-ABC',
        ]);

        $resp = $this->get($this->url($map->uuid));
        $resp->assertOk();
        $resp->assertDontSee('TITULO-CONFIDENCIAL-ABC');
        $resp->assertDontSee('LOCACION-SECRETA-XYZ');
    }

    public function test_mapeo_alterado_en_bd_se_muestra_alterado(): void
    {
        $map    = $this->sealMap([], ['x_pct' => 40.000, 'y_pct' => 60.000]);
        $marker = $map->views->first()->markers->first();

        // Baseline íntegro por el resolvedor.
        $this->assertSame('ok', SealVerifier::resolve('rmap', $map->uuid)['verdict']);

        // Mover el pin en BD (contenido sellado) → ALTERADO.
        DB::table('risk_map_markers')->where('id', $marker->id)->update(['x_pct' => 5.000]);

        $resp = $this->get($this->url($map->uuid));
        $resp->assertOk();
        $resp->assertSee('Documento alterado');
        $resp->assertSee('NO coincide con el sello');
    }

    public function test_mapeo_borrador_sin_sello_se_muestra_sin_sello(): void
    {
        // Borrador nunca firmado → "sin sello" (existe, pero no hay integridad que comprobar).
        $map = $this->makeDraftMap();
        $this->addView($map);

        $resp = $this->get($this->url($map->uuid));
        $resp->assertOk();
        $resp->assertSee('Documento sin sello');
    }

    public function test_uuid_inexistente_404_generico(): void
    {
        $this->get($this->url('11111111-2222-3333-4444-555555555555'))
            ->assertStatus(404)
            ->assertSee('No se encontró el documento');
    }
}
