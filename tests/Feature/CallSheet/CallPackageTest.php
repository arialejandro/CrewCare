<?php

namespace Tests\Feature\CallSheet;

use App\Http\Controllers\CallSheetController;
use App\Models\CallDay;
use App\Models\CallPackage;
use App\Models\Department;
use App\Models\Payee;
use App\Models\User;
use App\Support\CurrentProduction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\QaTestCase;

/**
 * PAQUETE del llamado: ensamble (front+back), envío a aprobación, firma de los 3 y congelado.
 */
class CallPackageTest extends QaTestCase
{
    private int $prodId;
    private string $ds = '2026-08-22';

    protected function setUp(): void
    {
        parent::setUp();
        $this->prodId = (int) DB::table('productions')->min('id');
        DB::table('productions')->where('id', $this->prodId)->update(['active' => 1]);
        CurrentProduction::forget();
    }

    private function makeCrew(string $dept, string $name): User
    {
        $u   = $this->makeUser('crew', ['name' => $name, 'lname' => 'QA', 'ncreditos' => $name]);
        $did = (int) Department::where('name', $dept)->value('id');
        DB::table('production_user')->updateOrInsert(
            ['production_id' => $this->prodId, 'user_id' => $u->id],
            ['department_id' => $did, 'role' => 'crew', 'is_lead' => 0, 'created_at' => now(), 'updated_at' => now()]
        );
        $payee = Payee::create(['legal_nature' => 'fisica', 'name' => $u->name, 'user_id' => $u->id]);
        $c = $payee->contracts()->create([
            'production_id' => $this->prodId, 'concept' => 'crew_work',
            'department_id' => $did, 'contracted_by_user_id' => $u->id,
            'payment_frequency' => 'weekly', 'is_active' => 1,
        ]);
        DB::table('payee_contract_work_dates')->insert(['payee_contract_id' => $c->id, 'work_date' => $this->ds]);
        DB::table('contract_envelopes')->insert([
            'payee_contract_id' => $c->id, 'production_id' => $this->prodId,
            'status' => 'completed', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $u;
    }

    public function test_paquete_render_y_descarga_back(): void
    {
        $this->actingAsRole('coordinator');
        CallDay::create(['production_id' => $this->prodId, 'call_date' => $this->ds, 'general_call' => '07:00']);
        $this->makeCrew('Arte', 'Pat Paquete');

        $this->get(route('callsheet.package', ['date' => $this->ds]))->assertOk();
        $this->assertDatabaseHas('call_packages', ['production_id' => $this->prodId, 'call_date' => $this->ds]);

        // Sin front: el "paquete" es solo el back, y el back suelto también sale.
        $res = $this->get(route('callsheet.package.pdf', ['date' => $this->ds]));
        $res->assertOk();
        $this->assertStringStartsWith('%PDF', $res->getContent());
        $this->get(route('callsheet.package.back', ['date' => $this->ds]))->assertOk();
    }

    public function test_paquete_firma_de_los_3_congela(): void
    {
        $admin = $this->actingAsRole('coordinator');
        CallDay::create(['production_id' => $this->prodId, 'call_date' => $this->ds, 'general_call' => '07:00']);
        $this->makeCrew('Arte', 'Fir Mante');

        // Un "front" válido: reusa los bytes del back como PDF de prueba.
        $day  = Carbon::parse($this->ds);
        $back = app(CallSheetController::class)->renderBackPdf($admin, $day, []);
        $path = 'call-packages/' . $this->prodId . '/' . $this->ds . '-front.pdf';
        Storage::disk('local')->put($path, $back);

        $this->get(route('callsheet.package', ['date' => $this->ds]));   // crea el paquete
        $pkg = CallPackage::where('production_id', $this->prodId)->where('call_date', $this->ds)->first();
        $pkg->front_path = $path;
        $pkg->front_pages = 1;
        $pkg->sign_field_map = [['page' => 1, 'x_pct' => 10, 'y_pct' => 90, 'w_pct' => 20, 'type' => 'sign', 'key' => 'lp']];
        $pkg->signers = [
            ['key' => 'lp', 'role_label' => 'Productor en Línea', 'user_id' => null, 'name' => ''],
            ['key' => 'gte', 'role_label' => 'Gerente de Producción', 'user_id' => null, 'name' => ''],
            ['key' => 'ad', 'role_label' => '1er AD', 'user_id' => null, 'name' => ''],
        ];
        $pkg->save();

        // Enviar a aprobación.
        $this->post(route('callsheet.package.send', ['date' => $this->ds]))->assertRedirect();
        $pkg->refresh();
        $this->assertSame(CallPackage::PENDING, $pkg->status);
        $this->assertSame(3, $pkg->signatures()->count());

        // Firmar las 3 (coordinator tiene callsheet.manage → puede firmar cualquiera).
        $sig = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+P+/HgAFhAJ/wlseKgAAAABJRU5ErkJggg==';
        foreach (['lp', 'gte', 'ad'] as $k) {
            $this->post(route('callsheet.package.sign.do', ['date' => $this->ds]), ['signer_key' => $k, 'signature_image' => $sig])->assertRedirect();
        }

        $pkg->refresh();
        $this->assertSame(CallPackage::APPROVED, $pkg->status);
        $this->assertNotNull($pkg->approved_at);
        $this->assertNotNull($pkg->frozen_path);
        $this->assertTrue(Storage::disk('local')->exists($pkg->frozen_path));

        // El estado en vivo (polling) reporta aprobado.
        $this->getJson(route('callsheet.package.state', ['date' => $this->ds]))
            ->assertOk()->assertJson(['status' => 'approved', 'all_signed' => true]);
    }
}
