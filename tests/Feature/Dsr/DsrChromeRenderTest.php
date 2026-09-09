<?php

namespace Tests\Feature\Dsr;

use App\Models\DailyReport;
use App\Models\Production;
use App\Models\User;
use App\Support\CurrentProduction;
use Spatie\Permission\PermissionRegistrar;
use Tests\QaTestCase;

/**
 * DSR · el chrome compartido (_report-v2-foot) YA NO duplica ni parte el <script>. Regresión determinista
 * del bug: un `@stack('scripts')` literal en un comentario del foot hacía que Blade renderizara el stack de
 * scripts DOS veces — una dentro del <script> del foot (metiendo un </script> que lo partía). Se prueba que
 * el fuente del typeahead (window.CCTypeahead) aparece EXACTAMENTE UNA vez en el HTML, y que el form de
 * "+ Hallazgo" trae sus 3 campos. Corre en crewcare_test (controla permisos); DSR fresco = no sellado.
 */
class DsrChromeRenderTest extends QaTestCase
{
    private function prod(): Production
    {
        $prod = Production::query()->orderBy('id')->first();
        Production::query()->where('id', '!=', $prod->id)->update(['active' => 0]);
        $prod->forceFill(['active' => 1])->save();
        CurrentProduction::forget();

        return $prod;
    }

    public function test_el_foot_no_duplica_el_stack_de_scripts_y_el_hallazgo_renderiza(): void
    {
        $prod = $this->prod();

        $user = $this->makeUser('super-admin');
        try {
            $user->givePermissionTo('dsr.create');
            $user->givePermissionTo('dsr.view');
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
        }

        // DSR FRESCO (created_at = ahora → NO sellado → la vista muestra "+ Hallazgo"). El usuario es el AUTOR.
        $dsr = DailyReport::create([
            'report_date'   => now()->toDateString(),
            'production_id' => $prod->id,
            'created_by_id' => $user->id,
            'status'        => 'open',
        ]);

        $this->actingAs($user);
        $res = $this->get(route('daily_reports.show', $dsr->id));
        $res->assertOk();

        $html = $res->getContent();

        // 🔑 PRUEBA DE LA CAUSA RAÍZ: el fuente del typeahead sale UNA sola vez. Antes del fix, el
        // `@stack('scripts')` del comentario del foot lo emitía por segunda vez dentro del <script> del foot.
        $veces = substr_count($html, 'window.CCTypeahead =');
        $this->assertSame(1, $veces, "El typeahead se emitió {$veces} vez/veces; debe ser 1 (2 = el @stack roto del foot volvió).");

        // El foot script está presente e íntegro (su comentario NO quedó como texto suelto: sigue en el <script>).
        $this->assertStringContainsString('themeBtn', $html, 'Falta el foot script del chrome.');

        // El form de "+ Hallazgo" trae sus 3 campos requeridos.
        $this->assertStringContainsString('name="log_time"', $html);
        $this->assertStringContainsString('name="hazard_event_id"', $html);
        $this->assertStringContainsString('name="description"', $html);
    }

    /**
     * §Asunto 2 · item 8: un action_taken largo da ERROR DE CAMPO (no 500 que pierde lo capturado), y un log
     * válido guarda log_time/description/hazard_event_id. La columna ya es TEXT (migración 000002).
     */
    public function test_action_taken_largo_da_error_de_campo_y_log_valido_guarda_los_campos(): void
    {
        $prod = $this->prod();
        $user = $this->makeUser('super-admin');
        try {
            $user->givePermissionTo('dsr.create');
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
        }
        $dsr = DailyReport::create([
            'report_date'   => now()->toDateString(),
            'production_id' => $prod->id,
            'created_by_id' => $user->id,
            'status'        => 'open',
        ]);
        $eventId = (int) (\App\Models\HazardEvent::query()->value('id') ?? 1);
        $this->actingAs($user);

        // (a) action_taken > 5000 → error de campo, SIN 500, SIN crear log.
        $this->post(route('daily_logs.store', $dsr->id), [
            'log_time'        => '08:30',
            'description'     => 'Personal cerca de la grúa.',
            'hazard_event_id' => $eventId,
            'action_taken'    => str_repeat('x', 6000),
        ])->assertSessionHasErrors('action_taken');
        $this->assertSame(0, \App\Models\DailyLog::where('daily_report_id', $dsr->id)->count(), 'No debe crearse log con action_taken inválido.');

        // (b) Log VÁLIDO (action_taken largo pero dentro de TEXT) → guarda los 3 campos.
        $largo = str_repeat('Se detiene la maniobra y se reubica al personal. ', 40); // ~1960 chars, > varchar(255) viejo
        $this->post(route('daily_logs.store', $dsr->id), [
            'log_time'        => '09:15',
            'description'     => 'Cruce peatonal sin señalizar.',
            'hazard_event_id' => $eventId,
            'action_taken'    => $largo,
        ])->assertSessionHasNoErrors();

        $log = \App\Models\DailyLog::where('daily_report_id', $dsr->id)->latest('id')->first();
        $this->assertNotNull($log, 'El log válido debe crearse.');
        $this->assertSame('09:15', substr((string) $log->log_time, 0, 5));
        $this->assertSame('Cruce peatonal sin señalizar.', $log->description);
        $this->assertSame($eventId, (int) $log->hazard_event_id);
        // TrimStrings (middleware) recorta el espacio de cola; el resto se guarda completo (no truncado a 255).
        $this->assertSame(trim($largo), $log->action_taken, 'El action_taken largo se guardó completo (TEXT, sin truncar).');
        $this->assertGreaterThan(255, strlen((string) $log->action_taken), 'Debe superar el viejo límite varchar(255).');
    }
}
