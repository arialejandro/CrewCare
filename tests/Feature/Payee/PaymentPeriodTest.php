<?php

namespace Tests\Feature\Payee;

use App\Models\DocumentType;
use App\Models\ExternalAuthorization;
use App\Models\Payee;
use App\Models\PayeeContract;
use App\Models\PaymentPeriod;
use App\Models\User;
use App\Support\CurrentProduction;
use App\Support\PayeePackage;
use App\Support\PeriodBoard;
use App\Support\PeriodReminder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\QaTestCase;

/**
 * VENTANA DE RECEPCIÓN POR PERIODO DE PAGO — verificación del bloque:
 *  - se crean periodos semanal/quincenal y uno de day player con su día;
 *  - un payee (contrato) solo aparece en los periodos de SU frecuencia;
 *  - un documento dentro de ventana queda RECIBIDO; fuera también, pero MARCADO;
 *  - el tablero coincide con PayeePackage; un HOD de transpo no ve el tablero de arte;
 *  - una 32-D no positiva sigue contando como faltante;
 *  - cambiar la frecuencia de un contrato NO borra los periodos ya recibidos;
 *  - permisos y puertas de módulo bien acotados.
 */
class PaymentPeriodTest extends QaTestCase
{
    private $prodId;
    private $deptA; // "arte"
    private $deptB; // "transpo"

    protected function setUp(): void
    {
        parent::setUp();
        $this->prodId = DB::table('productions')->min('id');
        $depts = DB::table('departments')->orderBy('id')->limit(2)->pluck('id')->all();
        [$this->deptA, $this->deptB] = $depts;

        // Asegura una producción activa para el store() del controlador.
        DB::table('productions')->where('id', $this->prodId)->update(['active' => 1]);
        CurrentProduction::forget();
    }

    private function attachDept(User $u, $deptId): void
    {
        DB::table('production_user')->updateOrInsert(
            ['production_id' => $this->prodId, 'user_id' => $u->id],
            ['department_id' => $deptId, 'role' => $u->getRoleNames()->first(), 'is_lead' => 0, 'created_at' => now(), 'updated_at' => now()]
        );
    }

    private function contract(Payee $p, User $by, string $freq): PayeeContract
    {
        return $p->contracts()->create([
            'concept' => 'service', 'contracted_by_user_id' => $by->id,
            'production_id' => $this->prodId, 'payment_frequency' => $freq, 'is_active' => 1,
        ]);
    }

    private function period(string $freq, $opens, $closes, array $extra = []): PaymentPeriod
    {
        return PaymentPeriod::create(array_merge([
            'production_id' => $this->prodId, 'frequency' => $freq,
            'opens_on' => $opens, 'closes_on' => $closes, 'status' => PaymentPeriod::STATUS_OPEN,
        ], $extra));
    }

    private function row(array $board, Payee $p): ?array
    {
        return collect($board['rows'])->first(fn ($r) => $r['payee']->id === $p->id);
    }

