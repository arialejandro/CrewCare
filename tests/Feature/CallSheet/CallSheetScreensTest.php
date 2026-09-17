<?php

namespace Tests\Feature\CallSheet;

use App\Models\CallDay;
use App\Models\CallDeptOffset;
use App\Models\CallPersonSchedule;
use App\Models\CallPlace;
use App\Models\Department;
use App\Models\Payee;
use App\Models\User;
use App\Support\CurrentProduction;
use Illuminate\Support\Facades\DB;
use Tests\QaTestCase;

/**
 * PARTES E/F · las 3 pantallas del llamado (departamentos, personas), el catálogo de lugares y el
 * back exportable (PDF). Verifica render, persistencia de offsets y que el back sale como PDF.
 */
class CallSheetScreensTest extends QaTestCase
{
    private int $prodId;
    private string $ds = '2026-08-22';

    protected function setUp(): void
    {
        parent::setUp();
        $this->prodId = (int) DB::table('productions')->min('id');
        DB::table('productions')->where('id', $this->prodId)->update(['active' => 1]);
        CurrentProduction::forget();
    }

    private function deptId(string $name): int
    {
        return (int) Department::where('name', $name)->value('id');
    }

    private function makeCrew(string $dept, string $name): User
    {
        $u = $this->makeUser('crew', ['name' => $name, 'lname' => 'QA', 'ncreditos' => $name]);
        DB::table('production_user')->updateOrInsert(
            ['production_id' => $this->prodId, 'user_id' => $u->id],
            ['department_id' => $this->deptId($dept), 'role' => 'crew', 'is_lead' => 0, 'created_at' => now(), 'updated_at' => now()]
        );
        $payee = Payee::create(['legal_nature' => 'fisica', 'name' => $u->name, 'user_id' => $u->id]);
        $c = $payee->contracts()->create([
            'production_id' => $this->prodId, 'concept' => 'crew_work',
            'department_id' => $this->deptId($dept), 'contracted_by_user_id' => $u->id,
            'payment_frequency' => 'weekly', 'is_active' => 1,
        ]);
        DB::table('payee_contract_work_dates')->insert(['payee_contract_id' => $c->id, 'work_date' => $this->ds]);
        DB::table('contract_envelopes')->insert([
            'payee_contract_id' => $c->id, 'production_id' => $this->prodId,
            'status' => 'completed', 'created_at' => now(), 'updated_at' => now(),
        ]);
        return $u;
    }

    public function test_departamentos_render_y_guarda_offset(): void
    {
        $this->actingAsRole('coordinator');
        // Un general para poder editar por hora.
        CallDay::create(['production_id' => $this->prodId, 'call_date' => $this->ds, 'general_call' => '07:00']);

        $this->get(route('callsheet.departments', ['date' => $this->ds]))->assertOk();

        $arte = $this->deptId('Arte');
        $this->post(route('callsheet.departments.save', ['date' => $this->ds]), [
            'dept' => [$arte => ['time' => '06:00', 'literal' => '']],
        ])->assertRedirect(route('callsheet.departments', ['date' => $this->ds]));

        $off = CallDeptOffset::where('production_id', $this->prodId)->where('department_id', $arte)->first();
        $this->assertNotNull($off);
        $this->assertSame(-60, $off->offset_minutes, '06:00 con general 07:00 = -60 min');
    }

    public function test_departamentos_guarda_canal_de_radio_global(): void
    {
        $this->actingAsRole('coordinator');
        CallDay::create(['production_id' => $this->prodId, 'call_date' => $this->ds, 'general_call' => '07:00']);
        $arte = $this->deptId('Arte');

        // El canal es un atributo GLOBAL del depto; se guarda aunque no haya offset.
        $this->post(route('callsheet.departments.save', ['date' => $this->ds]), [
            'dept' => [$arte => ['time' => '', 'literal' => '', 'radio' => '4']],
        ])->assertRedirect();
        $this->assertSame('4', Department::whereKey($arte)->value('radio_channel'));

        // Vaciarlo lo vuelve NULL.
        $this->post(route('callsheet.departments.save', ['date' => $this->ds]), [
            'dept' => [$arte => ['time' => '', 'literal' => '', 'radio' => '']],
        ])->assertRedirect();
        $this->assertNull(Department::whereKey($arte)->value('radio_channel'));
    }

