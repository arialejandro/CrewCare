<?php

namespace Tests\Feature\Crew;

use App\Models\Department;
use App\Models\Payee;
use App\Models\PayeeContract;
use App\Models\Production;
use App\Models\User;
use App\Support\ContractStatus;
use App\Support\CurrentProduction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\QaTestCase;

/**
 * CREW · MARCADOR DE CONTRATO (2026-09-10). Junto a cada persona del crew: ¿tiene contrato, le falta
 * capturar el trato, o no tiene? Reúsa InfosheetSigning::missingToAuthorize (mismo mínimo que ya gatea
 * la autorización) — no inventa un tercer concepto. Filtrable y con conteo por alcance. NO bloquea nada.
 * El estado DESALINEADO se REPORTÓ y NO se construyó (el puesto/unidad no es comparable sin ruido).
 */
class ContractStatusMarkerTest extends QaTestCase
{
    private function prod(): Production
    {
        $prod = Production::query()->orderBy('id')->first();
        Production::query()->where('id', '!=', $prod->id)->update(['active' => 0]);
        $prod->forceFill(['active' => 1])->save();
        CurrentProduction::forget();

        return $prod;
    }

    private function crew(string $tag): User
    {
        return $this->makeUser('crew', ['name' => $tag, 'crewlist_visible' => 1, 'activo' => 1]);
    }

    private function admin(): User
    {
        $u = $this->makeUser('super-admin');
        try {
            $u->givePermissionTo('users.view');
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
        }

        return $u;
    }

    /** Contrato crew_work del user. $complete=true = con el mínimo del trato; false = le falta. */
    private function contractFor(User $user, Production $prod, bool $complete): PayeeContract
    {
        $payee = Payee::create([
            'legal_nature' => Payee::NATURE_FISICA,
            'name'         => $user->name,
            'user_id'      => $user->id,
            'is_active'    => 1,
        ]);

        return PayeeContract::create([
            'payee_id'       => $payee->id,
            'production_id'  => $prod->id,
            'concept'        => PayeeContract::CONCEPT_CREW,
            'is_active'      => 1,
            'title'          => $complete ? 'Primer asistente de dirección' : null,
            'department_id'  => $complete ? Department::query()->value('id') : null,
            'fee_amount'     => $complete ? 15000 : 0,
            'effective_date' => $complete ? now()->toDateString() : null,
        ]);
    }

    public function test_estado_por_persona_sin_incompleto_ok(): void
    {
        $prod = $this->prod();

        $sin        = $this->crew('SinContrato' . Str::random(4));           // sin payee → SIN CONTRATO
        $incompleto = $this->crew('Incompleto' . Str::random(4));
        $ok         = $this->crew('ConContrato' . Str::random(4));

        $this->contractFor($incompleto, $prod, false);
        $this->contractFor($ok, $prod, true);

        $states = ContractStatus::forUserIds([$sin->id, $incompleto->id, $ok->id]);

        $this->assertSame(ContractStatus::SIN_CONTRATO, $states[$sin->id]['state']);
        $this->assertSame(ContractStatus::INCOMPLETO,   $states[$incompleto->id]['state']);
        $this->assertSame(ContractStatus::OK,           $states[$ok->id]['state']);

        // El INCOMPLETO reúsa missingLabel: dice QUÉ falta del trato (no un concepto nuevo).
        $this->assertStringContainsString('Falta capturar', $states[$incompleto->id]['detail']);
    }

