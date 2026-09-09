<?php

namespace Tests\Feature\Medevac;

use App\Models\MedevacPoster;
use Illuminate\Support\Facades\DB;

/**
 * VERIFICADOR PÚBLICO del póster MEDEVAC (GET /verificar/mdvc/{uuid}). SIN sesión.
 *
 * El póster tiene DOS estados (no tres): cada emisión es independiente → sin concepto de retiro,
 * sólo íntegro / alterado. Y NADA de contenido ni identidad se filtra: el acuse sólo dice que es
 * un "Póster MEDEVAC", nunca de qué locación, hospital, contactos ni emisor.
 *
 * NO se usa actingAs: se prueba el acceso de un INVITADO.
 */
class PublicVerifierTest extends MedevacVerticalTestCase
{
    private function url(string $uuid): string
    {
        return '/verificar/mdvc/' . $uuid;
    }

    public function test_poster_integro_200_sin_sesion(): void
    {
        $poster = $this->sealPoster();
        $resp   = $this->get($this->url($poster->uuid));

        $resp->assertOk();
        $resp->assertSee('Documento íntegro y vigente');
        $resp->assertSee($poster->uuid);
        $resp->assertSee($poster->folio());   // MDVC-####
        $resp->assertSee('Póster MEDEVAC');    // etiqueta genérica
    }

    /** El acuse público NO revela locación, hospital, contactos ni emisor. */
    public function test_verificador_no_filtra_locacion_contactos_ni_emisor(): void
    {
        $poster = $this->sealPoster([
            'location_label' => 'LOCACION-CONFIDENCIAL',
        ], [
            'location' => ['name' => 'LOCACION-CONFIDENCIAL', 'address' => 'DIRECCION-SECRETA'],
            'hospital' => ['name' => 'HOSPITAL-SECRETO', 'address' => 'x'],
            'contacts' => [
                ['key' => 'set_medic', 'label' => 'Set Medic', 'name' => 'CONTACTO-CONFIDENCIAL', 'phone' => '5559998888'],
            ],
        ]);

        $resp = $this->get($this->url($poster->uuid));
        $resp->assertOk();
        $resp->assertDontSee('LOCACION-CONFIDENCIAL');
        $resp->assertDontSee('DIRECCION-SECRETA');
        $resp->assertDontSee('HOSPITAL-SECRETO');
        $resp->assertDontSee('CONTACTO-CONFIDENCIAL');
        $resp->assertDontSee('5559998888');
        $resp->assertDontSee('CONFIDENCIAL-EMISOR');
    }

    public function test_poster_alterado_en_bd_se_muestra_alterado(): void
    {
        $poster = $this->sealPoster();

        $tampered = $poster->payload;
        $tampered['location']['name'] = 'CAMBIADA';
        DB::table('medevac_posters')->where('id', $poster->id)
            ->update(['payload' => json_encode($tampered)]);

        $resp = $this->get($this->url($poster->uuid));
        $resp->assertOk();
        $resp->assertSee('Documento alterado');
        $resp->assertSee('NO coincide con el sello');
    }

    /** Apagar el póster (is_active=0) NO lo marca alterado: is_active está fuera del hash. */
    public function test_poster_apagado_sigue_integro(): void
    {
        $poster = $this->sealPoster();

        DB::table('medevac_posters')->where('id', $poster->id)->update(['is_active' => 0]);

        $resp = $this->get($this->url($poster->uuid));
        $resp->assertOk();
        // Sin concepto de retiro: sigue íntegro y vigente, nunca "alterado".
        $resp->assertSee('Documento íntegro y vigente');
        $resp->assertDontSee('Documento alterado');
    }

    public function test_poster_sin_sello_se_muestra_sin_sello(): void
    {
        $scouting = $this->makeScouting();
        $poster   = MedevacPoster::create([
            'production_id'      => $scouting->production_id,
            'scouting_report_id' => $scouting->id,
            'revision'           => 1,
            'location_label'     => $scouting->location_name,
            'payload'            => ['version' => 2],
            'is_active'          => 1,
        ]);

        $this->get($this->url($poster->uuid))->assertOk()->assertSee('Documento sin sello');
    }

    public function test_uuid_inexistente_404_generico(): void
    {
        $this->get($this->url('11111111-2222-3333-4444-555555555555'))
            ->assertStatus(404)
            ->assertSee('No se encontró el documento');
    }
}
