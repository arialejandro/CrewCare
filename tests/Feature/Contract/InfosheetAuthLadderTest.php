<?php

namespace Tests\Feature\Contract;

use App\Models\Payee;
use App\Models\PayeeContract;
use App\Models\Position;
use App\Models\Setting;
use App\Support\Branding;
use App\Support\CurrentProduction;
use App\Support\InfosheetSigning;
use App\Support\SignaturePositions;
use Illuminate\Support\Facades\DB;
use Tests\QaTestCase;

/**
 * EL INFOSHEET · B3 — ESCALERA DE AUTORIZACIÓN. Dos modos configurables (route-config):
 *  - PARALELO (default): cualquier autorizador aprueba en cualquier orden.
 *  - SECUENCIAL: aprueban EN ORDEN (nivel a nivel); el de arriba espera a que el de abajo apruebe.
 */
class InfosheetAuthLadderTest extends QaTestCase
{
    private int $prodId;
    private int $pos1;   // nivel 1
    private int $pos2;   // nivel 2
    private $userA;      // ocupa nivel 1
    private $userB;      // ocupa nivel 2
    private PayeeContract $contract;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prodId = (int) DB::table('productions')->min('id');
        DB::table('productions')->where('id', $this->prodId)->update(['active' => 1]);
        CurrentProduction::forget();

        [$this->pos1, $this->pos2] = Position::orderBy('id')->take(2)->pluck('id')->all();

        // Dos autorizadores EN ORDEN: pos1 (nivel 1) → pos2 (nivel 2).
        Setting::updateOrCreate(['key' => SignaturePositions::KEY_AUTHORIZERS], ['value' => json_encode([$this->pos1, $this->pos2])]);
        Branding::forget();

        $this->userA = $this->makeUser('coordinator');
        $this->userB = $this->makeUser('coordinator');
        $this->seatPos($this->userA, $this->pos1);
        $this->seatPos($this->userB, $this->pos2);

        $payee = Payee::create(['legal_nature' => 'fisica', 'name' => 'Juan Crew']);
        // Trato COMPLETO: el candado de completitud es previo a la escalera (autorizar emite).
        $this->contract = $payee->contracts()->create([
            'concept' => PayeeContract::CONCEPT_CREW, 'is_active' => 1, 'production_id' => $this->prodId, 'crew_activity' => 'x',
            'title' => 'Gaffer', 'department_id' => DB::table('departments')->min('id'), 'fee_amount' => 50000,
            'effective_date' => now()->toDateString(),
        ]);
    }

    private function seatPos($user, int $posId): void
    {
        DB::table('production_user')->updateOrInsert(
            ['production_id' => $this->prodId, 'user_id' => $user->id],
            ['position_id' => $posId, 'role' => 'crew', 'created_at' => now(), 'updated_at' => now()]
        );
    }

    private function setSequential(bool $on): void
    {
        Setting::updateOrCreate(['key' => SignaturePositions::KEY_AUTH_SEQUENTIAL], ['value' => $on ? '1' : '0']);
        Branding::forget();
    }

    public function test_parallel_lets_any_authorizer_go_first(): void
    {
        $this->setSequential(false);
        $this->assertTrue(InfosheetSigning::canAuthorize($this->userA, $this->contract));
        $this->assertTrue(InfosheetSigning::canAuthorize($this->userB, $this->contract));
        $this->assertCount(2, InfosheetSigning::availableSlots($this->contract));
    }

    public function test_sequential_ladder_opens_one_level_at_a_time(): void
    {
        $this->setSequential(true);

        // Solo el nivel 1 está disponible; el nivel 2 espera su turno.
        $this->assertTrue(InfosheetSigning::canAuthorize($this->userA, $this->contract));
        $this->assertFalse(InfosheetSigning::canAuthorize($this->userB, $this->contract), 'el nivel 2 espera');
        $this->assertCount(1, InfosheetSigning::availableSlots($this->contract));

        // El estado marca el nivel 2 como BLOQUEADO (esperando turno).
        $status = InfosheetSigning::statusFor($this->contract);
        $this->assertFalse($status[0]['blocked']);
        $this->assertTrue($status[1]['blocked']);

        // El nivel 1 aprueba → se abre el nivel 2.
        $img = 'data:image/png;base64,' . str_repeat('A', 120);
        InfosheetSigning::authorize($this->contract->fresh(), $this->userA, $img, null);

        $this->assertTrue(InfosheetSigning::canAuthorize($this->userB, $this->contract->fresh()), 'ahora le toca al nivel 2');
        $this->assertFalse(InfosheetSigning::statusFor($this->contract->fresh())[1]['blocked'], 'ya no está bloqueado');
    }
}
