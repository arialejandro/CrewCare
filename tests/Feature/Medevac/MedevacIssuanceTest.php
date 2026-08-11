<?php

namespace Tests\Feature\Medevac;

use App\Models\MedevacPoster;
use Illuminate\Support\Facades\DB;

/**
 * EMISIÓN del póster MEDEVAC (endpoints REALES): congela el payload desde el SCOUTING, crea la
 * fila y la SELLA con la firma del safety que emite.
 *
 * Cubre: emitir → poster sellado + redirección al show; la REVISIÓN es del PROTOCOLO (sube sólo
 * si cambió el contenido del scouting, no por reimprimir); y que alterar el payload congelado en
 * BD rompe el sello (el documento es una fotografía inmutable).
 */
class MedevacIssuanceTest extends MedevacVerticalTestCase
{
    public function test_emision_crea_poster_sellado_y_redirige_al_show(): void
    {
        $so       = $this->actingAsRole('safety-officer');
        $scouting = $this->makeScouting();

        $resp = $this->post(route('medevac.store', $scouting->id), [
            'contacts' => [
                'set_medic' => ['name' => 'Dra. QA', 'phone' => '5551234567'],
            ],
        ]);
        $resp->assertSessionHasNoErrors();

        $poster = MedevacPoster::latest('id')->first();
        $this->assertNotNull($poster, 'El store debe persistir el póster.');
        $resp->assertRedirect(route('medevac.show', $poster->uuid));

        $this->assertSame(1, (int) $poster->revision, 'Primera emisión de la locación → revisión 1.');
        $this->assertSame($so->id, (int) $poster->issued_by_id, 'El emisor se congela server-side.');
        $this->assertNotEmpty($poster->uuid);

        // SELLO al emitir: existe firma y el póster intacto verifica ÍNTEGRO.
        $fresh = MedevacPoster::where('uuid', $poster->uuid)->first();
        $this->assertTrue($fresh->signatures()->exists(), 'El póster debe nacer sellado.');
        $this->assertTrue($fresh->verifyLatestSignature(), 'El sello recién emitido debe verificar íntegro.');

        // El póster sellado es alcanzable por su emisor.
        $this->get(route('medevac.show', $poster->uuid))->assertOk();
    }

    /**
     * La REVISIÓN es del PROTOCOLO, no un contador de clicks: reemitir SIN tocar el scouting
     * conserva el número (exportar dos veces el mismo PDF no son "dos versiones").
     */
    public function test_reemision_sin_cambio_de_scouting_conserva_la_revision(): void
    {
        $this->actingAsRole('safety-officer');
        $scouting = $this->makeScouting();

        $this->post(route('medevac.store', $scouting->id), [])->assertSessionHasNoErrors();
        $this->post(route('medevac.store', $scouting->id), [])->assertSessionHasNoErrors();

        $posters = MedevacPoster::where('scouting_report_id', $scouting->id)->orderBy('id')->get();
        $this->assertCount(2, $posters, 'Cada emisión es un documento nuevo (independiente).');
        $this->assertSame(1, (int) $posters[0]->revision);
        $this->assertSame(1, (int) $posters[1]->revision, 'Sin cambio en el scouting, la revisión NO sube.');
    }

    /** Si cambió el contenido del scouting (p. ej. el hospital), la revisión SÍ sube. */
    public function test_cambio_en_el_scouting_sube_la_revision(): void
    {
        $this->actingAsRole('safety-officer');
        $scouting = $this->makeScouting();

        $this->post(route('medevac.store', $scouting->id), [])->assertSessionHasNoErrors();

        // Cambia el hospital (campo del scouting que entra a la huella) y reemite.
        $scouting->update(['nearest_hospital' => 'Otro Hospital Distinto']);
        $this->post(route('medevac.store', $scouting->id), [])->assertSessionHasNoErrors();

        $posters = MedevacPoster::where('scouting_report_id', $scouting->id)->orderBy('id')->get();
        $this->assertSame(1, (int) $posters[0]->revision);
        $this->assertSame(2, (int) $posters[1]->revision, 'Un cambio real del scouting sube la revisión.');
    }

    /** El payload es la FOTOGRAFÍA congelada: alterarlo en BD debe romper la integridad. */
    public function test_payload_alterado_en_bd_rompe_el_sello(): void
    {
        $poster = $this->sealPoster();

        $this->assertTrue(
            MedevacPoster::where('uuid', $poster->uuid)->first()->verifyLatestSignature(),
            'Intacto no debe dar falso positivo.'
        );

        // Manipular el hospital congelado dentro del payload sellado.
        $tampered = $poster->payload;
        $tampered['hospital']['name'] = 'HOSPITAL FALSIFICADO';
        DB::table('medevac_posters')->where('id', $poster->id)
            ->update(['payload' => json_encode($tampered)]);

        $this->assertFalse(
            MedevacPoster::where('uuid', $poster->uuid)->first()->verifyLatestSignature(),
            'FALLO DE INTEGRIDAD: alterar el payload congelado no se detectó.'
        );
    }
}
