<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\SfxEffectType;
use App\Models\SafetyStandard;

/**
 * EffectStandardBridgeSeeder — PUENTE SPFX ↔ NORMAS (2026-07-18, Paso 5b).
 *
 * Reconcilia la normativa de cada TIPO de efecto especial (que vive como texto libre
 * estructurado por jurisdicción en `sfx_effect_types.standards_snapshot`) contra el
 * catálogo canónico `safety_standards`, poblando el pivote `effect_standard` por ID.
 * Cierra el círculo Pieza 1 (SPFX) ↔ Pieza 2+3 (Normas).
 *
 * FILOSOFÍA — CONSERVADORA Y MECÁNICA (son datos de compliance, NO se inventan
 * equivalencias): se empareja SOLO por código EXACTO de `regulation_code`. Nada de
 * aliases semánticos ni "misma NOM, otro año". Lo que no empareja limpio se LOGuea
 * como no-resuelto y se queda solo en el snapshot (que NUNCA se toca).
 *
 * REGLAS DE EXTRACCIÓN (keyed por jurisdicción del snapshot):
 *   csatf              → '#N …'            → 'Bulletin #N'         (catálogo: #1–#45)
 *   mexico             → 'NOM-…-STPS-AAAA' → tal cual              (badge STPS)
 *   eeuu_ca / eeuu_fed → '1910.x' / '1926.x' (con o sin 'OSHA'/'29 CFR')
 *                                          → '29 CFR 1910.x'       (badge OSHA)
 * Todo lo demás (Fire Code, SB 132, SEDENA, Ley Federal, NFPA, ATF, Título 19, AHJ,
 * ACGIH, Fact Sheets CSATF, "Sin boletín", Cal-OSHA §xxxx, NOM fuera del catálogo…)
 * → NO resuelve → se LOGuea (esperado: el 70,8% de las citas mapea; el resto no).
 *
 * IDEMPOTENTE: por efecto hace `standards()->sync($ids)` con el conjunto EXACTO de
 * ids resueltos → 2ª corrida = 0 vínculos nuevos.
 *
 * TRANSACCIONAL + DRY-RUN (no escribe nada). Dos vías:
 *   1) env('BRIDGE_DRY_RUN', false) === true, o
 *   2) (new EffectStandardBridgeSeeder)->setDryRun(true)->run()
 * En dry-run hace TODO el trabajo dentro de la transacción y al final DB::rollBack()
 * + reporta el plan; en real, DB::commit().
 *
 * FAIL-FAST (precedencia del Paso 5b):
 *   - falta `sfx_effect_types`  → aborta (requiere Pieza 1 / 2026-07-16-sfx-effect-types.sql).
 *   - falta `effect_standard`   → aborta (aplica 2026-07-18-effect-standard-bridge.sql).
 *   - falta / vacío `safety_standards` → aborta (requiere Pieza 2+3 poblada / 5a).
 *
 * Correr real:   php artisan db:seed --class=EffectStandardBridgeSeeder
 * Correr dry:    BRIDGE_DRY_RUN=true php artisan db:seed --class=EffectStandardBridgeSeeder
 */
class EffectStandardBridgeSeeder extends Seeder
{
    /** Modo simulación: hace el trabajo y hace rollback en vez de commit. */
    public $dryRun = false;

    /**
     * ALIAS CURADO — código canónico de SB 132, resuelto en run() contra la fila REAL
     * del catálogo (la que contiene el token 'SB 132' en su regulation_code). SB 132 se
     * cita en los snapshots por su nombre CORTO ('SB 132 (risk assessment)'), no por su
     * código completo ('Cal/OSHA CA Labor Code §§9150-9161 (SB 132)', id 123). Sin este
     * alias caería como "no-resuelta" y se leería como "falta en el catálogo" cuando SÍ
     * está. NO es matching difuso: es UN mapa explícito y curado de una abreviatura
     * conocida, resuelto contra la fila viva (si no existe, queda null → no fuerza nada).
     */
    private $sb132Code = null;

    /** Fluent setter para el runner de dry-run. */
    public function setDryRun($flag)
    {
        $this->dryRun = (bool) $flag;
        return $this;
    }

    /** Salida robusta: consola de artisan si existe, si no echo directo. */
    private function out($msg)
    {
        if ($this->command) {
            $this->command->line($msg);
        } else {
            echo $msg."\n";
        }
    }

