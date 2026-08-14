<?php

namespace Tests\Feature\Infosheet;

use App\Models\ContractEnvelope;
use App\Models\ContractEnvelopeRecipient;
use App\Models\Payee;
use App\Models\PayeeContract;
use App\Models\User;
use App\Support\CrewRosterBuilder;
use App\Support\ExternalParty;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\QaTestCase;

/**
 * EL INFOSHEET · FASE 4 — el contratado NO-CREW: usuario externo lite (sin login) + acceso por hash
 * de un solo uso + gate del listado. La firma en sí sigue por el flujo sin sesión (RFC), ya probado.
 */
class ExternalPartyTest extends QaTestCase
{
    private int $prodId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prodId = (int) DB::table('productions')->min('id');
        DB::table('productions')->where('id', $this->prodId)->update(['active' => 1]);
    }

    private function nonCrewPayee(): Payee
    {
        return Payee::create(['legal_nature' => 'moral', 'name' => 'Proveedor SA', 'rfc' => 'PRO980101AB2']);
    }

    public function test_provision_creates_lite_external_user_idempotent(): void
    {
        $payee = $this->nonCrewPayee();

        $u1 = ExternalParty::provisionForPayee($payee, 'prov@x.mx', 'Proveedor Firmante');
        $this->assertTrue($u1->is_external);
        $this->assertFalse($u1->crewlist_visible);
        $this->assertSame(0, (int) $u1->activo);
        $this->assertSame('prov@x.mx', $u1->email);
        $this->assertSame((int) $u1->id, (int) $payee->fresh()->user_id);
        // Contraseña inusable → no inicia sesión.
        $this->assertFalse(Auth::attempt(['email' => 'prov@x.mx', 'password' => 'secret']));

        // Idempotente: segunda vez devuelve el MISMO usuario (payee ya ligado), aunque cambie el correo.
        $u2 = ExternalParty::provisionForPayee($payee->fresh(), 'otro@x.mx', 'Otro');
        $this->assertSame((int) $u1->id, (int) $u2->id);
    }

    public function test_access_token_is_single_use(): void
    {
        $payee = $this->nonCrewPayee();
        $user  = ExternalParty::provisionForPayee($payee, 'prov@x.mx');
        ExternalParty::mintAccessLink($user);
        $token = $user->fresh()->external_access_token;
        $this->assertNotEmpty($token);

        $this->assertSame((int) $user->id, (int) optional(ExternalParty::consume($token))->id, 'primer uso vale');
        $this->assertNull(ExternalParty::consume($token), 'segundo uso ya no');
    }

    public function test_access_link_redirects_to_pending_sign_then_is_dead(): void
    {
        $payee = $this->nonCrewPayee();
        $user  = ExternalParty::provisionForPayee($payee, 'prov@x.mx', 'Proveedor Firmante');

        // Un sobre ENVIADO con el externo como contratado (armado mínimo, sin el builder).
        $contract = $payee->contracts()->create(['concept' => PayeeContract::CONCEPT_SERVICE, 'production_id' => $this->prodId, 'is_active' => 1]);
        $env = ContractEnvelope::create([
            'payee_contract_id' => $contract->id, 'production_id' => $this->prodId,
            'status' => ContractEnvelope::STATUS_SENT, 'documents' => [],
        ]);
        $rec = $env->recipients()->create([
            'role' => ContractEnvelopeRecipient::ROLE_CONTRACTED, 'payee_id' => $payee->id, 'user_id' => $user->id,
            'name' => 'Proveedor Firmante', 'email' => 'prov@x.mx',
            'status' => ContractEnvelopeRecipient::STATUS_SENT, 'sort_order' => 0,
        ]);
        $env->update(['current_recipient_id' => $rec->id]);

        ExternalParty::mintAccessLink($user);
        $token = $user->fresh()->external_access_token;

        // 1er uso → redirige a la firma; 2º uso → 410 (un solo uso).
        $this->get(route('external.access', ['token' => $token]))->assertRedirect();
        $this->get(route('external.access', ['token' => $token]))->assertStatus(410);
    }

    public function test_roster_hides_crewlist_invisible_users(): void
    {
        $admin = $this->makeUser('super-admin');
        $this->makeUser('crew', ['email' => 'vis@x.mx', 'crewlist_visible' => 1]);
        $this->makeUser('crew', ['email' => 'hid@x.mx', 'crewlist_visible' => 0]);

        $roster = CrewRosterBuilder::build($admin);
        $emails = collect($roster['groups'])->flatMap(fn ($g) => collect($g['people'])->pluck('email'))->all();

        $this->assertContains('vis@x.mx', $emails);
        $this->assertNotContains('hid@x.mx', $emails);
    }
}
