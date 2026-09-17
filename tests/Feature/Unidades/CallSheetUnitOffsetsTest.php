<?php

namespace Tests\Feature\Unidades;

use App\Models\CallDeptOffset;
use App\Models\CallPersonSchedule;
use App\Models\Department;
use App\Models\Production;
use App\Models\Unit;
use App\Models\User;
use App\Support\CurrentProduction;
use App\Support\CurrentUnit;
use Illuminate\Support\Str;
use Tests\QaTestCase;

/**
 * UNIDADES · 2b · dominio LLAMADO (offsets/horarios) — call_dept_offsets y call_person_schedules por unidad.
 *
 * Si se comparten, cambiar el offset de cámara en una unidad le cambia la hora a la otra sin que nadie se
 * entere. Ahora cada unidad tiene los suyos (unit_id, null = principal). call_person_schedules SIGUE SIN
 * FECHA. Con una sola unidad, el motor lee el mismo conjunto de hoy.
 */
class CallSheetUnitOffsetsTest extends QaTestCase
{
    private string $ds = '2026-08-22';

    private function prod(): Production
    {
        $prod = Production::query()->orderBy('id')->first();
        Production::query()->where('id', '!=', $prod->id)->update(['active' => 0]);
        $prod->forceFill(['active' => 1])->save();
        CurrentProduction::forget();

        return $prod;
    }

    private function saveDeptLiteral(int $deptId, string $literal): void
    {
        $this->post(route('callsheet.departments.save', ['date' => $this->ds]), [
            'dept' => [$deptId => ['literal' => $literal, 'time' => null, 'radio' => null]],
        ])->assertRedirect();
    }

    private function savePersonLiteral(int $userId, string $literal): void
    {
        $this->post(route('callsheet.people.save', ['date' => $this->ds]), [
            'person' => [$userId => ['sched_literal' => $literal, 'meal' => 1]],
        ])->assertRedirect();
    }

    public function test_offsets_y_horarios_son_por_unidad(): void
    {
        $prod   = $this->prod();
        $u2     = Unit::create(['production_id' => $prod->id, 'name' => 'Segunda unidad', 'sort_order' => 1, 'is_active' => true]);
        $dept   = Department::query()->orderBy('id')->first();
        $person = $this->makeUser('crew');

        $this->actingAsRole('coordinator');   // callsheet.manage

        // --- Offset de departamento: distinto por unidad ---
        $this->withSession([CurrentUnit::SESSION_KEY => $u2->id]);
        CurrentUnit::forget();
        $this->saveDeptLiteral($dept->id, 'U2CAM');

        $this->withSession([CurrentUnit::SESSION_KEY => null]);
        CurrentUnit::forget();
        $this->saveDeptLiteral($dept->id, 'PRICAM');

        $offU2  = CallDeptOffset::where('department_id', $dept->id)->where('unit_id', $u2->id)->first();
        $offPri = CallDeptOffset::where('department_id', $dept->id)->whereNull('unit_id')->first();
        $this->assertNotNull($offU2);
        $this->assertNotNull($offPri);
        $this->assertSame('U2CAM', $offU2->literal_value);
        $this->assertSame('PRICAM', $offPri->literal_value, 'La principal conserva el suyo — no lo pisó la 2ª unidad.');

        // --- Horario por persona: distinto por unidad, para la MISMA persona ---
        $this->withSession([CurrentUnit::SESSION_KEY => $u2->id]);
        CurrentUnit::forget();
        $this->savePersonLiteral($person->id, 'U2SCHED');

        $this->withSession([CurrentUnit::SESSION_KEY => null]);
        CurrentUnit::forget();
        $this->savePersonLiteral($person->id, 'PRISCHED');

        $schU2  = CallPersonSchedule::where('user_id', $person->id)->where('unit_id', $u2->id)->first();
        $schPri = CallPersonSchedule::where('user_id', $person->id)->whereNull('unit_id')->first();
        $this->assertNotNull($schU2);
        $this->assertNotNull($schPri);
        $this->assertSame('U2SCHED', $schU2->schedule_literal);
        $this->assertSame('PRISCHED', $schPri->schedule_literal, 'Dos horarios distintos para la misma persona, uno por unidad.');

        // --- Cambiar el de una unidad NO toca a la otra ---
        $this->withSession([CurrentUnit::SESSION_KEY => $u2->id]);
        CurrentUnit::forget();
        $this->saveDeptLiteral($dept->id, 'U2NUEVO');

        $this->assertSame('U2NUEVO', $offU2->fresh()->literal_value);
        $this->assertSame('PRICAM', $offPri->fresh()->literal_value, 'Cambiar la 2ª unidad no tocó la principal.');
    }

    public function test_call_person_schedules_sigue_sin_fecha(): void
    {
        // Contrato explícito del owner: la tabla es el estado del único día abierto → NO lleva fecha.
        $cols = \Illuminate\Support\Facades\Schema::getColumnListing('call_person_schedules');
        foreach (['date', 'call_date', 'shoot_date', 'day', 'schedule_date'] as $dateCol) {
            $this->assertNotContains($dateCol, $cols, "call_person_schedules NO debe tener columna de fecha ('{$dateCol}').");
        }
    }
}
