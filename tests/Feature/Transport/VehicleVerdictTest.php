<?php

namespace Tests\Feature\Transport;

use App\Models\VehicleInspection;
use App\Support\VehicleChecklist;
use App\Support\VehicleVerdict;
use Tests\TestCase;

/**
 * MOTOR DE VEREDICTO GRADUADO + `applies_when` — pruebas PURAS (sin BD).
 *
 * Cubre la cascada de niveles (alto_riesgo/pobre/normal/bien/excelente ↔ apto/no_apto), el
 * FAIL-SAFE (un punto aplicable sin contestar nunca da favorable) y la evaluación de las
 * expresiones de atributos que encienden los módulos del checklist.
 */
class VehicleVerdictTest extends TestCase
{
    private function crit($n) { return array_fill(0, $n, ['class' => 'critical', 'answer' => false]); }
    private function major($n) { return array_fill(0, $n, ['class' => 'major', 'answer' => false]); }
    private function minor($n) { return array_fill(0, $n, ['class' => 'minor', 'answer' => false]); }

    public function test_cero_hallazgos_da_excelente_apto(): void
    {
        $r = VehicleVerdict::compute([
            ['class' => 'critical', 'answer' => true],
            ['class' => 'major', 'answer' => true],
            ['class' => 'minor', 'answer' => true],
        ]);
        $this->assertSame(VehicleInspection::LEVEL_EXCELENTE, $r['level']);
        $this->assertSame(VehicleInspection::VERDICT_APTO, $r['verdict']);
    }

    public function test_hasta_tres_menores_da_bien_apto(): void
    {
        $r = VehicleVerdict::compute($this->minor(3));
        $this->assertSame(VehicleInspection::LEVEL_BIEN, $r['level']);
        $this->assertSame(VehicleInspection::VERDICT_APTO, $r['verdict']);
    }

    public function test_cuatro_menores_da_normal_apto_con_observaciones(): void
    {
        $r = VehicleVerdict::compute($this->minor(4));
        $this->assertSame(VehicleInspection::LEVEL_NORMAL, $r['level']);
        $this->assertSame(VehicleInspection::VERDICT_APTO, $r['verdict']);
    }

    public function test_uno_o_dos_mayores_da_normal_apto(): void
    {
        foreach ([1, 2] as $n) {
            $r = VehicleVerdict::compute($this->major($n));
            $this->assertSame(VehicleInspection::LEVEL_NORMAL, $r['level'], "{$n} mayores → normal");
            $this->assertSame(VehicleInspection::VERDICT_APTO, $r['verdict']);
        }
    }

    public function test_tres_mayores_da_pobre_no_apto(): void
    {
        $r = VehicleVerdict::compute($this->major(3));
        $this->assertSame(VehicleInspection::LEVEL_POBRE, $r['level']);
        $this->assertSame(VehicleInspection::VERDICT_NO_APTO, $r['verdict']);
    }

    public function test_un_critico_da_alto_riesgo_no_apto(): void
    {
        $r = VehicleVerdict::compute(array_merge($this->crit(1), $this->minor(1)));
        $this->assertSame(VehicleInspection::LEVEL_ALTO, $r['level']);
        $this->assertSame(VehicleInspection::VERDICT_NO_APTO, $r['verdict']);
        $this->assertSame(1, $r['n_critical']);
    }

    public function test_failsafe_un_punto_sin_contestar_nunca_da_favorable(): void
    {
        // Todo cumple menos uno sin contestar (answer null) → jamás favorable.
        $r = VehicleVerdict::compute([
            ['class' => 'minor', 'answer' => true],
            ['class' => 'major', 'answer' => null],
        ]);
        $this->assertTrue($r['incomplete']);
        $this->assertNull($r['level']);
        $this->assertSame(VehicleInspection::VERDICT_NO_APTO, $r['verdict']);
    }

    public function test_applies_when_sobre_atributos(): void
    {
        $comb = VehicleChecklist::normalizeAttributes(['powertrain' => 'combustion', 'seats' => 5]);
        $elec = VehicleChecklist::normalizeAttributes(['powertrain' => 'electric', 'seats' => 12, 'water_tank_liters' => 300, 'has_cargo_box' => true]);

        // null / '' = aplica siempre
        $this->assertTrue(VehicleChecklist::applies(null, $comb));
        $this->assertTrue(VehicleChecklist::applies('', $comb));

        // powertrain != electric → motor/combustible
        $this->assertTrue(VehicleChecklist::applies('powertrain != electric', $comb));
        $this->assertFalse(VehicleChecklist::applies('powertrain != electric', $elec));

        // powertrain != combustion → tracción eléctrica
        $this->assertFalse(VehicleChecklist::applies('powertrain != combustion', $comb));
        $this->assertTrue(VehicleChecklist::applies('powertrain != combustion', $elec));

        // seats > 8
        $this->assertFalse(VehicleChecklist::applies('seats > 8', $comb));
        $this->assertTrue(VehicleChecklist::applies('seats > 8', $elec));

        // water_tank_liters >= 200
        $this->assertFalse(VehicleChecklist::applies('water_tank_liters >= 200', $comb));
        $this->assertTrue(VehicleChecklist::applies('water_tank_liters >= 200', $elec));

        // token booleano suelto
        $this->assertFalse(VehicleChecklist::applies('has_cargo_box', $comb));
        $this->assertTrue(VehicleChecklist::applies('has_cargo_box', $elec));
    }

    public function test_normalize_rellena_defaults(): void
    {
        $a = VehicleChecklist::normalizeAttributes([]);
        $this->assertSame('combustion', $a['powertrain']);
        $this->assertFalse($a['has_cargo_box']);
        $this->assertNull($a['seats']);
        $this->assertNull($a['water_tank_liters']);
        $this->assertFalse($a['tows']);
    }
}
