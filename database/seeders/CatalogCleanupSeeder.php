<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Catálogo — LIMPIEZA post-fusión (2026-08-30). Aditivo, idempotente. Encadenado tras
 * CatalogFusionSeeder + CatalogCollapseEmptyDeptsSeeder. DATOS, no esquema.
 *
 * ⚠ Se resuelve por NOMBRE / catalog_key, NO por id: los id auto-increment de positions
 * NO son reproducibles en un seed FRESCO (probado: 'Jefe de Utilería' es 116 en la BD
 * incremental pero 117 en una recién sembrada). Los nombres sí son estables (los define el
 * CSV de fusión). Verificado que cada nombre tocado resuelve a UN solo puesto en ambas BDs.
 *
 * Hace CUATRO cosas (todas reversibles, nada se borra salvo alias de puestos retirados):
 *   1) name_en: aplica los 84 nombres en inglés estándar de la propuesta del owner. FUERA:
 *      Delegada del A.N.D.A., Coordinador HG, Elemento de Seguridad HG y Jefa de Equipo →
 *      español; y las 4 filas de 'Equipo' (traían notas entre paréntesis, no nombres reales).
 *   2) rank: corrige rangos que el regex "gerente/jefe" de la fusión sobre-atrapó. Asistentes
 *      → 50; diseñadores mid → 20. RECALCULA sort_order = dept.sort_order*100 + rank. PaeOrgChart
 *      usa position_id (no rank), así que re-rangear es seguro. (is_hod/hod_capable NO se tocan.)
 *   3) Equipo: DESACTIVA el EQUIPO FÍSICO (Móvil Alpha, Planta Set, Cabeza Remota) = filas del
 *      depto 'Equipo' sin catalog_key que no sean 'Asistente/Luces' (198, GATEADO) — Dolly (200)
 *      tiene catalog_key y es puesto real. De paso limpia el mojibake heredado del nombre del Móvil.
 *   4) Duplicados: unifica 3 pares. Sobrevive uno, el otro se DESACTIVA, sus alias pasan al
 *      sobreviviente (para que el import resuelva ambos nombres), sus asignaciones se mueven (hoy 0):
 *        Coordinador Ejecutivo ← Coord. Ejecutivo
 *        Jefe de Utilería      ← Jefe de Props / Property Master (semilla, por catalog_key)
 *        Coordinador MU&H      ← Coordinador de M&P (semilla, por catalog_key)
 */
