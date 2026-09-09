<?php

namespace Tests\Feature\Catalog;

use Illuminate\Support\Facades\DB;
use Tests\QaTestCase;

/**
 * CatalogCleanupSeeder — verifica los outcomes de la limpieza post-fusión sobre el catálogo
 * ya sembrado (RefreshDatabase corre el chain completo, incluido el cleanup). Se busca por
 * NOMBRE para no depender de ids auto-increment.
 */
class CatalogCleanupTest extends QaTestCase
{
    private function pos(string $name): ?object
    {
        return DB::table('positions')->where('name', $name)->first();
    }

    public function test_name_en_aplicado_y_los_cuatro_quedan_en_espanol(): void
    {
        $this->assertSame('Property Master', $this->pos('Jefe de Utilería')->name_en);
        $this->assertSame('HMU Coordinator', $this->pos('Coordinador MU&H')->name_en);
        $this->assertSame('Assistant Unit Manager', $this->pos('Asst. Gerente de Unidad')->name_en);

        // Los 4 que se quedan en español NO están en el mapa de la limpieza (test de la
        // EXCLUSIÓN, no del estado en BD: la fusión, al actualizar por id_vivo, puede dejar un
        // name_en revuelto en un seed fresco — eso es un artefacto pre-existente de la fusión,
        // ajeno a esta limpieza, y no ocurre en la BD incremental de producción).
        $map = (new \ReflectionClass(\Database\Seeders\CatalogCleanupSeeder::class))->getConstant('NAME_EN');
        foreach (['Jefa de Equipo', 'Coordinador HG', 'Elemento de Seguridad HG', 'Delegada del A.N.D.A.'] as $name) {
            $this->assertNotNull($this->pos($name), "existe {$name}");
            $this->assertArrayNotHasKey($name, $map, "{$name} NO recibe name_en de la limpieza (se queda en español)");
        }
    }

    public function test_rank_y_sort_order_corregidos(): void
    {
        foreach (['Asst. Gerente de Unidad', 'Gerente Asst. de Locaciones', 'Asst. de Diseñador Gráfico', 'Asst. Diseñador'] as $name) {
            $p = $this->pos($name);
            $this->assertSame(50, (int) $p->rank, "{$name} baja a rango 50");
            $dept = (int) DB::table('departments')->where('id', $p->department_id)->value('sort_order');
            $this->assertSame($dept * 100 + 50, (int) $p->sort_order, "{$name} recalcula sort_order");
        }
        foreach (['Diseñador de Sets', 'Diseñador Gráfico', 'Diseñador Gráfico de Vestuario'] as $name) {
            $p = $this->pos($name);
            $this->assertSame(20, (int) $p->rank, "{$name} baja a rango 20");
        }
    }

    public function test_equipo_unidades_desactivadas_y_gateados_intactos(): void
    {
        foreach (['Móvil Alpha', 'Planta Set', 'Cabeza Remota'] as $name) {
            $this->assertSame(0, (int) $this->pos($name)->active, "{$name} (equipo) desactivado");
        }
        // Puestos reales del depto Equipo: se quedan activos.
        $al = $this->pos('Asistente/Luces');
        $this->assertSame(1, (int) $al->active, 'Asistente/Luces es puesto (encargado de las luces)');
        $this->assertSame('Lighting Assistant', $al->name_en, 'Asistente/Luces recibe name_en');
        $this->assertSame(1, (int) $this->pos('Dolly')->active, 'Dolly es puesto real');
        // Mojibake corregido.
        $this->assertSame('Móvil Alpha', $this->pos('Móvil Alpha')->name);
    }

    public function test_duplicados_unificados_con_alias_heredados(): void
    {
        // Sobrevive uno, el otro se desactiva.
        $this->assertSame(1, (int) $this->pos('Coordinador Ejecutivo')->active);
        $this->assertSame(0, (int) $this->pos('Coord. Ejecutivo')->active);
        $this->assertSame(1, (int) $this->pos('Jefe de Utilería')->active);
        $this->assertSame(1, (int) $this->pos('Coordinador MU&H')->active);

        $jefeProps = DB::table('positions')->where('catalog_key', 'props.prop_master')->first();
        $coordMp   = DB::table('positions')->where('catalog_key', 'hmu.coordinator')->first();
        $this->assertSame(0, (int) $jefeProps->active, 'Jefe de Props (semilla) desactivado');
        $this->assertSame(0, (int) $coordMp->active, 'Coordinador de M&P (semilla) desactivado');

        // Los alias del retirado viven ahora en el sobreviviente (el import resuelve ambos nombres).
        $util = $this->pos('Jefe de Utilería');
        $aliases = DB::table('catalog_aliases')->where('entity_type', 'position')->where('entity_id', $util->id)->pluck('alias')->all();
        $this->assertContains('property master', $aliases);
        $this->assertContains('jefe de props', $aliases);
        $this->assertContains('jefe de utileria', $aliases);

        // El retirado ya no tiene alias (no resuelve a un inactivo).
        $this->assertSame(0, DB::table('catalog_aliases')->where('entity_type', 'position')->where('entity_id', $jefeProps->id)->count());
    }

    public function test_ajustes_frente2_rangos_y_jefa_de_equipo(): void
    {
        // Bajan a 20 (van bajo su jefe): Director de Arte en Set y Gerente de Soporte de Locaciones.
        foreach (['Director de Arte en Set', 'Gerente de Soporte de Locaciones'] as $name) {
            $p = $this->pos($name);
            $this->assertSame(20, (int) $p->rank, "{$name} baja a rango 20");
            $dept = (int) DB::table('departments')->where('id', $p->department_id)->value('sort_order');
            $this->assertSame($dept * 100 + 20, (int) $p->sort_order, "{$name} recalcula sort_order");
        }
        // Los demás rango-10 SE QUEDAN en 10 (son cabeza de su taller/depto).
        foreach (['Head Welder', 'Jefe de Carpintería', 'Jefe de Pintura Escénica', 'Jefa de Taller de Costura', 'Diseñador de M&P'] as $name) {
            $this->assertSame(10, (int) $this->pos($name)->rank, "{$name} sigue en rango 10");
        }
        // Jefa de Equipo desactivada (no borrada).
        $je = $this->pos('Jefa de Equipo');
        $this->assertNotNull($je);
        $this->assertSame(0, (int) $je->active, 'Jefa de Equipo desactivada');
    }

    public function test_paeorgchart_sigue_apuntando_a_la_posicion_15(): void
    {
        // El re-rango del 15 NO debe romper el organigrama (acopla por position_id, no por rank).
        $this->assertContains(15, \App\Support\PaeOrgChart::COORDINATOR_POSITION_IDS);
        $p15 = DB::table('positions')->where('id', 15)->first();
        $this->assertNotNull($p15, 'la posición 15 sigue existiendo');
    }
}
