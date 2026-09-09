<?php

namespace Tests\Feature\Ambulance;

use App\Models\AmbulanceCrew;
use App\Models\AmbulanceProvider;
use App\Models\Payee;
use App\Support\AmbulancePayeeLink;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * PASO 5 · migrar el PROVEEDOR de ambulancias a la base única (quien cobra), SIN tocar las actas
 * selladas, el padrón, ni el catálogo.
 */
class PayeeMigrationTest extends AmbulanceVerticalTestCase
{
    private function runBackfill(): void
    {
        Artisan::call('db:seed', ['--class' => \Database\Seeders\MigrateAmbulanceProvidersToPayeesSeeder::class, '--force' => true]);
    }

    /** 🔴 La primera prueba: las actas selladas siguen verificando; cero ALTERADO. */
    public function test_sealed_actas_survive_the_migration(): void
    {
        $provider = $this->makeProvider(['rfc' => 'ABC010101AB1']);
        $acta = $this->sealAmbulanceInspection(['provider_id' => $provider->id, 'provider_name' => $provider->name]);
        $this->assertTrue($acta->verifyLatestSignature(), 'sellada VÁLIDA antes');

        $this->runBackfill();

        $acta->refresh();
        $this->assertTrue($acta->verifyLatestSignature(), 'sellada VÁLIDA después: cero ALTERADO');
        $this->assertSame($provider->id, (int) $acta->provider_id, 'el id del proveedor no cambió');
        $this->assertSame($provider->name, $acta->provider_name, 'el snapshot del nombre no cambió');
    }

    /** El proveedor queda ligado a un payee MORAL con su contrato (quién contrató). */
    public function test_provider_becomes_moral_payee_with_contract(): void
    {
        $provider = $this->makeProvider(['name' => 'Ambulancias del Norte SA', 'rfc' => 'AMB010101AB1']);
        $this->runBackfill();

        $provider->refresh();
        $this->assertNotNull($provider->payee_id, 'quedó ligado');
        $payee = $provider->payee;
        $this->assertSame('moral', $payee->legal_nature);
        $this->assertSame('Ambulancias del Norte SA', $payee->name);
        $this->assertSame('AMB010101AB1', $payee->rfc);
        $this->assertSame(1, $payee->contracts()->count(), 'contrato creado (quién contrató)');
        $this->assertTrue($payee->isAmbulanceProvider());
    }

    /** Idempotente: correr el backfill dos veces no duplica payees ni contratos. */
    public function test_backfill_is_idempotent(): void
    {
        $provider = $this->makeProvider();
        $this->runBackfill();
        $payeeId = $provider->fresh()->payee_id;

        $this->runBackfill();
        $this->assertSame($payeeId, (int) $provider->fresh()->payee_id, 'no re-liga');
        $this->assertSame(1, Payee::whereHas('ambulanceProvider', fn ($q) => $q->where('id', $provider->id))->count());
    }

    /** El PADRÓN (ambulance_crew) NO se convierte en payee: factura la empresa, no el paramédico. */
    public function test_crew_padron_is_not_migrated_to_payees(): void
    {
        $provider = $this->makeProvider();
        $crew = AmbulanceCrew::create(['provider_id' => $provider->id, 'full_name' => 'Paramédico Único', 'is_active' => 1]);

        $this->runBackfill();

        $this->assertSame(0, Payee::where('name', 'Paramédico Único')->count(), 'el padrón no es payee');
        $this->assertDatabaseHas('ambulance_crew', ['id' => $crew->id, 'provider_id' => $provider->id]);
    }

    /** El catálogo de tipos y puntos NO cambia con la migración. */
    public function test_catalog_types_and_points_unchanged(): void
    {
        $types  = DB::table('ambulance_types')->count();
        $points = DB::table('ambulance_inspection_points')->count();
        $this->makeProvider();

        $this->runBackfill();

        $this->assertSame($types, DB::table('ambulance_types')->count());
        $this->assertSame($points, DB::table('ambulance_inspection_points')->count());
    }

    /** Un proveedor NUEVO (alta por el controlador) nace ya ligado a su payee. */
    public function test_new_provider_is_born_linked(): void
    {
        $this->actingAs($this->makeUser('safety-officer'));
        $this->post(route('ambulance.provider.store'), ['name' => 'Nueva Ambu SA', 'rfc' => 'NAS010101AB1'])
            ->assertRedirect();

        $provider = AmbulanceProvider::where('name', 'Nueva Ambu SA')->firstOrFail();
        $this->assertNotNull($provider->payee_id, 'nace ligado');
        $this->assertSame('moral', $provider->payee->legal_nature);
    }

    /** Transpo (HOD) NO ve el payee de la ambulancia, ni por URL directa. */
    public function test_transpo_cannot_see_ambulance_payee(): void
    {
        $safety   = $this->makeUser('safety-officer');
        $provider = $this->makeProvider(['created_by_id' => $safety->id]);
        $payee    = AmbulancePayeeLink::ensureFor($provider, $safety->id);

        $prodId = DB::table('productions')->min('id');
        $deptId = DB::table('departments')->min('id');
        $transpo = $this->makeUser('hod'); // HOD de transporte: tiene payees.view pero acotado a su depto
        DB::table('production_user')->updateOrInsert(
            ['production_id' => $prodId, 'user_id' => $transpo->id],
            ['department_id' => $deptId, 'role' => 'hod', 'is_lead' => 1, 'created_at' => now(), 'updated_at' => now()]
        );

        $this->actingAs($transpo);
        $this->get(route('payees.show', $payee))->assertForbidden();
    }
}
