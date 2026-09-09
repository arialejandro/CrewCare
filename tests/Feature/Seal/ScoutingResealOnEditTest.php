<?php

namespace Tests\Feature\Seal;

use App\Models\ScoutingReport;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;
use Tests\QaTestCase;

/**
 * INTEGRIDAD (2026-09-05) — un scouting YA SELLADO que se EDITA debe SEGUIR verificando, quede en el
 * estado que quede. Antes el re-sellado de ScoutingReportController::update() estaba guardado por
 * `status === 'final'`: bajar de "final" un scouting firmado lo mutaba SIN re-sellar → su sello quedaba
 * viejo y el documento se leía ALTERADO sin que nadie lo hubiera alterado. La condición correcta es
 * "¿ya estaba SELLADO?". Se conserva la doctrina: un borrador nunca sellado NO se sella al editarse.
 */
class ScoutingResealOnEditTest extends QaTestCase
{
    /** Autor safety con permiso para la ruta (los scoutings reusan locations.*). */
    private function autorSafety(): User
    {
        $user = $this->makeUser('safety-officer');
        try {
            $user->givePermissionTo('locations.create');
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // El rol ya la trae; seguimos.
        }

        return $user;
    }

    public function test_scouting_firmado_editado_bajando_de_final_sigue_verificando(): void
    {
        $user = $this->autorSafety();

        // Scouting FINAL, sellado por su autor (created_by_id = autor → puede editar/re-sellar).
        $scout = ScoutingReport::create([
            'location_name'    => 'Bodega Norte',
            'location_address' => 'Calle 1',
            'production_name'  => 'QA Prod',
            'status'           => 'final',
            'created_by_id'    => $user->id,
        ]);
        $scout->refresh();
        $scout->signDocument($user);

        $this->assertTrue($scout->fresh()->verifyLatestSignature(), 'Baseline: el scouting final sellado debe verificar.');
        $this->assertSame(1, $scout->signatures()->count());

        // EDICIÓN: cambia una columna SELLADA (location_name) y BAJA el estado a 'draft'.
        $this->actingAs($user)
            ->put(route('scoutings.update', $scout->id), [
                'location_name' => 'Bodega Norte (corregida)',
                'status'        => 'draft',
            ])
            ->assertRedirect();

        $fresh = ScoutingReport::find($scout->id);
        $this->assertSame('draft', $fresh->status, 'El estado debió bajar a draft.');
        $this->assertSame('Bodega Norte (corregida)', $fresh->location_name, 'El contenido sellado debió cambiar.');

        // EL PUNTO: sigue verificando PORQUE se re-selló pese a no estar en final.
        $this->assertTrue(
            $fresh->verifyLatestSignature(),
            'FALLO DE INTEGRIDAD: un scouting sellado, editado y bajado de final, quedó ALTERADO.'
        );
        $this->assertSame(2, $fresh->signatures()->count(), 'Debió agregarse una 2ª firma (re-sello) al historial.');
    }

    public function test_scouting_borrador_nunca_sellado_no_se_sella_al_editar(): void
    {
        $user = $this->autorSafety();

        // Borrador NUNCA sellado: editarlo en draft NO debe sellarlo (doctrina conservada).
        $scout = ScoutingReport::create([
            'location_name'    => 'Set A',
            'location_address' => 'Calle 2',
            'production_name'  => 'QA Prod',
            'status'           => 'draft',
            'created_by_id'    => $user->id,
        ]);
        $this->assertFalse($scout->signatures()->exists());

        $this->actingAs($user)
            ->put(route('scoutings.update', $scout->id), [
                'location_name' => 'Set A (editado)',
                'status'        => 'draft',
            ])
            ->assertRedirect();

        $this->assertFalse(
            ScoutingReport::find($scout->id)->signatures()->exists(),
            'Un borrador nunca sellado NO debe sellarse al editarse en draft.'
        );
    }
}
