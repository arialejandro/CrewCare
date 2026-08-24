<?php

namespace Tests\Feature\Crew;

use App\Models\Department;
use App\Models\Payee;
use App\Models\PayeeContract;
use App\Models\User;
use App\Support\CurrentProduction;
use App\Support\DayRosterBuilder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\QaTestCase;

/**
 * VISTA DEL ROSTER "¿quién trabaja hoy?" (2026-08-22).
 *
 * Los 4 estados los DERIVA DayRosterBuilder con la consulta agregada (no itera rosterStateOn).
 * Verifica estados, aislamiento por depto (HOD ve lo suyo), one-day-player, vencimiento, vacío
 * honesto, y que el conteo de consultas es CONSTANTE (no 2 por persona).
 */
class RosterViewTest extends QaTestCase
{
    private int $prodId;
    private string $dayStr;
    private Carbon $day;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prodId = (int) DB::table('productions')->min('id');
        DB::table('productions')->where('id', $this->prodId)->update(['active' => 1]);
        CurrentProduction::forget();
        $this->day = Carbon::parse('2026-08-22')->startOfDay();
        $this->dayStr = $this->day->toDateString();
    }

    private function deptId(string $name): int
    {
        return (int) Department::where('name', $name)->value('id');
    }

    private function putInDept(User $u, string $dept, bool $lead = false): void
    {
        DB::table('production_user')->updateOrInsert(
            ['production_id' => $this->prodId, 'user_id' => $u->id],
            ['department_id' => $this->deptId($dept), 'role' => 'crew', 'is_lead' => $lead ? 1 : 0, 'created_at' => now(), 'updated_at' => now()]
        );
    }

    /** Crea una persona de crew con su contrato crew_work y (opcional) fecha ese día / sobre firmado. */
    private function makeCrew(string $dept, array $o = []): array
    {
        // ncreditos = etiqueta → User::displayName la devuelve tal cual (así el test puede keyear por ella).
        $label = $o['name'] ?? ('Crew ' . Str::random(4));
        $u = $this->makeUser('crew', ['name' => $label, 'lname' => 'QA', 'ncreditos' => $label]);
        $this->putInDept($u, $dept);
        $payee = Payee::create(['legal_nature' => 'fisica', 'name' => $u->name, 'user_id' => $u->id]);
        $c = $payee->contracts()->create([
            'production_id'         => $this->prodId,
            'concept'               => 'crew_work',
            'department_id'         => $this->deptId($dept),
            'contracted_by_user_id' => $u->id,
            'payment_frequency'     => $o['freq'] ?? 'weekly',
            'is_active'             => $o['is_active'] ?? 1,
            'definitive_end_date'   => $o['end_date'] ?? null,
        ]);
        if (! empty($o['called'])) {
            $when = $o['called'] === true ? $this->dayStr : $o['called'];
            DB::table('payee_contract_work_dates')->insert(['payee_contract_id' => $c->id, 'work_date' => $when]);
        }
        if (! empty($o['signed'])) {
            DB::table('contract_envelopes')->insert([
                'payee_contract_id' => $c->id, 'production_id' => $this->prodId,
                'status' => 'completed', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        return [$u, $c];
    }

    /** Aplana todas las personas del roster a name=>state. */
    private function statesOf(array $roster): array
    {
        $out = [];
        foreach ($roster['groups'] as $g) {
            foreach ($g['people'] as $p) {
                $out[$p['name']] = $p['state'];
            }
        }
        return $out;
    }

    private function seeAllViewer(): User
    {
        return $this->makeUser('super-admin'); // crew.view.all-departments → sin filtro de depto
    }

    public function test_los_cuatro_estados(): void
    {
        [$called]  = $this->makeCrew('Arte', ['name' => 'Ana Llamada',  'called' => true, 'signed' => true]);
        [$pending] = $this->makeCrew('Arte', ['name' => 'Pau Pendiente', 'called' => true, 'signed' => false]);
        [$notcall] = $this->makeCrew('Arte', ['name' => 'Nora NoLlam',   'called' => false, 'signed' => true]);
        [$outInac] = $this->makeCrew('Arte', ['name' => 'Ivo Inactivo',  'called' => true, 'signed' => true, 'is_active' => 0]);

        $roster = DayRosterBuilder::build($this->seeAllViewer(), $this->day);
        $states = $this->statesOf($roster);

        $this->assertSame(PayeeContract::ROSTER_CALLED, $states['Ana Llamada']);
        $this->assertSame(PayeeContract::ROSTER_PENDING_SIGNATURE, $states['Pau Pendiente'], 'fecha ese día sin sobre = pendiente de firma, NO fuera');
        $this->assertSame(PayeeContract::ROSTER_NOT_CALLED, $states['Nora NoLlam']);
        $this->assertSame(PayeeContract::ROSTER_OUT, $states['Ivo Inactivo']);

        $this->assertSame(1, $roster['counts'][PayeeContract::ROSTER_CALLED]);
        $this->assertSame(1, $roster['counts'][PayeeContract::ROSTER_PENDING_SIGNATURE]);
    }

    public function test_hod_solo_ve_su_departamento(): void
    {
        $this->makeCrew('Arte', ['name' => 'Arte Uno', 'called' => true, 'signed' => true]);
        $this->makeCrew('Transportación', ['name' => 'Transpo Uno', 'called' => true, 'signed' => true]);

        // Un HOD de Arte (sin all-departments) → solo ve Arte.
        $hod = $this->makeUser('hod');
        $this->putInDept($hod, 'Arte', true);

        $states = $this->statesOf(DayRosterBuilder::build($hod, $this->day));
        $this->assertArrayHasKey('Arte Uno', $states);
        $this->assertArrayNotHasKey('Transpo Uno', $states, 'el HOD de Arte no ve el roster de Transporte');
    }

    public function test_one_day_player_solo_en_su_dia(): void
    {
        // Llamado SOLO el 22; firmado. En el 22 = llamado; en el 23 = no llamado (sigue en el sistema).
        $this->makeCrew('Arte', ['name' => 'Dani DayPlayer', 'called' => $this->dayStr, 'signed' => true]);

        $viewer = $this->seeAllViewer();
        $d22 = $this->statesOf(DayRosterBuilder::build($viewer, $this->day));
        $d23 = $this->statesOf(DayRosterBuilder::build($viewer, $this->day->copy()->addDay()));

        $this->assertSame(PayeeContract::ROSTER_CALLED, $d22['Dani DayPlayer']);
        $this->assertSame(PayeeContract::ROSTER_NOT_CALLED, $d23['Dani DayPlayer'], 'no desaparece: queda no-llamado el día siguiente');
    }

    public function test_vencimiento_saca_al_day_player_pero_no_al_fijo(): void
    {
        // Ambos con vigencia hasta el 22, llamados+firmados el 22. El 23 (> vigencia), PARTE C:
        //  · DAY PLAYER → FUERA (el vencimiento opera).
        //  · CREW FIJO  → sigue en el roster como NO LLAMADO (no cae a fuera por vencer).
        $this->makeCrew('Arte', ['name' => 'Dani Day', 'freq' => 'day_player', 'called' => true, 'signed' => true, 'end_date' => $this->dayStr]);
        $this->makeCrew('Arte', ['name' => 'Fija Fer', 'freq' => 'weekly',     'called' => true, 'signed' => true, 'end_date' => $this->dayStr]);

        $viewer = $this->seeAllViewer();
        $d22 = $this->statesOf(DayRosterBuilder::build($viewer, $this->day));
        $d23 = $this->statesOf(DayRosterBuilder::build($viewer, $this->day->copy()->addDay()));

        $this->assertSame(PayeeContract::ROSTER_CALLED, $d22['Dani Day']);
        $this->assertSame(PayeeContract::ROSTER_CALLED, $d22['Fija Fer']);

        $this->assertSame(PayeeContract::ROSTER_OUT,        $d23['Dani Day'], 'day player vencido = fuera');
        $this->assertSame(PayeeContract::ROSTER_NOT_CALLED, $d23['Fija Fer'], 'crew fijo vencido = sigue (no llamado)');

        $this->assertDatabaseHas('payee_contracts', ['definitive_end_date' => $this->dayStr]); // no se borró
    }

    public function test_desactivado_no_aparece_en_el_roster(): void
    {
        // PARTE G: users.activo=0 desaparece del roster (no se borra, se desactiva).
        [$u] = $this->makeCrew('Arte', ['name' => 'Baja Uno', 'called' => true, 'signed' => true]);
        DB::table('users')->where('id', $u->id)->update(['activo' => 0]);

        $states = $this->statesOf(DayRosterBuilder::build($this->seeAllViewer(), $this->day));
        $this->assertArrayNotHasKey('Baja Uno', $states, 'un desactivado no aparece en ningún estado');
    }

    public function test_dia_sin_contratos_muestra_vacio(): void
    {
        $roster = DayRosterBuilder::build($this->seeAllViewer(), $this->day);
        $this->assertSame([], $roster['groups'], 'sin contratos activos, lista vacía honesta (nada de rellenar)');
        $this->assertSame(0, $roster['counts']['total']);
    }

    public function test_consulta_agregada_es_constante_no_por_persona(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->makeCrew('Arte', ['called' => true, 'signed' => $i % 2 === 0]);
        }
        $viewer = $this->seeAllViewer();

        DB::connection()->enableQueryLog();
        DayRosterBuilder::build($viewer, $this->day);
        $n = count(DB::connection()->getQueryLog());
        DB::connection()->disableQueryLog();

        // Catálogos (departments, positions), pivote, la agregada, y CurrentProduction → un puñado.
        // Lo clave: NO son 2 por persona (25 personas = 50+). Cota generosa y CONSTANTE.
        $this->assertLessThanOrEqual(12, $n, "El roster debe resolverse en consultas constantes, no por persona (25 personas → {$n}; iterar rosterStateOn serían 50+).");
    }

    public function test_ruta_detras_de_feature_flag_y_scoped(): void
    {
        $this->makeCrew('Arte', ['name' => 'Ruta Arte', 'called' => true, 'signed' => true]);

        // (2026-08-23) RETIRADO: con el flag apagado (default) la ruta no existe, aun con permiso.
        $this->actingAsRole('coordinator');
        $this->get(route('roster.index', ['date' => $this->dayStr]))->assertNotFound();

        // Encendido el flag → 200 para quien tiene crew.view.
        config(['features.roster_day_view' => true]);
        \App\Support\Features::flush();
        $this->get(route('roster.index', ['date' => $this->dayStr]))->assertOk();

        // crew (sin crew.view) → 403 lo corta el middleware antes del flag.
        $this->actingAsRole('crew');
        $this->get(route('roster.index'))->assertForbidden();
    }
}
