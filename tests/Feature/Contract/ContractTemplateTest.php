<?php

namespace Tests\Feature\Contract;

use App\Models\ContractTemplate;
use App\Support\CurrentProduction;
use Illuminate\Support\Facades\DB;
use Tests\QaTestCase;

/**
 * CONTRACT BUILDER · editor de plantillas. Gating, persistencia y el PREVIEW (campos llenos +
 * anclas estampadas con firma de muestra).
 */
class ContractTemplateTest extends QaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $pid = DB::table('productions')->min('id');
        DB::table('productions')->where('id', $pid)->update(['active' => 1]);
        CurrentProduction::forget();
    }

    public function test_builder_gated_to_contracts_author(): void
    {
        // Abierto a quien REDACTA contratos: Line Producer y representante-legal.
        $this->actingAs($this->makeUser('line-producer'));
        $this->get(route('contracts.templates.index'))->assertOk();

        $repLegal = $this->makeUser('representante-legal');
        $this->actingAs($repLegal);
        $this->get(route('contracts.templates.index'))->assertOk();
        // La figura legal también FIRMA documentos (requisito del owner).
        $this->assertTrue($repLegal->can('documents.sign'), 'representante-legal firma documentos');

        // Cerrado a los demás: crew y coordinator NO tienen contracts.author.
        $this->actingAs($this->makeUser('crew'));
        $this->get(route('contracts.templates.index'))->assertForbidden();

        $this->actingAs($this->makeUser('coordinator'));
        $this->get(route('contracts.templates.index'))->assertForbidden();
    }

    public function test_store_and_update_persist(): void
    {
        $this->actingAs($this->makeUser('super-admin'));

        $this->post(route('contracts.templates.store'), [
            'name' => 'Contrato crew', 'applies_to' => ['crew_work'],
            'body' => '<p>Hola {{payee_nombre}}</p>', 'is_active' => 1,
        ])->assertRedirect();

        $tpl = ContractTemplate::firstWhere('name', 'Contrato crew');
        $this->assertNotNull($tpl);
        $this->assertTrue($tpl->is_active);
        $this->assertEqualsCanonicalizing(['crew_work'], $tpl->applies_to);

        $this->put(route('contracts.templates.update', $tpl), [
            'name' => 'Contrato crew v2', 'applies_to' => ['crew_work', 'service'], 'body' => '<p>x</p>',
        ])->assertRedirect();

        $fresh = $tpl->fresh();
        $this->assertSame('Contrato crew v2', $fresh->name);
        $this->assertFalse($fresh->is_active, 'sin is_active en el PUT → queda inactiva');
        $this->assertEqualsCanonicalizing(['crew_work', 'service'], $fresh->applies_to);
    }

    public function test_preview_fills_fields_and_stamps_signatures(): void
    {
        $this->actingAs($this->makeUser('super-admin'));

        $body = '<p>{{payee_nombre}} — {{honorarios}}</p><div>[[firma:contratado]]</div>';
        $res  = $this->post(route('contracts.templates.preview'), ['body' => $body]);
        $res->assertOk();
        $html = $res->getContent();

        $this->assertStringContainsString('María González Ríos', $html, 'el campo se llenó');
        $this->assertStringNotContainsString('{{payee_nombre}}', $html, 'no queda token crudo');
        $this->assertStringNotContainsString('[[firma:contratado]]', $html, 'no queda ancla cruda');
        $this->assertStringContainsString('cc-sig-stamp', $html, 'la firma se estampó');
    }

    public function test_create_preloads_scaffold_for_chosen_format(): void
    {
        $this->actingAs($this->makeUser('line-producer'));

        $res = $this->get(route('contracts.templates.create', ['arch' => 'field_sheet']));
        $res->assertOk();
        $res->assertSee('actividades empresariales');          // andamiaje real de la ficha (C)
        $res->assertSee('id="tplArch"', false);                // el selector de formato existe
        $res->assertSee('id="tplCanvas"', false);              // canvas Word-lite (WYSIWYG)
        $res->assertSee('cc-toolbar', false);                  // barra de formato
        $res->assertSee('cc-page', false);                     // la hoja (modo documento)
        $res->assertSee('id="tplPageSize"', false);            // selector de tamaño de página
    }

    public function test_store_persists_page_size(): void
    {
        $this->actingAs($this->makeUser('super-admin'));

        $this->post(route('contracts.templates.store'), [
            'name' => 'Contrato oficio', 'applies_to' => ['crew_work'],
            'page_size' => 'legal', 'body' => '<p>x</p>', 'is_active' => 1,
        ])->assertRedirect();

        $this->assertSame('legal', ContractTemplate::firstWhere('name', 'Contrato oficio')->page_size);
    }

    public function test_preview_sets_page_size(): void
    {
        $this->actingAs($this->makeUser('super-admin'));

        $legal = $this->post(route('contracts.templates.preview'), ['body' => '<p>x</p>', 'page_size' => 'legal']);
        $legal->assertOk();
        $this->assertStringContainsString('size:216mm 356mm', $legal->getContent(), 'Oficio/Legal');

        // tamaño inválido -> se normaliza a carta
        $carta = $this->post(route('contracts.templates.preview'), ['body' => '<p>x</p>', 'page_size' => 'bogus']);
        $this->assertStringContainsString('size:216mm 279mm', $carta->getContent(), 'default Carta');
    }

    public function test_store_persists_architecture(): void
    {
        $this->actingAs($this->makeUser('super-admin'));

        $this->post(route('contracts.templates.store'), [
            'name' => 'Ficha K&K', 'applies_to' => ['crew_work'],
            'architecture' => 'field_sheet', 'body' => '<p>{{payee_nombre}}</p>', 'is_active' => 1,
        ])->assertRedirect();

        $tpl = ContractTemplate::firstWhere('name', 'Ficha K&K');
        $this->assertSame('field_sheet', $tpl->architecture);
        $this->assertFalse($tpl->bilingual);
    }

    public function test_preview_applies_format_css(): void
    {
        $this->actingAs($this->makeUser('super-admin'));

        // declarations → CSS justificado (distintivo de ese formato)
        $res = $this->post(route('contracts.templates.preview'), ['body' => '<p>x</p>', 'architecture' => 'declarations']);
        $res->assertOk();
        $this->assertStringContainsString('text-align:justify', $res->getContent());

        // formato inválido → se normaliza al default (carátula), sin el CSS de declaraciones
        $res2 = $this->post(route('contracts.templates.preview'), ['body' => '<p>x</p>', 'architecture' => 'bogus']);
        $res2->assertOk();
        $this->assertStringNotContainsString('text-align:justify', $res2->getContent());
    }
}
