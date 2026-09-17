<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\DB;
use Tests\QaTestCase;

/**
 * Ver y cerrar sesiones desde el perfil (sesión larga pero REVOCABLE). Con el driver `file` la
 * página explica que hay que activar la sesión en base; con `database` lista y revoca.
 */
class SessionManagementTest extends QaTestCase
{
    public function test_pagina_carga_y_explica_si_no_hay_driver_database(): void
    {
        $this->actingAsRole('safety-officer');
        // En testing el driver no es `database` → la página lo explica, no rompe.
        $this->get('/profile/sesiones')->assertOk()->assertSee('sesión en base', false);
    }

    public function test_lista_sesiones_con_driver_database(): void
    {
        config(['session.driver' => 'database']);
        $u = $this->actingAsRole('safety-officer');
        DB::table('sessions')->insert([
            'id' => 'sess-x', 'user_id' => $u->id, 'ip_address' => '10.0.0.9',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120', 'payload' => 'x', 'last_activity' => now()->timestamp,
        ]);

        $this->get('/profile/sesiones')->assertOk()
            ->assertSee('Chrome · Windows')
            ->assertSee('10.0.0.9');
    }

    public function test_cerrar_otras_revoca_las_ajenas(): void
    {
        config(['session.driver' => 'database']);
        $u = $this->actingAsRole('safety-officer');
        DB::table('sessions')->insert([
            ['id' => 'otra-1', 'user_id' => $u->id, 'ip_address' => '1.1.1.1', 'user_agent' => 'x', 'payload' => 'p', 'last_activity' => now()->timestamp],
            ['id' => 'otra-2', 'user_id' => $u->id, 'ip_address' => '2.2.2.2', 'user_agent' => 'x', 'payload' => 'p', 'last_activity' => now()->timestamp],
        ]);
        $this->assertSame(2, DB::table('sessions')->where('user_id', $u->id)->count());

        // Ninguna coincide con la sesión actual del test → se revocan todas (cierra las ajenas).
        $this->post('/profile/sesiones/cerrar-otras')->assertRedirect();
        $this->assertSame(0, DB::table('sessions')->where('user_id', $u->id)->where('id', '!=', session()->getId())->count());
    }
}
