<?php

namespace Database\Seeders;

use App\Models\Position;
use App\Models\Production;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Backfill department_id / position_id on the production_user pivot.
 *
 * Context (PROGRESS.md "SIGUIENTE PASO INMEDIATO (a)"): the RBAC foundation attached all
 * 88 users to "Producción Demo" but left department_id/position_id NULL because the legacy
 * users.puestodepartamento column holds PLAIN TITLE strings (e.g. "Line Producer", "APOC")
 * with NO "Depto-Puesto" separator, so MapExistingUsersSeeder could not derive the org.
 *
 * This seeder builds a normalized title -> position lookup against the ALREADY-SEEDED global
 * catalog (35 departments / 192 positions). It NEVER invents positions: it only resolves a
 * title to a position that already exists in the catalog. The department is taken from the
 * matched position (positions.department_id). Where no confident match exists, the pivot is
 * left NULL (reported via counters) — links are never fabricated.
 *
 * Constraints honored:
 *   - Reads users read-only (puestodepartamento untouched). Writes ONLY production_user.
 *   - Idempotent / re-runnable (UPDATE of the two FKs on the existing pivot row).
 *   - Does NOT run global migrate; does NOT touch admin/daytest/age/puestodepartamento.
 *
 * Normalization for matching: trim, collapse whitespace, lowercase, strip accents, drop
 * trailing numeric suffixes ("Carpintero 3" -> "carpintero"; "Swing Gang 2" -> "swing gang").
 * The alias map below maps each normalized legacy title to a CANONICAL position name that is
 * known to exist in the seeded catalog. Only confident, human-verified aliases are included.
 */
class BackfillUserPositionsSeeder extends Seeder
{
    /**
     * Normalized legacy title  =>  canonical position name in the seeded catalog.
     *
     * Keys are already normalized (lowercase, no accents, single-spaced, numeric suffix
     * stripped). Values match positions.name EXACTLY (resolved case/accent-insensitively at
     * runtime as a safety net). Titles intentionally absent here stay NULL (see report).
     *
     * DISAMBIGUATION: a position NAME can be duplicated across departments in the global catalog
     * (e.g. "Bodeguero" exists under both Construcción and Decoración). When that happens, write
     * the value as "Position Name @ Department Name" so the matcher resolves the correct row.
     * Plain "Position Name" values are still accepted when the name is unique.
     */
    private array $aliases = [
        // --- Dirección / AD ---
        '1er ad'                              => '1er AD',
        '1er asistente de direccion'          => '1er AD',
        '2da asistente de direccion'          => '2nd AD',

        // --- Producción / Oficina de Producción ---
        'line producer'                       => 'Productor en Línea',
        'gerente de produccion'               => 'Gerente de Producción',
        'gerente de unidad'                   => 'Gerente de Unidad',
        'cordinadora de produccion'           => 'Coordinador de Producción',
        'apoc'                                => 'APOC',
        'asistente de prod. oficina'          => 'Asst. de Oficina de Producción',
        'cordinadora viajes'                  => 'Coordinador de Viajes',

        // --- Cámara ---
        'director de fotografia'              => 'Director de Fotografía',

        // --- Sonido ---
        // "Sonido" alone = department name, ambiguous -> intentionally NOT mapped.

        // --- Maquillaje y Peinados ---
        'coordinador maquillaje'              => 'Coordinador MU&H',

        // --- Vestuario ---
        'asistente de vestuario'              => 'Asistente de Vestuario',
        'coordinador vestuario'               => 'Coordinador de Vestuario',
        'compradora vestuario'                => 'Comprador de Vestuario',
        'disenadora de vestuario'             => 'Diseñador de Vestuario',
        // Backfill 2026-06-24 (A): tailor (new catalog row "Sastre" under Vestuario).
        'sastre vestuario'                    => 'Sastre',

        // --- Arte ---
        'cordinadora de arte'                 => 'Coordinador de Arte',
        'director de arte'                    => 'Director de Arte',
        'asistente de dir. arte'              => 'Asst. de Dirección de Arte',
        'artista conceptual'                  => 'Artista Conceptual',
        'asistente diseno grafico'            => 'Asst. de Diseñador Gráfico',
        'disenadora de set'                   => 'Diseñador de Sets',

        // --- Decoración ---
        'decorador'                           => 'Decorador',
        'decoradora en jefe'                  => 'Decorador',
        'decoradora de set'                   => 'Decorador en Set',
        'asistente de decoracion'             => 'Asistente de Decoración',
        'coordinadora de deco'                => 'Coordinador de Decoración',
        'swing gang'                          => 'Swing Gang',
        // Backfill 2026-06-24 (A): warehouse keeper for Decoración (new catalog row "Bodeguero"
        // under Decoración). Resolves at runtime via department context of that position.
        'bodeguero decoracion'                => 'Bodeguero @ Decoración',

        // --- Construcción ---
        'carpintero'                          => 'Carpintero',
        'hod carpintero'                      => 'Carpintero',
        'cordinadora de construccion'         => 'Coordinador de Construcción',
        'comprador costruccion'               => 'Compras de Construcción',
        'bodeguero construccion'              => 'Bodeguero @ Construcción',
        'pintor escenico'                     => 'Pintor',

        // --- Utilería / Props ---
        'prop master'                         => 'Jefe de Utilería',
        'asistente de utileria'               => 'Asistente de Utilería',
        'bodeguero utileria'                  => 'Bodeguero Props',
        'compras props'                       => 'Compras de Utilería en Set',
        // Backfill 2026-06-24 (A): coordinator for Props (new catalog row under Utilería).
        'coordinacion utileria'               => 'Coordinador de Utilería',

        // --- Locaciones ---
        'cordinadora locaciones'              => 'Coordinador de Locaciones',
        // Backfill 2026-06-24 (A): location scout (new catalog row "Scouter" under Locaciones).
        'scouter'                             => 'Scouter',

        // --- Transportación ---
        'cordinador de trasnpo'               => 'Coordinador de Transporte',
        'coordinador transpo oficina'         => 'Coordinador de Transporte',
        'driver deco'                         => 'Chofer',
        'driver director'                     => 'Chofer',
        'driver fotografo'                    => 'Chofer',
        'driver produccion'                   => 'Chofer',
        'driver salud y seguridad'            => 'Chofer',

        // --- Contabilidad ---
        'contador de produccion'              => 'Contador de Producción',
        'asistente contable'                  => 'Asst. Contable',
        'asistente de contabilidad'           => 'Asistente de Contabilidad',
        'segundo asistente de contabilidad'   => 'Asst. Contable',

        // --- Salud y Seguridad ---
        'supervisor salud y seguridad'        => 'Supervisor de Salud y Seguridad',
        'gerente salud y seguridad'           => 'Supervisor de Salud y Seguridad',
        'doctor en set'                       => 'Doctor en Set',
        'doctor construccion'                 => 'Doctor de Construcción',
    ];