class CatalogCleanupSeeder extends Seeder
{
    /** name_es => name_en (84 puestos de la propuesta). */
    private const NAME_EN = [
        'Crew de Cocina' => 'Kitchen Crew',
        'Director de Arte en Set' => 'On-Set Art Director',
        'Diseñador de Sets' => 'Set Designer',
        'Diseñador Gráfico' => 'Graphic Designer',
        'Asst. de Diseñador Gráfico' => 'Assistant Graphic Designer',
        'Artista Conceptual' => 'Concept Artist',
        'Asistente de Coord. de Arte' => 'Assistant Art Coordinator',
        'Asistente de Oficina de Arte' => 'Art Office Assistant',
        'Asst. de Diseño de Producción' => 'Assistant Production Designer',
        'Asistente de DoP' => 'DoP Assistant',
        '2ndo AC' => 'Second AC',
        'Cámara PA' => 'Camera PA',
        'Asistente de Casting' => 'Casting Assistant',
        'Asociado de Casting' => 'Casting Associate',
        'Head Welder' => 'Head Welder',
        'Jefe de Carpintería' => 'Carpentry Foreman',
        'Jefe de Pintura Escénica' => 'Scenic Paint Foreman',
        'Apoyo Eventual de Construcción' => 'Additional Construction Labor',
        'Asistente de Pintor Escénico' => 'Scenic Painter Assistant',
        'Asst. Coord. Construcción' => 'Assistant Construction Coordinator',
        'Compras de Construcción' => 'Construction Buyer',
        'Gang Boss Welder' => 'Welding Gang Boss',
        'Herrero' => 'Blacksmith',
        'Welder' => 'Welder',
        'Contador Fiscal' => 'Tax Accountant',
        'Auxiliar de Contabilidad' => 'Accounting Clerk',
        'Decorador en Set' => 'On-Set Set Dresser',
        'Asistente Decorador en Set' => 'Assistant On-Set Dresser',
        'Apoyo Decorador en Set' => 'On-Set Dressing Support',
        'Coordinador de Decoración' => 'Set Dec Coordinator',
        'Asistente de Decoración' => 'Set Dec Assistant',
        'Asst. Coord. de Decoración' => 'Assistant Set Dec Coordinator',
        'Compras de Decoración' => 'Set Dec Buyer',
        'Asistente de Director' => 'Assistant Director',
        'Asst. Continuista' => 'Assistant Script Supervisor',
        'Efectos Especiales Oficina' => 'SFX Office Coordinator',
        'Supervisor de VFX en Set' => 'On-Set VFX Supervisor',
        'Supervisor de Eléctricos' => 'Electrical Supervisor',
        'Storyboardista' => 'Storyboard Artist',
        'Director de Casting de Extras' => 'Background Casting Director',
        'Grip Asst.' => 'Grip Assistant',
        'Gerente Asst. de Locaciones' => 'Assistant Location Manager',
        'Gerente de Soporte de Locaciones' => 'Locations Support Manager',
        'Asst. Coordinador de Locaciones' => 'Assistant Locations Coordinator',
        'Apoyo de Limpieza' => 'Cleanup Support',
        'P.A. de Locaciones' => 'Locations PA',
        'Personal de Loc.' => 'Locations Support',
        'Diseñador de M&P' => 'HMU Designer',
        'Coordinador MU&H' => 'HMU Coordinator',
        'Apoyo M&P' => 'HMU Support',
        'Supervisora de Música' => 'Music Supervisor',
        'Asst. de Coordinador de Viajes' => 'Assistant Travel Coordinator',
        'Recepción de Oficina' => 'Office Receptionist',
        'Coordinadora de Intimidad' => 'Intimacy Coordinator',
        'Coach de Dialecto' => 'Dialect Coach',
        'Intérprete de Señas' => 'Sign Language Interpreter',
        'Asst. Gerente de Unidad' => 'Assistant Unit Manager',
        'Coordinador Ejecutivo' => 'Executive Coordinator',
        'Coord. Ejecutivo' => 'Executive Coordinator',
        'Ejecutivo Creativo' => 'Creative Executive',
        'Representante Legal' => 'Legal Representative',
        'Electric (rigging)' => 'Rigging Electric',
        'Grip (rigging)' => 'Rigging Grip',
        'Doctor de Construcción' => 'Construction Set Medic',
        'Greener Adicional en Set' => 'Additional On-Set Greensperson',
        'Contador de Transporte' => 'Transportation Accountant',
        'Asistente de Coord. de Transporte' => 'Assistant Transportation Coordinator',
        'Asistente de Transportación' => 'Transportation Assistant',
        'Trainee de Transportación' => 'Transportation Trainee',
        'Jefe de Utilería' => 'Property Master',
        'Coordinador de Utilería' => 'Props Coordinator',
        'Apoyo Eventual de Utilería' => 'Additional Props Labor',
        'Asistente de Utilería' => 'Props Assistant',
        'Asistente de Utilería en Set' => 'On-Set Props Assistant',
        'Bodeguero Props' => 'Props Warehouse Manager',
        'Compras de Utilería en Set' => 'On-Set Props Buyer',
        'Utilería en Set' => 'On-Set Props',
        'Asst. Diseñador' => 'Assistant Costume Designer',
        'Diseñador Gráfico de Vestuario' => 'Costume Graphic Designer',
        'Jefa de Taller de Costura' => 'Tailor Shop Supervisor',
        'Asst. Coordinador de Vestuario' => 'Assistant Costume Coordinator',
        'Asst. de Compras de Vestuario' => 'Assistant Costume Buyer',
        'Asistente de Video' => 'Video Assist Assistant',
        'Operador de VTR' => 'Video Assist Operator',
        // (2026-08-30, ajuste 2) Asistente/Luces SÍ es puesto (la persona encargada de las luces),
        // no unidad → se queda activo (cleanEquipo lo respeta) y recibe su name_en.
        'Asistente/Luces' => 'Lighting Assistant',
    ];

