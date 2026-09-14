<?php

namespace Tests\Feature\Outs;

use App\Models\CallDay;
use App\Models\DepartmentOut;
use App\Models\OutReporter;
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
    public function la_autoridad_es_por_designacion_no_por_puesto(): void
    {
        $pid = $this->pid();
        $deptId = DB::table('departments')->insertGetId([
            'name' => 'QA Dept ' . Str::random(6), 'active' => 1, 'sort_order' => 0,
        ]);
        $otherDeptId = DB::table('departments')->insertGetId([
            'name' => 'QA Otro ' . Str::random(6), 'active' => 1, 'sort_order' => 0,
        ]);
        // Usuario que es JEFE (is_hod) del depto — para probar que is_hod YA NO da autoridad.
        $hodPos = Position::create(['name' => 'Jefe QA', 'department_id' => $deptId, 'is_hod' => 1, 'active' => 1]);
        $u = User::forceCreate([
            'name' => 'Jefe QA', 'email' => 'jefeqa-' . Str::random(6) . '@qa.test',
            'password' => Hash::make('secret'), 'admin' => 0, 'activo' => 1,
        ]);
        DB::table('production_user')->insert([
            'production_id' => $pid, 'user_id' => $u->id, 'department_id' => $deptId,
            'position_id' => $hodPos->id, 'role' => 'crew', 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Ser jefe NO basta: sin designación, cero autoridad.
        $this->assertFalse(OutAuthority::seesAll($u));
        $this->assertSame([], OutAuthority::authorityDepartmentIds($u));
        $this->assertFalse(OutAuthority::canRegisterFor($u, $deptId));

        // DESIGNARLO sí le da autoridad — sobre ESE depto, no otro.
        OutReporter::create(['production_id' => $pid, 'department_id' => $deptId, 'user_id' => $u->id]);
        $this->assertSame([$deptId], OutAuthority::authorityDepartmentIds($u));
        $this->assertTrue(OutAuthority::canRegisterFor($u, $deptId));
        $this->assertFalse(OutAuthority::canRegisterFor($u, $otherDeptId));

        // Producción/coordinación ven todo (sin designación).
        $admin = $this->makeUser('super-admin');
        $this->assertTrue(OutAuthority::seesAll($admin));
        $this->assertNull(OutAuthority::visibleDepartmentIds($admin));
    }

    /** @test */
    public function solo_el_designado_reporta_la_salida_del_departamento(): void
    {
        $pid = $this->pid();
        $deptId = DB::table('departments')->insertGetId([
            'name' => 'QA Dept ' . Str::random(6), 'active' => 1, 'sort_order' => 0,
        ]);
        $user = User::forceCreate([
            'name' => 'Crew QA', 'email' => 'crewqa-' . Str::random(6) . '@qa.test',
            'password' => Hash::make('secret'), 'admin' => 0, 'activo' => 1,
        ]);
        DB::table('production_user')->insert([
            'production_id' => $pid, 'user_id' => $user->id, 'department_id' => $deptId,
            'position_id' => null, 'role' => 'crew', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $payload = ['department_id' => $deptId, 'shoot_date' => '2027-06-10', 'time' => '20:00'];

        // Sin designación: no puede reportar por el departamento (nadie captura si no está asignado).
        $this->actingAs($user);
        $this->post(route('outs.dept.store'), $payload)->assertForbidden();

        // Designado: ahora sí, y la salida es del DEPARTAMENTO (aplica a todos).
        OutReporter::create(['production_id' => $pid, 'department_id' => $deptId, 'user_id' => $user->id]);
        $this->post(route('outs.dept.store'), $payload)->assertRedirect();
        $this->assertDatabaseHas('department_outs', [
            'production_id' => $pid, 'department_id' => $deptId,
        ]);
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
