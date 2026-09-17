<?php

namespace Tests\Feature\Sfx;

use App\Models\SfxEffectType;
use App\Support\Features;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\QaTestCase;

/**
 * SPFX · IMAGEN PRINCIPAL del tipo de efecto (referencia visual de la card del catálogo).
 * Réplica del patrón de Herramientas (InspectionController::storeToolImage): sube/reemplaza al
 * disco público. Gate = sds.manage (misma autoridad que asociar insumos: es doctrina del catálogo).
 */
class SfxEffectImageTest extends QaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // El módulo SPFX vive detrás del flag 'sds_sfx' (como lo enciende el panel de admin).
        DB::table('feature_flags')->updateOrInsert(['key' => 'sds_sfx'], ['enabled' => 1]);
        Features::flush();
    }

    private function makeEffect(): SfxEffectType
    {
        return SfxEffectType::create([
            'code' => 'SFX-IMG-1', 'family' => 'Humo', 'name' => 'Humo de prueba', 'is_active' => 1,
        ]);
    }

    public function test_sds_manage_sube_la_imagen_principal(): void
    {
        Storage::fake('public');
        $effect = $this->makeEffect();
        $this->actingAsRole('super-admin');   // tiene sds.manage (+ Gate::before)

        $resp = $this->post(route('sfx-effects.image.store', $effect->id), [
            'image' => UploadedFile::fake()->image('efecto.jpg', 400, 250),
        ]);

        $resp->assertRedirect(route('sfx-effects.show', $effect->id));

        $fresh = $effect->fresh();
        $this->assertNotNull($fresh->image_path, 'La imagen debe quedar guardada en image_path.');
        Storage::disk('public')->assertExists($fresh->image_path);
        $this->assertNotNull($fresh->imageUrl(), 'imageUrl() debe resolver una URL cuando hay imagen.');
    }

    public function test_reemplazar_borra_la_imagen_anterior(): void
    {
        Storage::fake('public');
        $effect = $this->makeEffect();
        $this->actingAsRole('super-admin');

        $this->post(route('sfx-effects.image.store', $effect->id), ['image' => UploadedFile::fake()->image('a.jpg')]);
        $first = $effect->fresh()->image_path;

        $this->post(route('sfx-effects.image.store', $effect->id), ['image' => UploadedFile::fake()->image('b.jpg')]);
        $second = $effect->fresh()->image_path;

        $this->assertNotSame($first, $second, 'Reemplazar debe generar una ruta nueva.');
        Storage::disk('public')->assertMissing($first);   // la anterior se borra (no acumula basura)
        Storage::disk('public')->assertExists($second);
    }

    public function test_sin_sds_manage_no_puede_subir(): void
    {
        Storage::fake('public');
        $effect = $this->makeEffect();
        $this->actingAsRole('crew');   // sin sds.manage → 403 de middleware

        $this->post(route('sfx-effects.image.store', $effect->id), [
            'image' => UploadedFile::fake()->image('efecto.jpg'),
        ])->assertForbidden();

        $this->assertNull($effect->fresh()->image_path);
    }
}
