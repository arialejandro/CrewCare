<?php

namespace Tests\Feature\Infosheet;

use App\Models\ContractClause;
use App\Models\ContractEnvelope;
use App\Models\Payee;
use App\Models\PayeeContract;
use App\Models\Position;
use App\Models\Setting;
use App\Models\User;
use App\Support\Branding;
use App\Support\CurrentProduction;
use App\Support\InfosheetSigning;
use App\Support\SignaturePositions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\QaTestCase;

/**
 * EL INFOSHEET · FASE 3 — AUTORIZACIÓN (paso 2) + DISPARO (paso 3). Verificación:
 *  - el autorizador por defecto (Line Producer) aprueba con su firma AUTÓGRAFA → dispara la
 *    emisión del contrato + arma el sobre + lo envía a firma;
 *  - la autógrafa es OBLIGATORIA (cada paso requiere la firma, no basta el sello);
 *  - quien no es autorizador no puede autorizar (403);
 *  - la autorización queda SELLADA y verificable (la autógrafa entra al hash).
 */
class InfosheetAuthorizationTest extends QaTestCase
{
    private $prodId;
    private $prepPos;
    private $bindPos;
    private $deptId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prodId = DB::table('productions')->min('id');
        DB::table('productions')->where('id', $this->prodId)->update(['active' => 1]);
        CurrentProduction::forget();

