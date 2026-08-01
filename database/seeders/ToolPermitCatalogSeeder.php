<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Models\CatalogPendingStandard;
use App\Models\Permit;
use App\Models\PermitPoint;
use App\Models\Tool;
use App\Models\ToolCheckPoint;
use App\Models\ToolFamily;
use App\Models\ToolVariant;
use App\Support\StandardCodeResolver;

/**
 * ToolPermitCatalogSeeder — importa los catálogos de HERRAMIENTA (Capa B) y
 * PERMISOS DE ACTIVIDAD (Capa C). Idempotente (updateOrCreate por `code` +
 * sync de pivotes). Requiere el esquema de `2026-07-26-tools-permits-catalog.sql`
 * y que `safety_standards` ya esté sembrado (une contra él; no lo duplica).
 *
 * ── DE DÓNDE ────────────────────────────────────────────────────────────────
 * Fuente: database/seeders/data/crewcare_herramientas_catalogo.json (73 tipos)
 *         database/seeders/data/crewcare_permisos_catalogo.json     (15 permisos)
 * Copia BYTE A BYTE de los JSON del owner (no se modifican; las correcciones
 * viven AQUÍ, en el importador).
 *
 * ── LAS 4 CORRECCIONES (viven aquí, no en la fuente) ────────────────────────
 * 1. FAMILIA POR CLAVE: la fuente guarda el nombre visible ("2. Hoja de corte");
 *    mapeamos nombre→clave y persistimos el family_id. Si un nombre no resuelve y
 *    NO es HER-026 → FALLA RUIDOSO (excepción), no se importa con familia nula.
 * 2. HER-026 "Herramienta armada en taller": es la excepción diseñada → family_id
 *    NULL + is_wildcard=1. No es dato incompleto.
 * 3. PER-05/06/07 (por_definir) → se importan como `reverificacion`; su nota se
 *    SUSTITUYE por la regla acordada (REVERIFY_RULE). La reverificación sale de sus
 *    puntos site_sensitive (4/3/3), que ya vienen marcados.
 * 4. En permisos TODO punto es compuerta: se persiste es_compuerta tal cual (103/103
 *    true). En herramienta hay puntos informativos (asimetría correcta).
 *
 * ── NORMAS: UNIR, NO DUPLICAR ───────────────────────────────────────────────
 * Cada cadena de norma (buckets csatf/eeuu_ca/mexico) se resuelve con
 * StandardCodeResolver contra safety_standards.regulation_code. Ancla a DOS
 * niveles: ítem (tool/permit) y punto de comprobación. Lo que no resuelve se
 * PARQUEA en catalog_pending_standards (vínculo pendiente), sin inventar la norma.
 *
 * ── ESTADO SE DECLARA ───────────────────────────────────────────────────────
 * Todo nace con verified_at NULL (redactado desde oficio, sin auditar). El seeder
 * NUNCA marca verificado; tampoco pisa un verified_at/is_active ya puesto por el
 * owner (esos campos van guarded y no se pasan en el update).
 */
class ToolPermitCatalogSeeder extends Seeder
{
    /** Regla acordada que sustituye la nota "ABIERTO..." de PER-05/06/07 (corrección 3). */
    private const REVERIFY_RULE = 'Donde hay izaje o montaje temporal, hay reevaluación: '
        . 'el anclaje es del lugar pero el rig viaja, así que un rig que se arma para la '
        . 'ocasión se revisa al reanudar en otro sitio. Se re-verifican los puntos '
        . 'sensibles al sitio.';

    /** Permisos de montaje temporal cuyo alcance_sitio "por_definir" se resuelve a reverificacion. */
    private const TEMP_ASSEMBLY_PERMITS = ['PER-05', 'PER-06', 'PER-07'];

    /** Contadores para el report() final. */
    private array $stats = [];