    public function run()
    {
        $dry = $this->dryRun || filter_var(env('BRIDGE_DRY_RUN', false), FILTER_VALIDATE_BOOLEAN);

        // ---------------- FAIL-FAST de precedencia ----------------
        if (!Schema::hasTable('sfx_effect_types')) {
            throw new \RuntimeException(
                'EffectStandardBridgeSeeder ABORTADO: falta la tabla sfx_effect_types. '.
                'Aplica primero 2026-07-16-sfx-effect-types.sql (Pieza 1 / Capa A).'
            );
        }
        if (!Schema::hasTable('effect_standard')) {
            throw new \RuntimeException(
                'EffectStandardBridgeSeeder ABORTADO: falta la tabla effect_standard. '.
                'Aplica primero 2026-07-18-effect-standard-bridge.sql.'
            );
        }
        if (!Schema::hasTable('safety_standards') || DB::table('safety_standards')->count() === 0) {
            throw new \RuntimeException(
                'EffectStandardBridgeSeeder ABORTADO: safety_standards ausente o vacío. '.
                'Requiere Pieza 2+3 poblada (SafetyCatalogSeeder + EnrichedCatalogSeeder / Paso 5a).'
            );
        }

        // ---------------- Mapa code => id (match EXACTO por regulation_code) ----------------
        // regulation_code es UNIQUE (regla del Paso 4a); se enlaza a cualquier norma del
        // catálogo (activa o retirada): el histórico también es referencia válida.
        $codeToId = [];
        $dupCodes = [];
        foreach (SafetyStandard::all(['id', 'regulation_code', 'regulation_badge']) as $s) {
            $code = $s->regulation_code;
            if (isset($codeToId[$code])) {
                $dupCodes[] = $code; // no debería pasar (UNIQUE); se avisa si pasa
            }
            $codeToId[$code] = $s->id;
            // Resuelve el alias SB 132 contra la fila REAL (código que contiene el token).
            if ($this->sb132Code === null && strpos($code, 'SB 132') !== false) {
                $this->sb132Code = $code;
            }
        }

        // ---------------- Conteo ANTES (fuera de la transacción, para idempotencia) ----------------
        $before = DB::table('effect_standard')->count();

        // Acumuladores del reporte.
        $perEffect     = [];   // [code => ['name'=>, 'matched'=>[codes], 'unresolved'=>int]]
        $unresolved    = [];   // 'jur|raw' => [effectCode, ...]
        $linksTotal    = 0;    // suma de vínculos sync-eados
        $distinctNorms = [];   // ids de norma distintos ligados en toda la corrida
        $effectsNoLink = [];   // efectos que quedaron con 0 normas resueltas
        $effectsCount  = 0;

        DB::beginTransaction();
        try {
            foreach (SfxEffectType::orderBy('id')->get() as $effect) {
                $effectsCount++;
                $snapshot = $effect->standards_snapshot; // array por el cast (o null)
                $ids        = [];
                $matched    = [];
                $unresCount = 0;

                if (is_array($snapshot)) {
                    foreach ($snapshot as $jur => $list) {
                        if (!is_array($list)) {
                            continue;
                        }
                        foreach ($list as $raw) {
                            if (!is_string($raw) || $raw === '') {
                                continue;
                            }
                            $candidates = $this->extractCandidates($jur, $raw);
                            $hit = false;
                            foreach ($candidates as $cand) {
                                if (isset($codeToId[$cand])) {
                                    $sid = $codeToId[$cand];
                                    $ids[$sid] = $sid;
                                    $matched[$cand] = $cand;
                                    $distinctNorms[$sid] = $sid;
                                    $hit = true;
                                }
                            }
                            if (!$hit) {
                                $ukey = $jur.'|'.$raw;
                                if (!isset($unresolved[$ukey])) {
                                    $unresolved[$ukey] = [];
                                }
                                $unresolved[$ukey][] = $effect->code;
                                $unresCount++;
                            }
                        }
                    }
                }

                $ids = array_values($ids);
                $effect->standards()->sync($ids);   // idempotente
                $linksTotal += count($ids);
                if (!$ids) {
                    $effectsNoLink[] = $effect->code;
                }

                $perEffect[$effect->code] = [
                    'name'       => $effect->name,
                    'matched'    => array_values($matched),
                    'unresolved' => $unresCount,
                ];
            }

            // ---------------- REPORTE ----------------
            $this->report(
                $dry, $before, $effectsCount, $perEffect, $unresolved,
                $linksTotal, count($distinctNorms), $effectsNoLink, $dupCodes
            );

            if ($dry) {
                DB::rollBack();
                $this->out('');
                $this->out('>>> DRY-RUN: DB::rollBack() ejecutado — NADA se escribió en la BD.');
            } else {
                DB::commit();
                $this->out('');
                $this->out('>>> REAL: DB::commit() ejecutado — cambios PERSISTIDOS.');
            }
        } catch (\Throwable $ex) {
            DB::rollBack();
            $this->out('EffectStandardBridgeSeeder: EXCEPCIÓN → rollback. '.$ex->getMessage());
            throw $ex;
        }

        // ---------------- Conteo DESPUÉS (fuera de la transacción) ----------------
        $after = DB::table('effect_standard')->count();
        $this->out('');
        $this->out('=== CONTEO effect_standard (fuera de la transacción) ===');
        $this->out(sprintf('  antes=%d  después=%d', $before, $after));
        if ($dry) {
            $this->out('  IDEMPOTENCIA DRY-RUN: '.($before === $after ? 'OK (BD intacta)' : '¡DIFERENCIA! revisar'));
        }
    }

