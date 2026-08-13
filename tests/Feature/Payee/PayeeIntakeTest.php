<?php

namespace Tests\Feature\Payee;

use App\Http\Controllers\IntakeController;
use App\Models\DocumentType;
use App\Models\Payee;
use App\Models\PayeeBeneficiary;
use App\Models\PayeeDeclaredEquipment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\QaTestCase;

/**
 * VERIFICACIÓN del Paso 3 (intake autoservicio + captura por quien contrata + estado RECIBIDO).
 */
class PayeeIntakeTest extends QaTestCase
{
    private function signedStore(User $user): string
    {
        return URL::temporarySignedRoute('intake.store', now()->addDay(), ['user' => $user->id]);
    }

    private function attachDept(User $u, $prodId, $deptId): void
    {
        DB::table('production_user')->updateOrInsert(
            ['production_id' => $prodId, 'user_id' => $u->id],
            ['department_id' => $deptId, 'role' => 'crew', 'is_lead' => 0, 'created_at' => now(), 'updated_at' => now()]
        );
    }

    /** El intake cae en payees/regimes/ledger; el doc queda RECIBIDO (no validado) y con quién subió. */
    public function test_self_intake_persists_and_document_is_received_not_validated(): void
    {
        Storage::fake('local');
        $user = $this->makeUser('crew');
        $csf = DocumentType::where('code', 'CSF')->firstOrFail();

        $this->post($this->signedStore($user), [
            'name' => 'Juan Pérez', 'rfc' => 'PEXJ800101AB1', 'nationality' => 'mexicana',
            'regimes' => [['code' => '605', 'name' => 'Sueldos y salarios']],
            'documents' => [$csf->id => UploadedFile::fake()->create('csf.pdf', 120, 'application/pdf')],
        ])->assertOk();

        $payee = Payee::where('user_id', $user->id)->first();
        $this->assertNotNull($payee);
        $this->assertNotNull($payee->intake_submitted_at);
        $this->assertSame(1, $payee->fiscalRegimes()->count());

        $doc = $payee->documents()->first();
        $this->assertNotNull($doc);
        $this->assertSame('presentado', $doc->status, 'RECIBIDO');
        $this->assertNull($doc->validated_at, 'recibir no es validar (cotejo aparte)');
        $this->assertSame($user->id, (int) $doc->created_by_id, 'quién subió');
        Storage::disk('local')->assertExists($doc->photo_path);
    }

    /** Beneficiarios que no suman 100% no se guardan. */
    public function test_beneficiaries_must_sum_100(): void
    {
        $user = $this->makeUser('crew');

        $this->post($this->signedStore($user), ['beneficiaries' => [
            ['full_name' => 'A', 'relationship' => 'hijo', 'percentage' => 90],
        ]])->assertSessionHas('error');
        $this->assertSame(0, PayeeBeneficiary::count(), '90% no se guarda');

        $this->post($this->signedStore($user), ['beneficiaries' => [
            ['full_name' => 'A', 'relationship' => 'hijo', 'percentage' => 60],
            ['full_name' => 'B', 'relationship' => 'hija', 'percentage' => 40],
        ]])->assertOk();
        $this->assertSame(2, PayeeBeneficiary::count(), '100% sí se guarda');
    }

    /** Equipo declarado sobre el umbral queda FIRMADO (capa simple); bajo el umbral se ignora. */
    public function test_declared_equipment_over_threshold_is_signed(): void
    {
        $user = $this->makeUser('crew');

        $this->post($this->signedStore($user), ['declared_equipment' => [
            ['description' => 'Starlink', 'invoice_holder' => 'Juan', 'declared_value' => 8000],
            ['description' => 'Cable',    'invoice_holder' => 'Juan', 'declared_value' => 500],
        ]])->assertOk();

        $eqs = PayeeDeclaredEquipment::all();
        $this->assertCount(1, $eqs, 'solo el que supera el umbral (6000)');
        $eq = $eqs->first();
        $this->assertSame('Starlink', $eq->description);
        $this->assertNotNull($eq->accepted_at);
        $this->assertTrue($eq->signatures()->exists(), 'declaración firmada con la capa simple');
        $this->assertTrue($eq->isSigned());
    }

    /** Tipo de sangre y alergias NO aparecen en el intake (son dato clínico). */
    public function test_no_blood_type_or_allergies_in_intake(): void
    {
        $this->assertFalse(Schema::hasColumn('payees', 'blood_type'));
        $this->assertFalse(Schema::hasColumn('payees', 'allergies'));
        $this->assertFalse(Schema::hasColumn('payees', 'tipo_sangre'));
        $this->assertFalse(Schema::hasColumn('payees', 'alergias'));
    }

    /** Quien contrata captura por un proveedor NO usuario; escribe las mismas tablas; guarda de depto. */
    public function test_contractor_captures_and_is_department_guarded(): void
    {
        $prodId = DB::table('productions')->min('id');
        $depts  = DB::table('departments')->orderBy('id')->limit(2)->pluck('id')->all();

        $contractor = $this->makeUser('crew');
        $this->attachDept($contractor, $prodId, $depts[0]);
        $payee = Payee::create(['legal_nature' => 'moral', 'name' => 'Jardines SA']);
        $payee->contracts()->create(['concept' => 'service', 'contracted_by_user_id' => $contractor->id]);

        $this->actingAs($contractor);
        $this->post(route('payee.intake.store', $payee), ['name' => 'Jardines SA de CV', 'rfc' => 'JAR800101AB1'])
            ->assertRedirect();
        $this->assertNotNull($payee->fresh()->intake_submitted_at);

        // Un usuario de OTRO depto (sin all-departments) no puede tocar ese payee.
        $outsider = $this->makeUser('crew');
        $this->attachDept($outsider, $prodId, $depts[1]);
        $this->actingAs($outsider);
        $this->post(route('payee.intake.store', $payee), ['name' => 'hack'])->assertForbidden();
    }

    /** El alta dispara una invitación FIRMADA; sin firma la puerta se cierra (403). */
    public function test_signed_invitation_gates_the_intake(): void
    {
        $user = $this->makeUser('crew');
        $url = IntakeController::invitationUrl($user);

        $this->assertStringContainsString('/intake/' . $user->id, $url);
        $this->assertStringContainsString('signature=', $url);
        $this->get('/intake/' . $user->id)->assertForbidden(); // sin firma
    }
}
