<?php

namespace Tests\Feature\Catalog;

use Illuminate\Support\Facades\DB;
use Tests\QaTestCase;

/**
 * F4 · creación RÁPIDA de puesto desde el alta (Opción B) con alcance por departamento
 * (permiso `catalogs.manage.own-department`), y que el alta pinte el typeahead.
 */
class CatalogQuickCreateTest extends QaTestCase
{
    private function anyProductionId(): int
    {
        return (int) DB::table('productions')->value('id');
    }

    public function test_hod_crea_en_su_depto_y_es_rechazado_en_otro(): void
    {
        $hod   = $this->makeUser('hod');
        $own   = DB::table('departments')->where('active', 1)->first();
        $other = DB::table('departments')->where('active', 1)->where('id', '!=', $own->id)->first();
        DB::table('production_user')->insert([
            'production_id' => $this->anyProductionId(), 'user_id' => $hod->id,
            'department_id' => $own->id, 'role' => 'hod', 'is_lead' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($hod);

        // en SU departamento → crea
        $this->postJson(route('catalogo.position.quick'), ['name' => 'Puesto HOD QA', 'department_id' => $own->id])
            ->assertOk()->assertJsonStructure(['id', 'name', 'department_id']);
        $this->assertDatabaseHas('positions', ['name' => 'Puesto HOD QA', 'department_id' => $own->id, 'production_id' => null]);

        // en OTRO departamento → 403 y no se crea
        $this->postJson(route('catalogo.position.quick'), ['name' => 'Puesto Ajeno QA', 'department_id' => $other->id])
            ->assertForbidden();
        $this->assertDatabaseMissing('positions', ['name' => 'Puesto Ajeno QA']);
    }

    public function test_consolidador_crea_en_cualquier_depto(): void
    {
        $this->actingAsRole('coordinator');   // crew.view.all-departments + catalogs.manage.own-department
        $dept = DB::table('departments')->where('active', 1)->first();

        $this->postJson(route('catalogo.position.quick'), ['name' => 'Puesto Consolidador QA', 'department_id' => $dept->id])
            ->assertOk();
        $this->assertDatabaseHas('positions', ['name' => 'Puesto Consolidador QA']);
    }

    public function test_sin_permiso_no_puede_crear(): void
    {
        $this->actingAsRole('crew');
        $dept = DB::table('departments')->where('active', 1)->first();

        $this->postJson(route('catalogo.position.quick'), ['name' => 'X QA', 'department_id' => $dept->id])
            ->assertForbidden();
    }

    public function test_el_alta_pinta_el_typeahead(): void
    {
        $this->actingAsRole('coordinator');

        $this->get(route('adduser'))->assertOk()
            ->assertSee('js-typeahead', false)
            ->assertSee('data-ta-extra', false)
            ->assertSee('data-ta-create', false);   // el consolidador puede crear → el flag aparece
    }
}
