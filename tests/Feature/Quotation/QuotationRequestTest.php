<?php

namespace Tests\Feature\Quotation;

use App\Models\Quotation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\QaTestCase;

/** VERIFICACIÓN F5 — solicitud por ENLACE FIRMADO (SE SOLICITA), sin login. */
class QuotationRequestTest extends QaTestCase
{
    /** Crea una cotización SHELL (v1 vacía) para pedirla. */
    private function makeShell(string $status = Quotation::STATUS_RECEIVED): Quotation
    {
        $prodId = DB::table('productions')->min('id');
        $user   = User::first();
        $q = Quotation::create([
            'production_id' => $prodId, 'emitter_name' => 'Proveedor', 'emitter_email' => 'p@x.mx',
            'status' => $status, 'created_by_id' => $user->id, 'uuid' => (string) \Illuminate\Support\Str::uuid(),
        ]);
        $v = $q->versions()->create(['version_no' => 1, 'source_kind' => 'items']);
        $q->current_version_id = $v->id;
        $q->save();

        return $q;
    }

    private function showUrl(Quotation $q): string
    {
        return URL::temporarySignedRoute('quotations.request.show', now()->addDay(), ['quotation' => $q->id]);
    }

    private function submitUrl(Quotation $q): string
    {
        return URL::temporarySignedRoute('quotations.request.submit', now()->addDay(), ['quotation' => $q->id]);
    }

    /** El enlace SIN firma no entra (403); firmado sí (200). */
    public function test_unsigned_denied_signed_ok(): void
    {
        $q = $this->makeShell();
        $this->get(route('quotations.request.show', $q))->assertForbidden();   // sin firma
        $this->get($this->showUrl($q))->assertOk();                            // firmado
    }

    /** El proveedor llena la cotización por el enlace y queda RECIBIDA, sin login. */
    public function test_provider_fills_quotation_via_signed_link(): void
    {
        $q = $this->makeShell();

        $this->post($this->submitUrl($q), [
            'emitter_name' => 'Proveedor Real', 'emitter_email' => 'ventas@prov.mx',
            'source_kind' => 'items', 'iva_rate' => 16,
            'items' => [0 => ['description' => 'Renta grip', 'quantity' => 2, 'days' => 3, 'unit_price' => 500]], // 2×3×500 = 3000
        ])->assertOk(); // pantalla de gracias

        $q->refresh();
        $this->assertSame(Quotation::STATUS_RECEIVED, $q->status);
        $this->assertSame('ventas@prov.mx', $q->emitter_email, 'correo normalizado');
        $v = $q->currentVersion;
        $this->assertSame(1, $v->version_no, 'rellena el shell vacío en su lugar');
        $this->assertSame('3000.00', (string) $v->subtotal);
        $this->assertSame('3480.00', (string) $v->total, '3000 + 16% IVA');
        $this->assertCount(1, $v->items);
    }

    /** Una cotización ACEPTADA ya no se puede llenar por enlace (410). */
    public function test_accepted_quotation_link_is_gone(): void
    {
        $q = $this->makeShell(Quotation::STATUS_ACCEPTED);
        $this->get($this->showUrl($q))->assertStatus(410);
        $this->post($this->submitUrl($q), ['emitter_name' => 'x', 'source_kind' => 'items'])->assertStatus(410);
    }
}