    public function run(): void
    {
        foreach (['tool_families', 'tools', 'tool_check_points', 'permits', 'permit_points',
                  'tool_standard', 'check_point_standard', 'permit_standard', 'permit_tool',
                  'check_point_tool', 'catalog_pending_standards'] as $t) {
            if (! Schema::hasTable($t)) {
                throw new \RuntimeException("Falta la tabla `$t`. Aplica primero database/owner-apply/2026-07-26-tools-permits-catalog.sql");
            }
        }

        $her = $this->loadCatalog('crewcare_herramientas_catalogo.json', 'herramientas', 73);
        $per = $this->loadCatalog('crewcare_permisos_catalogo.json', 'permisos', 15);

        $resolver = new StandardCodeResolver(
            DB::table('safety_standards')->get(['id', 'regulation_code', 'regulation_badge'])
        );

        DB::transaction(function () use ($her, $per, $resolver) {
            $familyByName = $this->seedFamilies($her);
            $cpByCode     = $this->seedCheckPoints($her, $resolver);
            $toolByCode   = $this->seedTools($her, $familyByName, $cpByCode, $resolver);
            $permitByCode = $this->seedPermits($per, $resolver);
            $this->seedPermitPoints($per, $permitByCode);
            $this->wirePermitTools($per, $permitByCode, $toolByCode);
        });

        $this->report();
    }

    // ------------------------------------------------------------------ loader

    private function loadCatalog(string $file, string $key, int $expected): array
    {
        $path = database_path('seeders/data/' . $file);
        if (! is_file($path)) {
            throw new \RuntimeException("No existe el archivo fuente: $path");
        }
        $data = json_decode(file_get_contents($path), true);
        if (! is_array($data) || ! isset($data[$key]) || ! is_array($data[$key])) {
            throw new \RuntimeException("El JSON $file no trae el arreglo `$key`.");
        }
        // fail-fast: el conteo esperado protege contra un archivo truncado o cambiado.
        $count = count($data[$key]);
        if ($count !== $expected) {
            throw new \RuntimeException("$file: se esperaban $expected en `$key`, hay $count. Abortando antes de escribir.");
        }
        return $data;
    }

    // ---------------------------------------------------------------- familias

    /** @return array<string,int>  nombre visible de familia => id */
    private function seedFamilies(array $her): array
    {
        $map = [];
        $i = 0;
        foreach ($her['meta']['familias'] as $fam) {
            $family = ToolFamily::updateOrCreate(
                ['family_key' => $fam['clave']],
                [
                    'name'           => $fam['nombre'] ?? $fam['clave'],
                    'name_en'        => $fam['nombre_en'] ?? null,
                    'shape_question' => $fam['pregunta_de_forma'] ?? null,
                    'is_energized'   => (bool) ($fam['energizada'] ?? false),
                    'sort_order'     => $i++,
                ]
            );
            $map[$fam['nombre']] = $family->id;
        }
        $this->stats['familias'] = count($map);
        return $map;
    }

    // ------------------------------------------------------------ puntos (tool)

    /** @return array<string,int>  code de punto => id */
    private function seedCheckPoints(array $her, StandardCodeResolver $resolver): array
    {
        // INVARIANTE FAIL-SAFE (la calculadora de veredicto depende de ella): un punto
        // de COMPUERTA debe traer una salida reconocida que escale, o un gate caído
        // podría leerse como APTA. Se valida ruidoso al importar, nunca en silencio.
        $gateOutcomes = ['correccion_mismo_dia', 'reemplazo', 'actividad_no_ejecutable'];

        $map = [];
        $i = 0;
        foreach ($her['puntos_de_comprobacion'] as $pc) {
            $isGate  = (bool) ($pc['es_compuerta'] ?? true);
            $outcome = $pc['salida_si_falla'] ?? null;
            if ($isGate && ! in_array($outcome, $gateOutcomes, true)) {
                throw new \RuntimeException(
                    "ToolPermitCatalogSeeder: el punto de compuerta {$pc['id']} tiene salida_si_falla "
                    ."'".var_export($outcome, true)."' no reconocida. Un gate DEBE escalar "
                    ."(correccion_mismo_dia | reemplazo | actividad_no_ejecutable); sin eso, un gate "
                    ."caído se leería APTA. Corrige el catálogo antes de importar."
                );
            }
            $point = ToolCheckPoint::updateOrCreate(
                ['code' => $pc['id']],
                [
                    'scope'                => $pc['ambito'] ?? 'universal',
                    'scope_key'            => $pc['clave_ambito'] ?? null,
                    'text_es'              => $pc['texto'] ?? '',
                    'is_gate'              => (bool) ($pc['es_compuerta'] ?? true),
                    'severity'             => $pc['severidad'] ?? null,
                    'outcome_if_fail'      => $pc['salida_si_falla'] ?? null,
                    'supersedes'           => $pc['sustituye'] ?? null,
                    'photo_evidence'       => $pc['evidencia_foto'] ?? null,
                    'estimated_seconds'    => $pc['segundos_estimados'] ?? null,
                    'visible_to_naked_eye' => (bool) ($pc['observable_a_simple_vista'] ?? true),
                    'sort_order'           => $i++,
                ]
            );
            $map[$pc['id']] = $point->id;
            // Normas del PUNTO (nivel punto).
            $this->attachNorms($point, $pc['normas'] ?? [], $resolver);
        }
        $this->stats['puntos_herramienta'] = count($map);
        return $map;
    }

