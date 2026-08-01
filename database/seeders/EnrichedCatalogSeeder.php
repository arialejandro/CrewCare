<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\HazardEvent;
use App\Models\SafetyStandard;

/**
 * EnrichedCatalogSeeder — siembra el CATÁLOGO ENRIQUECIDO (2026-07-18).
 *
 * Fuente: database/seeders/data/enriched_catalog.php (GENERADO, parseo verde).
 *   ['standards'=>[{badge,code,category_name,url} x83],
 *    'events'   =>[{code,context,category_label,category_key_proposed,likelihood,
 *                   consequence,name_es,name_en,norm_refs:[{badge,code}]} x87]]
 *
 * Qué hace (todo en UNA transacción, con modo DRY-RUN):
 *   a) NORMAS
 *      - 2 STPS "bump" IN-PLACE: NOM-017-STPS-2024 REEMPLAZA a NOM-017-STPS-2008 y
 *        NOM-006-STPS-2023 REEMPLAZA a NOM-006-STPS-2014 → se renombra la fila VIEJA
 *        (regulation_code + reference_url + category_name del data file) CONSERVANDO
 *        su id y su verified_at/verified_by_id. NO crea filas nuevas. Idempotente:
 *        si el code NUEVO ya existe (2ª corrida) → no-op.
 *      - 5 normas NUEVAS (firstOrCreate por regulation_code): DOT 49 CFR 173.185,
 *        OSHA 29 CFR 1910.304, OSHA 29 CFR 1910 Subpart T, OSHA SB-132 y SCT. Nacen
 *        VERIFICADAS (verified_at=now(), verified_by_id=null) EXCEPTO la SCT
 *        (NOM-002-SCT-SEMAR-ARTF/2023) que nace PENDIENTE (verified_at=null).
 *      - Las 76 restantes ya existen → firstOrCreate = no-op (no las altera).
 *   b) EVENTOS: firstOrCreate los 87 (is_active=1, verified_at=NULL = pendientes,
 *      category = clave FINA mapeada desde el category_label vía HazardEvent::categories()).
 *   c) N:M: por cada evento resuelve cada norm_ref (badge+code) → safety_standards.id
 *      y sync() al pivote hazard_event_standard. Normaliza el artefacto
 *      'Bulletin #23 (incl. addendum 23E, ...)' → 'Bulletin #23'. Los codes que NO
 *      resuelven (9 addenda CSATF sin ficha propia) se LOGuean y se SALTAN (no aborta).
 *
 * FAIL-FAST: si falta hazard_events.verified_at → aborta (requiere el Paso 2 /
 * 2026-07-18-normas-eventos-verification.sql).
 *
 * DRY-RUN (no escribe nada): dos vías —
 *   1) env('ENRICHED_DRY_RUN', false) === true, o
 *   2) (new EnrichedCatalogSeeder)->setDryRun(true)->run()  (ver runner del owner)
 * En dry-run hace TODO el trabajo dentro de la transacción y al final DB::rollBack()
 * + reporta el plan; en real, DB::commit().
 *
 * Correr real:   php artisan db:seed --class=EnrichedCatalogSeeder
 * Correr dry:    ENRICHED_DRY_RUN=true php artisan db:seed --class=EnrichedCatalogSeeder
 */
class EnrichedCatalogSeeder extends Seeder
{
    /** Modo simulación: hace el trabajo y hace rollback en vez de commit. */
    public $dryRun = false;

    /**
     * Bump STPS IN-PLACE:  code NUEVO (del data file)  =>  code VIEJO (en BD).
     * Se renombra la fila vieja conservando id + verified_at.
     */
    private $stpsBump = [
        'NOM-017-STPS-2024' => 'NOM-017-STPS-2008',
        'NOM-006-STPS-2023' => 'NOM-006-STPS-2014',
    ];

    /**
     * Las 5 normas NUEVAS (regulation_code => nace_pendiente?).
     * Todas verificadas al nacer EXCEPTO la SCT (pendiente por su salvedad).
     */
    private $newStandards = [
        '49 CFR 173.185'                              => false,
        '29 CFR 1910.304'                             => false,
        '29 CFR 1910 Subpart T'                       => false,
        'Cal/OSHA CA Labor Code §§9150-9161 (SB 132)' => false,
        'NOM-002-SCT-SEMAR-ARTF/2023'                 => true,  // PENDIENTE
    ];

