<?php

namespace Tests\Feature\Crew;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\QaTestCase;

/**
 * GUARDAS FINAS del vertical CREW/RBAC que un middleware de ruta no puede expresar:
 *   - No cambiar tu PROPIO rol (anti auto-escalada/lockout).
 *   - Scope por departamento (canManageCrewMember): 403 fuera de alcance.
 *   - Doble puerta: otorgar/revocar acceso clinico exige SUPER-ADMIN (no basta users.assign-role).
 *   - Import: valida el archivo (rechaza no-xlsx/csv) y sigue tras el flag legacy `admin`.
 */
class CrewGuardsTest extends QaTestCase
{
    private function prodId(): int
    {
        return (int) \App\Support\CurrentProduction::get()->id;
    }

    /** Inserta la fila de pivote que fija el departamento de $user (fuente de ownDepartmentIds). */
    private function placeInDept(User $user, int $deptId): void
    {
        DB::table('production_user')->updateOrInsert(
            ['production_id' => $this->prodId(), 'user_id' => $user->id],
            ['department_id' => $deptId, 'role' => 'crew', 'is_lead' => false,
             'created_at' => now(), 'updated_at' => now()]
        );
    }

    /** @return int[] dos ids de departamento activos distintos */
    private function twoDepartments(): array
    {
        $ids = \App\Models\Department::where('active', 1)->orderBy('id')->pluck('id')->take(2)->values()->all();
        $this->assertCount(2, $ids, 'La fabrica debe sembrar >= 2 departamentos.');
        return $ids;
    }

    // ---------- No cambiar tu propio rol ----------

    public function test_no_puedes_cambiar_tu_propio_rol(): void
    {
        $me = $this->actingAsRole('line-producer'); // tiene users.assign-role + all-departments

        $resp = $this->post(route('roles.update', ['id' => $me->id]), ['role' => 'coordinator']);
        $resp->assertStatus(302);
        $resp->assertSessionHas('error');

        $me->refresh();
        $this->assertTrue($me->hasRole('line-producer'), 'El rol propio NO debe cambiar.');
        $this->assertFalse($me->hasRole('coordinator'));
    }

    // ---------- Scope por departamento (canManageCrewMember) ----------

    public function test_canManageCrewMember_reglas_base(): void
    {
        [$deptA, $deptB] = $this->twoDepartments();

        $superAdmin = $this->makeUser('super-admin');
        $viewer     = $this->makeUser('crew');
        $inA        = $this->makeUser('crew');
        $inB        = $this->makeUser('crew');

        $this->placeInDept($viewer, $deptA);
        $this->placeInDept($inA, $deptA);
        $this->placeInDept($inB, $deptB);

        // super-admin (all-departments via Gate::before) gestiona a cualquiera.
        $this->assertTrue($superAdmin->canManageCrewMember($inB));
        // viewer acotado a A: gestiona a los de A, NO a los de B.
        $this->assertTrue($viewer->canManageCrewMember($inA));
        $this->assertFalse($viewer->canManageCrewMember($inB));
    }

    public function test_acountupdate_403_fuera_de_scope(): void
    {
        [$deptA, $deptB] = $this->twoDepartments();

        // viewer: puede editar usuarios (permiso DIRECTO) pero SIN all-departments, acotado a A.
        $viewer = $this->makeUser('crew');
        $viewer->givePermissionTo('users.update');
        $this->placeInDept($viewer, $deptA);

        $targetOut = $this->makeUser('crew'); // en B -> fuera de scope
        $this->placeInDept($targetOut, $deptB);

        $targetIn = $this->makeUser('crew');  // en A -> dentro de scope
        $this->placeInDept($targetIn, $deptA);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($viewer->fresh());

        // Fuera de scope -> 403 (aunque pase el permiso de ruta users.update).
        $this->post(route('account.update', ['id' => $targetOut->id]), [
            'name' => 'X', 'email' => $targetOut->email,
        ])->assertForbidden();

        // Dentro de scope -> NO 403 (persiste, redirige 302).
        $this->post(route('account.update', ['id' => $targetIn->id]), [
            'name' => 'Dentro', 'email' => $targetIn->email,
        ])->assertStatus(302);
    }

    // ---------- Doble puerta: acceso clinico exige super-admin ----------

    public function test_medical_grant_exige_super_admin_no_basta_assign_role(): void
    {
        $target = $this->makeUser('crew');

        // line-producer tiene users.assign-role (pasa la ruta) pero NO es super-admin -> 403.
        $this->actingAsRole('line-producer');
        $this->post(route('roles.medical.grant', ['id' => $target->id]))->assertForbidden();
        $this->assertFalse($target->fresh()->hasDirectPermission('medical.view'));
    }

    public function test_super_admin_otorga_acceso_clinico_directo(): void
    {
        $target = $this->makeUser('crew');

        $this->actingAsRole('super-admin');
        $this->post(route('roles.medical.grant', ['id' => $target->id]))->assertStatus(302);

        $this->assertTrue($target->fresh()->hasDirectPermission('medical.view'),
            'El super-admin otorga medical.view como permiso DIRECTO.');
    }

    // ---------- Import: validacion de archivo + flag legacy admin ----------

    public function test_import_rechaza_archivo_no_xlsx(): void
    {
        $this->actingAsRole('super-admin'); // admin=1, pasa AdminMiddleware

        $bad = UploadedFile::fake()->create('crew.txt', 10, 'text/plain');
        $this->post(route('crewstore'), ['import_file' => $bad])
            ->assertSessionHasErrors('import_file');
    }

    public function test_import_exige_archivo(): void
    {
        $this->actingAsRole('super-admin');
        $this->post(route('crewstore'), [])->assertSessionHasErrors('import_file');
    }

    public function test_import_post_no_admin_redirige(): void
    {
        // line-producer (admin=0) NO pasa el AdminMiddleware -> redirect('/'), nunca llega a validar.
        $this->actingAsRole('line-producer');
        $this->post(route('crewstore'), [])->assertRedirect('/');
    }
}
