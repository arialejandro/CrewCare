<?php

namespace Tests\Feature\Crew;

use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\QaTestCase;

/**
 * PERSISTENCIA del vertical CREW: que los endpoints REALES de alta/edicion/asignacion
 * escriban en la BD (assertDatabaseHas), y que las reglas de negocio (rol no se respeta
 * sin users.assign-role; pivote production_user) se cumplan.
 */
class CrewPersistenceTest extends QaTestCase
{
    private function activeDeptId(): int
    {
        return (int) Department::where('active', 1)->orderBy('id')->value('id');
    }

    private function newUserPayload(array $overrides = []): array
    {
        $email = 'nuevo-' . Str::random(10) . '@qa.test';

        return array_merge([
            'name'          => 'Nuevo',
            'lname'         => 'Miembro',
            'lname2'        => 'QA',
            'ncreditos'     => 'Nuevo Miembro',
            'borndate'      => '1990-01-01',
            'sex'           => 'M',
            'phone'         => '5550001111',
            'email'         => $email,
            'password'      => 'secret1234',
            'password_confirmation' => 'secret1234',
            'department_id' => $this->activeDeptId(),
        ], $overrides);
    }

    // ---------- ALTA (/newuser) ----------

    public function test_super_admin_da_de_alta_y_persiste(): void
    {
        $this->actingAsRole('super-admin');
        $payload = $this->newUserPayload();

        $resp = $this->post(route('newuser'), $payload);
        $resp->assertStatus(302); // redirect a /adduser

        $this->assertDatabaseHas('users', [
            'email' => $payload['email'],
            'name'  => 'Nuevo',
            'lname' => 'Miembro',
        ]);

        // Rol base spatie + fila en el pivote production_user.
        $user = User::where('email', $payload['email'])->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('crew'), 'Sin campo role explicito nace crew.');

