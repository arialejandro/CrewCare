<?php

namespace Tests\Feature\Payee;

use App\Models\ContractEnvelope;
use App\Models\Payee;
use App\Models\PayeeContract;
use App\Models\PayeeContractWorkDate;
use Tests\QaTestCase;

/**
 * EL CONTRATO · PASO A — el DATO: carátula crew_work + fechas de trabajo por fase + mapeo de
 * estados del roster. Verificación del bloque:
 *  - crew_work acepta fechas NO contiguas, cada una con su fase;
 *  - las tarifas de viáticos difieren entre prep/wrap y shoot;
 *  - renta/servicio dejan en NULL todo lo de crew;
 *  - activo+fecha = llamado; activo sin fecha = no llamado; vencido/inactivo = fuera.
 */
class CrewContractTest extends QaTestCase
{
    private function crewContract(array $extra = []): PayeeContract
    {
        $payee = Payee::create(['legal_nature' => 'fisica', 'name' => 'Crew Uno']);
        return $payee->contracts()->create(array_merge([
            'concept' => PayeeContract::CONCEPT_CREW, 'is_active' => 1,
            'production_id' => \Illuminate\Support\Facades\DB::table('productions')->min('id'),
        ], $extra));
    }

    public function test_crew_work_accepts_non_contiguous_dates_with_phase(): void
    {
        $c = $this->crewContract(['definitive_end_date' => '2026-12-31']);

        // Días 1, 37 y 123 (no contiguos), cada uno con su fase.
        $c->workDates()->create(['work_date' => '2026-08-03', 'phase' => PayeeContractWorkDate::PHASE_PREP]);
        $c->workDates()->create(['work_date' => '2026-09-08', 'phase' => PayeeContractWorkDate::PHASE_SHOOT]);
        $c->workDates()->create(['work_date' => '2026-12-04', 'phase' => PayeeContractWorkDate::PHASE_WRAP]);

        $this->assertSame(3, $c->workDates()->count());
        $this->assertEqualsCanonicalizing(
            ['prep', 'shoot', 'wrap'],
            $c->workDates()->pluck('phase')->all()
        );
    }

    public function test_perdiem_differs_between_prep_wrap_and_shoot(): void
    {
        $c = $this->crewContract([
            'perdiem_weekly_prep'  => 1500,
            'perdiem_weekly_shoot' => 3000,
        ]);

        $this->assertNotSame(
            $c->weeklyPerdiemForPhase(PayeeContractWorkDate::PHASE_SHOOT),
            $c->weeklyPerdiemForPhase(PayeeContractWorkDate::PHASE_PREP),
            'la tarifa de shoot difiere de prep/wrap'
        );
        $this->assertSame('3000.00', $c->weeklyPerdiemForPhase(PayeeContractWorkDate::PHASE_SHOOT));
        $this->assertSame('1500.00', $c->weeklyPerdiemForPhase(PayeeContractWorkDate::PHASE_WRAP));
        $this->assertSame('1500.00', $c->weeklyPerdiemForPhase(PayeeContractWorkDate::PHASE_SOFT_PREP));
    }

    public function test_rental_and_service_leave_crew_fields_null(): void
    {
        $payee = Payee::create(['legal_nature' => 'moral', 'name' => 'Renta SA']);
        $rental  = $payee->contracts()->create(['concept' => PayeeContract::CONCEPT_RENTAL, 'is_active' => 1]);
        $service = $payee->contracts()->create(['concept' => PayeeContract::CONCEPT_SERVICE, 'is_active' => 1]);

        foreach ([$rental, $service] as $c) {
            $fresh = $c->fresh();
            foreach (['crew_activity', 'credit_name', 'effective_date', 'fee_amount',
                      'perdiem_weekly_shoot', 'contractor_rfc', 'beneficiary_name'] as $f) {
                $this->assertNull($fresh->$f, "'{$f}' debe quedar NULL en {$c->concept}");
            }
            // Un contrato que no es crew_work nunca produce roster.
            $this->assertSame(PayeeContract::ROSTER_OUT, $c->rosterStateOn('2026-09-08'));
        }
    }