    // ------------------------------------------------------------------- tipos

    /** @return array<string,int>  code de tipo => id */
    private function seedTools(array $her, array $familyByName, array $cpByCode, StandardCodeResolver $resolver): array
    {
        $map = [];
        $variantes = 0;
        $accesorios = 0;
        $conPermiso = 0;
        $i = 0;
        foreach ($her['herramientas'] as $t) {
            $code = $t['id'];
            $famName = $t['familia'] ?? null;
            $familyId = null;
            $isWildcard = false;

            if ($famName !== null && isset($familyByName[$famName])) {
                $familyId = $familyByName[$famName];
            } else {
                // Corrección 1+2: solo HER-026 puede quedar sin familia (comodín).
                if ($code !== 'HER-026') {
                    throw new \RuntimeException("Familia no resuelve para $code: '" . (string) $famName . "'. No se importa con familia nula.");
                }
                $isWildcard = true;
            }

            $tool = Tool::updateOrCreate(
                ['code' => $code],
                [
                    'tool_family_id'               => $familyId,
                    'is_wildcard'                  => $isWildcard,
                    'name'                         => $t['nombre'] ?? '',
                    'name_en'                      => $t['nombre_en'] ?? null,
                    'definition'                   => $t['definicion'] ?? null,
                    'context'                      => $t['contexto'] ?? null,
                    'quick_id'                     => $t['identificacion_rapida'] ?? null,
                    'main_risk'                    => $t['riesgo_principal'] ?? null,
                    'requires_designated_operator' => (bool) ($t['requiere_ejecutante_designado'] ?? false),
                    'is_accessory'                 => (bool) ($t['es_accesorio'] ?? false),
                    'triggers_permit_name'         => $t['permiso_que_dispara'] ?? null,
                    'aliases'                      => $t['alias_set'] ?? null,
                    'departments'                  => $t['departamentos'] ?? null,
                    'stages'                       => $t['etapas'] ?? null,
                    'critical_parts'               => $t['partes_criticas'] ?? null,
                    'failure_modes'                => $t['modos_falla_set'] ?? null,
                    'stop_checks'                  => $t['paro_inmediato'] ?? null,
                    'not_executable_checks'        => $t['actividad_no_ejecutable'] ?? null,
                    'observation_checks'           => $t['solo_observacion'] ?? null,
                    'min_ppe'                      => $t['epp_minimo'] ?? null,
                    'budget'                       => $t['presupuesto'] ?? null,
                    'notes'                        => $t['notas'] ?? null,
                    'sort_order'                   => $i++,
                ]
            );
            $map[$code] = $tool->id;

            if ($tool->is_accessory) $accesorios++;
            if (! empty($t['permiso_que_dispara'])) $conPermiso++;

            // Variantes (hijas). Upsert + poda de las que ya no estén en la fuente.
            $names = array_values($t['variantes'] ?? []);
            $v = 0;
            foreach ($names as $vn) {
                ToolVariant::updateOrCreate(['tool_id' => $tool->id, 'name' => $vn], ['sort_order' => $v++]);
                $variantes++;
            }
            ToolVariant::where('tool_id', $tool->id)
                ->whereNotIn('name', $names ?: ['__none__'])
                ->delete();

            // Pivote tipo↔punto (de puntos_ref).
            $cpIds = [];
            foreach ($t['puntos_ref'] ?? [] as $ref) {
                if (isset($cpByCode[$ref])) $cpIds[] = $cpByCode[$ref];
            }
            $tool->checkPoints()->sync($cpIds);

            // Normas del ÍTEM (nivel tipo).
            $this->attachNorms($tool, $t['normas'] ?? [], $resolver);
        }
        $this->stats['tipos'] = count($map);
        $this->stats['variantes'] = $variantes;
        $this->stats['accesorios'] = $accesorios;
        $this->stats['tipos_con_permiso'] = $conPermiso;
        return $map;
    }

