<?php

namespace Tests\Feature\Contract;

use App\Events\ContractEnvelopeCompleted;
use App\Exceptions\ContractEnvelopeException;
use App\Http\Controllers\ContractSignController;
use App\Models\ContractClause;
use App\Models\ContractConsent;
use App\Models\ContractEnvelope;
use App\Models\ContractEnvelopeRecipient;
use App\Models\Payee;
use App\Models\PayeeContract;
use App\Models\Position;
use App\Models\Setting;
use App\Models\User;
use App\Support\Branding;
use App\Support\ContractEmitter;
use App\Support\ContractEnvelopeBuilder;
use App\Support\ContractSigning;
use App\Support\CurrentProduction;
use App\Support\SignaturePositions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\QaTestCase;

/**
 * EL CONTRATO · PASO C — EL SOBRE Y LA RUTA DE FIRMA. Verificación del bloque: paquete + ruta
 * secuencial + papeles por puesto (vacante/duplicado = error) + persona congelada + 4 timestamps
 * + IP/método + consentimiento aparte + firma del contratado sin sesión con 2º factor + puerta del
 * roster + inmutabilidad del completado.
 */
class ContractEnvelopeTest extends QaTestCase
{
    private $prodId;
    private $prepPos;
    private $bindPos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prodId = DB::table('productions')->min('id');
        DB::table('productions')->where('id', $this->prodId)->update(['active' => 1]);
        CurrentProduction::forget();

