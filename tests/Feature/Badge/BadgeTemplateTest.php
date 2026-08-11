<?php

namespace Tests\Feature\Badge;

use App\Models\BadgeTemplate;
use Tests\QaTestCase;

/**
 * QA — VERTICAL GAFETES (ID-Badge configurable).
 *
 * El diseñador de plantilla lo gobierna el permiso `badge.design` (revocable), no el rol: por
 * fábrica lo tienen super-admin, line-producer y coordinador. La plantilla es UNA sola fila activa
 * (active=1); guardar hace upsert de esa fila y activeConfig() siempre devuelve DEFAULTS fusionado
 * con lo guardado.
 *
 * Cubre:
 *  1. RBAC del diseñador (designer/save/template): granted → entra; el resto → 403; invitado → login.
 *  2. Persistencia: guardar una plantilla válida crea/actualiza la fila activa y activeConfig la refleja.
 *  3. Validación: un payload inválido rebota con errores y NO persiste.
 *  4. Invariante de UNA fila activa: guardar dos veces no crea dos plantillas activas.
 *
 * badge.design por rol (RolesAndPermissionsSeeder): super-admin, line-producer, coordinator.
 */
class BadgeTemplateTest extends QaTestCase
{
    /** Roles con badge.design. */
    private function grantedRoles(): array
    {
        return ['super-admin', 'line-producer', 'coordinator'];
    }

    /** Roles SIN badge.design. */
    private function deniedRoles(): array
    {
        return ['hod', 'medic', 'safety-officer', 'crew', 'auditor'];
    }

    /**
     * Payload COMPLETO y válido para saveTemplate (todos los `required` presentes). Parte de los
     * DEFAULTS y permite overrides con valores distintivos para verificar la persistencia.
     */
    private function templatePayload(array $overrides = []): array
    {
        return array_merge([
            'project_name'          => '',
            'font_family'           => 'Poppins',
            'text_color'            => '#111111',
            'photo_shape'           => 'circle',
            'photo_size'            => 50,
            'photo_top'             => 37,
            'photo_left'            => 29,
            'production_logo_top'   => 6,
            'production_logo_left'  => 39,
            'production_logo_width' => 30,
            'brand_top'             => 15,
            'brand_left'            => 40,
            'brand_size'            => 14,
            'brand_weight'          => 300,
            'label_name'            => 'NOMBRE',
            'label_name_top'        => 135,
            'name_top'              => 138,
            'name_size'             => 24,
            'label_position'        => 'PUESTO',
            'label_position_top'    => 150,
            'position_top'          => 153,
            'position_size'         => 12,
            'powered_tone'          => 'gris',
            'powered_top'           => 158,
            'consecutive_top'       => 165,
        ], $overrides);
    }

    // =====================================================================================
    //  1. RBAC DEL DISEÑADOR
    // =====================================================================================

    /** @dataProvider grantedProvider */
    public function test_roles_con_badge_design_abren_el_disenador(string $role): void
    {
        $this->actingAsRole($role);
        $this->get(route('badge.designer'))->assertOk();
    }

    public static function grantedProvider(): array
    {
        return [['super-admin'], ['line-producer'], ['coordinator']];
    }

    /** @dataProvider deniedProvider */
    public function test_roles_sin_badge_design_reciben_403_en_el_disenador(string $role): void
    {
        $this->actingAsRole($role);
        $this->get(route('badge.designer'))->assertForbidden();
    }

    /** @dataProvider deniedProvider */
    public function test_roles_sin_badge_design_reciben_403_al_guardar(string $role): void
    {
        $this->actingAsRole($role);
        $this->post(route('badge.save'), $this->templatePayload())->assertForbidden();
        $this->assertSame(0, BadgeTemplate::count(), 'Un rol sin badge.design no debe persistir la plantilla.');
    }

    /** @dataProvider deniedProvider */
    public function test_roles_sin_badge_design_reciben_403_en_la_plantilla_pdf(string $role): void
    {
        // La descarga de la plantilla comparte el gate badge.design (403 antes de renderizar nada).
        $this->actingAsRole($role);
        $this->get(route('badge.template'))->assertForbidden();
    }

    public static function deniedProvider(): array
    {
        return [['hod'], ['medic'], ['safety-officer'], ['crew'], ['auditor']];
    }

    public function test_invitado_es_redirigido_a_login_en_el_disenador(): void
    {
        $this->get(route('badge.designer'))->assertRedirect(route('login'));
    }

    // =====================================================================================
    //  2. PERSISTENCIA + activeConfig
    // =====================================================================================

    public function test_guardar_una_plantilla_valida_persiste_la_fila_activa(): void
    {
        $this->actingAsRole('super-admin');

        $resp = $this->post(route('badge.save'), $this->templatePayload([
            'project_name' => 'GAFETE QA DISTINTIVO',
            'text_color'   => '#abcdef',
            'photo_shape'  => 'square',
            'name_size'    => 27,
        ]));

        $resp->assertRedirect(route('badge.designer'));
        $resp->assertSessionHas('success');

        $this->assertDatabaseHas('badge_templates', ['active' => 1]);

        // activeConfig fusiona DEFAULTS + lo guardado → mapa completo con los valores distintivos.
        $config = BadgeTemplate::activeConfig();
        $this->assertSame('GAFETE QA DISTINTIVO', $config['project_name']);
        $this->assertSame('#abcdef', $config['text_color']);
        $this->assertSame('square', $config['photo_shape']);
        $this->assertEquals(27, $config['name_size']);
        // Una llave de DEFAULTS que no vino en el POST sigue presente (mapa a prueba de plantilla parcial).
        $this->assertArrayHasKey('consecutive_top', $config);
        $this->assertArrayHasKey('card_bg', $config);
    }

    public function test_guardar_dos_veces_mantiene_una_sola_plantilla_activa(): void
    {
        $this->actingAsRole('super-admin');

        $this->post(route('badge.save'), $this->templatePayload(['project_name' => 'Primera']))
            ->assertSessionHas('success');
        $this->post(route('badge.save'), $this->templatePayload(['project_name' => 'Segunda']))
            ->assertSessionHas('success');

        // UNA sola fila activa (upsert de la fila active=1), y refleja el último guardado.
        $this->assertSame(1, BadgeTemplate::where('active', 1)->count(),
            'Debe existir exactamente una plantilla activa.');
        $this->assertSame('Segunda', BadgeTemplate::activeConfig()['project_name']);
    }

    // =====================================================================================
    //  3. VALIDACIÓN
    // =====================================================================================

    public function test_un_payload_invalido_rebota_y_no_persiste(): void
    {
        $this->actingAsRole('super-admin');

        // text_color fuera del patrón #hex6 + font_family fuera de la allow-list.
        $resp = $this->from(route('badge.designer'))->post(route('badge.save'), $this->templatePayload([
            'text_color'  => 'rojo',
            'font_family' => 'ComicSans',
        ]));

        $resp->assertSessionHasErrors(['text_color', 'font_family']);
        $this->assertSame(0, BadgeTemplate::count(), 'Un payload inválido NO debe crear plantilla.');
    }

    public function test_falta_un_campo_requerido_rebota(): void
    {
        $this->actingAsRole('super-admin');

        $payload = $this->templatePayload();
        unset($payload['photo_shape']); // required|in:PHOTO_SHAPES

        $this->from(route('badge.designer'))->post(route('badge.save'), $payload)
            ->assertSessionHasErrors('photo_shape');
        $this->assertSame(0, BadgeTemplate::count());
    }
}
