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
            'Asistentes de Dirección' => ['1', [
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
            // (2026-08-23) RENOMBRADO 'Producción Ejecutiva' → 'Productores' (canónico del call sheet;
            // el owner: "es el mismo bloque con distinto nombre, aparece uno o el otro"). El registro
            // y sus puestos se conservan; solo cambia la etiqueta. SignaturePositions::SIGNER_DEPARTMENTS
            // se actualizó en paralelo (lo referencia por nombre).
            'Productores' => ['3', [
                ['Productor Ejecutivo', true], ['Coord. Ejecutivo', false],
                ['Jefa de Equipo', false], ['Ejecutivo Creativo', false],
                ['Asistente Ejecutivo', false], ['Coordinador Ejecutivo', false],
                // Firmante de contratos (Firmas Prod.): quien obliga a la empresa. Es un USUARIO con
                // perfil (firma autenticado). Renómbralo/duplícalo si tu productora lo llama distinto.
                ['Representante Legal', false],
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
            // (2026-08-23) RENOMBRADO 'Video/VTR' → 'Video Assist, DIT y Data' (canónico del call sheet).
            'Video Assist, DIT y Data' => ['7', [
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
                ['Apoyo Eventual de Utilería', false],
                // Backfill 2026-06-24: legacy "Coordinacion Utileria" -> the dept had no
                // coordinator position in the catalog.
                ['Coordinador de Utilería', false],
                // 'Animalero' se MOVIÓ a su propio depto (ver más abajo · destilación 2026-07-18).
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
            'Efectos Visuales' => [null, [
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
            'Alimentación' => ['13', [
                ['Coordinador de Craft', true], ['Cafetero', false],
                ['Asst. Cafetero', false], ['Crew de Cocina', false],
            ]],
            'Salud y Seguridad' => ['16', [
                ['Supervisor de Salud y Seguridad', true], ['Asistente de S&S', false],
                ['Doctor en Set', false], ['Doctor de Construcción', false],
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
            'ANDA' => [null, [
                ['Delegada del A.N.D.A.', false],
            ]],
            // (2026-07-18) Destilación EFD: son TRES cosas DISTINTAS, no "Ambulancia (EFD)" bajo S&S.
            //   · Equipo = casa de renta de equipo/luces (lo que el call sheet llama "EFD").
            //   · Ambulancia = su PROPIO depto (servicio médico), NO Salud y Seguridad ni EFD.
            //   · Animalero = su propio depto (antes colgaba de Utilería).
            // El owner-apply 2026-07-18-departments-animalero-equipo.sql hace lo mismo en BD ya sembrada.
            'Animales' => [null, [
                ['Animalero', false],
            ]],
            'Equipo' => [null, [
                ['Móvil Alpha', false], ['Asistente/Luces', false], ['Planta Set', false],
                ['Dolly', false], ['Cabeza Remota', false],
            ]],
            'Ambulancia' => [null, [
                ['Ambulancia', false],
            ]],
            'Otros' => [null, [
                ['Doble', false], ['Coach de Dialecto', false],
                ['Coordinadora de Intimidad', false], ['Intérprete de Señas', false],
                ['Foto Fija', false],
            ]],
            // (2026-08-23) LOS SEIS DEPTOS FALTANTES del call sheet real (bloque owner PARTE B). Nacen
            // SIN puestos: el owner-apply hace lo mismo en BD ya sembrada. NO se mueven puestos que hoy
            // viven bajo otro depto (Continuista→Dirección, Foto Fija→Otros, Coordinador de Craft→Catering,
            // Director de Casting de Extras→Extras): eso alteraría la agrupación de crew ya asignado y va
            // como decisión aparte. El sort_order y name_en se fijan en el paso canónico de abajo.
            'Continuidad'       => [null, []],
            'Casting de Extras' => [null, []],
            'Foto Fija'         => [null, []],
            'Craft Service'     => [null, []],
            'Servicios Médicos' => [null, []],
            'Legal y Clearance' => [null, []],
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

        // -----------------------------------------------------------------------------------------
        // ORDEN CANÓNICO DEL CALL SHEET + name_en (bloque owner PARTE B, 2026-08-23).
        // Una fila por depto: [sort_order, name_en]. El sort_order es la POSICIÓN×10 (deja hueco para
        // insertar). Los name_en son la traducción del back bilingüe. Los nombres en ESPAÑOL se
        // conservan tal cual (solo se renombraron 'Productores' y 'Video Assist, DIT y Data', arriba):
        // Nombres en ESPAÑOL alineados a la lista canónica del owner (2026-08-23, 2ª pasada): se
        // renombraron VFX→Efectos Visuales, Animalero→Animales, Catering→Alimentación, A.N.D.A.→ANDA,
        // Asistente→Asistentes de Dirección (arriba, en las keys del catálogo). 'Crew Adicional' NO es
        // depto (bucket sintético del back).
        $canonical = [
            'Productores'              => [10,  'Producers'],
            'Dirección'                => [20,  'Direction'],
            'Escritores'               => [30,  'Writers'],
            'Producción'               => [40,  'Production'],
            'Oficina de Producción'    => [50,  'Production Office'],
            'Asistentes de Dirección'  => [60,  'Assistant Directors'],
            'Continuidad'              => [70,  'Continuity'],
            'Casting'                  => [80,  'Casting'],
            'Casting de Extras'        => [90,  'Extras Casting'],
            'Cámara'                   => [100, 'Camera'],
            'Video Assist, DIT y Data' => [110, 'Video Assist, DIT & Data'],
            'Sonido'                   => [120, 'Sound'],
            'Eléctricos'               => [130, 'Electric'],
            'Grips'                    => [140, 'Grip'],
            'Rigging'                  => [150, 'Rigging'],
            'Foto Fija'                => [160, 'Still Photography'],
            'Arte'                     => [170, 'Art Department'],
            'Decoración'               => [180, 'Set Decoration'],
            'Utilería'                 => [190, 'Property'],
            'Construcción'             => [200, 'Construction'],
            'Vestuario'                => [210, 'Wardrobe'],
            'Maquillaje y Peinados'    => [220, 'Make-Up & Hair'],
            'Efectos Especiales'       => [230, 'Special Effects'],
            'Efectos Visuales'         => [240, 'Visual Effects'],
            'Stunts'                   => [250, 'Stunts'],
            'Picture Cars'             => [260, 'Picture Cars'],
            'Animales'                 => [270, 'Animals'],
            'Extras'                   => [280, 'Background'],
            'Locaciones'               => [290, 'Locations'],
            'Transportación'           => [300, 'Transportation'],
            'Alimentación'             => [310, 'Catering'],
            'Craft Service'            => [320, 'Craft Service'],
            'Equipo'                   => [330, 'Equipment'],
            'Ambulancia'               => [340, 'Ambulance'],
            'Seguridad'                => [350, 'Security'],
            'Salud y Seguridad'        => [360, 'Health & Safety'],
            'Servicios Médicos'        => [370, 'Medic'],
            'Sustentabilidad'          => [380, 'Sustainability'],
            'Contabilidad'             => [390, 'Accounting'],
            'Legal y Clearance'        => [400, 'Legal & Clearance'],
            'ANDA'                     => [410, 'ANDA'],
            'Post Producción'          => [420, 'Post Production'],
            'Música'                   => [430, 'Music'],
            'Otros'                    => [440, 'Other'],
        ];
        foreach ($canonical as $name => [$sort, $nameEn]) {
            Department::where('name', $name)->update(['sort_order' => $sort, 'name_en' => $nameEn]);
        }

        $this->command->info("Departments seeded: {$deptCount} | Positions processed: {$posCount}");
        $this->command->info('Total departments: '.Department::count().' | Total positions: '.Position::count());
    }
}