    /** Fluent setter para el runner de dry-run. */
    public function setDryRun($flag)
    {
        $this->dryRun = (bool) $flag;
        return $this;
    }

    /** Salida robusta: usa la consola de artisan si existe, si no echo directo. */
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
        // Resolver el flag de dry-run: propiedad explícita gana; si no, env.
        $dry = $this->dryRun || filter_var(env('ENRICHED_DRY_RUN', false), FILTER_VALIDATE_BOOLEAN);

        // ---- FAIL-FAST: el delta de verificación debe estar aplicado (Paso 2) ----
        if (!Schema::hasColumn('hazard_events', 'verified_at')) {
            throw new \RuntimeException(
                'EnrichedCatalogSeeder ABORTADO: falta la columna hazard_events.verified_at. '.
                'Aplica primero el Paso 2 (2026-07-18-normas-eventos-verification.sql) y vuelve a correr.'
            );
        }

        // ---- Cargar el data file ----
        $path = database_path('seeders/data/enriched_catalog.php');
        if (!is_file($path)) {
            throw new \RuntimeException("EnrichedCatalogSeeder ABORTADO: no existe el data file {$path}.");
        }
        $data = require $path;
        if (!is_array($data) || !isset($data['standards'], $data['events'])) {
            throw new \RuntimeException('EnrichedCatalogSeeder ABORTADO: el data file no tiene la forma esperada (standards/events).');
        }
        $standards = $data['standards'];
        $events    = $data['events'];

        // ---- Mapa label => clave fina (categories() ya incluye las 25 nuevas + weather) ----
        $labelToKey = array_flip(HazardEvent::categories());

        // ---- Conteos ANTES (fuera de la transacción, para verificar idempotencia) ----
        $before = [
            'standards' => DB::table('safety_standards')->count(),
            'events'    => DB::table('hazard_events')->count(),
            'links'     => DB::table('hazard_event_standard')->count(),
        ];

        // Acumuladores para el reporte.
        $normsCreated   = [];   // filas nuevas
        $normsUpdated   = [];   // STPS bump aplicado
        $normsBumpNoop  = [];   // STPS bump ya renombrado (2ª corrida)
        $normsNoop      = 0;    // 76 existentes intactas
        $normsUnexpected = []; // firstOrCreate genérico que SÍ creó (no debería pasar)
        $eventsCreated  = 0;
        $eventsExisting = 0;
        $badCategory    = [];   // labels sin mapa (no debería pasar)
        $linksTotal     = 0;
        $unresolved     = [];   // 'BADGE|code' => [eventCode, ...]
        $eventsNoNorms  = [];   // eventos que quedaron con 0 normas (no debería pasar)

