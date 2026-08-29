<?php

namespace Tests\Feature\Catalog;

use Database\Seeders\CatalogCollapseEmptyDeptsSeeder;
use Illuminate\Support\Facades\DB;
use Tests\QaTestCase;

/**
 * Catálogo — colapso de deptos vacíos duplicados en su padre (2026-08-28). Idempotente.
 */
class CatalogCollapseTest extends QaTestCase
{
    private function seedCollapse(): void
    {
        $this->seed(CatalogCollapseEmptyDeptsSeeder::class); // idempotente: seguro re-correr
    }

    public function test_colapsados_desactivados_y_solos_activos(): void
    {
        $this->seedCollapse();

        foreach (['Casting de Extras', 'Craft Service', 'Continuidad', 'Servicios Médicos'] as $name) {
            $this->assertSame(0, (int) DB::table('departments')->where('name', $name)->value('active'), "$name debe quedar desactivado");
        }
        foreach (['Legal y Clearance', 'Foto Fija'] as $name) {
            $this->assertSame(1, (int) DB::table('departments')->where('name', $name)->value('active'), "$name se queda solo/activo");
        }
    }

    public function test_alias_del_hijo_resuelve_al_padre(): void
    {
        $this->seedCollapse();

        $ads = DB::table('departments')->where('name', 'Asistentes de Dirección')->value('id');
        $this->assertNotNull($ads);
        // "continuidad" (nombre del hijo) ahora es alias del padre.
        $this->assertTrue(
            DB::table('catalog_aliases')->where('entity_type', 'department')->where('entity_id', $ads)->where('alias', 'continuidad')->exists(),
            'el import debe resolver "continuidad" hacia Asistentes de Dirección'
        );
        // "craft" sigue resolviendo hacia el padre Catering.
        $catering = DB::table('departments')->where('name', 'Alimentación')->value('id');
        $this->assertTrue(
            DB::table('catalog_aliases')->where('entity_type', 'department')->where('entity_id', $catering)->where('alias', 'craft')->exists()
        );
    }

    public function test_idempotente(): void
    {
        $this->seedCollapse();
        $this->seedCollapse(); // 2ª vez no truena ni duplica
        $ads = DB::table('departments')->where('name', 'Asistentes de Dirección')->value('id');
        // 'continuidad' existe UNA vez por idioma (es+en); re-correr no la duplica.
        $this->assertSame(1, DB::table('catalog_aliases')->where('entity_type', 'department')->where('entity_id', $ads)->where('alias', 'continuidad')->where('lang', 'es')->count());
        $this->assertSame(2, DB::table('catalog_aliases')->where('entity_type', 'department')->where('entity_id', $ads)->where('alias', 'continuidad')->count());
    }
}