    public function test_contrato_apagado_o_de_otro_concepto_no_cuenta_como_cobertura(): void
    {
        $prod = $this->prod();

        // Contrato COMPLETO pero is_active=0 → no es cobertura viva → SIN CONTRATO.
        $apagado = $this->crew('Apagado' . Str::random(4));
        $this->contractFor($apagado, $prod, true)->forceFill(['is_active' => 0])->save();

        // Contrato de RENTA de equipo (no crew_work) → no cuenta como contrato de crew → SIN CONTRATO.
        $renta = $this->crew('SoloRenta' . Str::random(4));
        $payee = Payee::create(['legal_nature' => Payee::NATURE_FISICA, 'name' => $renta->name, 'user_id' => $renta->id, 'is_active' => 1]);
        PayeeContract::create([
            'payee_id' => $payee->id, 'production_id' => $prod->id,
            'concept'  => PayeeContract::CONCEPT_RENTAL, 'is_active' => 1, 'title' => 'Cámara', 'fee_amount' => 5000,
        ]);

        $states = ContractStatus::forUserIds([$apagado->id, $renta->id]);
        $this->assertSame(ContractStatus::SIN_CONTRATO, $states[$apagado->id]['state']);
        $this->assertSame(ContractStatus::SIN_CONTRATO, $states[$renta->id]['state']);
    }

    public function test_filtro_sql_y_conteo_casan(): void
    {
        $prod = $this->prod();

        $sin        = $this->crew('FiltSin' . Str::random(4));
        $incompleto = $this->crew('FiltInc' . Str::random(4));
        $ok         = $this->crew('FiltOk' . Str::random(4));
        $this->contractFor($incompleto, $prod, false);
        $this->contractFor($ok, $prod, true);

        $admin = $this->admin();               // all-departments → ve todo (sin acotar)

        $baseFor = fn () => User::applyDepartmentScope(DB::table('users')->where('activo', 1), $admin);

        // El filtro "sin" incluye al sin-contrato y EXCLUYE al que sí lo tiene completo.
        $sinIds = ContractStatus::applyFilter($baseFor(), ContractStatus::FILTER_SIN)->pluck('id')->all();
        $this->assertContains($sin->id, $sinIds);
        $this->assertNotContains($ok->id, $sinIds);
        $this->assertNotContains($incompleto->id, $sinIds);

        // "incompleto" incluye al incompleto y excluye al completo y al sin-contrato.
        $incIds = ContractStatus::applyFilter($baseFor(), ContractStatus::FILTER_INCOMPLETO)->pluck('id')->all();
        $this->assertContains($incompleto->id, $incIds);
        $this->assertNotContains($ok->id, $incIds);
        $this->assertNotContains($sin->id, $incIds);

        // "falta" (unión) los toma a ambos y no al completo.
        $faltaIds = ContractStatus::applyFilter($baseFor(), ContractStatus::FILTER_FALTA)->pluck('id')->all();
        $this->assertContains($sin->id, $faltaIds);
        $this->assertContains($incompleto->id, $faltaIds);
        $this->assertNotContains($ok->id, $faltaIds);

        // Conteos coherentes: falta = sin + incompleto, y los filtros SQL cuentan igual.
        $counts = ContractStatus::counts($baseFor());
        $this->assertSame($counts['sin'] + $counts['incompleto'], $counts['falta']);
        $this->assertSame(count($sinIds), $counts['sin']);
        $this->assertSame(count($incIds), $counts['incompleto']);
        $this->assertSame(count($faltaIds), $counts['falta']);
    }

    public function test_crew_list_pinta_los_chips_de_filtro(): void
    {
        $this->prod();
        $this->actingAs($this->admin());

        $res = $this->get(route('usuarioscrud'))->assertOk();
        $res->assertSee('Sin contrato');            // chip de filtro
        $res->assertSee('Contrato incompleto');     // chip de filtro
        $res->assertSee('miembros activos');

        // El filtro por query param responde OK (paginación exacta por SQL).
        $this->get(route('usuarioscrud', ['contract' => 'sin']))->assertOk();
        $this->get(route('usuarioscrud', ['contract' => 'incompleto']))->assertOk();
    }

    public function test_dados_de_baja_muestra_el_marcador(): void
    {
        $prod = $this->prod();

        $baja = $this->crew('BajaSinContrato' . Str::random(4));
        $baja->forceFill(['activo' => 0])->save();

        $this->actingAs($this->admin());
        $this->get(route('crew.inactive'))
            ->assertOk()
            ->assertSee($baja->name)
            ->assertSee('Sin contrato');   // el marcador aparece junto a la persona dada de baja
    }
}