    public function test_personas_render_y_guarda_horario(): void
    {
        $this->actingAsRole('coordinator');
        CallDay::create(['production_id' => $this->prodId, 'call_date' => $this->ds, 'general_call' => '07:00']);
        $u = $this->makeCrew('Arte', 'Ada Arte');

        $this->get(route('callsheet.people', ['date' => $this->ds]))->assertOk();

        $this->post(route('callsheet.people.save', ['date' => $this->ds]), [
            'person' => [$u->id => ['sched_time' => '05:30', 'meal' => '1']],
        ])->assertRedirect(route('callsheet.people', ['date' => $this->ds]));

        $ps = CallPersonSchedule::where('production_id', $this->prodId)->where('user_id', $u->id)->first();
        $this->assertNotNull($ps);
        $this->assertSame(-90, $ps->schedule_offset_minutes, '05:30 con general 07:00 = -90 min');
        $this->assertTrue((bool) $ps->meal_mark);
    }

    public function test_persona_literal_pickup(): void
    {
        $this->actingAsRole('coordinator');
        CallDay::create(['production_id' => $this->prodId, 'call_date' => $this->ds, 'general_call' => '07:00']);
        $u = $this->makeCrew('Arte', 'Leo Loc');

        $this->post(route('callsheet.people.save', ['date' => $this->ds]), [
            'person' => [$u->id => ['sched_literal' => 'O/C', 'pickup_literal' => 'N/A', 'meal' => '1']],
        ])->assertRedirect();

        $ps = CallPersonSchedule::where('user_id', $u->id)->first();
        $this->assertSame('O/C', $ps->schedule_literal);
        $this->assertNull($ps->schedule_offset_minutes, 'un literal no guarda offset');
        $this->assertSame('N/A', $ps->pickup_literal);
    }

    public function test_lugares_crud(): void
    {
        $this->actingAsRole('coordinator');
        $this->get(route('callsheet.places'))->assertOk();
        $this->post(route('callsheet.places.save'), [
            'places' => [['id' => '', 'code' => 'HRP', 'name' => 'Hotel Real de la Paz']],
        ])->assertRedirect(route('callsheet.places'));
        $this->assertDatabaseHas('call_places', ['production_id' => $this->prodId, 'code' => 'HRP', 'name' => 'Hotel Real de la Paz']);
    }

    public function test_back_sale_como_pdf(): void
    {
        $this->actingAsRole('coordinator');
        CallDay::create(['production_id' => $this->prodId, 'call_date' => $this->ds, 'general_call' => '07:00', 'cast_count' => 5, 'bg_count' => 10]);
        $this->makeCrew('Arte', 'Ben Back');

        $res = $this->get(route('callsheet.back', ['date' => $this->ds]));
        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
    }

    public function test_back_bilingue(): void
    {
        $this->actingAsRole('coordinator');
        CallDay::create(['production_id' => $this->prodId, 'call_date' => $this->ds, 'general_call' => '07:00']);
        $this->get(route('callsheet.back', ['date' => $this->ds, 'lang' => 'en']))->assertOk();
    }

    public function test_back_sale_en_los_4_presets(): void
    {
        $this->actingAsRole('coordinator');
        CallDay::create(['production_id' => $this->prodId, 'call_date' => $this->ds, 'general_call' => '07:00', 'cast_count' => 5, 'bg_count' => 10]);
        $this->makeCrew('Arte', 'Peg Preset');

        foreach (array_keys(\App\Support\CallSheetFormats::PRESETS) as $preset) {
            $res = $this->get(route('callsheet.back', ['date' => $this->ds, 'preset' => $preset]));
            $res->assertOk();
            $this->assertStringStartsWith('%PDF', $res->getContent(), "preset {$preset} debe salir como PDF");
        }
    }
}
