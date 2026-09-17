<?php

namespace Tests\Feature\Nav;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\QaTestCase;

/**
 * MENÚ CONTEXTUAL — la sección de crew muestra el DEPARTAMENTO del HOD ("Arte") en vez de un
 * genérico, para quien tiene un solo lente. Quien ve todos los departamentos mantiene el genérico.
 * El puesto/depto NO otorga accesos: solo personaliza el título.
 */
class ContextualSidebarTest extends QaTestCase
{
    private $prodId;
    private $deptA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prodId = DB::table('productions')->min('id');
        $this->deptA  = DB::table('departments')->orderBy('id')->value('id');
    }

    private function attachDept(User $u, $deptId): void
    {
        DB::table('production_user')->updateOrInsert(
            ['production_id' => $this->prodId, 'user_id' => $u->id],
            ['department_id' => $deptId, 'role' => $u->getRoleNames()->first(), 'is_lead' => 1, 'created_at' => now(), 'updated_at' => now()]
        );
    }

    public function test_single_department_hod_gets_its_department_name(): void
    {
        $hod = $this->makeUser('hod');
        $this->attachDept($hod, $this->deptA);

        $expected = DB::table('departments')->where('id', $this->deptA)->value('name');
        $this->assertSame($expected, $hod->fresh()->soleDepartmentName());
    }

    public function test_all_departments_viewer_keeps_generic(): void
    {
        // line-producer tiene crew.view.all-departments → ve todo → etiqueta genérica.
        $this->assertNull($this->makeUser('line-producer')->soleDepartmentName());
    }

    public function test_hod_without_department_keeps_generic(): void
    {
        $this->assertNull($this->makeUser('hod')->soleDepartmentName());
    }

    public function test_dashboard_renders_with_contextual_sidebar(): void
    {
        $hod = $this->makeUser('hod');
        $this->attachDept($hod, $this->deptA);

        // El sidebar (con soleDepartmentName) renderiza sin error en el dashboard (siguiendo el redirect de '/').
        $this->actingAs($hod)->followingRedirects()->get('/')->assertOk();
    }
}
