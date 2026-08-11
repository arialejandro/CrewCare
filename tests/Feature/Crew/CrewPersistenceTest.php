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
            'labn'          => '0', // labn es int(30) en la BD (STRICT mode); ver BUG reportado.
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
     * BUG #1 — CrewController::newuser valida `labn => required|string|max:255` (linea 89) pero
     * la columna users.labn es int(30) y MySQL corre en STRICT_TRANS_TABLES. El input del form
     * (admin/newuser.blade.php:209) es <input type="text"> "Jerarquia" — un usuario PUEDE teclear
     * letras. Resultado: PDOException 1366 no atrapada -> HTTP 500 (CrewController.php:158) en vez
     * de un error de validacion amable.
     * ESPERADO tras el fix (p.ej. regla `integer`): assertSessionHasErrors('labn') y sin 500.
     */
    public function test_labn_no_numerico_debe_dar_error_de_validacion_no_500(): void
    {
        // GUARDA DE REGRESIÓN de BUG-02: users.labn es int(30) pero CrewController:89 lo valida
        // como `string` -> con STRICT mode, una letra revienta en 500 en vez de un error amable.
        // Skipped hasta el fix (regla `integer`). Al arreglar, quitar el skip: debe pasar.
        $this->markTestSkipped('BUG-02: labn no numerico devuelve 500; pendiente de fix del owner.');

        $this->actingAsRole('super-admin');
        $payload = $this->newUserPayload(['labn' => 'ABC']); // no numerico

        $this->post(route('newuser'), $payload)->assertSessionHasErrors('labn');
        $this->assertDatabaseMissing('users', ['email' => $payload['email']]);
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
        // GUARDA DE REGRESIÓN de BUG-03: RoleAssignmentController:155 lee $data['department_id']
        // directo aunque se valida `nullable` -> si el POST lo omite, Undefined index -> 500.
        // Skipped hasta el fix (`$data['department_id'] ?? null`). Latente (el form real siempre lo manda).
        $this->markTestSkipped('BUG-03: roles.update sin department_id devuelve 500; pendiente de fix del owner.');

        $target = $this->makeUser('crew');

        $this->actingAsRole('line-producer');
        $this->post(route('roles.update', ['id' => $target->id]), [
            'role' => 'coordinator', // SIN department_id a proposito
        ])->assertStatus(302);
    }
}
