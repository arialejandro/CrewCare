<?php

namespace Tests\Feature\Payee;

use App\Models\AmbulanceProvider;
use App\Models\DocumentType;
use App\Models\ExternalAuthorization;
use App\Models\Payee;
use App\Models\PayeeContract;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\QaTestCase;

/**
 * VERIFICACIÓN del Paso 1 (BASE ÚNICA DE QUIEN COBRA · LA ENTIDAD). Cubre el checklist
 * del bloque: dos niveles, N:M por contrato, docs identidad vs contrato, CURP/NSS fuera
 * de identidad, scope hermano, fix de la foto de ambulancia y las formas de vigencia.
 */
class PayeeBaseTest extends QaTestCase
{
    /** Una identidad admite dos contratos con distinto contratante SIN duplicarse. */
    public function test_identity_holds_two_contracts_with_different_contractors(): void
    {
        $c1 = $this->makeUser('crew');
        $c2 = $this->makeUser('coordinator');

        $payee = Payee::create(['legal_nature' => Payee::NATURE_MORAL, 'name' => 'Casa de Renta X']);
        $payee->contracts()->create(['concept' => PayeeContract::CONCEPT_RENTAL, 'contracted_by_user_id' => $c1->id]);
        $payee->contracts()->create(['concept' => PayeeContract::CONCEPT_SERVICE, 'contracted_by_user_id' => $c2->id]);

        $this->assertSame(2, $payee->contracts()->count());
        $this->assertSame(1, Payee::where('name', 'Casa de Renta X')->count(), 'no se duplica la identidad');
        $this->assertSame(2, PayeeContract::whereIn('contracted_by_user_id', [$c1->id, $c2->id])
            ->distinct()->count('contracted_by_user_id'), 'dos contratantes distintos');
    }

    /** Crew que renta equipo: ligado a su user + dos contratos; fiscal en identidad, factura en contrato. */
    public function test_crew_member_renting_equipment_links_user_and_separates_documents(): void
    {
        $user = $this->makeUser('crew');
        $payee = Payee::create(['legal_nature' => Payee::NATURE_FISICA, 'name' => $user->name, 'user_id' => $user->id]);
        $crewContract = $payee->contracts()->create(['concept' => PayeeContract::CONCEPT_CREW,   'contracted_by_user_id' => $this->makeUser('coordinator')->id]);
        $rentContract = $payee->contracts()->create(['concept' => PayeeContract::CONCEPT_RENTAL, 'contracted_by_user_id' => $this->makeUser('coordinator')->id]);

        $this->assertTrue($payee->user->is($user), 'la identidad es la MISMA persona del crew');
        $this->assertSame(2, $payee->contracts()->count());

        $csf  = DocumentType::where('code', 'CSF')->firstOrFail();          // paquete fiscal → IDENTIDAD
        $fact = DocumentType::where('code', 'FACT_RENTA')->firstOrFail();   // factura de renta → CONTRATO
        $payee->documents()->create(['level' => 'persona', 'document_type' => $csf->name, 'document_type_id' => $csf->id, 'origen' => 'contractual', 'status' => 'presentado', 'is_active' => 1]);
        $rentContract->documents()->create(['level' => 'persona', 'document_type' => $fact->name, 'document_type_id' => $fact->id, 'origen' => 'contractual', 'status' => 'presentado', 'is_active' => 1]);

        $this->assertSame(1, $payee->documents()->count(), 'el paquete fiscal cuelga de la identidad');
        $this->assertSame(1, $rentContract->documents()->count(), 'la factura de renta cuelga del contrato');
        $this->assertSame(0, $crewContract->documents()->count(), 'no se mezclan');
        $this->assertSame(Payee::class, $payee->documents()->first()->holder_type);
        $this->assertSame(PayeeContract::class, $rentContract->documents()->first()->holder_type);
    }

    /** CURP y NSS NO son campo de identidad; el listado REPSE es un tipo `is_repse`. */
    public function test_curp_nss_are_not_identity_fields_but_a_repse_document(): void
    {
        $this->assertFalse(Schema::hasColumn('payees', 'curp'));
        $this->assertFalse(Schema::hasColumn('payees', 'nss'));

        $listado = DocumentType::where('code', 'REPSE_LISTADO_PERSONAL')->first();
        $this->assertNotNull($listado);
        $this->assertTrue((bool) $listado->is_repse, 'CURP/NSS aparecen solo con el régimen REPSE');
    }

