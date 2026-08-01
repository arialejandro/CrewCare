<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Position;
use Illuminate\Database\Seeder;

/**
 * Seeds the GLOBAL department + position catalog (production_id = NULL) from
 * ORG-TAXONOMY.md (real call-sheet taxonomy). This is a reusable TEMPLATE that each
 * production adapts (uses a subset + adds its own custom positions). Nothing here is
 * hardcoded as one production's org chart (PROGRESS.md FIRM requirement).
 *
 * Hierarchy: positions tagged [HOD] in ORG-TAXONOMY.md §2 get is_hod = true.
 * Idempotent (firstOrCreate by name / by department+name within global scope).
 */
class OrgCatalogSeeder extends Seeder
{
    public function run()
    {
        // department => [radio_channel, [ [positionName, isHod], ... ] ]
        $catalog = [
            'Dirección' => ['1', [
                ['Director', true], ['Asistente de Director', false],
                ['Continuista', false], ['Asst. Continuista', false],
            ]],
            'Asistente de Dirección' => ['1', [
                ['1er AD', true], ['Key 2nd AD', false], ['2nd AD', false],
                ['2nd 2nd AD', false], ['Key Set PA', false], ['Set PA', false], ['Cast PA', false],
            ]],
            'Producción' => ['3', [
                ['Productor en Línea', true], ['Gerente de Producción', false],
                ['Gerente de Unidad', false], ['Asst. Gerente de Unidad', false],
            ]],
            'Oficina de Producción' => ['3', [
                ['Coordinador de Producción', true], ['APOC', false],
                ['Coordinador de Viajes', false], ['Asst. de Coordinador de Viajes', false],
                ['Asst. de Oficina de Producción', false], ['Recepción de Oficina', false],
            ]],
            'Producción Ejecutiva' => ['3', [
                ['Productor Ejecutivo', true], ['Coord. Ejecutivo', false],
                ['Jefa de Equipo', false], ['Ejecutivo Creativo', false],
                ['Asistente Ejecutivo', false], ['Coordinador Ejecutivo', false],
            ]],
            'Escritores' => [null, [
                ['Creador/Escritor', true], ['Escritor', false],
                ['Coordinador de Guiones', false], ['Storyboardista', false],
            ]],
            'Cámara' => ['5', [
                ['Director de Fotografía', true], ['Operador de Cámara', false],
                ['1er AC', false], ['2ndo AC', false], ['Cámara PA', false],
                ['DIT', false], ['Data Manager', false], ['Asistente de DoP', false],
            ]],
            'Eléctricos' => ['6', [
                ['Gaffer', true], ['Supervisor de Eléctricos', false],
                ['Eléctrico', false], ['Operador de Consola', false],
            ]],
            'Grips' => ['6', [
                ['Key Grip', true], ['Grip', false], ['Grip Asst.', false], ['Dolly Grip', false],
            ]],
            'Rigging' => ['6', [
                ['Rigging Gaffer', true], ['Rigging Key Grip', true],
                ['Electric (rigging)', false], ['Grip (rigging)', false],
            ]],
            'Sonido' => ['7', [
                ['Sonido Directo', true], ['Operador de Boom', false], ['Utility', false],
            ]],
            'Video/VTR' => ['7', [
                ['Operador de VTR', true], ['Asistente de Video', false],
            ]],
            'Maquillaje y Peinados' => ['9', [
                ['Diseñador de M&P', true], ['Coordinador MU&H', false],
                ['Asst. Maquillaje', false], ['Asst. Peinados', false], ['Apoyo M&P', false],
            ]],
            'Vestuario' => ['8', [
                ['Diseñador de Vestuario', true], ['Asst. Diseñador', false],
                ['Supervisor de Vestuario', false], ['Coordinador de Vestuario', false],
                ['Asst. Coordinador de Vestuario', false], ['Comprador de Vestuario', false],
                ['Vestuarista', false], ['Asistente de Vestuario', false],
                ['Jefa de Sastrería', false], ['Jefa de Taller de Costura', false],
                ['Costurero', false], ['Diseñador Gráfico de Vestuario', false],
                ['Asst. de Compras de Vestuario', false],
                // Backfill 2026-06-24: legacy "Sastre Vestuario" (tailor) -> distinct from
                // "Jefa de Sastrería" (head of tailoring) and "Costurero" (seamstress).
                ['Sastre', false],
            ]],
            'Arte' => ['10', [
                ['Diseñador de Producción', true], ['Director de Arte', false],
                ['Director de Arte en Set', false], ['Asst. de Dirección de Arte', false],
                ['Asst. de Diseño de Producción', false], ['Coordinador de Arte', false],
                ['Asistente de Coord. de Arte', false], ['Asistente de Oficina de Arte', false],
                ['Diseñador de Sets', false], ['Diseñador Gráfico', false],
                ['Asst. de Diseñador Gráfico', false], ['Artista Conceptual', false],
            ]],
            'Decoración' => ['10', [
                ['Decorador', true], ['Decorador en Set', false],
                ['Coordinador de Decoración', false], ['Asst. Coord. de Decoración', false],
                ['Set Dresser', false], ['Asistente Decorador en Set', false],
                ['Apoyo Decorador en Set', false], ['Compras de Decoración', false],
                ['Leadman', false], ['Swing Gang', false], ['Asistente de Decoración', false],
                // Backfill 2026-06-24: legacy "Bodeguero Decoración" -> warehouse keeper for the
                // Decoration dept (distinct from "Bodeguero" under Construcción / "Bodeguero Props").
                ['Bodeguero', false],
            ]],
            'Construcción' => ['6', [
                ['Constructor', true], ['Coordinador de Construcción', false],
                ['Asst. Coord. Construcción', false], ['Foreman', false],
                ['Carpintero', false], ['Jefe de Carpintería', false], ['Herrero', false],
                ['Jefe de Pintura Escénica', false], ['Pintor', false],
                ['Asistente de Pintor Escénico', false], ['Compras de Construcción', false],
                ['Apoyo Eventual de Construcción', false], ['Bodeguero', false],
                ['Welder', false], ['Gang Boss Welder', false], ['Head Welder', false],
            ]],
            'Utilería' => ['10', [
                ['Jefe de Utilería', true], ['Utilería en Set', false],
                ['Asistente de Utilería', false], ['Asistente de Utilería en Set', false],
                ['Compras de Utilería en Set', false], ['Bodeguero Props', false],
                ['Apoyo Eventual de Utilería', false], ['Animalero', false],
                // Backfill 2026-06-24: legacy "Coordinacion Utileria" -> the dept had no
                // coordinator position in the catalog.
                ['Coordinador de Utilería', false],
            ]],
            'Locaciones' => ['4', [
                ['Gerente de Locaciones', true], ['Gerente Asst. de Locaciones', false],
                ['Coordinador de Locaciones', false], ['Asst. Coordinador de Locaciones', false],
                ['Asst. de Locaciones', false], ['P.A. de Locaciones', false],
                ['Apoyo de Locaciones', false], ['Gerente de Soporte de Locaciones', false],
                ['Soporte de Locaciones', false], ['Apoyo de Limpieza', false],
                ['Encargado de Basecamp', false], ['Personal de Loc.', false],
                // Backfill 2026-06-24: legacy "Scouter" (location scout) -> no equivalent existed.
                ['Scouter', false],
            ]],
            'Transportación' => ['15', [
                ['Coordinador de Transporte', true], ['Capitán de Transporte', false],
                ['Co Capitán de Transporte', false], ['Dispatcher', false],
                ['Asistente de Coord. de Transporte', false], ['Contador de Transporte', false],
                ['Asistente de Transportación', false], ['Trainee de Transportación', false],
                ['Chofer', false],
            ]],
            'Picture Cars' => ['11', [
                ['Jefa de Picture Cars', true], ['Coordinador de Picture Cars', false],
                ['Asst. de Picture Cars', false],
            ]],
            'Efectos Especiales' => [null, [
                ['Supervisor de Efectos Especiales', true], ['Efectos Especiales', false],
                ['Efectos Especiales Oficina', false],
            ]],
            'Casting' => [null, [
                ['Director de Casting', true], ['Asociado de Casting', false],
                ['Coordinador de Casting', false], ['Asistente de Casting', false],
                ['Supervisora de Elenco', false],
            ]],
            'Extras' => [null, [
                ['Director de Casting de Extras', true], ['Coordinador de Extras', false],
            ]],
            'Stunts' => ['12', [
                ['Coordinador de Stunts', true], ['Stunt', false],
            ]],
            'Música' => [null, [
                ['Supervisora de Música', true],
            ]],
            'VFX' => [null, [
                ['Productor de VFX', true], ['Supervisor de VFX', true],
                ['Supervisor de VFX en Set', false],
            ]],
            'Post Producción' => [null, [
                ['Supervisor de Postproducción', true], ['Coordinador de Postproducción', false],
                ['Asistente de Postproducción', false],
            ]],
            'Contabilidad' => [null, [
                ['Contador de Producción', true], ['Asst. Contable', false],
                ['Contador Fiscal', false], ['Auxiliar de Contabilidad', false],
                ['Asistente de Contabilidad', false],
            ]],
            'Catering' => ['13', [
                ['Coordinador de Craft', true], ['Cafetero', false],
                ['Asst. Cafetero', false], ['Crew de Cocina', false],
            ]],
            'Salud y Seguridad' => ['16', [
                ['Supervisor de Salud y Seguridad', true], ['Asistente de S&S', false],
                ['Doctor en Set', false], ['Doctor de Construcción', false], ['Ambulancia (EFD)', false],
            ]],
            'Sustentabilidad' => [null, [
                ['Jefa de Sustentabilidad', true], ['Coordinador de Sustentabilidad', false],
                ['Encargado de Logística Greener', false], ['Greener en Set', false],
                ['Greener Adicional en Set', false],
            ]],
            'Seguridad' => ['14', [
                ['Coordinador de Seguridad', true], ['Coordinador HG', false],
                ['Elemento de Seguridad HG', false],
            ]],
            'A.N.D.A.' => [null, [
                ['Delegada del A.N.D.A.', false],
            ]],
            'Otros' => [null, [
                ['Doble', false], ['Coach de Dialecto', false],
                ['Coordinadora de Intimidad', false], ['Intérprete de Señas', false],
                ['Foto Fija', false],
            ]],
        ];

        $deptCount = 0;
        $posCount = 0;

        foreach ($catalog as $deptName => [$channel, $positions]) {
            $dept = Department::firstOrCreate(
                ['name' => $deptName],
                ['radio_channel' => $channel, 'active' => true]
            );
            $deptCount++;

            foreach ($positions as [$posName, $isHod]) {
                Position::firstOrCreate(
                    ['name' => $posName, 'department_id' => $dept->id, 'production_id' => null],
                    ['is_hod' => $isHod, 'active' => true]
                );
                $posCount++;
            }
        }

        $this->command->info("Departments seeded: {$deptCount} | Positions processed: {$posCount}");
        $this->command->info('Total departments: '.Department::count().' | Total positions: '.Position::count());
    }
}