        DB::beginTransaction();
        try {
            // ===================== (a) NORMAS =====================
            foreach ($standards as $s) {
                $code  = $s['code'];
                $badge = $s['badge'];
                $name  = $s['category_name'];
                $url   = isset($s['url']) ? $s['url'] : null;

                if (isset($this->stpsBump[$code])) {
                    // --- BUMP STPS IN-PLACE ---
                    $oldCode = $this->stpsBump[$code];
                    $existingNew = SafetyStandard::where('regulation_code', $code)->first();
                    if ($existingNew) {
                        // 2ª corrida: ya está renombrada → no-op.
                        $normsBumpNoop[] = "{$code} (id={$existingNew->id})";
                    } else {
                        $old = SafetyStandard::where('regulation_code', $oldCode)->first();
                        if ($old) {
                            // Renombrar en su sitio; CONSERVA id + verified_at + verified_by_id.
                            $old->regulation_code = $code;
                            $old->reference_url   = $url;
                            $old->category_name   = $name;
                            $old->save();
                            $normsUpdated[] = "{$oldCode} → {$code} (id={$old->id}, verified_at conservado="
                                .var_export($old->verified_at ? (string) $old->verified_at : null, true).")";
                        } else {
                            // Borde improbable (ni vieja ni nueva): créala verificada.
                            $row = SafetyStandard::create([
                                'category_name'    => $name,
                                'regulation_badge' => $badge,
                                'regulation_code'  => $code,
                                'reference_url'    => $url,
                                'is_active'        => 1,
                                'verified_at'      => now(),
                                'verified_by_id'   => null,
                            ]);
                            $normsUpdated[] = "{$code} (NI vieja NI nueva existían → creada verificada id={$row->id})";
                        }
                    }
                    continue;
                }

                if (array_key_exists($code, $this->newStandards)) {
                    // --- NORMA NUEVA (firstOrCreate con verificación correcta) ---
                    $isPending = $this->newStandards[$code];
                    $row = SafetyStandard::firstOrCreate(
                        ['regulation_code' => $code],
                        [
                            'category_name'    => $name,
                            'regulation_badge' => $badge,
                            'reference_url'    => $url,
                            'is_active'        => 1,
                            'verified_at'      => $isPending ? null : now(),
                            'verified_by_id'   => null,
                        ]
                    );
                    if ($row->wasRecentlyCreated) {
                        $normsCreated[] = "[{$badge}] {$code} — ".($isPending ? 'PENDIENTE (verified_at=NULL)' : 'VERIFICADA')." (id={$row->id})";
                    } else {
                        $normsNoop++; // ya existía (2ª corrida)
                    }
                    continue;
                }

                // --- GENÉRICA: ya existe → no-op. (firstOrCreate no altera la fila.) ---
                $row = SafetyStandard::firstOrCreate(
                    ['regulation_code' => $code],
                    [
                        'category_name'    => $name,
                        'regulation_badge' => $badge,
                        'reference_url'    => $url,
                        'is_active'        => 1,
                    ]
                );
                if ($row->wasRecentlyCreated) {
                    $normsUnexpected[] = "[{$badge}] {$code} (id={$row->id})";
                } else {
                    $normsNoop++;
                }
            }

            // ===================== (b) EVENTOS =====================
            $i = 0;
            foreach ($events as $e) {
                $label = $e['category_label'];
                if (isset($labelToKey[$label])) {
                    $key = $labelToKey[$label];
                } else {
                    // Fallback defensivo: usa la clave propuesta y loguea.
                    $key = $e['category_key_proposed'];
                    $badCategory[] = "{$e['code']}: label sin mapa «{$label}» → uso «{$key}»";
                }

                $event = HazardEvent::firstOrCreate(
                    ['code' => $e['code']],
                    [
                        'context'             => $e['context'],
                        'category'            => $key,
                        'name_es'             => $e['name_es'],
                        'name_en'             => $e['name_en'],
                        'default_likelihood'  => $e['likelihood'],
                        'default_consequence' => $e['consequence'],
                        'is_active'           => 1,
                        'verified_at'         => null,       // PENDIENTES (propuestos)
                        'sort_order'          => 900 + $i,   // > todos los sort_order base
                    ]
                );
                if ($event->wasRecentlyCreated) {
                    $eventsCreated++;
                } else {
                    $eventsExisting++;
                }

                // ===================== (c) N:M =====================
                $ids = [];
                foreach ($e['norm_refs'] as $ref) {
                    $rBadge = $ref['badge'];
                    $rCode  = $ref['code'];

                    // Normalización: los bullets CSATF a veces traen un paréntesis
                    // ('Bulletin #23 (incl. addendum 23E, ...)') → recorta a la ficha base.
                    // OJO: solo CSATF; 'Cal/OSHA ... (SB 132)' (badge OSHA) NO se toca.
                    $normCode = $rCode;
                    if ($rBadge === 'CSATF') {
                        $pos = strpos($rCode, ' (');
                        if ($pos !== false) {
                            $normCode = substr($rCode, 0, $pos);
                        }
                    }

                    $sid = SafetyStandard::where('regulation_code', $normCode)->value('id');
                    if ($sid) {
                        $ids[$sid] = $sid;
                    } else {
                        $ukey = $rBadge.'|'.$rCode;
                        if (!isset($unresolved[$ukey])) {
                            $unresolved[$ukey] = [];
                        }
                        $unresolved[$ukey][] = $e['code'];
                    }
                }
                $ids = array_values($ids);
                $event->standards()->sync($ids);
                $linksTotal += count($ids);
                if (!$ids) {
                    $eventsNoNorms[] = $e['code'];
                }

                $i++;
            }

            // ===================== REPORTE =====================
            $this->report(
                $dry, $before,
                $normsCreated, $normsUpdated, $normsBumpNoop, $normsNoop, $normsUnexpected,
                $eventsCreated, $eventsExisting, $badCategory, $linksTotal, $unresolved,
                $eventsNoNorms, count($standards), count($events)
            );

            // ===================== COMMIT / ROLLBACK =====================
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
            $this->out('EnrichedCatalogSeeder: EXCEPCIÓN → rollback. '.$ex->getMessage());
            throw $ex;
        }