    /** El HERMANO filtra por depto del contratante; la original y production_user quedan intactos. */
    public function test_contracting_scope_sibling_and_department_scope_intact(): void
    {
        $prodId = DB::table('productions')->value('id');
        $depts  = DB::table('departments')->orderBy('id')->limit(2)->pluck('id')->all();
        [$deptA, $deptB] = [$depts[0], $depts[1]];

        $viewer = $this->makeUser('crew');                 // NO tiene crew.view.all-departments
        $this->attachDept($viewer, $prodId, $deptA);
        $this->assertFalse($viewer->can('crew.view.all-departments'));

        $contractorA = $this->makeUser('crew'); $this->attachDept($contractorA, $prodId, $deptA);
        $contractorB = $this->makeUser('crew'); $this->attachDept($contractorB, $prodId, $deptB);

        $payee = Payee::create(['legal_nature' => Payee::NATURE_MORAL, 'name' => 'Prov N']);
        $ctA = $payee->contracts()->create(['concept' => 'service', 'contracted_by_user_id' => $contractorA->id]);
        $ctB = $payee->contracts()->create(['concept' => 'service', 'contracted_by_user_id' => $contractorB->id]);

        $visible = User::applyContractingScope(PayeeContract::query(), $viewer)->pluck('id')->all();
        $this->assertContains($ctA->id, $visible, 'contrato del depto A: visible');
        $this->assertNotContains($ctB->id, $visible, 'contrato del depto B: NO visible');

        // La original sigue operando sobre users (production_user intacto).
        $seen = User::applyDepartmentScope(DB::table('users'), $viewer)->pluck('id')->all();
        $this->assertContains($contractorA->id, $seen);
        $this->assertNotContains($contractorB->id, $seen);
    }

    /** El form de documento de ambulancia GUARDA su foto (fix name photo_path → photo). */
    public function test_ambulance_document_form_stores_photo(): void
    {
        Storage::fake('public');
        $this->actingAsRole('super-admin');
        $provider = AmbulanceProvider::create(['name' => 'Ambu SA', 'is_active' => 1]);

        $resp = $this->post(route('ambulance.document.store'), [
            'holder_type'   => 'empresa',
            'holder_id'     => $provider->id,
            'document_type' => 'Póliza',
            'origen'        => 'normativo',
            'status'        => 'presentado',
            'photo'         => UploadedFile::fake()->image('poliza.jpg', 800, 600),
        ]);

        $resp->assertRedirect();
        $doc = ExternalAuthorization::latest('id')->first();
        $this->assertNotNull($doc);
        $this->assertNotNull($doc->photo_path, 'la foto YA no se descarta en silencio');
        Storage::disk('public')->assertExists($doc->photo_path);
    }

    /** Vigencia: mes corriente caduca distinto que 3 meses (90 días). 32-D exige positiva. */
    public function test_validity_shapes_expire_differently(): void
    {
        $issued = \Carbon\Carbon::parse('2026-08-05');
        $csf = DocumentType::where('code', 'CSF')->firstOrFail();            // current_month
        $dom = DocumentType::where('code', 'COMP_DOMICILIO')->firstOrFail(); // days_from_emission 90

        $this->assertSame('2026-08-31', $csf->expiryFrom($issued)->toDateString());
        $this->assertSame($issued->copy()->addDays(90)->toDateString(), $dom->expiryFrom($issued)->toDateString());
        $this->assertNotSame($csf->expiryFrom($issued)->toDateString(), $dom->expiryFrom($issued)->toDateString());

        $this->assertNull($csf->expiryFrom(null), 'sin emisión no calcula');
        $this->assertTrue((bool) DocumentType::where('code', 'OPINION_32D')->firstOrFail()->requires_positive_status);
    }

    private function attachDept(User $u, $prodId, $deptId): void
    {
        DB::table('production_user')->updateOrInsert(
            ['production_id' => $prodId, 'user_id' => $u->id],
            ['department_id' => $deptId, 'role' => 'crew', 'is_lead' => 0, 'created_at' => now(), 'updated_at' => now()]
        );
    }
}