    // ---------------------------------------------------------------- permisos

    /** @return array<string,int>  code de permiso => id */
    private function seedPermits(array $per, StandardCodeResolver $resolver): array
    {
        $map = [];
        $reverifCorregidos = 0;
        $i = 0;
        foreach ($per['permisos'] as $p) {
            $code = $p['id'];

            // Corrección 3: por_definir → reverificacion (solo los tres de montaje temporal).
            $siteScope = $p['alcance_sitio'] ?? null;
            $siteNote  = $p['alcance_sitio_nota'] ?? null;
            if (in_array($code, self::TEMP_ASSEMBLY_PERMITS, true) && $siteScope === 'por_definir') {
                $siteScope = 'reverificacion';
                $siteNote  = self::REVERIFY_RULE;
                $reverifCorregidos++;
            }

            // Autorización externa (0 o 1 objeto por permiso).
            $ae = $p['autorizacion_externa'] ?? null;
            $extAuthority = is_array($ae) ? ($ae['autoridad'] ?? null) : null;
            $extWhat      = is_array($ae) ? ($ae['que'] ?? null) : null;
            $extMandatory = is_array($ae) ? (isset($ae['obligatorio']) ? (bool) $ae['obligatorio'] : null) : null;

            $permit = Permit::updateOrCreate(
                ['code' => $code],
                [
                    'permit_key'         => $p['clave'] ?? null,
                    'family'             => $p['familia'] ?? null,
                    'name'               => $p['nombre'] ?? '',
                    'definition'         => $p['definicion'] ?? null,
                    'covers'             => $p['cubre'] ?? null,
                    'issued_when'        => $p['cuando_se_emite'] ?? null,
                    'issued_by'          => $p['emite'] ?? null,
                    'accepted_by'        => $p['acepta'] ?? null,
                    'signatures'         => $p['firmas'] ?? null,
                    'validity'           => $p['vigencia'] ?? null,
                    'scope'              => $p['alcance'] ?? null,
                    'site_scope'         => $siteScope,
                    'site_scope_note'    => $siteNote,
                    'reverify_on_move'   => $p['reverificacion_al_mover'] ?? null,
                    'ext_auth_authority' => $extAuthority,
                    'ext_auth_what'      => $extWhat,
                    'ext_auth_mandatory' => $extMandatory,
                    'budget'             => $p['presupuesto'] ?? null,
                    'notes'              => $p['notas'] ?? null,
                    'sort_order'         => $i++,
                ]
            );
            $map[$code] = $permit->id;

            // Normas del ÍTEM permiso.
            $this->attachNorms($permit, $p['normas'] ?? [], $resolver);
        }
        $this->stats['permisos'] = count($map);
        $this->stats['reverif_corregidos'] = $reverifCorregidos;
        return $map;
    }

    // --------------------------------------------------------- puntos (permiso)

    private function seedPermitPoints(array $per, array $permitByCode): void
    {
        $n = 0;
        $i = 0;
        foreach ($per['puntos_de_permiso'] as $pt) {
            $permitCode = $pt['permiso_ref'] ?? null;
            if (! isset($permitByCode[$permitCode])) {
                throw new \RuntimeException("Punto de permiso {$pt['id']} referencia un permiso inexistente: $permitCode");
            }
            PermitPoint::updateOrCreate(
                ['code' => $pt['id']],
                [
                    'permit_id'        => $permitByCode[$permitCode],
                    'text_es'          => $pt['texto'] ?? '',
                    'executor'         => $pt['ejecutor'] ?? null,
                    'is_gate'          => (bool) ($pt['es_compuerta'] ?? true),
                    'site_sensitive'   => (bool) ($pt['sensible_al_sitio'] ?? false),
                    'requires_contact' => (bool) ($pt['requiere_contacto'] ?? false),
                    'sort_order'       => $i++,
                ]
            );
            $n++;
        }
        $this->stats['puntos_permiso'] = $n;
    }

