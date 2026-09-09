<?php

namespace Tests\Feature\Payee;

use App\Models\Payee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\QaTestCase;

/**
 * PASO 4 · VISIBILIDAD — "quien contrata es quien ve", serve gateado de documentos, y la
 * exclusividad de ambulancias (producción/safety, nunca transpo) verificada por URL directa.
 */
class PayeeVisibilityTest extends QaTestCase
{
    private $prodId;
    private $deptA; // "arte"
    private $deptB; // "transpo"

    protected function setUp(): void
    {
        parent::setUp();
        $this->prodId = DB::table('productions')->min('id');
        $depts = DB::table('departments')->orderBy('id')->limit(2)->pluck('id')->all();
        [$this->deptA, $this->deptB] = $depts;
    }

    private function attachDept(User $u, $deptId): void
    {
        DB::table('production_user')->updateOrInsert(
            ['production_id' => $this->prodId, 'user_id' => $u->id],
            ['department_id' => $deptId, 'role' => $u->getRoleNames()->first(), 'is_lead' => 0, 'created_at' => now(), 'updated_at' => now()]
        );
    }

    /** Crea un payee contratado por $by (su depto define quién lo ve). */
    private function payeeContractedBy(User $by, string $name): Payee
    {
        $p = Payee::create(['legal_nature' => 'moral', 'name' => $name, 'is_active' => 1]);
        $p->contracts()->create(['concept' => 'service', 'contracted_by_user_id' => $by->id]);
        return $p;
    }

    // ── 4.2 · scope "quien contrata es quien ve" ──────────────────────────────
    public function test_hod_sees_only_payees_contracted_in_own_department(): void
    {
        $hodA = $this->makeUser('hod'); $this->attachDept($hodA, $this->deptA);
        $hodB = $this->makeUser('hod'); $this->attachDept($hodB, $this->deptB);

        $payeeA = $this->payeeContractedBy($hodA, 'Arte SA');
        $payeeB = $this->payeeContractedBy($hodB, 'Transpo SA');

        // El HOD de arte ve el suyo, NO el de transpo (ni en el índice ni por URL directa).
        $this->actingAs($hodA);
        $this->get(route('payees.index'))->assertOk()->assertSee('Arte SA')->assertDontSee('Transpo SA');
        $this->get(route('payees.show', $payeeA))->assertOk();
        $this->get(route('payees.show', $payeeB))->assertForbidden();
    }

    public function test_all_departments_role_sees_everything(): void
    {
        $hodA = $this->makeUser('hod'); $this->attachDept($hodA, $this->deptA);
        $payeeA = $this->payeeContractedBy($hodA, 'Arte SA');

        // line-producer tiene crew.view.all-departments → bypass, ve todo.
        $lp = $this->makeUser('line-producer');
        $this->actingAs($lp);
        $this->get(route('payees.index'))->assertOk()->assertSee('Arte SA');
        $this->get(route('payees.show', $payeeA))->assertOk();
    }

    public function test_module_gate_blocks_roles_without_permission(): void
    {
        // crew no tiene payees.view → 403 de middleware, ni siquiera llega al scope.
        $this->actingAs($this->makeUser('crew'));
        $this->get(route('payees.index'))->assertForbidden();
    }

    // ── 4.1 · serve gateado de documentos ─────────────────────────────────────
    public function test_document_is_served_only_to_scoped_viewer(): void
    {
        Storage::fake('local');
        $hodA = $this->makeUser('hod'); $this->attachDept($hodA, $this->deptA);
        $hodB = $this->makeUser('hod'); $this->attachDept($hodB, $this->deptB);

        $payeeA = $this->payeeContractedBy($hodA, 'Arte SA');
        $doc = $payeeA->documents()->create([
            'level' => 'persona', 'document_type' => 'INE',
            'photo_path' => 'payee/docs/' . $payeeA->id . '/doc_secret.pdf', 'is_active' => 1,
        ]);
        Storage::disk('local')->put($doc->photo_path, '%PDF-1.4 contenido privado');

        // En alcance → 200 y PDF.
        $this->actingAs($hodA);
        $this->get(route('payees.document', ['payee' => $payeeA->id, 'doc' => $doc->id]))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        // Fuera de alcance → 403 (nunca el archivo).
        $this->actingAs($hodB);
        $this->get(route('payees.document', ['payee' => $payeeA->id, 'doc' => $doc->id]))->assertForbidden();
    }