    /** Asistentes atrapados en rango 10 → 50. */
    private const RANK_50 = ['Asst. de Diseñador Gráfico', 'Gerente Asst. de Locaciones', 'Asst. Gerente de Unidad', 'Asst. Diseñador'];
    /**
     * Diseñadores mid + sub-jefaturas atrapados en rango 10 → 20. Director de Arte en Set va bajo
     * el Diseñador de Producción y Gerente de Soporte de Locaciones bajo el Gerente de Locaciones:
     * con rango 10 se imprimían al mismo nivel que su jefe (ajuste 2, 2026-08-30).
     */
    private const RANK_20 = ['Diseñador de Sets', 'Diseñador Gráfico', 'Diseñador Gráfico de Vestuario', 'Director de Arte en Set', 'Gerente de Soporte de Locaciones'];

    /** Se desactivan por nombre (no se borran). */
    private const DEACTIVATE = ['Jefa de Equipo'];

    /**
     * Duplicados: nombre del sobreviviente => nombre del retirado. Todo por NOMBRE:
     * en un seed fresco el catalog_key de las filas solo-vivo queda REVUELTO (la fusión
     * actualiza por id_vivo y cae en el puesto equivocado), pero el nombre siempre es fiel.
     */
    private const DUPES = [
        'Coordinador Ejecutivo' => 'Coord. Ejecutivo',
        'Jefe de Utilería'      => 'Jefe de Props',        // trae name_en Property Master + catalog_key props.prop_master
        'Coordinador MU&H'      => 'Coordinador de M&P',   // trae catalog_key hmu.coordinator
    ];

    /** Alias extra del sobreviviente (nombre del sobreviviente + inglés), en minúsculas. */
    private const EXTRA_ALIASES = [
        'Coordinador Ejecutivo' => [['es', 'coord. ejecutivo'], ['es', 'coordinador ejecutivo'], ['en', 'executive coordinator']],
        'Jefe de Utilería'      => [['es', 'jefe de utileria'], ['es', 'jefe de utilería'], ['en', 'property master']],
        'Coordinador MU&H'      => [['es', 'coordinador mu&h'], ['es', 'coordinador muh'], ['en', 'hmu coordinator']],
    ];

    public function run(): void
    {
        $this->applyNameEn();
        $this->fixRanks(self::RANK_50, 50);
        $this->fixRanks(self::RANK_20, 20);
        $this->cleanEquipo();
        $this->deactivateByName();
        $this->unifyDuplicates();
    }

    private function deactivateByName(): void
    {
        $n = DB::table('positions')->whereIn('name', self::DEACTIVATE)->update(['active' => 0]);
        if ($this->command) { $this->command->info("CatalogCleanup · desactivados por nombre: [" . implode(', ', self::DEACTIVATE) . "] ({$n})."); }
    }

    private function applyNameEn(): void
    {
        $n = 0;
        foreach (self::NAME_EN as $nameEs => $en) {
            $n += DB::table('positions')->where('name', $nameEs)->update(['name_en' => $en]);
        }
        if ($this->command) { $this->command->info("CatalogCleanup · name_en: " . count(self::NAME_EN) . " puestos (filas cambiadas: {$n})."); }
    }

