<?php

namespace Tests\Feature\Contract;

use App\Exceptions\ContractEmitException;
use App\Models\ContractClause;
use App\Models\Department;
use App\Models\Payee;
use App\Models\PayeeContract;
use App\Models\Setting;
use App\Support\Branding;
use App\Support\ContractCoverSheet;
use App\Support\ContractEmitter;
use App\Support\CurrentProduction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\QaTestCase;

/**
 * EL CONTRATO · PASO B — EL DOCUMENTO. Biblioteca de clausulados (byte-intact, versionada, por
 * subtipo), carátula dompdf neutra (bilingüe, oculta vacíos, cero marca CrewCare) y CONGELADO al
 * emitir (clausulado+versión, idioma, contratante).
 */
class ContractDocumentTest extends QaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Asegura una producción activa para el store() de clausulados (usa CurrentProduction).
        DB::table('productions')->where('id', $this->prod())->update(['active' => 1]);
        CurrentProduction::forget();
    }

    private function prod()
    {
        return DB::table('productions')->min('id');
    }

    private function setContractor(array $overrides = []): void
    {
        $vals = array_merge([
            'company_name'        => 'Pimienta Films SA',
            'rfc'                 => 'PFI860101AB3',
            'office_address'      => 'Reforma 222, CDMX',
            'representante_legal' => 'Ana Pérez',
            'correo_contratante'  => 'legal@pimienta.mx',
        ], $overrides);
        foreach ($vals as $k => $v) {
            Setting::updateOrCreate(['key' => $k], ['value' => $v]);
        }
        Branding::forget();
    }

    private function clause(array $extra = []): ContractClause
    {
        return ContractClause::create(array_merge([
            'production_id' => $this->prod(),
            'name'          => 'Contrato Crew',
            'applies_to'    => ['crew_work'],
            'language'      => 'es',
            'file_path'     => 'contracts/clauses/x.pdf',
            'version'       => 1,
            'is_active'     => 1,
        ], $extra));
    }

    private function crewContract(array $extra = []): PayeeContract
    {
        $payee = Payee::create(['legal_nature' => 'fisica', 'name' => 'Juan Crew']);
        return $payee->contracts()->create(array_merge([
            'concept' => PayeeContract::CONCEPT_CREW, 'is_active' => 1, 'production_id' => $this->prod(),
            'crew_activity' => 'Gaffer',
        ], $extra));
    }

    public function test_clauses_offer_only_matching_subtype(): void
    {
        $crew = $this->clause(['applies_to' => ['crew_work']]);
        $svc  = $this->clause(['applies_to' => ['service'], 'name' => 'Vendor']);

        $applicable = ContractClause::forProduction($this->prod())->active()->get()
            ->filter(fn ($c) => $c->appliesToSubtype(PayeeContract::CONCEPT_CREW));

        $this->assertTrue($applicable->contains('id', $crew->id));
        $this->assertFalse($applicable->contains('id', $svc->id), 'un clausulado de service no se ofrece a crew_work');
    }

    public function test_uploaded_clause_is_byte_intact(): void
    {
        Storage::fake('local');
        $this->actingAs($this->makeUser('super-admin'));

        $bytes = "%PDF-1.7\nclausulado real " . str_repeat('X', 200) . "\n%%EOF";
        $file  = UploadedFile::fake()->createWithContent('contrato.pdf', $bytes);

        $this->post(route('contracts.clauses.store'), [
            'name' => 'Contrato Crew', 'file' => $file, 'applies_to' => ['crew_work'], 'language' => 'es',
        ])->assertRedirect();

        $clause = ContractClause::firstOrFail();
        $this->assertSame(hash('sha256', $bytes), $clause->file_hash, 'el hash coincide');
        $this->assertSame($bytes, Storage::disk('local')->get($clause->file_path), 'se conserva byte-idéntico');

        $this->get(route('contracts.clauses.download', $clause))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_new_version_does_not_change_an_emitted_contract(): void
    {
        Storage::fake('local');
        $this->setContractor();
        $contract = $this->crewContract();
        $v1 = $this->clause(['version' => 1, 'root_id' => null]);

        ContractEmitter::emit($contract, $v1, 'es', $this->makeUser('line-producer'));
        $this->assertSame($v1->id, $contract->fresh()->clause_id);

        // Subir una versión nueva de la misma familia…
        $this->clause(['version' => 2, 'root_id' => $v1->id]);
        // …no cambia el contrato ya emitido: sigue apuntando a v1.
        $this->assertSame($v1->id, $contract->fresh()->clause_id, 'lo emitido sigue en su versión');
    }

    public function test_caratula_hides_empty_rows_and_has_no_crewcare_mark(): void
    {
        $this->setContractor();
        $contract = $this->crewContract(['crew_activity' => 'Gaffer', 'fee_amount' => 15000, 'fee_currency' => 'MXN']);

        $rows = ContractCoverSheet::rows($contract, 'es');
        $labels = collect($rows)->pluck('label_es');

        $this->assertTrue(collect($rows)->contains(fn ($r) => $r['value'] === 'Gaffer'), 'pinta lo que sí tiene');
        $this->assertFalse($labels->contains('Viático desayuno'), 'un campo vacío no imprime su renglón');

        // La plantilla no lleva ninguna marca de CrewCare.
        $html = view('contracts.caratula', [
            'rows' => $rows, 'language' => 'es', 'logo' => null, 'contract' => $contract,
        ])->render();
        $this->assertStringNotContainsStringIgnoringCase('crewcare', $html);
        $this->assertNull(ContractCoverSheet::logoDataUri(), 'sin client_logo no hay logo (nunca el de CrewCare)');
    }

    public function test_caratula_renders_in_each_language(): void
    {
        $this->setContractor();
        $dept = Department::first();
        $contract = $this->crewContract(['department_id' => optional($dept)->id]);

        foreach (['es', 'en', 'bilingual'] as $lang) {
            $bytes = ContractCoverSheet::render($contract, $lang);
            $this->assertStringStartsWith('%PDF', $bytes, "la carátula sale en {$lang}");
        }
    }

    public function test_language_inherited_from_clause_is_overridable_before_emit(): void
    {
        Storage::fake('local');
        $this->setContractor();
        $contract = $this->crewContract();
        $clause = $this->clause(['language' => ContractClause::LANG_BILINGUAL]);

        // Se emite en 'es' aunque el clausulado sea bilingüe (idioma cambiado antes de emitir).
        ContractEmitter::emit($contract, $clause, 'es', $this->makeUser('line-producer'));
        $this->assertSame('es', $contract->fresh()->language);
    }

    public function test_emit_freezes_contractor_and_settings_edit_does_not_change_it(): void
    {
        Storage::fake('local');
        $this->setContractor(['company_name' => 'Empresa A SA']);
        $contract = $this->crewContract();
        $clause = $this->clause();

        ContractEmitter::emit($contract, $clause, 'es', $this->makeUser('line-producer'));
        $this->assertSame('Empresa A SA', $contract->fresh()->contractor_legal_name);
        $this->assertNotNull($contract->fresh()->caratula_path, 'se generó la carátula');
        $this->assertTrue($contract->fresh()->isEmitted());

        // Editar settings DESPUÉS no cambia un contrato ya emitido.
        $this->setContractor(['company_name' => 'Empresa B SA']);
        $this->assertSame('Empresa A SA', $contract->fresh()->contractor_legal_name, 'lo emitido no cambia');
    }

    public function test_emit_refuses_incomplete_contractor(): void
    {
        // Contratante VACÍO en settings.
        foreach (['company_name', 'rfc', 'office_address', 'representante_legal', 'correo_contratante'] as $k) {
            Setting::updateOrCreate(['key' => $k], ['value' => '']);
        }
        Branding::forget();

        $contract = $this->crewContract();
        $clause = $this->clause();

        try {
            ContractEmitter::emit($contract, $clause, 'es', null);
            $this->fail('debió lanzar ContractEmitException');
        } catch (ContractEmitException $e) {
            $this->assertNotEmpty($e->missing, 'dice qué falta del contratante');
        }
        $this->assertFalse($contract->fresh()->isEmitted(), 'no se emitió con contratante incompleto');
    }

    public function test_clause_library_gated_to_settings_manage(): void
    {
        // line-producer NO tiene settings.manage.
        $this->actingAs($this->makeUser('line-producer'));
        $this->get(route('contracts.clauses.index'))->assertForbidden();

        // super-admin sí (Gate::before).
        $this->actingAs($this->makeUser('super-admin'));
        $this->get(route('contracts.clauses.index'))->assertOk();
    }

    public function test_emit_endpoint_produces_caratula(): void
    {
        Storage::fake('local');
        $this->setContractor();
        $contract = $this->crewContract();
        $clause = $this->clause();

        // line-producer (bypass) puede capturar el payee.
        $this->actingAs($this->makeUser('line-producer'));
        $this->post(route('contracts.emit', $contract), ['clause_id' => $clause->id, 'language' => 'es'])
            ->assertRedirect();

        $this->assertTrue($contract->fresh()->isEmitted());
        $this->get(route('contracts.caratula', $contract))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }
}
