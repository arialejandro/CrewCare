<?php

namespace Tests\Feature\Outs;

use App\Models\CallDay;
use App\Models\DepartmentOut;
use App\Models\Position;
use App\Models\User;
use App\Support\CurrentProduction;
use App\Support\OutAuthority;
use App\Support\OutRegistrar;
use App\Support\OutWindow;
use App\Support\Turnaround;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\QaTestCase;

/**
 * Ventana (§2), autoridad por puesto, registro y turnaround (§4). Fechas futuras para no chocar con
 * lo que siembre el demo.
 */
class OutsDomainTest extends QaTestCase
{
    private function pid(): int
    {
        $id = CurrentProduction::id();
        $this->assertNotNull($id, 'El seed debe dejar una producción vigente.');

        return $id;
    }

    private function callDay(int $pid, string $date, string $general, ?int $unit = null): CallDay
    {
        return CallDay::create([
            'production_id' => $pid, 'unit_id' => $unit, 'call_date' => $date, 'general_call' => $general,
        ]);
    }

    /** @test */
    public function un_out_de_madrugada_pertenece_al_rodaje_que_termino(): void
    {
        $pid = $this->pid();
        $this->callDay($pid, '2027-01-10', '14:00:00');

        $res = OutWindow::resolveShootDate(Carbon::parse('2027-01-11 02:30'), null, $pid);

        $this->assertTrue($res['resolved']);
        $this->assertSame('2027-01-10', $res['shoot_date']);   // el día que terminó, no el que empieza
    }

    /** @test */
    public function fuera_de_la_ventana_de_20h_no_se_asigna(): void
    {
        $pid = $this->pid();
        $this->callDay($pid, '2027-02-10', '06:00:00');        // general temprano

        $res = OutWindow::resolveShootDate(Carbon::parse('2027-02-11 02:30'), null, $pid);   // 20.5 h

        $this->assertFalse($res['resolved']);
        $this->assertSame('outside_window', $res['reason']);
    }

    /** @test */
    public function outAt_para_el_dia_manda_la_madrugada_al_dia_natural_siguiente(): void
    {
        $pid = $this->pid();
        $this->callDay($pid, '2027-03-10', '14:00:00');

        $mad = OutWindow::outAtForShootDay('2027-03-10', '02:30', null, $pid);
        $this->assertTrue($mad['resolved']);
        $this->assertSame('2027-03-11 02:30:00', $mad['out_at']->toDateTimeString());

        $tarde = OutWindow::outAtForShootDay('2027-03-10', '18:30', null, $pid);
        $this->assertSame('2027-03-10 18:30:00', $tarde['out_at']->toDateTimeString());
    }

    /** @test */
    public function la_autoridad_es_por_puesto_is_hod(): void
    {
        $pid = $this->pid();
        $deptId = DB::table('departments')->insertGetId([
            'name' => 'QA Dept ' . Str::random(6), 'active' => 1, 'sort_order' => 0,
        ]);
        $otherDeptId = DB::table('departments')->insertGetId([
            'name' => 'QA Otro ' . Str::random(6), 'active' => 1, 'sort_order' => 0,
        ]);
        $hodPos = Position::create(['name' => 'Jefe QA', 'department_id' => $deptId, 'is_hod' => 1, 'active' => 1]);

        // Usuario SIN rol que conceda crew.view.all-departments (no seesAll), pero jefe del depto.
        $hod = User::forceCreate([
            'name' => 'HOD QA', 'email' => 'hodqa-' . Str::random(6) . '@qa.test',
            'password' => Hash::make('secret'), 'admin' => 0, 'activo' => 1,
        ]);
        DB::table('production_user')->insert([
            'production_id' => $pid, 'user_id' => $hod->id, 'department_id' => $deptId,
            'position_id' => $hodPos->id, 'role' => 'crew', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertFalse(OutAuthority::seesAll($hod));
        $this->assertSame([$deptId], OutAuthority::authorityDepartmentIds($hod));
        $this->assertTrue(OutAuthority::canRegisterFor($hod, $deptId));
        $this->assertFalse(OutAuthority::canRegisterFor($hod, $otherDeptId));

        // Producción/coordinación ven todo.
        $admin = $this->makeUser('super-admin');
        $this->assertTrue(OutAuthority::seesAll($admin));
        $this->assertNull(OutAuthority::visibleDepartmentIds($admin));   // null = todos
    }

    /** @test */
    public function registro_es_idempotente_y_corregible(): void
    {
        $pid = $this->pid();
        $deptId = DB::table('departments')->insertGetId([
            'name' => 'QA Reg ' . Str::random(6), 'active' => 1, 'sort_order' => 0,
        ]);

        OutRegistrar::departmentOut($pid, null, '2027-04-10', $deptId, Carbon::parse('2027-04-10 20:00'));
        OutRegistrar::departmentOut($pid, null, '2027-04-10', $deptId, Carbon::parse('2027-04-10 21:15')); // corrección

        $rows = DepartmentOut::where('production_id', $pid)->whereDate('shoot_date', '2027-04-10')
            ->where('department_id', $deptId)->get();
        $this->assertCount(1, $rows);                                    // una sola fila (corrige, no duplica)
        $this->assertSame('21:15', Carbon::parse($rows[0]->out_at)->format('H:i'));
    }

    /** @test */
    public function turnaround_se_deriva_entre_salida_y_siguiente_llamado(): void
    {
        $pid = $this->pid();
        $deptId = DB::table('departments')->insertGetId([
            'name' => 'QA TA ' . Str::random(6), 'active' => 1, 'sort_order' => 0,
        ]);
        $this->callDay($pid, '2027-05-10', '14:00:00');
        $this->callDay($pid, '2027-05-11', '12:00:00');                  // siguiente llamado

        OutRegistrar::departmentOut($pid, null, '2027-05-10', $deptId, Carbon::parse('2027-05-10 20:00'));

        $rows = Turnaround::rowsForDay($pid, null, '2027-05-10', null);
        $mine = array_values(array_filter($rows, fn ($r) => $r['department_id'] === $deptId));
        $this->assertCount(1, $mine);
        // De 2027-05-10 20:00 a 2027-05-11 12:00 = 16 h = 960 min.
        $this->assertSame(960, $mine[0]['minutes']);
        $this->assertSame('2027-05-11 12:00:00', $mine[0]['next_call_at']->toDateTimeString());
    }

    /** @test */
    public function sin_siguiente_llamado_no_hay_turnaround(): void
    {
        $pid = $this->pid();
        $deptId = DB::table('departments')->insertGetId([
            'name' => 'QA NN ' . Str::random(6), 'active' => 1, 'sort_order' => 0,
        ]);
        // Salida en una fecha muy futura sin call_day posterior alguno → sin siguiente llamado.
        OutRegistrar::departmentOut($pid, null, '2029-12-31', $deptId, Carbon::parse('2029-12-31 20:00'));

        $rows = Turnaround::rowsForDay($pid, null, '2029-12-31', null);
        $mine = array_values(array_filter($rows, fn ($r) => $r['department_id'] === $deptId));
        $this->assertCount(1, $mine);
        $this->assertNull($mine[0]['minutes']);          // no se inventa
        $this->assertNull($mine[0]['next_call_at']);
    }
}
