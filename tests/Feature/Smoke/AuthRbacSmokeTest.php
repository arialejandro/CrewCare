<?php

namespace Tests\Feature\Smoke;

use Spatie\Permission\Models\Permission;
use Tests\QaTestCase;

/**
 * Smoke de auth + RBAC sobre el install de FABRICA. Prueba que el arnes funciona
 * end-to-end: la BD de prueba se levanta sembrada, los roles se aplican, y las
 * puertas de permiso responden como deben (login / 200 / 403).
 */
class AuthRbacSmokeTest extends QaTestCase
{
    public function test_el_seed_de_fabrica_levanto_los_catalogos(): void
    {
        $this->assertDatabaseCount('hazard_events', 207);
        $this->assertGreaterThanOrEqual(64, Permission::count());
    }

    public function test_invitado_es_redirigido_a_login(): void
    {
        $this->get(route('usuarioscrud'))->assertRedirect(route('login'));
    }

    public function test_super_admin_ve_la_home_del_panel(): void
    {
        $this->actingAsRole('super-admin');
        $this->get(route('home'))->assertOk();
    }

    public function test_super_admin_ve_el_listado_de_crew(): void
    {
        $this->actingAsRole('super-admin');
        $this->get(route('usuarioscrud'))->assertOk();
    }

    public function test_crew_no_puede_ver_el_listado_de_crew(): void
    {
        $this->actingAsRole('crew');
        $this->get(route('usuarioscrud'))->assertForbidden();
    }

    public function test_crew_no_puede_ver_vigilancia_epi(): void
    {
        $this->actingAsRole('crew');
        $this->get(route('epi.index'))->assertForbidden();
    }
}
