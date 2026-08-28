<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * CATÁLOGO ORGANIZACIONAL — siembra la FUSIÓN (semilla declarada + catálogo vivo). Delta #114 (datos).
 *
 * Fuente de verdad = los CSV versionados en database/seeders/data/:
 *   · catalogo_fusionado_departamentos.csv (48)  · catalogo_fusionado_puestos.csv (255)
 *   · crewcare_seed_departments.csv / _positions.csv → SOLO para los alias es/en (los fusionados no los traen).
 *
 * REGLAS (owner):
 *  - NO renumera / NO trunca: los puestos vivos conservan su id numérico y sus asignaciones
 *    (production_user, contratos, rutas de firma apuntan ahí). Vivos = UPDATE en su lugar; nuevos = INSERT.
 *  - is_hod es MARCA OPERATIVA (un depto puede tener VARIAS): en `ambos`+nuevos gana la semilla
 *    (is_hod ← hod_capable); los `solo vivo` conservan su is_hod. `hod_capable` se guarda aparte.
 *  - catalog_key NO es único: varias filas vivas comparten clave (agrupación semántica, no llave).
 *  - `binding` se siembra aunque la entidad UNIDAD no exista todavía (queda inerte).
 *
 * IDEMPOTENTE: correr dos veces no duplica. Vivos por id (respaldo depto+nombre = misma llave natural
 * que OrgCatalogSeeder), deptos nuevos por nombre (único), puestos nuevos por catalog_key (único),
 * alias con insertOrIgnore.
 */
class CatalogFusionSeeder extends Seeder
{
    private const DEP_FILE  = 'catalogo_fusionado_departamentos.csv';
    private const POS_FILE  = 'catalogo_fusionado_puestos.csv';
    private const SEED_DEP  = 'crewcare_seed_departments.csv';
    private const SEED_POS  = 'crewcare_seed_positions.csv';