    // ----------------------------------------------------- permiso ↔ herramienta

    private function wirePermitTools(array $per, array $permitByCode, array $toolByCode): void
    {
        $pairs = 0;
        foreach ($per['permisos'] as $p) {
            $permitId = $permitByCode[$p['id']] ?? null;
            if (! $permitId) continue;
            $toolIds = [];
            foreach ($p['herramientas_que_lo_disparan'] ?? [] as $h) {
                $hid = $h['id'] ?? null;
                if ($hid !== null && isset($toolByCode[$hid])) {
                    $toolIds[] = $toolByCode[$hid];
                    $pairs++;
                } elseif ($hid !== null) {
                    throw new \RuntimeException("Permiso {$p['id']} dispara una herramienta inexistente: $hid");
                }
            }
            Permit::find($permitId)->tools()->sync($toolIds);
        }
        $this->stats['permiso_herramienta_pares'] = $pairs;
    }

    // ------------------------------------------------------------------- normas

    /**
     * Une las normas de un modelo (buckets csatf/eeuu_ca/mexico) a safety_standards
     * (sync idempotente) y parquea las no-resueltas en catalog_pending_standards.
     * `$normas` puede ser [] (lista vacía) u objeto por buckets.
     */
    private function attachNorms($model, $normas, StandardCodeResolver $resolver): void
    {
        $resolvedIds = [];
        $pending = [];
        if (is_array($normas)) {
            foreach (['csatf', 'eeuu_ca', 'mexico'] as $bucket) {
                $vals = $normas[$bucket] ?? [];
                if (is_string($vals)) $vals = [$vals];
                foreach ((array) $vals as $raw) {
                    $raw = trim((string) $raw);
                    if ($raw === '') continue;
                    $r = $resolver->resolve($raw, $bucket);
                    if ($r['id'] !== null) {
                        $resolvedIds[$r['id']] = true;
                    } else {
                        $pending[$bucket . '||' . $raw] = ['raw' => $raw, 'bucket' => $bucket];
                    }
                }
            }
        }

        // Pivote real (idempotente).
        $model->standards()->sync(array_keys($resolvedIds));

        // Vínculos pendientes: limpiar y re-registrar (idempotente).
        $model->pendingStandards()->delete();
        foreach ($pending as $p) {
            $model->pendingStandards()->create([
                'raw_code'     => $p['raw'],
                'jurisdiction' => $p['bucket'],
            ]);
        }

        $this->stats['normas_resueltas'] = ($this->stats['normas_resueltas'] ?? 0) + count($resolvedIds);
        $this->stats['normas_pendientes'] = ($this->stats['normas_pendientes'] ?? 0) + count($pending);
    }

    // ------------------------------------------------------------------- report

    private function report(): void
    {
        $distinctPending = DB::table('catalog_pending_standards')->distinct()->count('raw_code');
        $lines = [
            'ToolPermitCatalogSeeder — importación completa:',
            "  familias={$this->stats['familias']}  tipos={$this->stats['tipos']}  "
                . "variantes={$this->stats['variantes']}  accesorios={$this->stats['accesorios']}  "
                . "tipos_con_permiso={$this->stats['tipos_con_permiso']}",
            "  puntos_herramienta={$this->stats['puntos_herramienta']}  "
                . "permisos={$this->stats['permisos']}  puntos_permiso={$this->stats['puntos_permiso']}",
            "  permiso↔herramienta pares={$this->stats['permiso_herramienta_pares']}  "
                . "reverif_corregidos={$this->stats['reverif_corregidos']}",
            "  normas: vínculos_resueltos={$this->stats['normas_resueltas']}  "
                . "vínculos_pendientes={$this->stats['normas_pendientes']}  "
                . "cadenas_pendientes_distintas={$distinctPending}",
        ];
        foreach ($lines as $l) {
            if ($this->command) $this->command->info($l);
        }
    }
}
