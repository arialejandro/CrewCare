<?php

namespace Tests\Feature\Transport;

use App\Models\TransportOrder;
use App\Support\TransportPreload;
use Illuminate\Support\Facades\DB;
use Tests\QaTestCase;

/**
 * Fase 3 · la PRECARGA: conjunto efectivo = base (always_pickup) ∪ marcados; agrupa por vehículo;
 * quien no tiene van → corrida de uno. Snapshot al crear.
 */
class TransportPreloadTest extends QaTestCase
{
    private function pos(int $deptId, string $name): int
    {
        return (int) DB::table('positions')->insertGetId([
            'name' => $name, 'department_id' => $deptId, 'production_id' => null,
            'is_hod' => 0, 'active' => 1, 'rank' => 60, 'binding' => 'unit',
            'existence' => 'core', 'hod_capable' => 0, 'sort_order' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function crewIn(int $pid, int $deptId, int $posId): int
    {
        $u = $this->makeUser('crew');
        DB::table('production_user')->insert([
            'production_id' => $pid, 'user_id' => $u->id, 'department_id' => $deptId,
            'position_id' => $posId, 'role' => 'crew', 'is_lead' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $u->id;
    }

    public function test_conjunto_efectivo_y_agrupado_por_vehiculo(): void
    {
        $pid    = (int) DB::table('productions')->value('id');
        $deptId = (int) DB::table('departments')->where('active', 1)->value('id');

        $basePos  = $this->pos($deptId, 'QA Base');
        $vanPos   = $this->pos($deptId, 'QA Van');
        $loosePos = $this->pos($deptId, 'QA Suelto');

        // Base: el puesto QA Base lleva pick up siempre.
        DB::table('transport_position_config')->insert([
            'production_id' => $pid, 'position_id' => $basePos, 'is_leadership' => 0,
            'always_pickup' => 1, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        // Vehículo (ficticio 9001) asignado al puesto QA Van.
        DB::table('transport_vehicle_assignments')->insert([
            'production_id' => $pid, 'position_id' => $vanPos, 'user_id' => null,
            'vehicle_id' => 9001, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $baseA  = $this->crewIn($pid, $deptId, $basePos);
        $baseB  = $this->crewIn($pid, $deptId, $basePos);
        $vanU   = $this->crewIn($pid, $deptId, $vanPos);
        $looseU = $this->crewIn($pid, $deptId, $loosePos);

        // Marcados: vanU y looseU. baseA/baseB entran por BASE (sin marca).
        foreach ([$vanU, $looseU] as $u) {
            DB::table('transport_pickup_marks')->insert([
                'production_id' => $pid, 'user_id' => $u, 'is_marked' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $eff = TransportPreload::effectiveUserIds($pid);
        $this->assertEqualsCanonicalizing([$baseA, $baseB, $vanU, $looseU], $eff);

        $order = TransportOrder::create([
            'production_id' => $pid, 'order_date' => now()->toDateString(), 'version' => 1,
            'status' => TransportOrder::STATUS_DRAFT, 'created_by_id' => $baseA,
        ]);
        $made = TransportPreload::into($order);

        // van9001 → 1 corrida con vanU; baseA/baseB/looseU sin van → 3 corridas de uno. Total 4.
        $this->assertSame(4, $made);

        $order->load('runs.occupants');
        $vehRun = $order->runs->firstWhere('vehicle_id', 9001);
        $this->assertNotNull($vehRun);
        $this->assertSame($vanU, (int) $vehRun->occupants->first()->user_id);
        $this->assertSame('set', $vehRun->run_class);
        $this->assertSame(3, $order->runs->whereNull('vehicle_id')->count());
    }

    public function test_sin_base_ni_marcados_no_precarga_nada(): void
    {
        $pid = (int) DB::table('productions')->value('id');
        $order = TransportOrder::create([
            'production_id' => $pid, 'order_date' => now()->toDateString(), 'version' => 1,
            'status' => TransportOrder::STATUS_DRAFT, 'created_by_id' => $this->makeUser('crew')->id,
        ]);

        $this->assertSame(0, TransportPreload::into($order));   // ningún vehículo con nadie → sin corrida vacía
    }
}