        [$this->prepPos, $this->bindPos] = Position::orderBy('id')->take(2)->pluck('id')->all();
        Setting::updateOrCreate(['key' => SignaturePositions::KEY_PREPARER], ['value' => $this->prepPos]);
        Setting::updateOrCreate(['key' => SignaturePositions::KEY_BINDER],   ['value' => $this->bindPos]);
        $this->setContractor();
    }

    private function setContractor(array $ov = []): void
    {
        foreach (array_merge([
            'company_name' => 'Pimienta Films SA', 'rfc' => 'PFI860101AB3', 'office_address' => 'Reforma 222',
            'representante_legal' => 'Ana Pérez', 'correo_contratante' => 'legal@pimienta.mx',
        ], $ov) as $k => $v) {
            Setting::updateOrCreate(['key' => $k], ['value' => $v]);
        }
        Branding::forget();
    }

    private function attachPosition(User $u, $positionId): void
    {
        DB::table('production_user')->updateOrInsert(
            ['production_id' => $this->prodId, 'user_id' => $u->id],
            ['position_id' => $positionId, 'role' => 'crew', 'is_lead' => 0, 'created_at' => now(), 'updated_at' => now()]
        );
    }

    /** Prepara los dos internos (preparador + obliga) en sus puestos. */
    private function seatInternals(): array
    {
        $prep = $this->makeUser('coordinator'); $prep->forceFill(['name' => 'Prep', 'lname' => 'Uno', 'email' => 'prep@x.mx'])->save();
        $bind = $this->makeUser('line-producer'); $bind->forceFill(['name' => 'Bind', 'lname' => 'Dos', 'email' => 'bind@x.mx'])->save();
        $this->attachPosition($prep, $this->prepPos);
        $this->attachPosition($bind, $this->bindPos);
        return [$prep, $bind];
    }

    private function emitContract(string $concept, Payee $payee): PayeeContract
    {
        Storage::disk('local')->put('contracts/clauses/x.pdf', '%PDF-1.7 clausulado real');
        $contract = $payee->contracts()->create([
            'concept' => $concept, 'is_active' => 1, 'production_id' => $this->prodId, 'crew_activity' => 'Gaffer',
        ]);
        $clause = ContractClause::create([
            'production_id' => $this->prodId, 'name' => 'Contrato', 'applies_to' => [$concept],
            'language' => 'es', 'file_path' => 'contracts/clauses/x.pdf', 'version' => 1, 'is_active' => 1,
        ]);
        ContractEmitter::emit($contract, $clause, 'es', null);
        return $contract->fresh();
    }

    private function crewPayee(string $borndate = '1990-05-15'): Payee
    {
        $u = $this->makeUser('crew'); $u->forceFill(['borndate' => $borndate])->save();
        return Payee::create(['legal_nature' => 'fisica', 'name' => 'Juan Crew', 'user_id' => $u->id]);
    }

    public function test_envelope_groups_package_and_freezes_recipients(): void
    {
        Storage::fake('local');
        $this->seatInternals();
        $contract = $this->emitContract(PayeeContract::CONCEPT_CREW, $this->crewPayee());

        $env = ContractEnvelopeBuilder::build($contract, null);

        // Paquete: carátula + clausulado en el snapshot.
        $kinds = collect($env->documents)->pluck('kind')->all();
        $this->assertContains('caratula', $kinds);
        $this->assertContains('clause', $kinds);

        // Tres papeles, en orden, con la persona CONGELADA.
        $recs = $env->orderedRecipients()->get();
        $this->assertEqualsCanonicalizing(['preparer', 'contracted', 'binder'], $recs->pluck('role')->all());
        $prep = $recs->firstWhere('role', 'preparer');
        $this->assertSame('Prep Uno', $prep->name);
        $this->assertSame('prep@x.mx', $prep->email);
        $this->assertSame('Pimienta Films SA', $prep->empresa);

        // Sellado (integridad del paquete).
        $this->assertTrue($env->verifyLatestSignature());
    }

    public function test_vacant_or_duplicate_position_errors_clearly(): void
    {
        Storage::fake('local');
        $contract = $this->emitContract(PayeeContract::CONCEPT_CREW, $this->crewPayee());

        // Nadie en el puesto de preparador → error de VACANTE.
        $this->expectException(ContractEnvelopeException::class);
        ContractEnvelopeBuilder::build($contract, null);
    }

    public function test_duplicate_position_errors(): void
    {
        Storage::fake('local');
        // Dos personas en el puesto de preparador.
        $a = $this->makeUser('coordinator'); $this->attachPosition($a, $this->prepPos);
        $b = $this->makeUser('coordinator'); $this->attachPosition($b, $this->prepPos);
        $bind = $this->makeUser('line-producer'); $this->attachPosition($bind, $this->bindPos);

        $contract = $this->emitContract(PayeeContract::CONCEPT_CREW, $this->crewPayee());

        $this->expectException(ContractEnvelopeException::class);
        ContractEnvelopeBuilder::build($contract, null);
    }

    public function test_route_advances_and_completes_and_notifies(): void
    {
        Storage::fake('local');
        Event::fake([ContractEnvelopeCompleted::class]);
        $this->seatInternals();
        $env = ContractEnvelopeBuilder::build($this->emitContract(PayeeContract::CONCEPT_CREW, $this->crewPayee()), null);
        ContractSigning::send($env);

        $order = $env->orderedRecipients()->get();
        $this->assertSame((int) $order[0]->id, (int) $env->fresh()->current_recipient_id, 'la ruta empieza en el primero');

        ContractSigning::sign($order[0]->fresh(), 'authenticated', '1.1.1.1');
        $this->assertSame((int) $order[1]->id, (int) $env->fresh()->current_recipient_id, 'avanza sola al siguiente');

        ContractSigning::sign($order[1]->fresh(), 'signed_link_2fa', '2.2.2.2');
        ContractSigning::sign($order[2]->fresh(), 'authenticated', '3.3.3.3');

        $this->assertTrue($env->fresh()->isCompleted(), 'al firmar el último, se completa');
        Event::assertDispatched(ContractEnvelopeCompleted::class);

        // 4 marcas + ip + método quedaron.
        $r = $order[1]->fresh();
        $this->assertNotNull($r->signed_at);
        $this->assertNotNull($r->viewed_at);
        $this->assertSame('2.2.2.2', $r->ip_address);
        $this->assertSame('signed_link_2fa', $r->sign_method);
    }

    public function test_changing_position_holder_does_not_redirect_a_sent_envelope(): void
    {
        Storage::fake('local');
        [$prep] = $this->seatInternals();
        $env = ContractEnvelopeBuilder::build($this->emitContract(PayeeContract::CONCEPT_CREW, $this->crewPayee()), null);
        ContractSigning::send($env);

        // El puesto cambia de manos DESPUÉS de enviar.
        $other = $this->makeUser('coordinator'); $other->forceFill(['name' => 'Nuevo'])->save();
        $this->attachPosition($other, $this->prepPos);
        DB::table('production_user')->where('user_id', $prep->id)->update(['position_id' => null]);

        // El destinatario del sobre sigue congelado en la persona original.
        $rec = $env->recipients()->where('role', 'preparer')->first();
        $this->assertSame((int) $prep->id, (int) $rec->user_id, 'un sobre enviado no se redirige');
        $this->assertSame('Prep Uno', $rec->name);
    }

    public function test_factor_is_borndate_for_crew_and_rfc_for_non_crew(): void
    {
        Storage::fake('local');
        $this->seatInternals();

        // Crew → fecha de nacimiento.
        $crewEnv = ContractEnvelopeBuilder::build($this->emitContract(PayeeContract::CONCEPT_CREW, $this->crewPayee('1988-03-04')), null);
        $crewRec = $crewEnv->recipients()->where('role', 'contracted')->first();
        $this->assertSame('borndate', ContractSigning::factorType($crewEnv));
        $this->assertTrue(ContractSigning::verifyFactor($crewRec, '1988-03-04'));
        $this->assertFalse(ContractSigning::verifyFactor($crewRec, '2000-01-01'));

        // No-crew (servicio, proveedor con RFC) → RFC como contraseña.
        $vendor = Payee::create(['legal_nature' => 'moral', 'name' => 'Ambulancias SA', 'rfc' => 'AMB980101XY7']);
        $svcEnv = ContractEnvelopeBuilder::build($this->emitContract(PayeeContract::CONCEPT_SERVICE, $vendor), null);
        $svcRec = $svcEnv->recipients()->where('role', 'contracted')->first();
        $this->assertSame('rfc', ContractSigning::factorType($svcEnv));
        $this->assertTrue(ContractSigning::verifyFactor($svcRec, 'amb980101xy7'), 'RFC normalizado');
        $this->assertFalse(ContractSigning::verifyFactor($svcRec, 'OTRO000000AAA'));
    }

    public function test_consent_recorded_once_and_reused(): void
    {
        Storage::fake('local');
        $this->seatInternals();
        $env = ContractEnvelopeBuilder::build($this->emitContract(PayeeContract::CONCEPT_CREW, $this->crewPayee()), null);
        $rec = $env->recipients()->where('role', 'contracted')->first();

        ContractSigning::recordConsent($rec, '1.2.3.4');
        ContractSigning::recordConsent($rec, '1.2.3.4');   // segunda vez: NO duplica

        // El contratado crew ES un usuario → el consentimiento se llavea por USER (vale para sobres posteriores).
        $this->assertSame(1, ContractConsent::where('consenter_type', 'user')->where('consenter_id', $rec->user_id)->count());
        $this->assertTrue(ContractConsent::has('user', (int) $rec->user_id));
    }

    public function test_completed_envelope_refuses_more_signatures(): void
    {
        Storage::fake('local');
        $this->seatInternals();
        $env = ContractEnvelopeBuilder::build($this->emitContract(PayeeContract::CONCEPT_CREW, $this->crewPayee()), null);
        ContractSigning::send($env);
        foreach ($env->orderedRecipients()->get() as $r) {
            ContractSigning::sign($r->fresh(), 'authenticated', '1.1.1.1');
        }
        $this->assertTrue($env->fresh()->isCompleted());

        // Un sobre completado no admite más firmas.
        $this->expectException(ContractEnvelopeException::class);
        ContractSigning::sign($env->recipients()->first()->fresh(), 'authenticated', '1.1.1.1');
    }

    public function test_contracted_crew_signs_via_signed_link_with_second_factor(): void
    {
        Storage::fake('local');
        $this->seatInternals();
        // Orden: contratado primero, para aislar su firma.
        Setting::updateOrCreate(['key' => SignaturePositions::KEY_ORDER], ['value' => 'contracted,preparer,binder']);
        Branding::forget();

        $env = ContractEnvelopeBuilder::build($this->emitContract(PayeeContract::CONCEPT_CREW, $this->crewPayee('1991-07-09')), null);
        ContractSigning::send($env);
        $rec = $env->recipients()->where('role', 'contracted')->first();

        // Sin sesión: el enlace firmado muestra el segundo factor.
        $this->get(ContractSignController::signUrl($rec))->assertOk()->assertSee('Verifica tu identidad');

        // Cotejar la fecha de nacimiento → pasa.
        $verifyUrl = URL::temporarySignedRoute('contracts.sign.verify', now()->addHours(3), ['recipient' => $rec->id]);
        $this->post($verifyUrl, ['factor_value' => '1991-07-09'])->assertRedirect();

        // Ahora ve la página de firma.
        $this->get(ContractSignController::signUrl($rec))->assertOk()->assertSee('Firma tu contrato');

        // Firmar el paquete (con consentimiento).
        $signUrl = URL::temporarySignedRoute('contracts.sign.do', now()->addHours(3), ['recipient' => $rec->id]);
        $this->post($signUrl, ['consent' => 1])->assertRedirect();

        $this->assertTrue($rec->fresh()->isSigned());
        $this->assertNotNull($rec->fresh()->ip_address);
        // El contratado crew es usuario → el consentimiento se llavea por USER.
        $this->assertTrue(ContractConsent::has('user', (int) $rec->user_id), 'se registró el consentimiento');
    }
}