    /** Corrige rank y RECALCULA sort_order = dept.sort_order*100 + rank (join, por nombre). */
    private function fixRanks(array $names, int $rank): void
    {
        DB::table('positions')
            ->join('departments', 'positions.department_id', '=', 'departments.id')
            ->whereIn('positions.name', $names)
            ->update([
                'positions.rank'       => $rank,
                'positions.sort_order' => DB::raw('departments.sort_order * 100 + ' . $rank),
            ]);
        if ($this->command) { $this->command->info("CatalogCleanup · rank→{$rank}: [" . implode(', ', $names) . "]."); }
    }

    /**
     * Desactiva el EQUIPO FÍSICO del depto 'Equipo' = TODO salvo los 2 que se quedan: Dolly
     * (puesto real) y Asistente/Luces (198, GATEADO). Por NOMBRE, no por catalog_key (que en
     * un seed fresco queda revuelto). También limpia el mojibake heredado del nombre del Móvil.
     */
    private function cleanEquipo(): void
    {
        $equipoId = DB::table('departments')->where('name', 'Equipo')->value('id');
        if (! $equipoId) { return; }

        // Normaliza el mojibake del Móvil (cualquier variante que empiece con 'M' y no sea la limpia).
        DB::table('positions')->where('department_id', $equipoId)
            ->where('name', 'like', 'M%')->where('name', '<>', 'Móvil Alpha')
            ->update(['name' => 'Móvil Alpha']);

        // Desactiva las UNIDADES: todo en Equipo menos los 2 keepers.
        $n = DB::table('positions')->where('department_id', $equipoId)
            ->whereNotIn('name', ['Dolly', 'Asistente/Luces'])
            ->update(['active' => 0]);
        if ($this->command) { $this->command->info("CatalogCleanup · Equipo (unidades) desactivadas: {$n}."); }
    }

    private function unifyDuplicates(): void
    {
        foreach (self::DUPES as $survivorName => $retiredName) {
            $sv = DB::table('positions')->where('name', $survivorName)->first();
            $rt = DB::table('positions')->where('name', $retiredName)->first();
            if (! $sv || ! $rt || (int) $rt->id === (int) $sv->id) { continue; }

            $survivor = (int) $sv->id;
            $retired  = (int) $rt->id;

            // Mueve asignaciones del retirado al sobreviviente (hoy 0; idempotente).
            $moved = DB::table('production_user')->where('position_id', $retired)->update(['position_id' => $survivor]);

            // Copia los alias del retirado al sobreviviente.
            foreach (DB::table('catalog_aliases')->where('entity_type', 'position')->where('entity_id', $retired)->get() as $a) {
                $this->ensureAlias($survivor, $a->lang, $a->alias);
            }
            // El propio nombre del retirado como alias es del sobreviviente.
            $this->ensureAlias($survivor, 'es', Str::lower(Str::ascii($rt->name)));
            // Alias extra explícitos (nombre del sobreviviente + inglés).
            foreach (self::EXTRA_ALIASES[$survivorName] ?? [] as $pair) {
                $this->ensureAlias($survivor, $pair[0], $pair[1]);
            }

            // Retira los alias del retirado (que no resuelvan a un inactivo) y desactívalo.
            DB::table('catalog_aliases')->where('entity_type', 'position')->where('entity_id', $retired)->delete();
            DB::table('positions')->where('id', $retired)->update(['active' => 0]);

            if ($this->command) { $this->command->info("CatalogCleanup · unifica '{$rt->name}'→'{$survivorName}' (asignaciones movidas: {$moved})."); }
        }
    }

    private function ensureAlias(int $posId, string $lang, string $alias): void
    {
        $alias = trim($alias);
        if ($alias === '') { return; }
        $exists = DB::table('catalog_aliases')
            ->where('entity_type', 'position')->where('entity_id', $posId)
            ->where('lang', $lang)->where('alias', $alias)->exists();
        if (! $exists) {
            DB::table('catalog_aliases')->insert([
                'entity_type' => 'position', 'entity_id' => $posId, 'lang' => $lang, 'alias' => $alias,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}
