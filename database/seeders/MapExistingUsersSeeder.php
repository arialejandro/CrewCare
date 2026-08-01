<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Position;
use App\Models\Production;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Maps the ~88 existing users to spatie roles and attaches them to "Producción Demo".
 *
 * Role mapping (AUTH-RBAC-PLAN.md C.1, simplified per the build spec):
 *   admin = 1  -> super-admin
 *   everyone else -> crew (default)
 *
 * Does NOT modify any existing user column (daytest/admin/age/puestodepartamento stay
 * untouched — strangler/additive). Reads users read-only and calls assignRole() + the
 * production_user pivot. Idempotent (syncRoles + updateOrInsert).
 *
 * Org derivation: when puestodepartamento = "Depto-Puesto" can be parsed, we firstOrCreate
 * the department/position in the GLOBAL catalog and record them on the pivot. Malformed /
 * empty strings -> null department/position (logged via counters).
 */
class MapExistingUsersSeeder extends Seeder
{
    public function run()
    {
        $production = Production::where('name', 'Producción Demo')->firstOrFail();

        $superAdmins = 0;
        $crew = 0;
        $pivotRows = 0;
        $orgMatched = 0;
        $orgUnmatched = 0;

        User::query()->orderBy('id')->each(function (User $user) use (
            $production, &$superAdmins, &$crew, &$pivotRows, &$orgMatched, &$orgUnmatched
        ) {
            // ---- 1. Global spatie role ----
            $role = ((int) $user->admin === 1) ? 'super-admin' : 'crew';
            $user->syncRoles([$role]);
            $role === 'super-admin' ? $superAdmins++ : $crew++;

            // ---- 2. Derive org from the desnormalized string (read-only) ----
            $departmentId = null;
            $positionId = null;
            $raw = trim((string) $user->puestodepartamento);

            if ($raw !== '' && strpos($raw, '-') !== false) {
                [$deptName, $posName] = array_map('trim', explode('-', $raw, 2));
                if ($deptName !== '') {
                    $dept = Department::firstOrCreate(
                        ['name' => $deptName],
                        ['active' => true]
                    );
                    $departmentId = $dept->id;

                    if ($posName !== '') {
                        $pos = Position::firstOrCreate(
                            ['name' => $posName, 'department_id' => $dept->id, 'production_id' => null],
                            ['is_hod' => false, 'active' => true]
                        );
                        $positionId = $pos->id;
                    }
                    $orgMatched++;
                } else {
                    $orgUnmatched++;
                }
            } else {
                $orgUnmatched++;
            }

            // ---- 3. Attach to Producción Demo via production_user pivot ----
            DB::table('production_user')->updateOrInsert(
                ['production_id' => $production->id, 'user_id' => $user->id],
                [
                    'department_id' => $departmentId,
                    'position_id' => $positionId,
                    'role' => $role,
                    'is_lead' => false,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
            $pivotRows++;
        });

        $this->command->info("Users mapped -> super-admin: {$superAdmins} | crew: {$crew}");
        $this->command->info("production_user rows: {$pivotRows} | org matched: {$orgMatched} | unmatched: {$orgUnmatched}");
    }
}
