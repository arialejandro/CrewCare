<?php

namespace Tests\Unit;

use App\Models\CallDeptOffset;
use App\Models\CallPersonSchedule;
use App\Support\CallSheetEngine;
use Tests\TestCase;

/**
 * PARTE D · el MOTOR DE HORARIOS. Aritmética por OFFSET (no hora absoluta) y precedencia
 * persona→depto→general. Sin BD: prueba las funciones puras del motor.
 */
class CallSheetEngineTest extends TestCase
{
    public function test_offset_desde_el_general(): void
    {
        // Ejemplo textual del owner: offset -150 (2.5 h precall).
        $this->assertSame('04:30', CallSheetEngine::addMinutes('07:00', -150));
        // Al mover el general a 08:00, el MISMO offset da 05:30 (el patrón se conserva).
        $this->assertSame('05:30', CallSheetEngine::addMinutes('08:00', -150));
        // Envuelve en 24 h (precall que cruza medianoche).
        $this->assertSame('23:00', CallSheetEngine::addMinutes('00:30', -90));
        $this->assertNull(CallSheetEngine::addMinutes(null, -150));
    }

    public function test_minutos_desde_una_hora(): void
    {
        $this->assertSame(-150, CallSheetEngine::minutesFrom('07:00', '04:30'));
        $this->assertSame(60, CallSheetEngine::minutesFrom('07:00', '08:00'));
        // Precall grande no debe salir como +22 h.
        $this->assertSame(-180, CallSheetEngine::minutesFrom('06:00', '03:00'));
    }

    public function test_precedencia_persona_sobre_depto_sobre_general(): void
    {
        $general = '07:00';
        $dept = new CallDeptOffset(['offset_minutes' => -60]);   // depto -1 h → 06:00

        // Sin fila de persona → hereda el depto.
        $s = CallSheetEngine::resolveSchedule($general, $dept, null);
        $this->assertSame('06:00', $s['time']);
        $this->assertSame('dept', $s['source']);

        // Persona con offset propio GANA sobre el depto.
        $person = new CallPersonSchedule(['schedule_offset_minutes' => -180]);  // -3 h → 04:00
        $s = CallSheetEngine::resolveSchedule($general, $dept, $person);
        $this->assertSame('04:00', $s['time']);
        $this->assertSame('person', $s['source']);
        $this->assertSame('06:00', $s['suggested'], 'la sugerencia sigue siendo la del depto');
    }

    public function test_sin_depto_ni_persona_es_el_general(): void
    {
        $s = CallSheetEngine::resolveSchedule('07:00', null, null);
        $this->assertSame('07:00', $s['time']);
        $this->assertSame('general', $s['source']);
    }

    public function test_literal_no_se_recalcula(): void
    {
        // Depto con literal O/C.
        $dept = new CallDeptOffset(['literal_value' => 'O/C']);
        $s = CallSheetEngine::resolveSchedule('07:00', $dept, null);
        $this->assertNull($s['time']);
        $this->assertSame('O/C', $s['literal']);

        // Persona con literal en horario gana.
        $person = new CallPersonSchedule(['schedule_literal' => 'Per GC']);
        $s = CallSheetEngine::resolveSchedule('07:00', $dept, $person);
        $this->assertSame('Per GC', $s['literal']);
    }

    public function test_pickup_literal_y_offset(): void
    {
        // N/A literal.
        $p1 = new CallPersonSchedule(['pickup_literal' => 'N/A']);
        $r1 = CallSheetEngine::resolvePickup('07:00', $p1);
        $this->assertSame('N/A', $r1['literal']);
        $this->assertNull($r1['time']);

        // Offset + lugar por clave.
        $p2 = new CallPersonSchedule(['pickup_offset_minutes' => -30, 'pickup_place_id' => 5]);
        $r2 = CallSheetEngine::resolvePickup('07:00', $p2, [5 => 'HRP']);
        $this->assertSame('06:30', $r2['time']);
        $this->assertSame('HRP', $r2['place']);

        // Sin persona → columna vacía.
        $r3 = CallSheetEngine::resolvePickup('07:00', null);
        $this->assertNull($r3['time']);
        $this->assertNull($r3['literal']);
    }
}
