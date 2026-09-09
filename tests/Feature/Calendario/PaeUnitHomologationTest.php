<?php

namespace Tests\Feature\Calendario;

use App\Models\EmergencyActionPlan;
use App\Models\Unit;
use Tests\QaTestCase;

/**
 * PAE · homologación de unidad. `unit_name` = FOTO CONGELADA (sellada) y manda al mostrar. `unit_id` = la
 * referencia VIVA (nombre actual, aunque se renombre). NUNCA se reescribe unit_name.
 */
class PaeUnitHomologationTest extends QaTestCase
{
    public function test_el_unit_name_sellado_manda_como_snapshot(): void
    {
        $pae = new EmergencyActionPlan(['unit_name' => 'Segunda unidad (feb)']);
        $this->assertSame('Segunda unidad (feb)', $pae->unitDisplayName(), 'La foto congelada es lo que dice el documento.');
    }

    public function test_sin_snapshot_cae_a_la_unidad_viva_o_a_la_principal(): void
    {
        $u = Unit::create(['name' => 'Segunda unidad', 'sort_order' => 1, 'is_active' => true]);

        $conUnidad = new EmergencyActionPlan([]);
        $conUnidad->unit_id = $u->id;
        $this->assertSame('Segunda unidad', $conUnidad->unitDisplayName());
        $this->assertSame('Segunda unidad', $conUnidad->liveUnitName());

        $principal = new EmergencyActionPlan([]);
        $this->assertSame('Unidad principal', $principal->unitDisplayName());
        $this->assertNull($principal->liveUnitName());
    }

    public function test_un_rename_se_puede_señalar_sin_tocar_el_snapshot(): void
    {
        $u = Unit::create(['name' => 'Unidad Norte', 'sort_order' => 1, 'is_active' => true]);
        $pae = new EmergencyActionPlan(['unit_name' => 'Unidad Norte']);   // snapshot al emitir
        $pae->unit_id = $u->id;

        $u->update(['name' => 'Unidad Costa']);   // se renombró después

        $this->assertSame('Unidad Norte', $pae->unitDisplayName(), 'El snapshot sellado NO cambia.');
        $this->assertSame('Unidad Costa', $pae->fresh()?->liveUnitName() ?? $pae->load('unit')->liveUnitName(), 'La referencia viva resuelve el nombre actual.');
    }
}