    /**
     * Extrae los CÓDIGOS canónicos candidatos de una cita cruda, según su jurisdicción.
     * Devuelve códigos con la MISMA forma que safety_standards.regulation_code.
     */
    private function extractCandidates($jur, $raw)
    {
        if ($jur === 'csatf') {
            return $this->csatfCodes($raw);
        }
        if ($jur === 'mexico') {
            return $this->stpsCodes($raw);
        }
        if ($jur === 'eeuu_ca' || $jur === 'eeuu_fed') {
            return $this->oshaCodes($raw);
        }
        // Jurisdicción desconocida (no debería ocurrir): sin candidatos → no-resuelto.
        return [];
    }

    /** '#19 Open Flame', '#4 / #4A', 'Sin boletín; #5/#21' → ['Bulletin #19', ...]. */
    private function csatfCodes($raw)
    {
        $out = [];
        if (preg_match_all('/#(\d+)/', $raw, $m)) {
            foreach ($m[1] as $n) {
                $code = 'Bulletin #'.$n;
                $out[$code] = $code;
            }
        }
        return array_values($out);
    }

    /** 'NOM-002-STPS-2010 (incendios) ✔', 'NOM-036-1-STPS-2018' → códigos exactos. */
    private function stpsCodes($raw)
    {
        $out = [];
        if (preg_match_all('/NOM-\d+(?:-\d+)?-STPS-\d+/', $raw, $m)) {
            foreach ($m[0] as $c) {
                $out[$c] = $c;
            }
        }
        return array_values($out);
    }

    /** 'OSHA HazCom 1910.1200', '1910.146', 'OSHA 29 CFR 1910.106' → '29 CFR 1910.x'. */
    private function oshaCodes($raw)
    {
        $out = [];
        if (preg_match_all('/\b(1910|1926)\.(\d+)/', $raw, $m, PREG_SET_ORDER)) {
            foreach ($m as $pair) {
                $code = '29 CFR '.$pair[1].'.'.$pair[2];
                $out[$code] = $code;
            }
        }
        // Alias curado SB 132 (ver $sb132Code). Sólo se dispara con el token exacto
        // 'SB 132'/'SB-132' → NO confunde con 'SB 735' (otra ley, correctamente no-resuelta).
        if ($this->sb132Code !== null && preg_match('/\bSB[\s-]?132\b/', $raw)) {
            $out[$this->sb132Code] = $this->sb132Code;
        }
        return array_values($out);
    }

    /** Imprime el plan legible. */
    private function report(
        $dry, $before, $effectsCount, $perEffect, $unresolved,
        $linksTotal, $distinctNorms, $effectsNoLink, $dupCodes
    ) {
        $this->out('==================================================================');
        $this->out('  EffectStandardBridgeSeeder — '.($dry ? 'DRY-RUN (simulación, sin escribir)' : 'CORRIDA REAL'));
        $this->out('==================================================================');
        $this->out(sprintf('Efectos procesados: %d.  effect_standard antes: %d.', $effectsCount, $before));

        if ($dupCodes) {
            $this->out('');
            $this->out('¡ATENCIÓN! regulation_code duplicados en el catálogo (rompe el mapa): '
                .implode(', ', array_unique($dupCodes)));
        }

        $this->out('');
        $this->out('--- POR EFECTO (normas canónicas ligadas por ID) ---');
        foreach ($perEffect as $code => $info) {
            $m = $info['matched'];
            $this->out(sprintf('  %-7s %-52s  → %d norma(s)%s',
                $code,
                $this->trunc($info['name'], 52),
                count($m),
                $info['unresolved'] ? '  ('.$info['unresolved'].' no-resuelta[s])' : ''
            ));
            if ($m) {
                $this->out('           ['.implode(' · ', $m).']');
            }
        }

        $this->out('');
        $this->out('--- TOTALES ---');
        $this->out('  vínculos a crear (sync)        : '.$linksTotal);
        $this->out('  normas canónicas distintas     : '.$distinctNorms);
        if ($effectsNoLink) {
            $this->out('  efectos SIN ninguna norma resuelta ('.count($effectsNoLink).'): '.implode(', ', $effectsNoLink));
        } else {
            $this->out('  todos los efectos quedaron con ≥1 norma canónica.');
        }

        $this->out('');
        $totalRefs = 0;
        foreach ($unresolved as $evs) {
            $totalRefs += count($evs);
        }
        $this->out('--- CITAS NO RESUELTAS (se quedan solo en el snapshot; no abortan) : '
            .count($unresolved).' distintas / '.$totalRefs.' referencias ---');
        ksort($unresolved);
        foreach ($unresolved as $ukey => $evs) {
            $this->out('  · '.$ukey.'  ('.count($evs).'x) → '.implode(', ', $evs));
        }
        if (!$unresolved) {
            $this->out('  (ninguna — todo resolvió)');
        }
    }

    /** Recorta un string a $n chars con puntos suspensivos (para la tabla del reporte). */
    private function trunc($s, $n)
    {
        $s = (string) $s;
        return mb_strlen($s) > $n ? (mb_substr($s, 0, $n - 1).'…') : $s;
    }
}
