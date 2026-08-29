<?php

namespace Tests\Feature\Transport;

use App\Models\Vehicle;
use App\Support\CurrentProduction;
use App\Support\TransportDrivers;
use Illuminate\Support\Facades\DB;

/**
 * Transportación — selector de driver ACOTADO (depto transporte + puesto chofer) y "un driver, una
 * unidad" (default libres + toggle; reasignar libera la unidad anterior con aviso).
 */
class TransportDriverTest extends VehicleVerticalTestCase
{
    /** Alta de un chofer real del catálogo (depto `transport` + puesto `transport.driver`). */
    private function makeDriver(): int
    {
        $u = $this->makeUser('crew');
        $deptId = DB::table('departments')->where('catalog_key', 'transport')->value('id');
        $posId  = DB::table('positions')->where('catalog_key', 'transport.driver')->where('active', 1)->value('id');
        DB::table('production_user')->insert([
            'production_id' => CurrentProduction::id(), 'user_id' => $u->id,
            'department_id' => $deptId, 'position_id' => $posId, 'role' => 'crew', 'is_lead' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return (int) $u->id;
    }

    /** Alta de un miembro de transpo con un puesto ARBITRARIO (p. ej. capitán, no chofer). */
    private function makeTransportMember(string $posCatalogKey): int
    {
        $u = $this->makeUser('crew');
        $deptId = DB::table('departments')->where('catalog_key', 'transport')->value('id');
        $posId  = DB::table('positions')->where('catalog_key', $posCatalogKey)->where('active', 1)->value('id');
        DB::table('production_user')->insert([
            'production_id' => CurrentProduction::id(), 'user_id' => $u->id,
            'department_id' => $deptId, 'position_id' => $posId, 'role' => 'crew', 'is_lead' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return (int) $u->id;
    }

    public function test_driver_de_corrida_es_todo_transpo_no_solo_choferes(): void
    {
        $captain = $this->makeTransportMember('transport.captain'); // capitán: NO chofer
        $chofer  = $this->makeDriver();
        $other   = $this->makeUser('crew')->id;                     // fuera de transpo

        $ids = collect(TransportDrivers::departmentPicker(CurrentProduction::id()))->pluck('user_id')->all();
        $this->assertContains($captain, $ids, 'el capitán de transpo SÍ puede ser driver de corrida');
        $this->assertContains($chofer, $ids);
        $this->assertNotContains($other, $ids, 'no salen los 150 del crew');
    }

    public function test_solo_choferes_de_transpo_son_candidatos(): void
    {
        $driver = $this->makeDriver();
        $other  = $this->makeUser('crew')->id; // no está en transpo

        $ids = TransportDrivers::candidateIds(CurrentProduction::id());
        $this->assertContains($driver, $ids);
        $this->assertNotContains($other, $ids);
    }

    public function test_marca_al_asignado_con_su_unidad(): void
    {
        $driver = $this->makeDriver();
        $vA = $this->makeVehicle('auto', ['driver_user_id' => $driver, 'make' => 'Van', 'model' => 'A']);

        // Desde el form de OTRA unidad, el driver aparece como ocupado (en Van A).
        $rows = collect(TransportDrivers::forVehicleForm(CurrentProduction::id(), null))->keyBy('id');
        $this->assertNotNull($rows[$driver]['other_vehicle']);

        // Desde el form de SU MISMA unidad, no cuenta como ocupado (es la actual).
        $rowsOwn = collect(TransportDrivers::forVehicleForm(CurrentProduction::id(), $vA->id))->keyBy('id');
        $this->assertNull($rowsOwn[$driver]['other_vehicle']);
    }

    public function test_reasignar_libera_la_unidad_anterior_con_aviso(): void
    {
        $this->actingAsRole('safety-officer');
        $driver = $this->makeDriver();
        $vA = $this->makeVehicle('auto', ['driver_user_id' => $driver]);
        $vB = $this->makeVehicle('auto');

        $res = $this->post(route('transport.vehicle.update', $vB), [
            'owner_kind' => 'other', 'driver_user_id' => $driver,
        ]);
        $res->assertRedirect();

        $this->assertNull($vA->fresh()->driver_user_id, 'la unidad anterior quedó libre');
        $this->assertSame($driver, (int) $vB->fresh()->driver_user_id);
        $this->assertNotNull(session('warn'), 'avisa que la anterior quedó sin conductor');
    }
}