    public function test_document_must_belong_to_the_payee(): void
    {
        Storage::fake('local');
        $lp = $this->makeUser('line-producer'); // bypass, para aislar el 404 del holder
        $hodA = $this->makeUser('hod'); $this->attachDept($hodA, $this->deptA);
        $hodB = $this->makeUser('hod'); $this->attachDept($hodB, $this->deptB);

        $payeeA = $this->payeeContractedBy($hodA, 'Arte SA');
        $payeeB = $this->payeeContractedBy($hodB, 'Transpo SA');
        $docB = $payeeB->documents()->create([
            'level' => 'persona', 'document_type' => 'INE',
            'photo_path' => 'payee/docs/' . $payeeB->id . '/doc_b.pdf', 'is_active' => 1,
        ]);
        Storage::disk('local')->put($docB->photo_path, '%PDF fake');

        // El doc de B no cuelga de A → 404 aunque el viewer tenga alcance total.
        $this->actingAs($lp);
        $this->get(route('payees.document', ['payee' => $payeeA->id, 'doc' => $docB->id]))->assertNotFound();
    }

    /** La descarga de un documento fiscal queda REGISTRADA (bitácora) y NO se bloquea. */
    public function test_document_download_is_logged(): void
    {
        Storage::fake('local');
        $hodA = $this->makeUser('hod'); $this->attachDept($hodA, $this->deptA);
        $payeeA = $this->payeeContractedBy($hodA, 'Arte SA');
        $doc = $payeeA->documents()->create([
            'level' => 'persona', 'document_type' => 'CSF',
            'photo_path' => 'payee/docs/' . $payeeA->id . '/doc_fiscal.pdf', 'is_active' => 1,
        ]);
        Storage::disk('local')->put($doc->photo_path, '%PDF-1.4 privado');

        $this->actingAs($hodA);
        $this->get(route('payees.document', ['payee' => $payeeA->id, 'doc' => $doc->id]))->assertOk();

        $this->assertDatabaseHas('payee_document_downloads', [
            'payee_id'    => $payeeA->id,
            'document_id' => $doc->id,
            'user_id'     => $hodA->id,
        ]);
    }

    /** La descarga ZIP por-persona empaqueta sus documentos, respeta el alcance y los registra. */
    public function test_person_zip_download_bundles_docs_and_logs(): void
    {
        Storage::fake('local');
        $hodA = $this->makeUser('hod'); $this->attachDept($hodA, $this->deptA);
        $hodB = $this->makeUser('hod'); $this->attachDept($hodB, $this->deptB);
        $payeeA = $this->payeeContractedBy($hodA, 'Arte SA');

        $d1 = $payeeA->documents()->create([
            'level' => 'persona', 'document_type' => 'CSF',
            'photo_path' => 'payee/docs/arte/' . $payeeA->id . '/doc_1.pdf', 'is_active' => 1,
        ]);
        $d2 = $payeeA->documents()->create([
            'level' => 'persona', 'document_type' => 'INE',
            'photo_path' => 'payee/docs/arte/' . $payeeA->id . '/doc_2.pdf', 'is_active' => 1,
        ]);
        Storage::disk('local')->put($d1->photo_path, '%PDF-1.4 uno');
        Storage::disk('local')->put($d2->photo_path, '%PDF-1.4 dos');

        // En alcance → 200, zip, y AMBOS docs quedan en la bitácora.
        $this->actingAs($hodA);
        $res = $this->get(route('payees.documents.zip', $payeeA));
        $res->assertOk();
        $this->assertStringContainsString('zip', strtolower((string) $res->headers->get('content-type')));
        $this->assertDatabaseHas('payee_document_downloads', ['payee_id' => $payeeA->id, 'document_id' => $d1->id, 'user_id' => $hodA->id]);
        $this->assertDatabaseHas('payee_document_downloads', ['payee_id' => $payeeA->id, 'document_id' => $d2->id, 'user_id' => $hodA->id]);

        // Fuera de alcance → 403 (nunca el paquete).
        $this->actingAs($hodB);
        $this->get(route('payees.documents.zip', $payeeA))->assertForbidden();
    }

