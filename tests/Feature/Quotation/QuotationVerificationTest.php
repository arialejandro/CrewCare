<?php

namespace Tests\Feature\Quotation;

use App\Models\Quotation;
use App\Models\QuotationVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\QaTestCase;

/** VERIFICACIÓN F6 — checks del bloque aún no cubiertos: vencida, no-usuario-al-cotizar, visibilidad. */
class QuotationVerificationTest extends QaTestCase
{
    private string $sig = 'data:image/png;base64,AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA';

    private function attachDept(User $u, $deptId): void
    {
        $prodId = DB::table('productions')->min('id');
        DB::table('production_user')->updateOrInsert(
            ['production_id' => $prodId, 'user_id' => $u->id],
            ['department_id' => $deptId, 'role' => 'crew', 'is_lead' => 1, 'created_at' => now(), 'updated_at' => now()]
        );
    }

    /** Una cotización VENCIDA avisa (isExpired) pero se puede ACEPTAR igual. */
    public function test_expired_quotation_can_still_be_accepted(): void
    {
        Storage::fake('local');
        $this->actingAsRole('line-producer');
        $prodId = DB::table('productions')->min('id');

        $q = Quotation::create(['production_id' => $prodId, 'emitter_name' => 'P', 'status' => Quotation::STATUS_RECEIVED, 'uuid' => (string) \Illuminate\Support\Str::uuid()]);
        $v = $q->versions()->create(['version_no' => 1, 'source_kind' => 'items', 'valid_until' => now()->subDays(5), 'iva_rate' => 16]);
        $v->items()->create(['sort_order' => 0, 'description' => 'X', 'quantity' => 1, 'unit_price' => 100]);
        $v->recomputeTotals(); $v->save();
        $q->current_version_id = $v->id; $q->save();

        $this->assertTrue($q->fresh()->isExpired(), 'está vencida');
        $this->post(route('quotations.accept', $q), ['signature_image' => $this->sig])->assertRedirect();
        $this->assertSame(Quotation::STATUS_ACCEPTED, $q->fresh()->status, 'se acepta igual');
    }

    /** Capturar una cotización (recibida, no aceptada) NO crea ningún usuario. */
    public function test_capturing_quotation_creates_no_user(): void
    {
        $this->actingAsRole('line-producer');
        $before = User::count();

        $this->post(route('quotations.store'), [
            'emitter_name' => 'Proveedor', 'emitter_email' => 'sinuser@x.mx',
            'source_kind' => 'items', 'items' => [0 => ['description' => 'S', 'quantity' => 1, 'unit_price' => 10]],
        ])->assertRedirect();

        $this->assertSame($before, User::count(), 'cotizar no crea usuario');
    }

    /** Un HOD de transpo NO ve las cotizaciones de arte (visibilidad por departamento). */
    public function test_transpo_does_not_see_arte_quotations(): void
    {
        $prodId  = DB::table('productions')->min('id');
        $depts   = DB::table('departments')->orderBy('id')->limit(2)->pluck('id')->all();
        [$arte, $transpo] = [$depts[0], $depts[1]];

        // Cotización de ARTE, creada por otro.
        $creator = $this->makeUser('line-producer');
        $q = Quotation::create(['production_id' => $prodId, 'department_id' => $arte, 'emitter_name' => 'Arte', 'status' => 'recibida', 'created_by_id' => $creator->id, 'uuid' => (string) \Illuminate\Support\Str::uuid()]);

        // Usuario con quotations.manage PERO sin all-departments, adscrito a TRANSPO.
        $viewer = $this->makeUser('crew');
        $viewer->givePermissionTo('quotations.manage');
        $this->attachDept($viewer, $transpo);

        $visible = Quotation::query()->visibleTo($viewer->fresh())->pluck('id')->all();
        $this->assertNotContains($q->id, $visible, 'transpo no ve la cotización de arte');

        // La misma cotización pero en TRANSPO sí la ve.
        $own = Quotation::create(['production_id' => $prodId, 'department_id' => $transpo, 'emitter_name' => 'Transpo', 'status' => 'recibida', 'created_by_id' => $creator->id, 'uuid' => (string) \Illuminate\Support\Str::uuid()]);
        $visible2 = Quotation::query()->visibleTo($viewer->fresh())->pluck('id')->all();
        $this->assertContains($own->id, $visible2, 'sí ve la de su departamento');
    }
}
