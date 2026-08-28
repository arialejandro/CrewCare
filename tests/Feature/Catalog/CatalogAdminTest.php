<?php

namespace Tests\Feature\Catalog;

use Illuminate\Support\Facades\DB;
use Tests\QaTestCase;

/**
 * Vista de administración del CATÁLOGO organizacional fusionado (F3):
 * index gateado, toggle is_hod (varias por depto), alta/edición, baja por DESACTIVACIÓN (no borra).
 */
class CatalogAdminTest extends QaTestCase
{
    public function test_index_abre_para_super_admin(): void
    {
        $this->actingAsRole('super-admin');
        $this->get(route('catalogo.index'))->assertOk()->assertSee('Catálogo organizacional', false);
    }

    public function test_index_cerrado_sin_permiso(): void
    {
        $this->actingAsRole('crew');
        $this->get(route('catalogo.index'))->assertForbidden();
    }

    public function test_toggle_is_hod_hace_flip(): void
    {
        $this->actingAsRole('super-admin');
        $pos = DB::table('positions')->whereNull('production_id')->first();
        $before = (int) $pos->is_hod;

        $this->post(route('catalogo.position.hod', $pos->id))->assertRedirect();

        $this->assertSame($before ? 0 : 1, (int) DB::table('positions')->where('id', $pos->id)->value('is_hod'));
    }

    public function test_un_depto_puede_tener_varios_is_hod(): void
    {
        $this->actingAsRole('super-admin');
        $dept = DB::table('positions')->whereNull('production_id')
            ->select('department_id', DB::raw('COUNT(*) c'))->groupBy('department_id')
            ->having('c', '>=', 2)->first();
        $ids = DB::table('positions')->where('department_id', $dept->department_id)->limit(2)->pluck('id');

        foreach ($ids as $id) {
            DB::table('positions')->where('id', $id)->update(['is_hod' => 0]);
            $this->post(route('catalogo.position.hod', $id))->assertRedirect();   // → 1
        }

        $hods = DB::table('positions')->where('department_id', $dept->department_id)->where('is_hod', 1)->count();
        $this->assertGreaterThanOrEqual(2, $hods);   // sin unicidad
    }

    public function test_crea_puesto_y_departamento(): void
    {
        $this->actingAsRole('super-admin');
        $dept = DB::table('departments')->where('active', 1)->first();

        $this->post(route('catalogo.position.store'), [
            'department_id' => $dept->id, 'name' => 'Puesto QA Nuevo', 'name_en' => 'QA New',
            'rank' => 30, 'binding' => 'unit', 'grade' => 'coordinator', 'is_hod' => 1,
        ])->assertRedirect();
        $this->assertDatabaseHas('positions', ['name' => 'Puesto QA Nuevo', 'rank' => 30, 'binding' => 'unit', 'is_hod' => 1]);

        $this->post(route('catalogo.dept.store'), ['name' => 'Depto QA Nuevo', 'name_en' => 'QA Dept'])->assertRedirect();
        $this->assertDatabaseHas('departments', ['name' => 'Depto QA Nuevo']);
    }

    public function test_edita_puesto_es_en_rango_binding_grado(): void
    {
        $this->actingAsRole('super-admin');
        $pos = DB::table('positions')->whereNull('production_id')->first();

        $this->post(route('catalogo.position.update', $pos->id), [
            'department_id' => $pos->department_id, 'name' => $pos->name, 'name_en' => 'Edited EN',
            'rank' => 15, 'binding' => 'production', 'grade' => 'manager',
        ])->assertRedirect();

        $this->assertDatabaseHas('positions', ['id' => $pos->id, 'name_en' => 'Edited EN', 'rank' => 15, 'binding' => 'production']);
    }

    public function test_baja_por_desactivacion_no_borra(): void
    {
        $this->actingAsRole('super-admin');
        $pos = DB::table('positions')->whereNull('production_id')->where('active', 1)->first();

        $this->post(route('catalogo.position.deactivate', $pos->id))->assertRedirect();

        // sigue existiendo, solo inactivo (nunca borrado)
        $this->assertDatabaseHas('positions', ['id' => $pos->id, 'active' => 0]);
    }
}