    /** Sin documentos con archivo, la descarga ZIP no truena: regresa con aviso. */
    public function test_person_zip_download_without_docs_redirects(): void
    {
        Storage::fake('local');
        $hodA = $this->makeUser('hod'); $this->attachDept($hodA, $this->deptA);
        $payeeA = $this->payeeContractedBy($hodA, 'Arte SA');

        $this->actingAs($hodA);
        $this->get(route('payees.documents.zip', $payeeA))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    /** Un doc con archivo en disco, colgado del payee. Helper para el bulk. */
    private function docFor(Payee $payee, string $type, string $body): \App\Models\ExternalAuthorization
    {
        $doc = $payee->documents()->create([
            'level' => 'persona', 'document_type' => $type,
            'photo_path' => 'payee/docs/' . $payee->id . '/' . $type . '_' . uniqid() . '.pdf', 'is_active' => 1,
        ]);
        Storage::disk('local')->put($doc->photo_path, $body);
        return $doc;
    }

    /** La descarga MASIVA (global) empaqueta a TODOS los visibles y registra cada doc. */
    public function test_bulk_download_global_bundles_all_visible_and_logs(): void
    {
        Storage::fake('local');
        $lp = $this->makeUser('line-producer'); // bypass → ve todo
        $hodA = $this->makeUser('hod'); $this->attachDept($hodA, $this->deptA);
        $hodB = $this->makeUser('hod'); $this->attachDept($hodB, $this->deptB);

        $pA = $this->payeeContractedBy($hodA, 'Arte SA');
        $pB = $this->payeeContractedBy($hodB, 'Transpo SA');
        $dA = $this->docFor($pA, 'CSF', '%PDF-1.4 arte');
        $dB = $this->docFor($pB, 'INE', '%PDF-1.4 transpo');

        $this->actingAs($lp);
        $res = $this->get(route('payees.documents.bulk'));
        $res->assertOk();
        $this->assertStringContainsString('zip', strtolower((string) $res->headers->get('content-type')));

        // Ambos docs (de ambos departamentos) quedan en la bitácora.
        $this->assertDatabaseHas('payee_document_downloads', ['payee_id' => $pA->id, 'document_id' => $dA->id, 'user_id' => $lp->id]);
        $this->assertDatabaseHas('payee_document_downloads', ['payee_id' => $pB->id, 'document_id' => $dB->id, 'user_id' => $lp->id]);
    }

    /** El filtro por departamento acota el bulk a ese depto (no incluye al otro). */
    public function test_bulk_download_by_department_filters(): void
    {
        Storage::fake('local');
        $lp = $this->makeUser('line-producer');
        $hodA = $this->makeUser('hod'); $this->attachDept($hodA, $this->deptA);
        $hodB = $this->makeUser('hod'); $this->attachDept($hodB, $this->deptB);

        $pA = $this->payeeContractedBy($hodA, 'Arte SA');
        $pB = $this->payeeContractedBy($hodB, 'Transpo SA');
        $dA = $this->docFor($pA, 'CSF', '%PDF-1.4 arte');
        $dB = $this->docFor($pB, 'INE', '%PDF-1.4 transpo');

        $this->actingAs($lp);
        $this->get(route('payees.documents.bulk', ['dept' => $this->deptA]))->assertOk();

        // Solo el de arte quedó registrado; el de transpo NO (quedó fuera del filtro).
        $this->assertDatabaseHas('payee_document_downloads', ['document_id' => $dA->id]);
        $this->assertDatabaseMissing('payee_document_downloads', ['document_id' => $dB->id]);
    }

    /** El bulk respeta el ALCANCE: un HOD solo empaqueta lo de su departamento. */
    public function test_bulk_download_is_scoped_to_viewer(): void
    {
        Storage::fake('local');
        $hodA = $this->makeUser('hod'); $this->attachDept($hodA, $this->deptA);
        $hodB = $this->makeUser('hod'); $this->attachDept($hodB, $this->deptB);

        $pA = $this->payeeContractedBy($hodA, 'Arte SA');
        $pB = $this->payeeContractedBy($hodB, 'Transpo SA');
        $dA = $this->docFor($pA, 'CSF', '%PDF-1.4 arte');
        $dB = $this->docFor($pB, 'INE', '%PDF-1.4 transpo');

        // El HOD de arte pide "global": SOLO recibe lo suyo, nunca el doc de transpo.
        $this->actingAs($hodA);
        $this->get(route('payees.documents.bulk'))->assertOk();
        $this->assertDatabaseHas('payee_document_downloads', ['document_id' => $dA->id, 'user_id' => $hodA->id]);
        $this->assertDatabaseMissing('payee_document_downloads', ['document_id' => $dB->id]);
    }

    /** Sin documentos en el filtro, el bulk no truena: regresa con aviso. */
    public function test_bulk_download_without_docs_redirects(): void
    {
        Storage::fake('local');
        $hodA = $this->makeUser('hod'); $this->attachDept($hodA, $this->deptA);
        $this->payeeContractedBy($hodA, 'Arte SA'); // sin documentos

        $this->actingAs($hodA);
        $this->get(route('payees.documents.bulk'))->assertRedirect()->assertSessionHas('error');
    }

    // ── 4.3 · ambulancias exclusivo producción/safety, nunca transpo ──────────
    public function test_transpo_hod_cannot_reach_ambulances_by_direct_url(): void
    {
        // Un HOD (transpo) no tiene ambulance.manage ni ambulance.view.
        $hod = $this->makeUser('hod'); $this->attachDept($hod, $this->deptB);
        $this->actingAs($hod);
        $this->get('/ambulancia')->assertForbidden();
        $this->get('/ambulancia/actas')->assertForbidden();
    }

    public function test_production_sees_ambulances_but_cannot_manage(): void
    {
        // line-producer = producción: VE (ambulance.view) pero NO gestiona (manage).
        $this->actingAs($this->makeUser('line-producer'));
        $this->get('/ambulancia')->assertOk();
        $this->get('/ambulancia/verificar')->assertForbidden(); // escritura = manage
    }

    public function test_safety_manages_ambulances(): void
    {
        $this->actingAs($this->makeUser('safety-officer'));
        $this->get('/ambulancia')->assertOk();
        $this->get('/ambulancia/verificar')->assertOk(); // safety sí gestiona
    }

    // ── 4.4 · los permisos existentes no cambian para quien no debe ───────────
    public function test_default_grants_are_tight(): void
    {
        // Quien NO contrata no recibe payees.view por defecto.
        foreach (['crew', 'medic', 'safety-officer', 'auditor'] as $role) {
            $this->assertFalse($this->makeUser($role)->can('payees.view'), "$role NO debe ver payees por defecto");
        }
        // Producción y HOD sí.
        foreach (['line-producer', 'coordinator', 'hod'] as $role) {
            $this->assertTrue($this->makeUser($role)->can('payees.view'), "$role sí ve payees");
        }
    }
}
