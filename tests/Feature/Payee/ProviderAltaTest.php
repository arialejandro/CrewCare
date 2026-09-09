<?php

namespace Tests\Feature\Payee;

use App\Models\Department;
use App\Models\Payee;
use App\Models\User;
use App\Support\CurrentProduction;
use Illuminate\Support\Facades\DB;
use Tests\QaTestCase;

/**
 * ALTA DE PROVEEDOR (carril proveedor puro). Un HOD registra un proveedor de su departamento
 * (Payee + primer contrato); queda scoped a su depto vía el contratante. La regla del CrewList
 * corta el alta si la persona está en el llamado (eso es crew).
 */
class ProviderAltaTest extends QaTestCase
{
    private $prodId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prodId = DB::table('productions')->min('id');
        DB::table('productions')->where('id', $this->prodId)->update(['active' => 1]);
        CurrentProduction::forget();
    }

    private function deptId(string $name): int
    {
        return (int) Department::where('name', $name)->value('id');
    }

    private function hodInDept(string $deptName): User
    {
        $u = $this->makeUser('hod');
        DB::table('production_user')->updateOrInsert(
            ['production_id' => $this->prodId, 'user_id' => $u->id],
            ['department_id' => $this->deptId($deptName), 'role' => 'crew', 'is_lead' => 1, 'created_at' => now(), 'updated_at' => now()]
        );
        return $u;
    }

    public function test_hod_registers_a_pure_provider_scoped_to_their_department(): void
    {
        $hod = $this->hodInDept('Arte');

        $this->actingAs($hod)->post(route('providers.store'), [
            'legal_nature'         => 'moral',
            'name'                 => 'Rentas Fantasma SA',
            'legal_representative' => 'Ana Pérez',
            'rfc'                  => 'RFA860101AB3',
            'email'                => 'contacto@fantasma.mx',
            'department_id'        => $this->deptId('Arte'),
            'concept'              => 'equipment_rental',
            'fee_amount'           => 15000,
            'fee_currency'         => 'MXN',
            'payment_frequency'    => 'weekly',
        ])->assertRedirect();

        $payee = Payee::where('name', 'Rentas Fantasma SA')->first();
        $this->assertNotNull($payee);
        $this->assertSame('moral', $payee->legal_nature);
        $this->assertNull($payee->user_id, 'proveedor puro: sin usuario ligado');

        $contract = $payee->contracts()->first();
        $this->assertSame('equipment_rental', $contract->concept);
        $this->assertSame($this->deptId('Arte'), (int) $contract->department_id);
        $this->assertSame((int) $hod->id, (int) $contract->contracted_by_user_id);

        // Visible al HOD que lo dio de alta (scopeVisibleTo eje A: contratante en su depto).
        $this->assertTrue(Payee::query()->visibleTo($hod)->whereKey($payee->id)->exists());
        // NO visible a un HOD de otro departamento.
        $other = $this->hodInDept('Transportación');
        $this->assertFalse(Payee::query()->visibleTo($other)->whereKey($payee->id)->exists());
    }

    public function test_crewlist_toggle_blocks_pure_provider_alta(): void
    {
        $hod = $this->hodInDept('Arte');
        $this->actingAs($hod)->post(route('providers.store'), [
            'in_crewlist'   => '1',
            'legal_nature'  => 'fisica',
            'name'          => 'Un Driver',
            'department_id' => $this->deptId('Arte'),
            'concept'       => 'service',
        ])->assertSessionHas('error');

        $this->assertNull(Payee::where('name', 'Un Driver')->first(), 'si está en el llamado es crew: no se crea proveedor');
    }

    public function test_regular_crew_cannot_reach_provider_alta(): void
    {
        $crew = $this->makeUser('crew');   // sin payees.view → ni siquiera entra al módulo
        $this->actingAs($crew)->get(route('providers.create'))->assertForbidden();
    }

    /**
     * CARRIL 2 — el driver que YA es crew renta su auto: se AGREGA un contrato de renta a su MISMA
     * identidad (no se duplica), con su propia frecuencia y la ficha mínima del vehículo.
     */
    public function test_add_rental_contract_with_vehicle_to_existing_crew_identity(): void
    {
        $hod = $this->hodInDept('Transportación');

        $driver = $this->makeUser('crew');
        DB::table('production_user')->updateOrInsert(
            ['production_id' => $this->prodId, 'user_id' => $driver->id],
            ['department_id' => $this->deptId('Transportación'), 'role' => 'crew', 'is_lead' => 0, 'created_at' => now(), 'updated_at' => now()]
        );
        $payee = Payee::create(['legal_nature' => 'fisica', 'name' => 'Juan Driver', 'user_id' => $driver->id]);
        $payee->contracts()->create([
            'production_id' => $this->prodId, 'concept' => 'crew_work',
            'department_id' => $this->deptId('Transportación'), 'contracted_by_user_id' => $hod->id,
            'payment_frequency' => 'weekly', 'is_active' => 1,
        ]);

        $this->actingAs($hod)->post(route('payees.contract.store', $payee), [
            'concept'           => 'equipment_rental',
            'title'             => 'Renta de camioneta',
            'fee_amount'        => 8000,
            'fee_currency'      => 'MXN',
            'payment_frequency' => 'biweekly',
            'asset_make'        => 'Toyota',
            'asset_model'       => 'Hilux',
            'asset_plate'       => 'ABC123',
            'asset_year'        => 2020,
        ])->assertRedirect();

        $payee->refresh()->load('contracts');
        $this->assertCount(2, $payee->contracts, 'una identidad, dos contratos (trabajo + renta)');

        $rental = $payee->contracts->firstWhere('concept', 'equipment_rental');
        $this->assertNotNull($rental);
        $this->assertSame('biweekly', $rental->payment_frequency, 'frecuencia propia, distinta del trabajo');
        $this->assertSame('Toyota', $rental->asset_ref['make'] ?? null);
        $this->assertSame('Hilux', $rental->asset_ref['model'] ?? null);
        $this->assertSame(2020, (int) ($rental->asset_ref['year'] ?? 0));
        $this->assertSame('ABC123', $rental->asset_ref['plate'] ?? null);
    }
}
