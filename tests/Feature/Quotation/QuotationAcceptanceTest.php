<?php

namespace Tests\Feature\Quotation;

use App\Models\DocumentType;
use App\Models\Payee;
use App\Models\Quotation;
use App\Models\User;
use App\Support\SealVerifier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\QaTestCase;

/** VERIFICACIÓN F3+F4 — aceptación sellada (hoja dompdf) + enganche al aceptar (PASO F). */
class QuotationAcceptanceTest extends QaTestCase
{
    private string $sig;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sig = 'data:image/png;base64,'.str_repeat('A', 200); // ≥100 chars
    }

    /** Crea una cotización de partidas y devuelve el modelo (como line-producer). */
    private function makeItemsQuotation(string $email = 'proveedor@x.mx'): Quotation
    {
        $this->post(route('quotations.store'), [
            'emitter_name' => 'Proveedor', 'emitter_email' => $email,
            'source_kind' => 'items', 'iva_rate' => 16,
            'items' => [0 => ['description' => 'Servicio', 'quantity' => 1, 'unit_price' => 1000]],
        ])->assertRedirect();

        return Quotation::latest('id')->first();
    }

    /** Aceptar sella (íntegro), genera la hoja, y el verificador público la resuelve como 'ok'. */
    public function test_accept_seals_and_generates_sheet_and_verifies(): void
    {
        Storage::fake('local');
        $this->actingAsRole('line-producer');
        $q = $this->makeItemsQuotation();

        $this->post(route('quotations.accept', $q), ['signature_image' => $this->sig])->assertRedirect();

        $q->refresh();
        $this->assertSame(Quotation::STATUS_ACCEPTED, $q->status);
        $this->assertNotNull($q->accepted_at);
        $this->assertSame($q->currentVersion->contentHash(), $q->accepted_doc_hash);
        $this->assertTrue($q->verifyLatestSignature(), 'el sello queda íntegro');
        $this->assertNotNull($q->acceptance_sheet_path);
        Storage::disk('local')->assertExists($q->acceptance_sheet_path);

        // Verificador PÚBLICO: (tipo cotz, uuid) → veredicto ok.
        $dto = SealVerifier::resolve('cotz', $q->uuid);
        $this->assertNotNull($dto);
        $this->assertSame('ok', $dto['verdict']);
    }

    /** Aceptar liga al PAYEE existente cuando el correo coincide, y satisface el requisito COTIZACION. */
    public function test_accept_links_existing_payee_by_email_and_satisfies_requirement(): void
    {
        Storage::fake('local');
        $this->actingAsRole('line-producer');

        $payee = Payee::create(['legal_nature' => 'fisica', 'name' => 'Jardines SA', 'email' => 'contacto@jardines.mx', 'is_active' => 1]);
        $q = $this->makeItemsQuotation('CONTACTO@Jardines.MX '); // distinto case/espacios

        $this->post(route('quotations.accept', $q), ['signature_image' => $this->sig])->assertRedirect();

        $q->refresh();
        $this->assertSame($payee->id, $q->payee_id, 'ligó al payee existente por correo');

        $type = DocumentType::where('code', 'COTIZACION')->first();
        $this->assertTrue(
            $payee->documents()->where('is_active', 1)->where('document_type_id', $type?->id)->exists(),
            'la cotización satisface el requisito documental'
        );
    }

    /** Sin coincidencia de correo → provisiona el EXTERNO LITE (payee nuevo + user externo). */
    public function test_accept_provisions_external_lite_when_no_match(): void
    {
        Storage::fake('local');
        $this->actingAsRole('line-producer');
        $q = $this->makeItemsQuotation('nadie@desconocido.mx');

        $usersBefore = User::count();
        $this->post(route('quotations.accept', $q), ['signature_image' => $this->sig])->assertRedirect();

        $q->refresh();
        $this->assertNotNull($q->payee_id);
        $payee = Payee::find($q->payee_id);
        $this->assertSame('nadie@desconocido.mx', $payee->email);
        $this->assertGreaterThan($usersBefore, User::count(), 'se creó el usuario externo lite');
        $ext = User::find($payee->user_id);
        $this->assertSame(1, (int) $ext->is_external);
        $this->assertSame(0, (int) $ext->activo, 'nace fuera de listados');
    }

    /** El PDF subido se descarga byte-IDÉNTICO DESPUÉS de aceptar (no se estampa nada encima). */
    public function test_pdf_stays_byte_intact_after_acceptance(): void
    {
        Storage::fake('local');
        $this->actingAsRole('line-producer');
        $bytes = "%PDF-1.4\ncotización original\n%%EOF";

        $this->post(route('quotations.store'), [
            'emitter_name' => 'Prov PDF', 'source_kind' => 'pdf', 'total' => 3000,
            'pdf' => UploadedFile::fake()->createWithContent('c.pdf', $bytes),
        ])->assertRedirect();
        $q = Quotation::latest('id')->first();

        $this->post(route('quotations.accept', $q), ['signature_image' => $this->sig])->assertRedirect();

        $v = $q->fresh()->currentVersion;
        $res = $this->get(route('quotations.version_pdf', [$q, $v]));
        $res->assertOk();
        $this->assertSame($bytes, $res->getContent(), 'el PDF sigue idéntico tras aceptar');
    }

    /** Quien tiene manage pero NO accept (coordinator) no puede aceptar. */
    public function test_accept_is_gated_to_accept_permission(): void
    {
        Storage::fake('local');
        $this->actingAsRole('line-producer');
        $q = $this->makeItemsQuotation();

        $this->actingAsRole('coordinator');
        $this->post(route('quotations.accept', $q), ['signature_image' => $this->sig])->assertForbidden();
    }
}