    public function test_roster_state_mapping(): void
    {
        // CREW FIJO (weekly): base del roster + PUERTA de firma (PARTE C).
        $c = $this->crewContract(['definitive_end_date' => '2026-10-31', 'payment_frequency' => 'weekly']);
        $c->workDates()->create(['work_date' => '2026-09-08', 'phase' => PayeeContractWorkDate::PHASE_SHOOT]);

        // 🔴 PUERTA: con fecha ese día pero SIN sobre completado = PENDIENTE DE FIRMA (visible,
        // accionable — no invisible, no fuera). Nunca cuenta como llamado.
        $this->assertSame(PayeeContract::ROSTER_PENDING_SIGNATURE, $c->rosterStateOn('2026-09-08'), 'fecha ese día sin sobre = pendiente de firma');
        $this->assertSame(PayeeContract::ROSTER_NOT_CALLED,        $c->rosterStateOn('2026-09-09'), 'sin fecha ese día = no llamado');

        // Con el sobre completado, el día con fecha pasa a LLAMADO.
        ContractEnvelope::create([
            'payee_contract_id' => $c->id, 'production_id' => $c->production_id,
            'status' => ContractEnvelope::STATUS_COMPLETED,
        ]);

        $this->assertSame(PayeeContract::ROSTER_CALLED,     $c->rosterStateOn('2026-09-08'), 'activo + fecha + sobre = llamado');
        $this->assertSame(PayeeContract::ROSTER_NOT_CALLED, $c->rosterStateOn('2026-09-09'), 'activo sin fecha = no llamado');
        // 🔴 CREW FIJO vencido: NO cae a fuera; se queda hasta el wrap (PARTE C). Sin fecha ese día → no llamado.
        $this->assertSame(PayeeContract::ROSTER_NOT_CALLED, $c->rosterStateOn('2026-11-15'), 'crew fijo vencido sigue en el roster (no llamado)');

        // Inactivar el CONTRATO sí lo saca, sin borrar nada.
        $c->update(['is_active' => 0]);
        $this->assertSame(PayeeContract::ROSTER_OUT, $c->fresh()->rosterStateOn('2026-09-08'), 'contrato inactivo = fuera');
        $this->assertSame(1, $c->workDates()->count(), 'las fechas siguen ahí (no se borran)');
    }

    public function test_day_player_vence_pero_crew_fijo_no(): void
    {
        // DAY PLAYER: el vencimiento SÍ opera (PARTE C).
        $dp = $this->crewContract(['definitive_end_date' => '2026-10-31', 'payment_frequency' => 'day_player']);
        ContractEnvelope::create([
            'payee_contract_id' => $dp->id, 'production_id' => $dp->production_id,
            'status' => ContractEnvelope::STATUS_COMPLETED,
        ]);
        $this->assertSame(PayeeContract::ROSTER_NOT_CALLED, $dp->rosterStateOn('2026-10-15'), 'day player vigente sin fecha = no llamado');
        $this->assertSame(PayeeContract::ROSTER_OUT,        $dp->rosterStateOn('2026-11-15'), 'day player vencido = fuera');

        // Crew FIJO en la misma fecha vencida NO cae a fuera.
        $fx = $this->crewContract(['definitive_end_date' => '2026-10-31', 'payment_frequency' => 'biweekly']);
        ContractEnvelope::create([
            'payee_contract_id' => $fx->id, 'production_id' => $fx->production_id,
            'status' => ContractEnvelope::STATUS_COMPLETED,
        ]);
        $this->assertSame(PayeeContract::ROSTER_NOT_CALLED, $fx->rosterStateOn('2026-11-15'), 'crew fijo vencido = no llamado (sigue)');
    }
}