    // ── 1 · se crean periodos semanal, quincenal y day player con su día ───────
    public function test_weekly_biweekly_and_day_player_periods_are_created(): void
    {
        $lp = $this->makeUser('line-producer');
        $this->actingAs($lp);

        $this->post(route('periods.store'), [
            'frequency' => PayeeContract::FREQ_WEEKLY, 'opens_on' => '2026-08-10', 'closes_on' => '2026-08-16',
        ])->assertRedirect();
        $this->post(route('periods.store'), [
            'frequency' => PayeeContract::FREQ_BIWEEKLY, 'opens_on' => '2026-08-01', 'closes_on' => '2026-08-15',
        ])->assertRedirect();

        $dp = Payee::create(['legal_nature' => 'fisica', 'name' => 'Day Player Uno']);
        $this->post(route('periods.store'), [
            'frequency' => PayeeContract::FREQ_DAY_PLAYER, 'opens_on' => '2026-08-12', 'closes_on' => '2026-08-14',
            'worked_on' => '2026-08-12', 'payee_id' => $dp->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('payment_periods', ['frequency' => 'weekly']);
        $this->assertDatabaseHas('payment_periods', ['frequency' => 'biweekly']);
        $this->assertDatabaseHas('payment_periods', [
            'frequency' => 'day_player', 'payee_id' => $dp->id, 'worked_on' => '2026-08-12',
        ]);
    }

    /** DAY PLAYER sin día ni persona = error de validación (no es semana, es el día trabajado). */
    public function test_day_player_requires_worked_day_and_person(): void
    {
        $this->actingAs($this->makeUser('line-producer'));
        $this->post(route('periods.store'), [
            'frequency' => PayeeContract::FREQ_DAY_PLAYER, 'opens_on' => '2026-08-12', 'closes_on' => '2026-08-14',
        ])->assertSessionHasErrors(['worked_on', 'payee_id']);
    }

    // ── 2 · un payee solo aparece en los periodos de SU frecuencia ─────────────
    public function test_board_only_lists_contracts_of_the_period_frequency(): void
    {
        $lp = $this->makeUser('line-producer'); // bypass: ve todo, aísla el matcheo por frecuencia

        $weekly   = Payee::create(['legal_nature' => 'fisica', 'name' => 'Semanal SA']);
        $biweekly = Payee::create(['legal_nature' => 'fisica', 'name' => 'Quincenal SA']);
        $this->contract($weekly, $lp, PayeeContract::FREQ_WEEKLY);
        $this->contract($biweekly, $lp, PayeeContract::FREQ_BIWEEKLY);

        $period = $this->period(PayeeContract::FREQ_WEEKLY, '2026-08-10', '2026-08-16');
        $board = PeriodBoard::build($period, $lp);

        $this->assertNotNull($this->row($board, $weekly), 'el contrato semanal aparece');
        $this->assertNull($this->row($board, $biweekly), 'el contrato quincenal NO aparece en el periodo semanal');
    }

    // ── 3 · dentro de ventana = recibido; fuera = recibido MARCADO ─────────────
    public function test_reception_inside_window_is_stamped_not_out_of_window(): void
    {
        $lp = $this->makeUser('line-producer');
        $payee = Payee::create(['legal_nature' => 'fisica', 'name' => 'Dentro SA']);
        $this->contract($payee, $lp, PayeeContract::FREQ_WEEKLY);

        $period = $this->period(PayeeContract::FREQ_WEEKLY, Carbon::today()->subDay(), Carbon::today()->addDays(2));
        $res = PaymentPeriod::resolveReception($payee, $this->prodId);

        $this->assertNotNull($res['period']);
        $this->assertTrue($res['period']->is($period));
        $this->assertFalse($res['out_of_window'], 'dentro de ventana no se marca');
    }

    public function test_reception_outside_window_is_still_received_but_marked(): void
    {
        $lp = $this->makeUser('line-producer');
        $payee = Payee::create(['legal_nature' => 'fisica', 'name' => 'Fuera SA']);
        $this->contract($payee, $lp, PayeeContract::FREQ_WEEKLY);

        // Única ventana: en el PASADO y cerrada. La recepción NO se rechaza: cuelga marcada.
        $past = $this->period(PayeeContract::FREQ_WEEKLY, '2026-07-01', '2026-07-07', ['status' => PaymentPeriod::STATUS_CLOSED]);
        $res = PaymentPeriod::resolveReception($payee, $this->prodId);

        $this->assertNotNull($res['period'], 'se recibe igual (no se rechaza)');
        $this->assertTrue($res['period']->is($past));
        $this->assertTrue($res['out_of_window'], 'fuera de ventana = marcado');
    }

    // ── 4 · el tablero coincide con PayeePackage + 6 · 32-D no positiva falta ───
    public function test_board_state_matches_payee_package_and_non_positive_32d_counts_missing(): void
    {
        $lp = $this->makeUser('line-producer');
        $payee = Payee::create(['legal_nature' => 'fisica', 'name' => 'Fiscal SA']);
        $this->contract($payee, $lp, PayeeContract::FREQ_WEEKLY);

        $dt32 = DocumentType::where('code', 'OPINION_32D')->firstOrFail();
        $doc = $payee->documents()->create([
            'level' => 'persona', 'document_type' => $dt32->name, 'document_type_id' => $dt32->id,
            'issued_at' => now()->toDateString(), 'result_status' => ExternalAuthorization::RESULT_NEGATIVE,
            'origen' => 'contractual', 'status' => 'presentado', 'is_active' => 1,
        ]);

        $period = $this->period(PayeeContract::FREQ_WEEKLY, '2026-08-10', '2026-08-16');
        $cutDay = PayeePackage::cutDay($this->prodId);

        // Filtro por 32-D: el estado del tablero == PayeePackage::evaluate, y NO POSITIVA = falta.
        $board = PeriodBoard::build($period, $lp, ['document_type_id' => $dt32->id]);
        $row = $this->row($board, $payee);
        $this->assertNotNull($row);
        $this->assertSame(
            PayeePackage::evaluate($dt32, $payee->documents()->get(), $cutDay),
            $row['cells'][$dt32->id],
            'el tablero reusa el estado de PayeePackage'
        );
        $this->assertSame(PayeePackage::ST_NOT_POSITIVE, $row['cells'][$dt32->id]);
        $this->assertFalse($row['delivered'], 'una 32-D no positiva NO cuenta como entregada');
        $this->assertContains('Fiscal SA', $board['tally']['who_missing']);

        // Al volverla positiva, entrega.
        $doc->update(['result_status' => ExternalAuthorization::RESULT_POSITIVE]);
        $board = PeriodBoard::build($period, $lp, ['document_type_id' => $dt32->id]);
        $row = $this->row($board, $payee);
        $this->assertSame(PayeePackage::ST_RECEIVED, $row['cells'][$dt32->id]);
        $this->assertTrue($row['delivered']);
        $this->assertSame(0, $board['tally']['missing']);
    }

    // ── 5 · un HOD de transpo no ve el tablero de arte ────────────────────────
    public function test_transpo_hod_does_not_see_the_arte_board(): void
    {
        $hodArte    = $this->makeUser('hod'); $this->attachDept($hodArte, $this->deptA);
        $hodTranspo = $this->makeUser('hod'); $this->attachDept($hodTranspo, $this->deptB);

        $payee = Payee::create(['legal_nature' => 'fisica', 'name' => 'Arte SA']);
        $this->contract($payee, $hodArte, PayeeContract::FREQ_WEEKLY);   // contratado por ARTE
        $period = $this->period(PayeeContract::FREQ_WEEKLY, '2026-08-10', '2026-08-16');

        $this->assertNotNull($this->row(PeriodBoard::build($period, $hodArte), $payee), 'arte sí ve lo suyo');
        $this->assertNull($this->row(PeriodBoard::build($period, $hodTranspo), $payee), 'transpo NO ve el de arte');
    }

    // ── 7 · cambiar la frecuencia NO borra los periodos ya recibidos ──────────
    public function test_changing_contract_frequency_does_not_erase_received_periods(): void
    {
        $lp = $this->makeUser('line-producer');
        $payee = Payee::create(['legal_nature' => 'fisica', 'name' => 'Estable SA']);
        $c = $this->contract($payee, $lp, PayeeContract::FREQ_WEEKLY);

        $period = $this->period(PayeeContract::FREQ_WEEKLY, Carbon::today()->subDay(), Carbon::today()->addDays(2));
        $dt = DocumentType::where('code', 'CSF')->firstOrFail();
        $doc = $payee->documents()->create([
            'level' => 'persona', 'document_type' => $dt->name, 'document_type_id' => $dt->id,
            'payment_period_id' => $period->id, 'received_out_of_window' => 0,
            'origen' => 'contractual', 'status' => 'presentado', 'is_active' => 1,
        ]);

        // La contabilidad cambia la frecuencia del contrato…
        $c->update(['payment_frequency' => PayeeContract::FREQ_BIWEEKLY]);

        // …el periodo sigue existiendo y el documento sigue colgando de él.
        $this->assertDatabaseHas('payment_periods', ['id' => $period->id]);
        $this->assertSame($period->id, (int) $doc->fresh()->payment_period_id, 'lo ya recibido conserva su periodo');
    }

    // ── frecuencia del contrato: se asigna y respeta la puerta ────────────────
    public function test_manager_can_set_contract_frequency_and_hod_cannot(): void
    {
        $lp = $this->makeUser('line-producer');
        $payee = Payee::create(['legal_nature' => 'fisica', 'name' => 'Freq SA']);
        $c = $this->contract($payee, $lp, PayeeContract::FREQ_WEEKLY);

        $this->actingAs($lp)
            ->post(route('periods.contract.frequency', $c), ['payment_frequency' => PayeeContract::FREQ_BIWEEKLY])
            ->assertRedirect();
        $this->assertSame('biweekly', $c->fresh()->payment_frequency);

        // Un HOD no tiene periods.manage → la ruta lo bloquea.
        $this->actingAs($this->makeUser('hod'))
            ->post(route('periods.contract.frequency', $c), ['payment_frequency' => PayeeContract::FREQ_WEEKLY])
            ->assertForbidden();
        $this->assertSame('biweekly', $c->fresh()->payment_frequency, 'el HOD no la cambió');
    }

    // ── permisos: quién ve / quién administra ─────────────────────────────────
    public function test_permission_grants_are_scoped(): void
    {
        foreach (['line-producer', 'coordinator', 'hod'] as $role) {
            $this->assertTrue($this->makeUser($role)->can('periods.view'), "$role ve el tablero");
        }
        foreach (['crew', 'medic', 'safety-officer', 'auditor'] as $role) {
            $this->assertFalse($this->makeUser($role)->can('periods.view'), "$role NO ve el tablero");
        }
        foreach (['line-producer', 'coordinator'] as $role) {
            $this->assertTrue($this->makeUser($role)->can('periods.manage'), "$role administra");
        }
        $this->assertFalse($this->makeUser('hod')->can('periods.manage'), 'el HOD solo consulta, no administra');
    }

    public function test_module_gate_blocks_roles_without_view(): void
    {
        $this->actingAs($this->makeUser('crew'));
        $this->get(route('periods.index'))->assertForbidden();
    }

    /** El tablero y la lista RENDERIZAN (el entregable que importa se pinta sin errores). */
    public function test_index_and_board_pages_render(): void
    {
        $lp = $this->makeUser('line-producer');
        $payee = Payee::create(['legal_nature' => 'fisica', 'name' => 'Render SA']);
        $this->contract($payee, $lp, PayeeContract::FREQ_WEEKLY);
        $period = $this->period(PayeeContract::FREQ_WEEKLY, '2026-08-10', '2026-08-16', ['label' => 'Semana 5']);

        $this->actingAs($lp);
        $this->get(route('periods.index'))->assertOk()->assertSee('Periodos de pago')->assertSee('Semana 5');
        $this->get(route('periods.show', $period))->assertOk()->assertSee('Render SA')->assertSee('Quiénes faltan');
    }

    // ── §a · recordatorio MANUAL a quienes faltan ─────────────────────────────
    public function test_reminder_targets_missing_names_production_and_marks_external(): void
    {
        $lp = $this->makeUser('line-producer');

        // Payee con AUTOSERVICIO (usuario + teléfono propio).
        $u = $this->makeUser('crew');
        $u->forceFill(['phone' => '5544332211'])->save();
        $selfPayee = Payee::create(['legal_nature' => 'fisica', 'name' => 'Autoservicio SA', 'user_id' => $u->id]);
        $this->contract($selfPayee, $lp, PayeeContract::FREQ_WEEKLY);

        // Payee EXTERNO (sin usuario): también falta, pero sin autoservicio.
        $ext = Payee::create(['legal_nature' => 'fisica', 'name' => 'Externo SA']);
        $this->contract($ext, $lp, PayeeContract::FREQ_WEEKLY);

        $period = $this->period(PayeeContract::FREQ_WEEKLY, '2026-08-10', '2026-08-16', ['label' => 'Semana 5']);
        $rows = PeriodReminder::build($period, $lp);

        $self = collect($rows)->first(fn ($r) => $r['payee']->id === $selfPayee->id);
        $this->assertNotNull($self);
        $this->assertTrue($self['self_serve']);
        $this->assertTrue($self['has_phone']);
        $this->assertStringContainsString('5544332211', $self['wa']);
        $decoded = urldecode($self['wa']);
        $prod = DB::table('productions')->where('id', $this->prodId)->value('name') ?: config('app.name');
        $this->assertStringContainsString($prod, $decoded, 'el mensaje NOMBRA la producción');
        $this->assertNotEmpty($self['missing'], 'dice qué le falta');

        $extRow = collect($rows)->first(fn ($r) => $r['payee']->id === $ext->id);
        $this->assertNotNull($extRow);
        $this->assertFalse($extRow['self_serve'], 'externo sin autoservicio');
        $this->assertNull($extRow['wa']);

        // NUNCA se arma para quien ya entregó: toda fila tiene algo faltante.
        foreach ($rows as $r) {
            $this->assertNotEmpty($r['missing']);
        }

        // La página renderiza con datos reales (autoservicio + externo).
        $this->actingAs($lp)->get(route('periods.reminders', $period))
            ->assertOk()->assertSee('Autoservicio SA')->assertSee('Externo SA');
    }

    public function test_reminders_page_is_gated_to_managers(): void
    {
        $period = $this->period(PayeeContract::FREQ_WEEKLY, '2026-08-10', '2026-08-16');
        $this->actingAs($this->makeUser('hod'));
        $this->get(route('periods.reminders', $period))->assertForbidden();   // hod = solo consulta
        $this->actingAs($this->makeUser('crew'));
        $this->get(route('periods.reminders', $period))->assertForbidden();
        $this->actingAs($this->makeUser('line-producer'));
        $this->get(route('periods.reminders', $period))->assertOk()->assertSee('Recordatorios');
    }
}
