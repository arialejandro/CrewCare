<?php

namespace Tests\Feature\Admin;

use App\Models\HazardEvent;
use Illuminate\Http\UploadedFile;
use Tests\QaTestCase;

/**
 * Importación del CSV de medidas de control (HazardEventController@importControlCsv).
 *
 * Decisión del owner (2026-08-17): el MISMO CSV debe poder CREAR eventos nuevos, no solo
 * actualizar medidas. Se verifican las cuatro garantías del importador:
 *   1) código nuevo → CREA el evento (name_es, categoría del catálogo cerrado, context por prefijo).
 *   2) categoría fuera del catálogo cerrado → se guarda NULL (nunca inventa una categoría).
 *   3) código existente con celda vacía → NUNCA pisa la medida escrita con un vacío (idempotente).
 *   4) código existente con categoría vacía en BD → la RELLENA (sin pisar una ya puesta).
 *   5) código nuevo SIN name_es → NO se crea (fila incompleta).
 */
class HazardEventImportTest extends QaTestCase
{
    /** Header idéntico al del export para reflejar el archivo real del owner. */
    private const HEADER = 'code,usos,name_es,categoria,actividad,normas,control_measure_es,control_measure_en';

    private function importCsv(string $body)
    {
        $file = UploadedFile::fake()->createWithContent('medidas.csv', self::HEADER . "\n" . $body);

        return $this->post(route('hazardevents.control.import'), ['csv' => $file]);
    }

    public function test_csv_creates_new_events_and_never_overwrites_with_blanks(): void
    {
        $this->actingAsRole('super-admin');

        // Un evento que YA existe, con medida escrita y SIN categoría (hueco a rellenar).
        HazardEvent::create([
            'code' => 'QAE-001', 'context' => 'transversal', 'name_es' => 'Evento existente',
            'control_measure_es' => 'MEDIDA ORIGINAL',
        ]);

        $rows = implode("\n", [
            // 1) nuevo con prefijo conocido → context=location, categoría válida, medida escrita
            'LOC-989,0,"Peligro nuevo en locación",traffic,,,"Cerrar carril y vigías","Close lane"',
            // 2) nuevo con prefijo desconocido → context=transversal, categoría nueva válida (health)
            'QAX-777,0,"Peligro ergonómico",health,,,"Pausas y estiramiento",""',
            // 3) nuevo con categoría FUERA del catálogo cerrado → category NULL
            'QAZ-888,0,"Categoría inexistente",no_existe,,,"Alguna medida",""',
            // 4) existente: celda de medida VACÍA (no debe pisar) + categoría a rellenar
            'QAE-001,3,"Evento existente",electrical,,,"",""',
            // 5) nuevo SIN name_es → NO se crea
            'QAN-002,0,"",heights,,,"Medida sin nombre",""',
        ]);

        $this->importCsv($rows)->assertSessionHas('success');

        // 1) creado con context por prefijo y categoría/medida
        $loc = HazardEvent::where('code', 'LOC-989')->first();
        $this->assertNotNull($loc, 'LOC-989 debió crearse');
        $this->assertSame('location', $loc->context);
        $this->assertSame('traffic', $loc->category);
        $this->assertSame('Cerrar carril y vigías', $loc->control_measure_es);
        $this->assertNotNull($loc->verified_at, 'un evento creado por el CSV curado nace verificado');

        // 2) prefijo desconocido → transversal; categoría nueva (health) aceptada
        $qax = HazardEvent::where('code', 'QAX-777')->first();
        $this->assertNotNull($qax);
        $this->assertSame('transversal', $qax->context);
        $this->assertSame('health', $qax->category);

        // 3) categoría fuera del catálogo cerrado → NULL (no se inventa)
        $qaz = HazardEvent::where('code', 'QAZ-888')->first();
        $this->assertNotNull($qaz);
        $this->assertNull($qaz->category);

        // 4) medida NO pisada por vacío; categoría hueca RELLENADA
        $qae = HazardEvent::where('code', 'QAE-001')->first();
        $this->assertSame('MEDIDA ORIGINAL', $qae->control_measure_es, 'la medida no debe pisarse con vacío');
        $this->assertSame('electrical', $qae->category, 'la categoría vacía debe rellenarse');

        // 5) nuevo sin nombre → no se crea
        $this->assertNull(HazardEvent::where('code', 'QAN-002')->first(), 'sin name_es no se crea');
    }

    /** Reimportar el mismo archivo no cambia nada (idempotente): 0 creados, 0 actualizados. */
    public function test_reimport_is_idempotent(): void
    {
        $this->actingAsRole('super-admin');

        $rows = 'LOC-990,0,"Peligro idempotente",traffic,,,"Medida","Measure"';
        $this->importCsv($rows);
        $countAfterFirst = HazardEvent::count();

        $this->importCsv($rows)->assertSessionHas('success');
        $this->assertSame($countAfterFirst, HazardEvent::count(), 'reimportar no debe duplicar');

        $ev = HazardEvent::where('code', 'LOC-990')->first();
        $this->assertSame('Medida', $ev->control_measure_es);
    }
}
