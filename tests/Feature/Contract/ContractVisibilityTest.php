<?php

namespace Tests\Feature\Contract;

use App\Models\ContractEnvelope;
use App\Models\Department;
use App\Models\Payee;
use App\Models\PayeeContract;
use App\Models\User;
use App\Support\ContractVisibility;
use Illuminate\Support\Facades\DB;
use Tests\QaTestCase;

/**
 * VISIBILIDAD DE CONTRATOS por departamento (consulta de solo lectura). Cada depto ve lo suyo;
 * Producción / Oficina de Producción / Contabilidad ven todo; super-admin ve todo.
 */
class ContractVisibilityTest extends QaTestCase
{
    private $prodId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prodId = DB::table('productions')->min('id');
    }

    private function deptId(string $name): int
    {
        return (int) Department::where('name', $name)->value('id');
    }

    private function userInDept(string $role, string $deptName): User
    {
        $u = $this->makeUser($role);
        DB::table('production_user')->updateOrInsert(
            ['production_id' => $this->prodId, 'user_id' => $u->id],
            ['department_id' => $this->deptId($deptName), 'role' => 'crew', 'is_lead' => 0, 'created_at' => now(), 'updated_at' => now()]
        );
        return $u;
    }

    private function envInDept(string $deptName): ContractEnvelope
    {
        $payee = Payee::create(['legal_nature' => 'fisica', 'name' => 'Payee ' . $deptName]);
        $contract = $payee->contracts()->create([
            'concept' => PayeeContract::CONCEPT_CREW, 'is_active' => 1, 'production_id' => $this->prodId,
            'department_id' => $this->deptId($deptName), 'crew_activity' => 'X',
        ]);
        return ContractEnvelope::create([
            'payee_contract_id' => $contract->id, 'production_id' => $this->prodId,
            'status' => ContractEnvelope::STATUS_SENT, 'documents' => [],
        ]);
    }

    public function test_accounting_sees_all_and_gets_panel(): void
    {
        $arte = $this->envInDept('Arte');
        $conta = $this->envInDept('Contabilidad');

        $acct = $this->userInDept('crew', 'Contabilidad');
        $this->assertTrue(ContractVisibility::seesAll($acct), 'contabilidad ve todo');
        $this->assertTrue($acct->canSeePanel(), 'contabilidad entra al panel sin otro permiso');

        $ids = ContractVisibility::scopeVisible(ContractEnvelope::query(), $acct)->pluck('id')->all();
        $this->assertContains($arte->id, $ids);
        $this->assertContains($conta->id, $ids);
    }

    public function test_production_office_sees_all(): void
    {
        $this->assertTrue(ContractVisibility::seesAll($this->userInDept('crew', 'Oficina de Producción')));
        $this->assertTrue(ContractVisibility::seesAll($this->userInDept('crew', 'Producción')));
    }

    public function test_department_member_sees_only_own_department(): void
    {
        $arte = $this->envInDept('Arte');
        $transpo = $this->envInDept('Transportación');

        $u = $this->userInDept('crew', 'Arte');
        $this->assertFalse(ContractVisibility::seesAll($u));

        $ids = ContractVisibility::scopeVisible(ContractEnvelope::query(), $u)->pluck('id')->all();
        $this->assertContains($arte->id, $ids, 've lo de su departamento');
        $this->assertNotContains($transpo->id, $ids, 'no ve el de otro departamento');

        $this->assertTrue(ContractVisibility::canView($u, $arte));
        $this->assertFalse(ContractVisibility::canView($u, $transpo));
    }

    public function test_consult_route_blocks_crew_without_panel(): void
    {
        // Un crew de un depto normal NO tiene panel → la consulta le responde 403 aunque adivine la URL.
        $u = $this->userInDept('crew', 'Arte');
        $this->assertFalse($u->canSeePanel());
        $this->actingAs($u)->get(route('contracts.consult.index'))->assertForbidden();
    }

    public function test_accounting_can_open_the_consult_panel(): void
    {
        $this->envInDept('Arte');
        $acct = $this->userInDept('crew', 'Contabilidad');
        $this->actingAs($acct)->get(route('contracts.consult.index'))->assertOk()->assertSee('Contratos');
    }
}
