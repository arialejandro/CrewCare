<?php

namespace Tests\Feature\Transport;

use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Support\VehicleChecklist;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\QaTestCase;

/**
 * Base del vertical TRANSPORTACIÓN (Bloque 1). Se apoya en el CATÁLOGO REAL sembrado de fábrica
 * (VehicleCatalogSeeder → 13 tipos + 52 puntos). No es una clase de prueba (no termina en Test.php).
 *
 * Fotos: Storage::fake('public') para no ensuciar el disco real (las fotos por punto son obligatorias
 * en requires_photo o punto reprobado).
 */
abstract class VehicleVerticalTestCase extends QaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /** Un tipo real del catálogo. 'auto' = combustión 5 plazas → solo puntos núcleo. */
    protected function aType(string $code = 'auto'): VehicleType
    {
        $type = VehicleType::active()->where('code', $code)->first();
        $this->assertNotNull($type, "El catálogo de fábrica debe traer el tipo {$code}.");
        return $type;
    }

    /** Crea un vehículo mínimo del tipo dado. */
    protected function makeVehicle(string $code = 'auto', array $attrs = []): Vehicle
    {
        $type = $this->aType($code);
        return Vehicle::create(array_merge([
            'vehicle_type_id' => $type->id,
            'type_code'       => $type->code,
            'make'            => 'Toyota',
            'model'           => 'QA',
            'plate'           => 'QA-' . Str::random(4),
            'owner_kind'      => Vehicle::OWNER_OTHER,
            'owner_name'      => 'Dueño QA',
            'attr_values'     => $type->profile(),
            'is_active'       => 1,
        ], $attrs));
    }

    /** Los códigos de los puntos aplicables al vehículo (mismo orden que el controlador). */
    protected function applicableCodes(Vehicle $vehicle): array
    {
        return VehicleChecklist::pointsFor($vehicle->resolvedAttributes())->pluck('code')->all();
    }

    /**
     * Payload para POST /transportacion/verificar. Responde todos 'ok' salvo overrides, y adjunta
     * la foto obligatoria en cada punto requires_photo o reprobado.
     *
     * @param  array  $answerOverrides  code => 'ok'|'fail'
     */
    protected function checklistPayload(Vehicle $vehicle, array $answerOverrides = [], array $extra = []): array
    {
        $points  = VehicleChecklist::pointsFor($vehicle->resolvedAttributes());
        $answers = [];
        $photos  = [];
        foreach ($points as $p) {
            $ans = $answerOverrides[$p->code] ?? 'ok';
            $answers[$p->code] = $ans;
            if ($p->requires_photo || $ans === 'fail') {
                $photos[$p->code] = UploadedFile::fake()->image($p->code . '.jpg', 24, 24);
            }
        }

        return array_merge([
            'vehicle_id'   => $vehicle->id,
            'answers'      => $answers,
            'point_photos' => $photos,
            'km'           => 1200,
        ], $extra);
    }
}
