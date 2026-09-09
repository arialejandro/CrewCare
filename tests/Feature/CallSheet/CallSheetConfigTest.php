<?php

namespace Tests\Feature\CallSheet;

use App\Models\CallDay;
use App\Models\Production;
use App\Support\CurrentProduction;
use Illuminate\Support\Facades\DB;
use Tests\QaTestCase;

/**
 * PARTE E · config del llamado (rediseño 2026-08-23). El general es el ancla; las comidas se
 * AUTO-ESTABLECEN por la hora del general (Café/Craft siempre, sin box lunch) y se guardan como
 * OFFSET. Notas globales por producción. Verifica render, auto-comidas, offset, notas y gate.
 */
class CallSheetConfigTest extends QaTestCase
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

    public function test_general_de_manana_auto_establece_el_set_de_dia(): void
    {
        $this->actingAsRole('coordinator');
        $this->get(route('callsheet.config', ['date' => $this->ds]))->assertOk();

        $cd = CallDay::where('production_id', $this->prodId)->whereDate('call_date', $this->ds)->first();
        $this->assertNotNull($cd);
        $this->assertSame(0, $cd->meals()->count(), 'sin día previo no inventa comidas hasta poner el general');

        // General 07:00 → set de DÍA: Café/Craft, Desayuno, Snack, Comida, Snack (5).
        $this->post(route('callsheet.config.save', ['date' => $this->ds]), ['general_call' => '07:00'])
            ->assertRedirect(route('callsheet.config', ['date' => $this->ds]));

        $cd->refresh();
        $this->assertSame(5, $cd->meals()->count());
        $this->assertTrue($cd->meals()->where('label', 'Café/Craft')->exists(), 'Café/Craft siempre');
        $this->assertSame(-60, $cd->meals()->where('label', 'Desayuno')->value('offset_minutes'));
        $this->assertFalse($cd->meals()->where('label', 'like', '%box lunch%')->exists(), 'sin box lunch');
    }

    public function test_general_de_tarde_auto_establece_el_set_de_noche(): void
    {
        $this->actingAsRole('coordinator');
        $this->get(route('callsheet.config', ['date' => $this->ds]));
        $this->post(route('callsheet.config.save', ['date' => $this->ds]), ['general_call' => '17:00'])->assertRedirect();

        $cd = CallDay::where('production_id', $this->prodId)->whereDate('call_date', $this->ds)->first();
        // Set de NOCHE: Café/Craft, Snack, Cena, Snack fuerte (4).
        $this->assertSame(4, $cd->meals()->count());
        $this->assertTrue($cd->meals()->where('label', 'Cena')->exists());
        $this->assertFalse($cd->meals()->where('label', 'Desayuno')->exists());
    }

    public function test_comida_editada_se_guarda_como_offset(): void
    {
        $this->actingAsRole('coordinator');
        $this->get(route('callsheet.config', ['date' => $this->ds]));

        $this->post(route('callsheet.config.save', ['date' => $this->ds]), [
            'general_call' => '07:00',
            'meals' => [['label' => 'Comida', 'enabled' => '1', 'time' => '13:30']],   // 6.5 h tras el general
        ])->assertRedirect();

        $cd = CallDay::where('production_id', $this->prodId)->whereDate('call_date', $this->ds)->first();
        $meal = $cd->meals()->where('label', 'Comida')->first();
        $this->assertSame(390, $meal->offset_minutes, '13:30 con general 07:00 = +390 min como offset');
    }

    public function test_notas_globales_se_guardan_en_la_produccion(): void
    {
        $this->actingAsRole('coordinator');
        $this->get(route('callsheet.notes'))->assertOk();

        $this->post(route('callsheet.notes.save'), [
            'notes'      => 'Producción no se hace responsable de autos particulares.',
            'safety_bar' => 'Tu seguridad es primero',
        ])->assertRedirect(route('callsheet.notes'));

        $s = Production::find($this->prodId)->settings;
        $this->assertSame('Producción no se hace responsable de autos particulares.', $s['callsheet_notes']);
        $this->assertSame('Tu seguridad es primero', $s['callsheet_safety_bar']);
    }

    public function test_notas_se_guardan_desde_la_config(): void
    {
        $this->actingAsRole('coordinator');
        $this->get(route('callsheet.config', ['date' => $this->ds]))->assertOk();

        // Las notas globales viven ahora en la misma pantalla de config (antes de la firma).
        $this->post(route('callsheet.config.save', ['date' => $this->ds]), [
            'general_call' => '07:00',
            'notes'        => 'Hospital más cercano: 555-0100.',
            'safety_bar'   => 'Tu seguridad es primero',
        ])->assertRedirect(route('callsheet.config', ['date' => $this->ds]));

        $s = Production::find($this->prodId)->settings;
        $this->assertSame('Hospital más cercano: 555-0100.', $s['callsheet_notes']);
        $this->assertSame('Tu seguridad es primero', $s['callsheet_safety_bar']);
    }

    public function test_notas_extra_se_guardan_desde_la_config(): void
    {
        $this->actingAsRole('coordinator');
        $this->get(route('callsheet.config', ['date' => $this->ds]))->assertOk();

        // Hoteles + emergencia viven en config (alimentan bloques del pie). El FORMATO ya NO va aquí.
        $this->post(route('callsheet.config.save', ['date' => $this->ds]), [
            'general_call'   => '07:00',
            'hotels_note'    => 'FS = Four Seasons',
            'emergency_note' => 'Hospital: 555-0100',
        ])->assertRedirect(route('callsheet.config', ['date' => $this->ds]));

        $s = Production::find($this->prodId)->settings;
        $this->assertSame('FS = Four Seasons', $s['callsheet_hotels']);
        $this->assertSame('Hospital: 555-0100', $s['callsheet_emergency']);
    }

    public function test_formato_se_elige_en_su_pantalla(): void
    {
        $this->actingAsRole('coordinator');
        $this->get(route('callsheet.format'))->assertOk();

        $this->post(route('callsheet.format.save'), ['preset' => 'intl'])
            ->assertRedirect(route('callsheet.format'));
        $this->assertSame('intl', Production::find($this->prodId)->settings['callsheet_preset']);

        $this->post(route('callsheet.format.save'), ['preset' => 'no_existe'])->assertSessionHasErrors('preset');
    }

    public function test_gate_produccion(): void
    {
        $this->actingAsRole('crew');
        $this->get(route('callsheet.config', ['date' => $this->ds]))->assertForbidden();
        $this->get(route('callsheet.notes'))->assertForbidden();
    }
}