        [$this->prepPos, $this->bindPos] = Position::orderBy('id')->take(2)->pluck('id')->all();
        $this->deptId = DB::table('departments')->min('id');
        Setting::updateOrCreate(['key' => SignaturePositions::KEY_PREPARER], ['value' => $this->prepPos]);
        Setting::updateOrCreate(['key' => SignaturePositions::KEY_BINDER],   ['value' => $this->bindPos]);
        foreach ([
            'company_name' => 'Pimienta Films SA', 'rfc' => 'PFI860101AB3', 'office_address' => 'Reforma 222',
            'representante_legal' => 'Ana Pérez', 'correo_contratante' => 'legal@pimienta.mx',
        ] as $k => $v) {
            Setting::updateOrCreate(['key' => $k], ['value' => $v]);
        }
        Branding::forget();
    }

    private function image(): string
    {
        return 'data:image/png;base64,' . base64_encode(str_repeat('x', 200));
    }

    private function makeClause(): void
    {
        Storage::disk('local')->put('contracts/clauses/x.pdf', '%PDF-1.7 clausulado');
        ContractClause::create([
            'production_id' => $this->prodId, 'name' => 'Contrato', 'applies_to' => ['crew_work'],
            'language' => 'es', 'file_path' => 'contracts/clauses/x.pdf', 'version' => 1, 'is_active' => 1,
        ]);
    }

    private function seatInternals(): void
    {
        $prep = $this->makeUser('coordinator');
        $bind = $this->makeUser('line-producer');
        DB::table('production_user')->updateOrInsert(
            ['production_id' => $this->prodId, 'user_id' => $prep->id],
            ['position_id' => $this->prepPos, 'role' => 'crew', 'is_lead' => 0, 'created_at' => now(), 'updated_at' => now()]
        );
        DB::table('production_user')->updateOrInsert(
            ['production_id' => $this->prodId, 'user_id' => $bind->id],
            ['position_id' => $this->bindPos, 'role' => 'crew', 'is_lead' => 0, 'created_at' => now(), 'updated_at' => now()]
        );
    }

    private function crewContract(): PayeeContract
    {
        $u = $this->makeUser('crew');
        $payee = Payee::create(['legal_nature' => 'fisica', 'name' => 'Juan Crew', 'user_id' => $u->id]);
        // Trato COMPLETO: autorizar emite y congela, así que el candado
        // (InfosheetSigning::missingToAuthorize) exige puesto, depto, importe y fecha de inicio.
        return $payee->contracts()->create([
            'concept' => 'crew_work', 'is_active' => 1, 'production_id' => $this->prodId, 'crew_activity' => 'Gaffer',
            'title' => 'Gaffer', 'department_id' => $this->deptId, 'fee_amount' => 100000,
            'effective_date' => now()->toDateString(),
        ]);
    }

    public function test_default_lp_authorization_triggers_generation(): void
    {
        Storage::fake('local');
        $this->makeClause();
        $this->seatInternals();
        $contract = $this->crewContract();

        $this->actingAs($this->makeUser('line-producer'));
        $this->post(route('infosheet.authorize', $contract->payee_id), ['signature_image' => $this->image()])
            ->assertRedirect();

        $contract->refresh();
        $this->assertTrue($contract->isEmitted(), 'el contrato se emitió al autorizar');
        $this->assertTrue($contract->envelopes()->exists(), 'se armó el sobre');
        $this->assertSame(ContractEnvelope::STATUS_SENT, $contract->envelopes()->first()->status, 'el sobre salió a firma');

        $auth = $contract->authorizations()->first();
        $this->assertNotNull($auth->signature_image, 'quedó la autógrafa');
        $this->assertTrue($auth->verifyLatestSignature(), 'la autorización está sellada e íntegra');
    }

    public function test_autograph_is_required(): void
    {
        Storage::fake('local');
        $this->makeClause();
        $this->seatInternals();
        $contract = $this->crewContract();

        $this->actingAs($this->makeUser('line-producer'));
        $this->post(route('infosheet.authorize', $contract->payee_id), [])
            ->assertSessionHasErrors('signature_image');

        $this->assertFalse($contract->fresh()->isEmitted(), 'sin firma no se dispara nada');
    }

    /**
     * CANDADO DE COMPLETITUD — una hoja en blanco NO se puede autorizar. Autorizar EMITE el contrato
     * y lo congela: firmar un stub producía un contrato vacío e inmutable (pasó en dev el 2026-08-25).
     */
    public function test_incomplete_infosheet_cannot_be_authorized(): void
    {
        Storage::fake('local');
        $this->makeClause();
        $this->seatInternals();

        $contract = $this->crewContract();
        $contract->update(['title' => null, 'department_id' => null, 'fee_amount' => null, 'effective_date' => null]);

        $lp = $this->makeUser('line-producer');
        $this->assertNotEmpty(InfosheetSigning::missingToAuthorize($contract->fresh()));
        $this->assertFalse(InfosheetSigning::canAuthorize($lp, $contract->fresh()));

        // Ni por la ruta directa: el servidor manda, no la UI.
        $this->actingAs($lp);
        $this->post(route('infosheet.authorize', $contract->payee_id), ['signature_image' => $this->image()])
            ->assertForbidden();

        $contract->refresh();
        $this->assertFalse($contract->isEmitted(), 'no se emitió un contrato vacío');
        $this->assertSame(0, $contract->authorizations()->count(), 'no quedó una autorización huérfana');

        // Y tampoco se manda a autorización a medias.
        $this->post(route('infosheet.submit', $contract->payee_id))
            ->assertRedirect()->assertSessionHas('error');
    }

    /** El envío a autorización SÍ pasa cuando el trato está completo. */
    public function test_complete_infosheet_can_be_authorized(): void
    {
        Storage::fake('local');
        $this->seatInternals();
        $contract = $this->crewContract();   // ya nace completo

        $this->assertSame([], InfosheetSigning::missingToAuthorize($contract));
        $this->assertTrue(InfosheetSigning::canAuthorize($this->makeUser('line-producer'), $contract));
    }

    public function test_non_authorizer_cannot_authorize(): void
    {
        Storage::fake('local');
        $contract = $this->crewContract();

        $this->actingAs($this->makeUser('crew'));   // ni rol line-producer ni puesto autorizador
        $this->post(route('infosheet.authorize', $contract->payee_id), ['signature_image' => $this->image()])
            ->assertForbidden();
    }

    /**
     * EL DISPARADOR (8d) · BANDEJA "por autorizar": el autorizador VE los tratos capturados que le
     * toca autorizar; los stubs vacíos NO aparecen; quien no es autorizador no ve nada.
     */
    public function test_pending_bandeja_lists_captured_infosheet_for_the_authorizer(): void
    {
        Storage::fake('local');
        $this->seatInternals();

        $contract = $this->crewContract();
        $contract->update(['title' => 'Gaffer']);   // capturado (puesto)

        // Stub vacío (otro payee, sin captura) → NO debe aparecer en la bandeja.
        $stub = Payee::create(['legal_nature' => 'fisica', 'name' => 'Stub Vacio']);
        $stub->contracts()->create(['concept' => 'crew_work', 'is_active' => 1, 'production_id' => $this->prodId]);

        // El LP (autorizador por defecto) ve el capturado, no el stub.
        $this->actingAs($this->makeUser('line-producer'));
        $this->get(route('infosheet.pending'))->assertOk()
            ->assertSee('Juan Crew')
            ->assertDontSee('Stub Vacio');

        // Un crew (no autorizador) no ve el trato.
        $this->actingAs($this->makeUser('crew'));
        $this->get(route('infosheet.pending'))->assertOk()->assertDontSee('Juan Crew');
    }

    /** ENVIAR A AUTORIZACIÓN avisa al autorizador (best-effort) y confirma; NO emite por sí solo. */
    public function test_submit_to_authorization_notifies_and_confirms(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        Storage::fake('local');
        $this->seatInternals();

        $lp = $this->makeUser('line-producer');
        $lp->forceFill(['email' => 'lp@x.mx'])->save();   // autorizador con correo

        $contract = $this->crewContract();
        $contract->update(['title' => 'Gaffer']);

        $this->actingAs($lp);   // el capturista (LP tiene capture por bypass) envía a autorización
        $this->post(route('infosheet.submit', $contract->payee_id))
            ->assertRedirect()->assertSessionHas('success');

        $this->assertFalse($contract->fresh()->isEmitted(), 'enviar a autorización NO emite; solo avisa');
    }
}