    public function run()
    {
        $production = Production::where('name', 'Producción Demo')->firstOrFail();

        // Build two lookups over the GLOBAL catalog (production_id IS NULL):
        //   $catalog      : normalized position name        -> position row (name must be unique)
        //   $catalogByDept: "normName|normDeptName"          -> position row (disambiguates dupes)
        // Department names come from departments.name via the position's department_id.
        $deptNameById = DB::table('departments')->pluck('name', 'id')->all();
        $catalog = [];
        $catalogByDept = [];
        foreach (Position::query()->whereNull('production_id')->get() as $pos) {
            $catalog[$this->normalize($pos->name)] = $pos;
            $deptName = $deptNameById[$pos->department_id] ?? '';
            $catalogByDept[$this->normalize($pos->name).'|'.$this->normalize($deptName)] = $pos;
        }

        $matched = 0;
        $unmatched = 0;
        $unmatchedTitles = [];

        User::query()->orderBy('id')->each(function (User $user) use (
            $production, $catalog, $catalogByDept, &$matched, &$unmatched, &$unmatchedTitles
        ) {
            $raw = trim((string) $user->puestodepartamento);
            $norm = $this->normalize($raw);

            $positionId = null;
            $departmentId = null;

            if ($norm !== '') {
                // 1) Try the explicit alias map (after numeric-suffix stripping).
                $canonical = $this->aliases[$norm] ?? null;

                // 2) Fall back to a direct catalog hit (legacy title already == a catalog name).
                if ($canonical !== null) {
                    // An alias value may be "Name @ Department" to disambiguate a name that exists
                    // under more than one department; otherwise it's a plain (unique) position name.
                    if (strpos($canonical, '@') !== false) {
                        [$cName, $cDept] = array_map('trim', explode('@', $canonical, 2));
                        $pos = $catalogByDept[$this->normalize($cName).'|'.$this->normalize($cDept)] ?? null;
                    } else {
                        $pos = $catalog[$this->normalize($canonical)] ?? null;
                    }
                } else {
                    $pos = $catalog[$norm] ?? null;
                }

                if ($pos !== null) {
                    $positionId = $pos->id;
                    $departmentId = $pos->department_id;
                }
            }

            if ($positionId !== null) {
                $matched++;
            } else {
                $unmatched++;
                if ($raw !== '') {
                    $unmatchedTitles[$raw] = ($unmatchedTitles[$raw] ?? 0) + 1;
                }
            }

            // Write ONLY the two FKs onto the existing pivot row (idempotent).
            DB::table('production_user')
                ->where('production_id', $production->id)
                ->where('user_id', $user->id)
                ->update([
                    'department_id' => $departmentId,
                    'position_id'   => $positionId,
                    'updated_at'    => now(),
                ]);
        });

        $this->command->info("production_user backfill -> matched: {$matched} | unmatched (NULL): {$unmatched}");
        if (!empty($unmatchedTitles)) {
            ksort($unmatchedTitles);
            $this->command->warn('Unmatched titles (left NULL):');
            foreach ($unmatchedTitles as $title => $cnt) {
                $this->command->warn(sprintf('  %dx  %s', $cnt, $title));
            }
        }
    }

    /**
     * Normalize a title for matching: trim, collapse whitespace, lowercase, strip accents,
     * and drop a trailing numeric suffix ("Carpintero 3" -> "carpintero").
     */
    private function normalize(string $s): string
    {
        $s = trim($s);
        // Strip accents/diacritics (transliterate to ASCII).
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
        if ($converted !== false) {
            $s = $converted;
        }
        // iconv//TRANSLIT can emit things like "n'~" — strip non-letter combining leftovers.
        $s = preg_replace("/[\x80-\xff'^~`\"]/", '', $s);
        $s = strtolower($s);
        $s = preg_replace('/\s+/', ' ', $s);
        $s = trim($s);
        // Drop a trailing standalone number ("carpintero 3", "swing gang 2").
        $s = preg_replace('/\s+\d+$/', '', $s);
        return trim($s);
    }
}
