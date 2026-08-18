<?php

namespace Tests\Feature\Contract;

use App\Events\ContractEnvelopeCompleted;
use App\Exceptions\ContractEnvelopeException;
use App\Http\Controllers\ContractSignController;
use App\Listeners\EmailSignedContractToParty;
use App\Models\ContractClause;
use App\Models\ContractConsent;
use App\Models\ContractEnvelope;
use App\Models\ContractEnvelopeRecipient;
use App\Models\ContractTemplate;
use App\Models\Payee;
use App\Models\PayeeContract;
use App\Models\Position;
use App\Models\Setting;
use App\Models\User;
use App\Support\Branding;
use App\Support\ContractEmitter;
use App\Support\ContractEnvelopeBuilder;
use App\Support\ContractSigning;
use App\Support\ContractTemplateRenderer;
use App\Support\CurrentProduction;
use App\Support\SignaturePositions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
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

    // ── B1 · DESTINATARIOS DE COPIA / ENTREGA-CERTIFICADA ────────────────────

    /** Una copia NO entra a la ruta de firma: no es turno, no bloquea, no cuenta para completar. */
    public function test_copy_recipient_stays_out_of_the_signing_route(): void
    {
        Storage::fake('local');
        $this->seatInternals();
        $env  = ContractEnvelopeBuilder::build($this->emitContract(PayeeContract::CONCEPT_CREW, $this->crewPayee()), null);
        $copy = $env->recipients()->create([
            'role' => ContractEnvelopeRecipient::ROLE_COPY, 'delivery_mode' => ContractEnvelopeRecipient::DELIVERY_COPY,
            'sort_order' => 99, 'name' => 'Contabilidad', 'email' => 'conta@qa.test', 'status' => 'pending',
        ]);

        ContractSigning::send($env);
        $this->assertNotSame((int) $copy->id, (int) $env->fresh()->current_recipient_id, 'el turno nunca es la copia');
        $this->assertTrue($env->fresh()->currentRecipient->isSigner());

        foreach ($env->orderedRecipients()->get() as $r) {
            ContractSigning::sign($r->fresh(), 'authenticated', '1.1.1.1');
        }
        $this->assertTrue($env->fresh()->isCompleted(), 'completa solo con firmantes; la copia no bloquea');
        $this->assertFalse($copy->fresh()->isSigned());
        $this->assertFalse($env->orderedRecipients()->get()->contains('id', $copy->id), 'la ruta excluye la copia');
        $this->assertTrue($env->copyRecipients()->get()->contains('id', $copy->id), 'la copia vive en copyRecipients');
    }

    /** Al COMPLETARSE, cada copia recibe su entrega certificada: correo + delivered_at + evento. */
    public function test_completion_delivers_certified_copy_to_copy_recipients(): void
    {
        Storage::fake('local');
        $this->seatInternals();
        $env  = ContractEnvelopeBuilder::build($this->emitContract(PayeeContract::CONCEPT_CREW, $this->crewPayee()), null);
        $copy = $env->recipients()->create([
            'role' => ContractEnvelopeRecipient::ROLE_COPY, 'delivery_mode' => ContractEnvelopeRecipient::DELIVERY_COPY,
            'sort_order' => 99, 'name' => 'Legal', 'email' => 'legal@qa.test', 'status' => 'pending',
        ]);
        ContractSigning::send($env);

        $autograph = 'data:image/png;base64,' . str_repeat('A', 160);
        foreach ($env->orderedRecipients()->get() as $r) {
            ContractSigning::sign($r->fresh(), 'authenticated', '9.9.9.9', $autograph);
        }
        $this->assertTrue($env->fresh()->isCompleted());

        $this->assertNotNull($copy->fresh()->delivered_at, 'delivered_at sellado');
        $this->assertNotNull(
            \App\Models\ContractEnvelopeEvent::where('envelope_id', $env->id)
                ->where('event', \App\Models\ContractEnvelopeEvent::COPY_DELIVERED)->first(),
            'quedó el evento copy_delivered en la bitácora'
        );

        $toLegal = collect(Mail::getSymfonyTransport()->messages())
            ->contains(fn ($m) => $m->getOriginalMessage()->getTo()[0]->getAddress() === 'legal@qa.test');
        $this->assertTrue($toLegal, 'la copia recibió su correo de entrega');
    }

    /** El controlador agrega/quita copias con la guarda de captura; un sobre retirado no admite copias. */
    public function test_add_and_remove_copy_via_controller(): void
    {
        Storage::fake('local');
        $this->seatInternals();
        $this->actingAsRole('super-admin');
        $env = ContractEnvelopeBuilder::build($this->emitContract(PayeeContract::CONCEPT_CREW, $this->crewPayee()), null);

        $this->post(route('contracts.envelope.copy.add', $env), ['name' => 'Conta', 'email' => 'conta@qa.test'])->assertRedirect();
        $copy = $env->copyRecipients()->first();
        $this->assertNotNull($copy);
        $this->assertSame('conta@qa.test', $copy->email);

        $this->delete(route('contracts.envelope.copy.remove', ['envelope' => $env->id, 'recipient' => $copy->id]))->assertRedirect();
        $this->assertSame(0, $env->copyRecipients()->count(), 'la copia no entregada se puede quitar');

        // Sobre anulado (retirado): no admite copias.
        $env->update(['status' => ContractEnvelope::STATUS_CANCELLED, 'cancelled_at' => now()]);
        $this->post(route('contracts.envelope.copy.add', $env->fresh()), ['name' => 'X', 'email' => 'x@qa.test']);
        $this->assertSame(0, $env->copyRecipients()->count(), 'un sobre retirado rechaza copias');
    }

    // ── B4 · RUTEO PARALELO (firmar en cualquier orden) ──────────────────────

    /** En paralelo todos los firmantes reciben a la vez y firman en cualquier orden; completa al final. */
    public function test_parallel_routing_signs_in_any_order(): void
    {
        Storage::fake('local');
        $this->seatInternals();
        Setting::updateOrCreate(['key' => SignaturePositions::KEY_SIGN_PARALLEL], ['value' => '1']);
        Branding::forget();

        try {
            $env = ContractEnvelopeBuilder::build($this->emitContract(PayeeContract::CONCEPT_CREW, $this->crewPayee()), null);
            ContractSigning::send($env);

            // TODOS los firmantes quedan ABIERTOS al enviar (no solo el primero).
            $order = $env->orderedRecipients()->get();
            foreach ($order as $r) {
                $this->assertSame('sent', $r->fresh()->status, 'todos abiertos en paralelo');
            }

            $img = 'data:image/png;base64,' . str_repeat('A', 120);
            // Firmar en orden INVERSO (permitido en paralelo).
            ContractSigning::sign($order[2]->fresh(), 'authenticated', '3.3.3.3', $img);
            $this->assertFalse($env->fresh()->isCompleted(), 'faltan firmantes');
            ContractSigning::sign($order[0]->fresh(), 'authenticated', '1.1.1.1', $img);
            $this->assertFalse($env->fresh()->isCompleted());
            ContractSigning::sign($order[1]->fresh(), 'authenticated', '2.2.2.2', $img);

            $this->assertTrue($env->fresh()->isCompleted(), 'completa cuando todos firmaron, sin importar el orden');
            $this->assertNull($env->fresh()->current_recipient_id);
        } finally {
            Setting::where('key', SignaturePositions::KEY_SIGN_PARALLEL)->delete();
            Branding::forget();
        }
    }

    /** Guardia: un firmante ya firmado no puede re-firmar en paralelo (doble submit bloqueado). */
    public function test_parallel_blocks_double_submit(): void
    {
        Storage::fake('local');
        $this->seatInternals();
        Setting::updateOrCreate(['key' => SignaturePositions::KEY_SIGN_PARALLEL], ['value' => '1']);
        Branding::forget();

        try {
            $env = ContractEnvelopeBuilder::build($this->emitContract(PayeeContract::CONCEPT_CREW, $this->crewPayee()), null);
            ContractSigning::send($env);
            $order = $env->orderedRecipients()->get();

            $img = 'data:image/png;base64,' . str_repeat('A', 120);
            ContractSigning::sign($order[0]->fresh(), 'authenticated', '1.1.1.1', $img);

            try {
                ContractSigning::sign($order[0]->fresh(), 'authenticated', '1.1.1.1', $img);
                $this->fail('un firmante ya firmado no debe re-firmar');
            } catch (ContractEnvelopeException $e) {
                $this->assertStringContainsString('turno', $e->getMessage());
            }
        } finally {
            Setting::where('key', SignaturePositions::KEY_SIGN_PARALLEL)->delete();
            Branding::forget();
        }
    }

    // ── B5 · RESOLVEDOR CONDICIONAL (firmante extra por importe) ─────────────

    /** Una regla por importe agrega un firmante EXTRA solo cuando los honorarios lo alcanzan; dedup. */
    public function test_conditional_signer_added_only_above_threshold(): void
    {
        Storage::fake('local');
        $this->seatInternals();

        // Un puesto extra con UN ocupante, firmante condicional arriba de $50k (dos reglas al mismo
        // puesto para probar el dedup).
        $condPos = (int) Position::orderBy('id')->skip(2)->value('id');
        DB::table('production_user')->where('production_id', $this->prodId)->where('position_id', $condPos)->delete();
        $extra = $this->makeUser('coordinator');
        $extra->forceFill(['name' => 'Extra', 'lname' => 'Firmante', 'email' => 'extra@x.mx'])->save();
        $this->attachPosition($extra, $condPos);

        Setting::updateOrCreate(['key' => SignaturePositions::KEY_CONDITIONAL_SIGNERS], ['value' => json_encode([
            ['min' => 50000, 'entry' => $condPos],
            ['min' => 60000, 'entry' => $condPos],   // regla duplicada → no debe duplicar la firma
        ])]);
        // El resolvedor condicional está APAGADO por default; este test valida el modo ACTIVO.
        Setting::updateOrCreate(['key' => SignaturePositions::KEY_CONDITIONAL_ENABLED], ['value' => '1']);
        Branding::forget();

        try {
            // BAJO umbral → sin firmante extra.
            $low = $this->emitContract(PayeeContract::CONCEPT_CREW, $this->crewPayee('1990-01-01'));
            $low->update(['fee_amount' => 10000]);
            $envLow = ContractEnvelopeBuilder::build($low->fresh(), null);
            $this->assertSame(0, $envLow->recipients()->where('user_id', $extra->id)->count(), 'bajo umbral: sin extra');

            // SOBRE umbral → agrega el firmante extra UNA sola vez (dedup).
            $high = $this->emitContract(PayeeContract::CONCEPT_CREW, $this->crewPayee('1991-02-02'));
            $high->update(['fee_amount' => 90000]);
            $envHigh = ContractEnvelopeBuilder::build($high->fresh(), null);
            $this->assertSame(1, $envHigh->recipients()->where('user_id', $extra->id)->count(), 'sobre umbral: un extra, sin duplicar');
            // Y firma como ROLE_SIGNER (entra a la ruta).
            $this->assertTrue($envHigh->orderedRecipients()->get()->contains('user_id', $extra->id));
        } finally {
            Setting::whereIn('key', [SignaturePositions::KEY_CONDITIONAL_SIGNERS, SignaturePositions::KEY_CONDITIONAL_ENABLED])->delete();
            Branding::forget();
        }
    }

    /** Con el interruptor APAGADO (default) las reglas por importe quedan inertes: sin firmante extra. */
    public function test_conditional_signer_ignored_when_toggle_off(): void
    {
        Storage::fake('local');
        $this->seatInternals();

        $condPos = (int) Position::orderBy('id')->skip(2)->value('id');
        DB::table('production_user')->where('production_id', $this->prodId)->where('position_id', $condPos)->delete();
        $extra = $this->makeUser('coordinator');
        $extra->forceFill(['name' => 'Extra', 'lname' => 'Off', 'email' => 'extra-off@x.mx'])->save();
        $this->attachPosition($extra, $condPos);

        // Regla que SÍ alcanzaría el umbral, pero el toggle NO se enciende → debe ignorarse.
        Setting::updateOrCreate(['key' => SignaturePositions::KEY_CONDITIONAL_SIGNERS], ['value' => json_encode([
            ['min' => 50000, 'entry' => $condPos],
        ])]);
        Branding::forget();

        try {
            $high = $this->emitContract(PayeeContract::CONCEPT_CREW, $this->crewPayee('1992-03-03'));
            $high->update(['fee_amount' => 90000]);
            $envHigh = ContractEnvelopeBuilder::build($high->fresh(), null);
            $this->assertSame(0, $envHigh->recipients()->where('user_id', $extra->id)->count(), 'toggle off: regla inerte');
        } finally {
            Setting::where('key', SignaturePositions::KEY_CONDITIONAL_SIGNERS)->delete();
            Branding::forget();
        }
    }

    // ── FASE 5 · ROBUSTEZ ───────────────────────────────────────────────────

    /** Un solo sobre EN CURSO por contrato (un contrato por persona). */
    public function test_only_one_active_envelope_per_contract(): void
    {
        Storage::fake('local');
        $this->seatInternals();
        $contract = $this->emitContract(PayeeContract::CONCEPT_CREW, $this->crewPayee());

        $env1 = ContractEnvelopeBuilder::build($contract, null);
        $this->assertNotNull($env1->id);

        try {
            ContractEnvelopeBuilder::build($contract->fresh(), null);
            $this->fail('no debe crear un segundo sobre en curso');
        } catch (ContractEnvelopeException $e) {
            $this->assertStringContainsString('en curso', $e->getMessage());
        }

        // Anulado el primero, se puede reemitir.
        $env1->update(['status' => ContractEnvelope::STATUS_CANCELLED, 'cancelled_at' => now()]);
        $env2 = ContractEnvelopeBuilder::build($contract->fresh(), null);
        $this->assertNotSame($env1->id, $env2->id);
    }

    /** El avance de firma es transaccional: un doble submit no re-firma ni avanza dos pasos. */
    public function test_double_submit_does_not_advance_the_route_twice(): void
    {
        Storage::fake('local');
        $this->seatInternals();
        $env = ContractEnvelopeBuilder::build($this->emitContract(PayeeContract::CONCEPT_CREW, $this->crewPayee()), null);
        ContractSigning::send($env);
        $order = $env->orderedRecipients()->get();

        ContractSigning::sign($order[0]->fresh(), 'authenticated', '1.1.1.1');
        $this->assertSame((int) $order[1]->id, (int) $env->fresh()->current_recipient_id, 'avanzó una vez');

        try {
            ContractSigning::sign($order[0]->fresh(), 'authenticated', '1.1.1.1');   // ya no es su turno
            $this->fail('un doble submit no debe re-firmar');
        } catch (ContractEnvelopeException $e) {
            $this->assertStringContainsString('turno', $e->getMessage());
        }
        $this->assertSame((int) $order[1]->id, (int) $env->fresh()->current_recipient_id, 'sigue en el mismo turno');
    }

    /** Una vez emitido, la hoja de información queda CONGELADA (no puede editarse el trato sellado). */
    public function test_infosheet_save_is_locked_after_emission(): void
    {
        Storage::fake('local');
        $this->actingAsRole('super-admin');
        $payee    = $this->crewPayee();
        $contract = $this->emitContract(PayeeContract::CONCEPT_CREW, $payee);
        $contract->update(['fee_amount' => 1000]);

        $res = $this->post(route('infosheet.save', $payee->id), ['_step' => 'fees', 'fee_shoot_amount' => 5000]);

        $res->assertRedirect();
        $res->assertSessionHas('error');
        $this->assertEquals(1000.0, (float) $contract->fresh()->fee_amount, 'los datos no cambian tras emitir');
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

        // Ahora ve la página de firma. Ola 6: el contrato se LEE inline (visor pdf.js) con salto a
        // firmar; no es solo un canvas. El POST de firma NO cambia (el sello sigue intacto).
        $this->get(ContractSignController::signUrl($rec))->assertOk()
            ->assertSee('Firma tu contrato')
            ->assertSee('js/vendor/pdfjs/pdf.min.js', false)
            ->assertSee('Ir a firmar');

        // Firmar el paquete (con consentimiento + FIRMA AUTÓGRAFA obligatoria, DocuSign).
        $signUrl = URL::temporarySignedRoute('contracts.sign.do', now()->addHours(3), ['recipient' => $rec->id]);
        $autograph = 'data:image/png;base64,' . str_repeat('A', 160);
        $this->post($signUrl, ['consent' => 1, 'signature_image' => $autograph])->assertRedirect();

        $signed = $rec->fresh();
        $this->assertTrue($signed->isSigned());
        $this->assertNotNull($signed->ip_address);
        // La autógrafa quedó en la fila y el SELLO la cubre (verificable e íntegra).
        $this->assertSame($autograph, $signed->signature_image);
        $this->assertTrue($signed->verifyLatestSignature(), 'el sello cubre la autógrafa e íntegra');
        // El contratado crew es usuario → el consentimiento se llavea por USER.
        $this->assertTrue(ContractConsent::has('user', (int) $rec->user_id), 'se registró el consentimiento');
    }

    // ── Fase 3.4 · correo al completarse la ruta ──────────────────────────────
    public function test_completion_listener_is_wired(): void
    {
        Event::fake();
        Event::assertListening(ContractEnvelopeCompleted::class, EmailSignedContractToParty::class);
    }

    public function test_completing_route_emails_the_contracted_party_with_attachments(): void
    {
        Storage::fake('local');
        $this->seatInternals();

        $payee = $this->crewPayee();
        $payee->user->forceFill(['email' => 'contratado@qa.test'])->save();
        $env = ContractEnvelopeBuilder::build($this->emitContract(PayeeContract::CONCEPT_CREW, $payee), null);
        ContractSigning::send($env);

        $contracted = $env->recipients()->where('role', ContractEnvelopeRecipient::ROLE_CONTRACTED)->first();
        $this->assertSame('contratado@qa.test', $contracted->email);

        $before = Mail::getSymfonyTransport()->messages()->count();

        // Firmar TODA la ruta (sin falsear el evento → el listener corre de verdad).
        $autograph = 'data:image/png;base64,' . str_repeat('A', 160);
        foreach ($env->orderedRecipients()->get() as $r) {
            ContractSigning::sign($r->fresh(), 'authenticated', '9.9.9.9', $autograph);
        }
        $this->assertTrue($env->fresh()->isCompleted());

        $messages = Mail::getSymfonyTransport()->messages();
        // Al completar salen los avisos "te toca" de cada turno (Fase 4) + el correo de CIERRE al final.
        $this->assertGreaterThan($before, $messages->count(), 'se envió al menos el correo de cierre');

        // El ÚLTIMO es el de cierre: va al contratado con el paquete adjunto.
        $sent = $messages->last()->getOriginalMessage();
        $this->assertSame('contratado@qa.test', $sent->getTo()[0]->getAddress());
        // El paquete (carátula + clausulado) viaja ADJUNTO.
        $this->assertGreaterThanOrEqual(2, count($sent->getAttachments()), 'los PDF del paquete van adjuntos');
    }

    // ── Fase 3.5 · lista configurable de N firmantes con la entrada dinámica dept_hod ─────────
    public function test_signer_list_with_dept_hod_builds_route(): void
    {
        Storage::fake('local');

        // Depto del contrato + su JEFE (is_lead) → resuelve la entrada DEPT_HOD.
        $dept = \App\Models\Department::first();
        $hod  = $this->makeUser('crew'); $hod->forceFill(['name' => 'Jefa', 'lname' => 'Depto'])->save();
        DB::table('production_user')->updateOrInsert(
            ['production_id' => $this->prodId, 'user_id' => $hod->id],
            ['department_id' => $dept->id, 'is_lead' => 1, 'role' => 'crew', 'created_at' => now(), 'updated_at' => now()]
        );

        // Un firmante por PUESTO (cualquier puesto ocupado por exactamente una persona).
        $signerPos  = Position::orderBy('id')->first()->id;
        $signerUser = $this->makeUser('coordinator'); $signerUser->forceFill(['name' => 'Firma', 'lname' => 'Puesto'])->save();
        $this->attachPosition($signerUser, $signerPos);

        // Módulo de firma: lista = [puesto, HOD del depto], EN ORDEN.
        Setting::updateOrCreate(['key' => SignaturePositions::KEY_SIGNERS],
            ['value' => json_encode([$signerPos, SignaturePositions::DEPT_HOD])]);
        Branding::forget();
        $this->assertTrue(SignaturePositions::hasSignerList());

        $contract = $this->emitContract(PayeeContract::CONCEPT_CREW, $this->crewPayee());
        $contract->update(['department_id' => $dept->id]);

        $env  = ContractEnvelopeBuilder::build($contract->fresh(), null);
        $recs = $env->orderedRecipients()->get();

        // Contratado + N firmantes, en orden. dept_hod congela a la JEFA del depto del contrato.
        $this->assertSame(['contracted', 'signer', 'signer'], $recs->pluck('role')->all());
        $this->assertSame((int) $signerUser->id, (int) $recs[1]->user_id, 'el firmante por puesto');
        $this->assertSame((int) $hod->id, (int) $recs[2]->user_id, 'dept_hod → jefe del depto del contrato');
        $this->assertSame(__('HOD del departamento'), $recs[2]->cargo);

        // La ruta sigue sellada e íntegra.
        $this->assertTrue($env->verifyLatestSignature());
    }

    // ── Fase 1c · la plantilla se estampa con las firmas REALES del sobre ──────
    private function seatOneSigner(): int
    {
        $pos  = Position::orderBy('id')->first()->id;
        $user = $this->makeUser('coordinator'); $user->forceFill(['name' => 'Firma', 'lname' => 'Puesto'])->save();
        $this->attachPosition($user, $pos);
        Setting::updateOrCreate(['key' => SignaturePositions::KEY_SIGNERS], ['value' => json_encode([$pos])]);
        Branding::forget();
        return $pos;
    }

    public function test_sigmap_from_envelope_reflects_real_signatures(): void
    {
        Storage::fake('local');
        $pos = $this->seatOneSigner();
        $env = ContractEnvelopeBuilder::build($this->emitContract(PayeeContract::CONCEPT_CREW, $this->crewPayee()), null);

        // El ancla quedó CONGELADA en cada destinatario (contratado + puesto).
        $recs = $env->orderedRecipients()->get();
        $this->assertSame('contratado', $recs[0]->anchor_key);
        $this->assertSame('puesto:' . $pos, $recs[1]->anchor_key);

        // Sin firmas → toda ancla pendiente (null), pero con su etiqueta.
        $map0 = ContractTemplateRenderer::sigMapForEnvelope($env->fresh());
        $this->assertNull($map0['contratado']);
        $this->assertNull($map0['puesto:' . $pos]);
        $this->assertArrayHasKey('contratado', $map0['__labels']);

        // Firmar al CONTRATADO (primero en la ruta) con su autógrafa.
        ContractSigning::send($env);
        $autograph = 'data:image/png;base64,' . str_repeat('A', 160);
        ContractSigning::sign($env->recipients()->where('role', 'contracted')->first()->fresh(), 'authenticated', '5.5.5.5', $autograph);

        $map = ContractTemplateRenderer::sigMapForEnvelope($env->fresh());
        // Contratado → estampa REAL, íntegra, con su hash de sello.
        $this->assertIsArray($map['contratado']);
        $this->assertSame($autograph, $map['contratado']['image']);
        $this->assertTrue($map['contratado']['verified']);
        $this->assertNotEmpty($map['contratado']['hash']);
        // El firmante por puesto aún no firma → sigue pendiente.
        $this->assertNull($map['puesto:' . $pos]);
    }

    public function test_envelope_template_document_stamps_real_signatures(): void
    {
        Storage::fake('local');
        $pos = $this->seatOneSigner();
        $payee = $this->crewPayee();
        $env = ContractEnvelopeBuilder::build($this->emitContract(PayeeContract::CONCEPT_CREW, $payee), null);

        // Plantilla ACTIVA del subtipo con dos anclas: el contratado y el firmante por puesto.
        ContractTemplate::create([
            'production_id' => $this->prodId, 'name' => 'Crew', 'applies_to' => [PayeeContract::CONCEPT_CREW],
            'body' => '<p>{{payee_nombre}}</p><div>[[firma:contratado]]</div><div>[[firma:puesto:' . $pos . ']]</div>',
            'language' => 'es', 'is_active' => 1,
        ]);

        // Firmar al contratado; el firmante por puesto queda pendiente.
        ContractSigning::send($env);
        $autograph = 'data:image/png;base64,' . str_repeat('A', 160);
        ContractSigning::sign($env->recipients()->where('role', 'contracted')->first()->fresh(), 'authenticated', '5.5.5.5', $autograph);

        $this->actingAs($this->makeUser('super-admin'));
        $res = $this->get(route('contracts.envelope.template', $env));
        $res->assertOk();
        $html = $res->getContent();

        $this->assertStringContainsString($payee->name, $html, 'el campo {{payee_nombre}} se llenó');
        $this->assertStringContainsString('cc-sig-stamp', $html, 'el contratado se estampó');
        $this->assertStringContainsString($autograph, $html, 'con su autógrafa REAL');
        $this->assertStringContainsString('cc-sig-pending', $html, 'el firmante por puesto sigue pendiente');
        $this->assertStringNotContainsString('[[firma:', $html, 'no quedan anclas crudas');
    }

    public function test_envelope_template_document_404_without_active_template(): void
    {
        Storage::fake('local');
        $this->seatOneSigner();
        $env = ContractEnvelopeBuilder::build($this->emitContract(PayeeContract::CONCEPT_CREW, $this->crewPayee()), null);

        // Sin plantilla activa para el subtipo → 404 (nada que armar).
        $this->actingAs($this->makeUser('super-admin'));
        $this->get(route('contracts.envelope.template', $env))->assertNotFound();
    }
}