    public function run(): void
    {
        $dir = database_path('seeders/data');
        $now = Carbon::now();

        $this->seedDepartments($dir, $now);

        // Mapas de departamento (tras sembrarlos).
        $deptByName  = [];   // name  => id
        $deptByKey   = [];   // key   => [ids]
        $deptSortById = [];  // id    => sort_order
        foreach (DB::table('departments')->get(['id', 'name', 'catalog_key', 'sort_order']) as $d) {
            $deptByName[$d->name] = $d->id;
            $deptSortById[$d->id] = (int) $d->sort_order;
            if ($d->catalog_key) {
                $deptByKey[$d->catalog_key][] = $d->id;
            }
        }

        $unresolved = $this->seedPositions($dir, $now, $deptByName, $deptByKey, $deptSortById);

        $this->seedAliases($dir, $now);
        $this->seedAmbiguousAliases($now);

        $depCount = DB::table('departments')->count();
        $posCount = DB::table('positions')->count();
        $aliCount = DB::table('catalog_aliases')->count();
        $this->command?->info("CatalogFusion: {$depCount} departamentos, {$posCount} puestos, {$aliCount} alias.");
        if ($unresolved) {
            $this->command?->warn('CatalogFusion: puestos nuevos SIN departamento resuelto (revisar): ' . implode('; ', $unresolved));
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    private function seedDepartments(string $dir, Carbon $now): void
    {
        foreach ($this->readCsv("$dir/" . self::DEP_FILE) as $r) {
            $vals = [
                'name_en'      => $this->nn($r['name_en']),
                'sort_order'   => (int) $r['sort_order'],
                'catalog_key'  => $this->nn($r['catalog_key']),
                'existence'    => $this->cleanExistence($r['existence']),
                'account_hint' => $this->nn($r['account_hint']),
                'updated_at'   => $now,
            ];

            if ($r['origen'] === 'vivo' && $r['id_vivo'] !== ''
                && DB::table('departments')->where('id', (int) $r['id_vivo'])->exists()) {
                // Vivo: UPDATE por id exacto. NO tocar `name` (se preserva el vivo).
                DB::table('departments')->where('id', (int) $r['id_vivo'])->update($vals);
            } else {
                // Nuevo (semilla) o fresco sin ese id: por nombre, que es UNIQUE en departments.
                DB::table('departments')->updateOrInsert(
                    ['name' => $r['name_es']],
                    $vals + ['active' => 1, 'created_at' => $now]
                );
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    /** @return array<string> claves de puestos nuevos sin depto resuelto (para reporte). */
    private function seedPositions(string $dir, Carbon $now, array $deptByName, array $deptByKey, array $deptSortById): array
    {
        $unresolved = [];

        foreach ($this->readCsv("$dir/" . self::POS_FILE) as $r) {
            $deptId = $this->resolveDept($r['depto'], $r['dept_key'], $deptByName, $deptByKey);
            $rank   = (int) $r['rank'];
            $sort   = ($deptId ? ($deptSortById[$deptId] ?? 0) : 0) * 100 + $rank;

            $vals = [
                'catalog_key' => $this->nn($r['catalog_key']),
                'rank'        => $rank,
                'binding'     => $r['binding'] ?: 'unit',
                'hod_capable' => (int) $r['hod_capable'],
                'grade'       => $this->nn($r['grade']),
                'existence'   => $this->cleanExistence($r['existence']),
                'sort_order'  => $sort,
                'updated_at'  => $now,
            ];
            if ($r['name_en'] !== '' && $r['name_en'] !== 'FALTA') {
                $vals['name_en'] = $r['name_en'];
            }
            // is_hod: gana la semilla en `ambos` y nuevos; los `solo vivo` conservan su valor (identidad).
            $vals['is_hod'] = $r['origen'] === 'solo vivo'
                ? (int) $r['is_hod_vivo']
                : (int) $r['hod_capable'];

            if ($r['origen'] !== 'solo semilla' && $r['id_vivo'] !== ''
                && DB::table('positions')->where('id', (int) $r['id_vivo'])->exists()) {
                // Vivo por id exacto: UPDATE en su lugar (id + asignaciones intactos). NO tocar name/department_id.
                DB::table('positions')->where('id', (int) $r['id_vivo'])->update($vals);
                continue;
            }

            if ($deptId === null) {
                $unresolved[] = ($r['catalog_key'] ?: $r['name_es']) . " ({$r['depto']}/{$r['dept_key']})";
                continue;
            }

            if ($r['origen'] !== 'solo semilla') {
                // Vivo que en ESTA base no existe por id (p.ej. base fresca): respaldo por depto+nombre
                // (misma llave natural que OrgCatalogSeeder). Si tampoco existe, se inserta.
                $q = DB::table('positions')->where('department_id', $deptId)->where('name', $r['name_es'])->whereNull('production_id');
                if ($q->exists()) {
                    $q->update($vals);
                } else {
                    DB::table('positions')->insert($vals + [
                        'department_id' => $deptId, 'name' => $r['name_es'],
                        'active' => 1, 'production_id' => null, 'created_at' => $now,
                    ]);
                }
                continue;
            }

            // Nuevo desde semilla: por catalog_key (único para los solo-semilla).
            DB::table('positions')->updateOrInsert(
                ['catalog_key' => $r['catalog_key']],
                $vals + [
                    'department_id' => $deptId, 'name' => $r['name_es'],
                    'active' => 1, 'production_id' => null, 'created_at' => $now,
                ]
            );
        }

        return $unresolved;
    }

    /** depto por nombre exacto; si no, por dept_key SOLO si es único. null si ambiguo/no resuelto. */
    private function resolveDept(string $depto, string $deptKey, array $deptByName, array $deptByKey): ?int
    {
        if ($depto !== '' && isset($deptByName[$depto])) {
            return $deptByName[$depto];
        }
        if ($deptKey !== '' && isset($deptByKey[$deptKey]) && count($deptByKey[$deptKey]) === 1) {
            return $deptByKey[$deptKey][0];
        }
        return null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    private function seedAliases(string $dir, Carbon $now): void
    {
        // Mapas catalog_key => [ids] (varias filas comparten clave).
        $posByKey = [];
        foreach (DB::table('positions')->whereNotNull('catalog_key')->get(['id', 'catalog_key']) as $p) {
            $posByKey[$p->catalog_key][] = $p->id;
        }
        $depByKey = [];
        foreach (DB::table('departments')->whereNotNull('catalog_key')->get(['id', 'catalog_key']) as $d) {
            $depByKey[$d->catalog_key][] = $d->id;
        }

        foreach ($this->readCsv("$dir/" . self::SEED_POS) as $r) {
            $ids = $posByKey[$r['position_id']] ?? [];
            foreach ($ids as $id) {
                $this->insertAliases('position', $id, $r['aliases_es'] ?? '', $r['aliases_en'] ?? '', $now);
            }
        }
        foreach ($this->readCsv("$dir/" . self::SEED_DEP) as $r) {
            $ids = $depByKey[$r['department_id']] ?? [];
            foreach ($ids as $id) {
                $this->insertAliases('department', $id, $r['aliases_es'] ?? '', $r['aliases_en'] ?? '', $now);
            }
        }
    }

    private function insertAliases(string $type, int $entityId, string $es, string $en, Carbon $now): void
    {
        $rows = [];
        foreach ($this->splitPipe($es) as $a) {
            $rows[] = ['entity_type' => $type, 'entity_id' => $entityId, 'lang' => 'es', 'alias' => $a, 'created_at' => $now, 'updated_at' => $now];
        }
        foreach ($this->splitPipe($en) as $a) {
            $rows[] = ['entity_type' => $type, 'entity_id' => $entityId, 'lang' => 'en', 'alias' => $a, 'created_at' => $now, 'updated_at' => $now];
        }
        if ($rows) {
            DB::table('catalog_aliases')->insertOrIgnore($rows);   // el UNIQUE dedup en re-corridas
        }
    }

    private function seedAmbiguousAliases(Carbon $now): void
    {
        $rules = [
            ['coordinador', 'require_department', 'Nunca resuelve solo: exige el departamento en la misma celda (hay 11 "Coordinador de").'],
            ['supervisor',  'grade_prefix',       'Es prefijo de GRADO, no de función: no identifica un puesto por sí solo → mandar a revisión.'],
        ];
        foreach ($rules as [$alias, $rule, $note]) {
            $exists = DB::table('catalog_ambiguous_aliases')->where('alias', $alias)->exists();
            DB::table('catalog_ambiguous_aliases')->updateOrInsert(
                ['alias' => $alias],
                ['rule' => $rule, 'note' => $note, 'is_active' => 1, 'updated_at' => $now] + ($exists ? [] : ['created_at' => $now])
            );
        }
    }

    // ─── utilidades ───────────────────────────────────────────────────────────
    /** Lee un CSV con encabezado → array de filas asociativas. Tolera BOM y línea final vacía. */
    private function readCsv(string $path): array
    {
        $out = [];
        if (! is_file($path) || ($fh = fopen($path, 'r')) === false) {
            return $out;
        }
        $header = fgetcsv($fh);
        if ($header === false) {
            fclose($fh);
            return $out;
        }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);   // BOM
        $n = count($header);
        while (($row = fgetcsv($fh)) !== false) {
            if ($row === [null] || (count($row) === 1 && trim((string) $row[0]) === '')) {
                continue;   // línea vacía
            }
            $row = array_pad(array_slice($row, 0, $n), $n, '');
            $out[] = array_combine($header, array_map(fn ($v) => is_string($v) ? trim($v) : $v, $row));
        }
        fclose($fh);

        return $out;
    }

    private function splitPipe(string $v): array
    {
        return array_values(array_unique(array_filter(array_map('trim', explode('|', $v)), fn ($s) => $s !== '')));
    }

    private function nn(string $v): ?string
    {
        return $v === '' ? null : $v;
    }

    /** "PROPUESTO:core" → "core". */
    private function cleanExistence(string $v): string
    {
        $v = trim(preg_replace('/^PROPUESTO:/i', '', $v));

        return $v !== '' ? $v : 'core';
    }
}