        $prod = \App\Support\CurrentProduction::get();
        $this->assertDatabaseHas('production_user', [
            'production_id' => $prod->id,
            'user_id'       => $user->id,
            'department_id' => $payload['department_id'],
        ]);
    }

    public function test_alta_rechaza_email_duplicado(): void
    {
        $existing = $this->makeUser('crew');

        $this->actingAsRole('super-admin');
        $payload = $this->newUserPayload(['email' => $existing->email]);

        $this->post(route('newuser'), $payload)->assertSessionHasErrors('email');
        // No se creo un segundo registro con ese correo.
        $this->assertSame(1, User::where('email', $existing->email)->count());
    }

    public function test_alta_rechaza_password_debil_o_sin_confirmar(): void
    {
        $this->actingAsRole('super-admin');

        // Password corto.
        $this->post(route('newuser'), $this->newUserPayload([
            'password' => 'abc', 'password_confirmation' => 'abc',
        ]))->assertSessionHasErrors('password');

        // Confirmacion que no coincide.
        $this->post(route('newuser'), $this->newUserPayload([
            'password' => 'secret1234', 'password_confirmation' => 'otracosa',
        ]))->assertSessionHasErrors('password');
    }

    public function test_super_admin_asigna_rol_en_el_alta(): void
    {
        $this->actingAsRole('super-admin');
        $payload = $this->newUserPayload(['role' => 'coordinator']);

        $this->post(route('newuser'), $payload)->assertStatus(302);

        $user = User::where('email', $payload['email'])->first();
        $this->assertTrue($user->hasRole('coordinator'), 'super-admin (users.assign-role) SI fija el rol.');
    }

    public function test_coordinator_no_puede_escalar_rol_en_el_alta(): void
    {
        // coordinator tiene users.create pero NO users.assign-role: el campo role se IGNORA.
        $this->actingAsRole('coordinator');
        $payload = $this->newUserPayload(['role' => 'line-producer']);

        $this->post(route('newuser'), $payload)->assertStatus(302);

        $user = User::where('email', $payload['email'])->first();
        $this->assertNotNull($user, 'El alta persiste igual.');
        $this->assertTrue($user->hasRole('crew'), 'Sin users.assign-role el rol solicitado se ignora -> crew.');
        $this->assertFalse($user->hasRole('line-producer'), 'NO debe haber escalada de privilegios.');
    }

    public function test_hod_sin_departamento_recibe_error_amable_no_500(): void
    {
        // hod (sin all-departments) al que nadie asigno departamento: el alta se BLOQUEA
        // con mensaje, sin 500 y sin crear usuario.
        $this->actingAsRole('hod');
        $payload = $this->newUserPayload();

        $resp = $this->post(route('newuser'), $payload);
        $resp->assertStatus(302);
        $resp->assertSessionHas('error');
        $this->assertDatabaseMissing('users', ['email' => $payload['email']]);
    }

    // ---------- EDICION (/acountupdate/{id}) ----------

    public function test_super_admin_edita_y_persiste(): void
    {
        $target = $this->makeUser('crew', ['name' => 'Antes', 'phone' => '5550000000']);

        $this->actingAsRole('super-admin');
        $this->post(route('account.update', ['id' => $target->id]), [
            'name'  => 'Despues',
            'lname' => 'Editado',
            'email' => $target->email, // mismo email (unique ignore self)
            'phone' => '5559998888',
        ])->assertStatus(302);

        $this->assertDatabaseHas('users', [
            'id'    => $target->id,
            'name'  => 'Despues',
            'phone' => '5559998888',
        ]);
    }

    public function test_edicion_a_email_ya_existente_da_error_no_500(): void
    {
        $otro   = $this->makeUser('crew');
        $target = $this->makeUser('crew');

        $this->actingAsRole('super-admin');
        $this->post(route('account.update', ['id' => $target->id]), [
            'name'  => $target->name,
            'email' => $otro->email, // choca con otro registro
        ])->assertSessionHasErrors('email');

        // El registro objetivo NO cambio su email.
        $this->assertDatabaseHas('users', ['id' => $target->id, 'email' => $target->email]);
    }

    // ---------- ASIGNACION DE ROL (/rolescrud/{id}) ----------

    public function test_asignacion_de_rol_persiste_en_spatie_y_pivote(): void
    {
        $target = $this->makeUser('crew');
        $deptId = $this->activeDeptId();

        $this->actingAsRole('line-producer'); // tiene users.assign-role + all-departments
        // department_id SIEMPRE se manda desde el form real (el <select> existe); aqui lo mandamos
        // igual para reflejar el flujo real y de paso verificar el pivote.
        $this->post(route('roles.update', ['id' => $target->id]), [
            'role'          => 'coordinator',
            'department_id' => $deptId,
        ])->assertStatus(302);

        $target->refresh();
        $this->assertTrue($target->hasRole('coordinator'));
        $this->assertFalse($target->hasRole('crew'), 'syncRoles deja EXACTAMENTE el nuevo rol.');

        $prod = \App\Support\CurrentProduction::get();
        $pivot = DB::table('production_user')
            ->where('production_id', $prod->id)
            ->where('user_id', $target->id)
            ->first();
        $this->assertNotNull($pivot);
        $this->assertSame('coordinator', $pivot->role);
        $this->assertSame($deptId, (int) $pivot->department_id);
    }

    // =====================================================================================
    // REGRESIONES DE BUGS REALES (ver reporte). Estos tests fijan el comportamiento ACTUAL
    // (defectuoso) para que el defecto sea VISIBLE en la suite. Cuando se corrija el codigo,
    // ESTE test fallara: en ese momento cambia el assert al comportamiento esperado indicado.
    // =====================================================================================

    /**
     * `labn` ("Jerarquía") se RETIRÓ del alta y la edición (2026-08-27, owner): ya no se captura,
     * ni se valida, ni se escribe. La jerarquía se DERIVA del puesto/rol, no se teclea. La columna
     * users.labn queda DURMIENTE con sus valores viejos. Antes un labn no numérico daba error de
     * validación (BUG-02); ahora el campo no existe → el alta debe proceder sin él, y un valor
     * basura enviado por un cliente viejo se IGNORA (no bloquea, no se escribe, sin 500).
     */
    public function test_labn_retirado_del_alta_no_es_requerido_y_se_ignora(): void
    {
        $this->actingAsRole('super-admin');

        // El form real ya no manda labn → el alta procede sin él.
        $sinLabn = $this->newUserPayload();
        $this->post(route('newuser'), $sinLabn)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => $sinLabn['email']]);

        // Un cliente viejo que aún mande labn (incluso no numérico) no rompe: se ignora.
        $conBasura = $this->newUserPayload(['labn' => 'ABC']);
        $this->post(route('newuser'), $conBasura)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => $conBasura['email']]);
    }

    /**
     * BUG #2 — RoleAssignmentController::update lee `$data['department_id']` directo (linea 155),
     * pero el campo se valida `nullable`: si el POST OMITE department_id, no aparece en el arreglo
     * validado -> "Undefined index: department_id" -> ErrorException -> HTTP 500. El form real no
     * lo dispara (el <select> siempre se envia, '' -> null via ConvertEmptyStringsToNull), pero
     * cualquier cliente que omita el campo revienta.
     * ESPERADO tras el fix (p.ej. `$data['department_id'] ?? null`): 302 y rol asignado.
     */
    public function test_roles_update_sin_department_id_no_debe_dar_500(): void
    {
        // BUG-03 CORREGIDO (`$data['department_id'] ?? null`): omitir department_id ya no revienta.
        // Guarda ACTIVA contra regresión.
        $target = $this->makeUser('crew');

        $this->actingAsRole('line-producer');
        $this->post(route('roles.update', ['id' => $target->id]), [
            'role' => 'coordinator', // SIN department_id a proposito
        ])->assertStatus(302);
    }
}
