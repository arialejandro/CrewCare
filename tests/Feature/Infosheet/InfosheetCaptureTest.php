<?php

namespace Tests\Feature\Infosheet;

use App\Models\Payee;
use App\Models\PayeeContract;
use Illuminate\Support\Facades\DB;
use Tests\QaTestCase;

/**
 * EL INFOSHEET · FASE 2 — captura del TRATO. Verificación del bloque:
 *  - el editor y el guardado están gateados por la PUERTA de auto-edición (nadie edita el trato de otro);
 *  - guardar cada sección persiste sus columnas (puesto/actividad, importes por fase + total, fechas);
 *  - el importe total (fee_amount) es la suma de los importes por fase;
 *  - las fechas se reemplazan (rango removible) y se deduplican por día;
 *  - se crea UN solo contrato crew_work por persona por producción (firstOrCreate);
 *  - la tarjeta del Infosheet aparece en /profile.
 */
class InfosheetCaptureTest extends QaTestCase
{
    private function payee(string $name = 'Crew Uno'): Payee
    {
        return Payee::create(['legal_nature' => 'fisica', 'name' => $name]);
    }

    private function crewContract(Payee $payee): ?PayeeContract
    {
        return $payee->contracts()->where('concept', PayeeContract::CONCEPT_CREW)->first();
    }

    public function test_capture_is_gated_by_auto_edit_guard(): void
    {
        $payee = $this->payee();

        // Crew sin departamento → sin alcance sobre el payee → 403 (ver y guardar).
        $this->actingAs($this->makeUser('crew'));
        $this->get(route('infosheet.edit', $payee->id))->assertForbidden();
        $this->post(route('infosheet.save', $payee->id), ['_step' => 'role'])->assertForbidden();

        // line-producer (bypass crew.view.all-departments) SÍ puede capturar.
        $this->actingAs($this->makeUser('line-producer'));
        $this->get(route('infosheet.edit', $payee->id))->assertOk();
    }

    public function test_role_section_persists_and_creates_single_contract(): void
    {
        $this->actingAs($this->makeUser('line-producer'));
        $payee = $this->payee();
        $dept  = DB::table('departments')->where('active', 1)->value('id');

        $this->post(route('infosheet.save', $payee->id), [
            '_step'          => 'role',
            'department_id'  => $dept,
            'crew_activity'  => 'Coordinación de pruebas',
            'effective_date' => '2026-09-01',
        ])->assertRedirect();

        $c = $this->crewContract($payee);
        $this->assertNotNull($c);
        $this->assertSame('Coordinación de pruebas', $c->crew_activity);
        // El nombre en créditos ya NO se teclea aquí: se deriva del nombre de la persona.
        $this->assertSame($payee->name, $c->credit_name);
        $this->assertSame('2026-09-01', optional($c->effective_date)->format('Y-m-d'));

        // Capturar de nuevo NO crea un segundo contrato (firstOrCreate impone la unicidad).
        $this->post(route('infosheet.save', $payee->id), ['_step' => 'role', 'crew_activity' => 'Otra'])->assertRedirect();
        $this->assertSame(1, $payee->contracts()->where('concept', PayeeContract::CONCEPT_CREW)->count());
    }

    public function test_fees_total_is_sum_of_phase_amounts(): void
    {
        $this->actingAs($this->makeUser('line-producer'));
        $payee = $this->payee();

        $this->post(route('infosheet.save', $payee->id), [
            '_step'                 => 'fees',
            'fee_prep_weeks'        => 6, 'fee_prep_rate'  => 8000, 'fee_prep_amount'  => 48000,
            'fee_shoot_weeks'       => 7, 'fee_shoot_rate' => 8000, 'fee_shoot_amount' => 56000,
            'tax_iva'               => 16640,
            'payment_document_type' => 'recibo',
            'budget_account'        => 'CTA-COVID',
            'manages_petty_cash'    => '1',
        ])->assertRedirect();

        $c = $this->crewContract($payee)->fresh();
        $this->assertEquals(48000.0, (float) $c->fee_prep_amount);
        $this->assertEquals(104000.0, (float) $c->fee_amount, 'el total es la suma de los importes por fase');
        $this->assertSame('recibo', $c->payment_document_type);
        $this->assertEquals(16640.0, (float) $c->tax_iva);
        $this->assertTrue((bool) $c->manages_petty_cash);
    }

    public function test_dates_dedup_and_are_removable(): void
    {
        $this->actingAs($this->makeUser('line-producer'));
        $payee = $this->payee();

        // Rango expandido con un día repetido: se deduplica por unique(contract,date).
        $this->post(route('infosheet.save', $payee->id), [
            '_step' => 'dates',
            'dates' => [
                ['date' => '2026-09-01', 'phase' => 'prep'],
                ['date' => '2026-09-02', 'phase' => 'prep'],
                ['date' => '2026-09-01', 'phase' => 'prep'],
            ],
        ])->assertRedirect();
        $this->assertSame(2, $this->crewContract($payee)->workDates()->count());

        // Reenviar MENOS días reemplaza el conjunto (quitar un día sin rehacer el rango).
        $this->post(route('infosheet.save', $payee->id), [
            '_step' => 'dates',
            'dates' => [['date' => '2026-09-01', 'phase' => 'prep']],
        ])->assertRedirect();
        $this->assertSame(1, $this->crewContract($payee)->workDates()->count());
    }

    public function test_infosheet_card_shows_on_profile(): void
    {
        $this->actingAs($this->makeUser('crew'));
        $this->get('/profile')->assertOk()->assertSee('Hoja de información');
    }
}