        // ---- Conteos DESPUÉS (fuera de la transacción) ----
        $after = [
            'standards' => DB::table('safety_standards')->count(),
            'events'    => DB::table('hazard_events')->count(),
            'links'     => DB::table('hazard_event_standard')->count(),
        ];
        $this->out('');
        $this->out('=== CONTEOS BD (fuera de la transacción) ===');
        $this->out(sprintf('  safety_standards      : antes=%d  después=%d', $before['standards'], $after['standards']));
        $this->out(sprintf('  hazard_events         : antes=%d  después=%d', $before['events'], $after['events']));
        $this->out(sprintf('  hazard_event_standard : antes=%d  después=%d', $before['links'], $after['links']));
        if ($dry) {
            $ok = ($before === $after);
            $this->out('  IDEMPOTENCIA DRY-RUN  : '.($ok ? 'OK (antes == después, BD intacta)' : '¡DIFERENCIA! revisar'));
        }
    }

    /** Imprime el plan legible. */
    private function report(
        $dry, $before,
        $normsCreated, $normsUpdated, $normsBumpNoop, $normsNoop, $normsUnexpected,
        $eventsCreated, $eventsExisting, $badCategory, $linksTotal, $unresolved,
        $eventsNoNorms, $standardsCount, $eventsCount
    ) {
        $this->out('==================================================================');
        $this->out('  EnrichedCatalogSeeder — '.($dry ? 'DRY-RUN (simulación, sin escribir)' : 'CORRIDA REAL'));
        $this->out('==================================================================');
        $this->out(sprintf('Data file: %d normas / %d eventos.', $standardsCount, $eventsCount));

        $this->out('');
        $this->out('--- NORMAS: CREAR ('.count($normsCreated).') ---');
        foreach ($normsCreated as $l) { $this->out('  + '.$l); }
        if (!$normsCreated) { $this->out('  (ninguna — ¿2ª corrida?)'); }

        $this->out('');
        $this->out('--- NORMAS: ACTUALIZAR IN-PLACE / STPS bump ('.count($normsUpdated).') ---');
        foreach ($normsUpdated as $l) { $this->out('  ~ '.$l); }
        if ($normsBumpNoop) {
            $this->out('  STPS bump ya aplicado (no-op, 2ª corrida): '.implode(', ', $normsBumpNoop));
        }
        if (!$normsUpdated && !$normsBumpNoop) { $this->out('  (ninguna)'); }

        $this->out('');
        $this->out('--- NORMAS: NO-OP (ya existían) : '.$normsNoop.' ---');
        if ($normsUnexpected) {
            $this->out('  ¡ATENCIÓN! firstOrCreate genérico CREÓ filas inesperadas ('.count($normsUnexpected).'):');
            foreach ($normsUnexpected as $l) { $this->out('    + '.$l); }
        }

        $this->out('');
        $this->out('--- EVENTOS ('.$eventsCount.') ---');
        $this->out('  a crear (wasRecentlyCreated) : '.$eventsCreated);
        $this->out('  ya existentes (no-op)        : '.$eventsExisting);
        if ($badCategory) {
            $this->out('  labels sin mapa de categoría ('.count($badCategory).'):');
            foreach ($badCategory as $l) { $this->out('    ! '.$l); }
        } else {
            $this->out('  categorías: 100% resueltas por HazardEvent::categories().');
        }

        $this->out('');
        $this->out('--- VÍNCULOS N:M (hazard_event_standard) ---');
        $this->out('  a crear (sync de eventos nuevos) : '.$linksTotal);
        if ($eventsNoNorms) {
            $this->out('  ¡ATENCIÓN! eventos SIN ninguna norma resuelta ('.count($eventsNoNorms).'): '.implode(', ', $eventsNoNorms));
        } else {
            $this->out('  garantía: TODOS los eventos quedaron con ≥1 norma.');
        }

        $this->out('');
        $totalRefsUnres = 0;
        foreach ($unresolved as $evs) { $totalRefsUnres += count($evs); }
        $this->out('--- NORM_REFS NO RESUELTOS (saltados, no abortan) : '
            .count($unresolved).' codes distintos / '.$totalRefsUnres.' referencias ---');
        ksort($unresolved);
        foreach ($unresolved as $ukey => $evs) {
            $this->out('  · '.$ukey.'  ('.count($evs).'x) → '.implode(', ', $evs));
        }
        if (!$unresolved) { $this->out('  (ninguno — todo resolvió)'); }
    }
}
