<?php

namespace App\Models;

use App\Traits\GeneratesUuidKey;
use App\Traits\HasDigitalSignatures;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

/**
 * EmergencyActionPlan — PLAN DE ATENCIÓN A EMERGENCIAS (PAE, 2026-08-06).
 *
 * Segundo documento del motor de salida (hermano del MedevacPoster). UNO POR
 * LLAMADO (día de rodaje); puede cubrir DOS locaciones (company move). NO es
 * captura nueva: se RENDERIZA desde el/los SCOUTING elegidos (hospital, distancia,
 * ETA, GPS, accesos, y los RIESGOS ya evaluados) más el organigrama de emergencia
 * del crew. Ver App\Support\EmergencyActionPlanBuilder.
 *
 * ── ESTA FILA ES UNA FOTOGRAFÍA, NO UNA CONSULTA (doctrina MedevacPoster) ────────
 * `payload` guarda header + organigrama + bloques por locación (riesgos, hospital,
 * mapa) YA RESUELTOS al emitir. El documento no se recalcula al abrirlo: se lee tal
 * como se emitió. Si mañana cambia el scouting o el mapa, el PAE sigue diciendo lo
 * que dijo y el sello SHA-256 lo prueba.
 *
 * ── VERSIONADO (owner 2026-08-06, REEMPLAZA la decisión previa de "independientes") ─
 * El PAE se puede EDITAR: cada edición emite una REVISIÓN nueva (documento sellado
 * aparte, con su propio hash/QR/uuid) que SUPERSEDE a la anterior (`supersedes_id`) y
 * apaga la vieja del listado (`is_active=0`, sin borrarla). `revision` arranca en 1 y
 * sube +1 por edición; la versión visible del documento es v{revision}.0. El sello no
 * se rompe al superseder porque `is_active` está FUERA del hash. Cada versión sigue
 * siendo verificable por su uuid (vigente / alterado); no hay estado "retirado".
 *
 * ── SELLO ───────────────────────────────────────────────────────────────────────
 * Se firma sobre el DATO (attributesToArray ksorteado: payload + shoot_day +
 * plan_date + unit_name + issued_*), nunca sobre el render. `is_active` queda FUERA
 * del hash: es estado de listado, no contenido (y nunca se borra con ->delete()).
 *
 * REGLA DE NOMBRES: ninguna columna colisiona con props de Eloquent.
 */
class EmergencyActionPlan extends Model
{
    use HasDigitalSignatures, GeneratesUuidKey;

    protected $table = 'emergency_action_plans';

    protected $fillable = [
        'uuid', 'production_id', 'unit_id',
        'shoot_day', 'revision', 'supersedes_id', 'root_id', 'plan_date', 'unit_name', 'plan_label',
        'payload',
        'issued_by_id', 'issued_by_name', 'issued_at', 'is_active',
    ];

    protected $casts = [
        'payload'    => 'array',
        'plan_date'  => 'date',
        'issued_at'  => 'datetime',
        'is_active'  => 'boolean',
        'shoot_day'  => 'integer',
        'revision'   => 'integer',
    ];

    /**
     * FUERA del hash: `is_active` es estado de listado posterior a la emisión, no
     * contenido sellado. Apagar un PAE (is_active=0) no lo marca ALTERADO.
     */
    protected $signatureExcludes = ['is_active'];

    /**
     * (2026-09-05 · Unidades P1) `unit_id` EXCLUIDA del hash SOLO cuando es null (los 4 PAE sellados la
     * traen en null → su sello NO cambia; con valor SÍ se sella). CONVIVE con `unit_name` (texto libre
     * ya sellado): unit_name se queda intacto, unit_id se suma como referencia estructurada — ver el
     * reporte de homologación. La const la aplica el trait. Sin cablear filtros (Paso 2).
     */
    const NULLABLE_HASH_EXCLUDES = ['unit_id'];

    /**
     * ¿Está aplicado el SQL del módulo? Memo por petición. Sin la tabla, el módulo se
     * apaga (menú/rutas/botón) en vez de tronar. Mismo patrón que MedevacPoster::supported().
     */
    public static function supported(): bool
    {
        static $memo = null;
        if ($memo === null) {
            try {
                $memo = Schema::hasTable('emergency_action_plans');
            } catch (\Throwable $e) {
                $memo = false;
            }
        }
        return $memo;
    }

    // ---- Relaciones (vivas, para lectura; el PAE ya congeló lo que importa) ----
    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class, 'production_id');
    }

    /** Referencia VIVA a la unidad (unit_id → units). NULL = unidad principal. Sólo lectura. */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    /**
     * (2026-09-06 · Unidades 2A) Nombre de la unidad a MOSTRAR — doctrina snapshot: `unit_name` es la FOTO
     * CONGELADA (lo que se estampó al emitir, sellado) y es lo que el documento DICE. Sin snapshot (PAE
     * viejo) cae a la referencia VIVA (`unit_id` resuelve el nombre actual) o a la principal. NUNCA
     * reescribe `unit_name`. El ESTAMPADO al emitir (unit_name := nombre de la unidad elegida) se cablea en
     * el flujo de emisión (Paso 2b), no aquí.
     */
    public function unitDisplayName(): string
    {
        $snap = trim((string) $this->unit_name);
        if ($snap !== '') {
            return $snap;
        }

        return Unit::displayName($this->unit_id);
    }

    /**
     * Nombre ACTUAL de la unidad referida (`unit_id`), o null si es principal / sin referencia. Sirve para
     * SEÑALAR si la unidad se renombró desde que se estampó `unit_name` (foto vs referencia viva) — se
     * muestra, no se resuelve, como el resto de las divergencias.
     */
    public function liveUnitName(): ?string
    {
        if ($this->unit_id === null) {
            return null;
        }
        $u = $this->unit;

        return $u ? $u->name : null;
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_id');
    }

    /**
     * Folio ESTABLE del documento (mismo para todas sus versiones): usa `root_id` — la
     * primera versión de la cadena — y cae al id propio cuando es la v1 (root_id NULL) o
     * cuando el delta de versionado no está aplicado. Así v1/v2/v3 comparten PAE-####.
     */
    public function folio(): string
    {
        $anchor = (int) ($this->root_id ?? 0) ?: (int) $this->getKey();
        return 'PAE-' . str_pad((string) $anchor, 4, '0', STR_PAD_LEFT);
    }

    /** ¿Está aplicado el delta de versionado (columna `revision`)? Degrade-safe. */
    public static function supportsVersioning(): bool
    {
        static $memo = null;
        if ($memo === null) {
            try {
                $memo = Schema::hasColumn('emergency_action_plans', 'revision');
            } catch (\Throwable $e) {
                $memo = false;
            }
        }
        return $memo;
    }

    /** Número de revisión (>=1). 1 si el delta de versionado no está aplicado. */
    public function revisionNumber(): int
    {
        $r = (int) ($this->revision ?? 1);
        return $r >= 1 ? $r : 1;
    }

    /** Versión visible del DOCUMENTO: v{revision}.0 (v1.0, v2.0, …). */
    public function versionLabel(): string
    {
        return 'v' . $this->revisionNumber() . '.0';
    }

    /** La versión ANTERIOR que este documento reemplaza (null si es la v1). FK-soft. */
    public function supersedesPlan(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    /**
     * Clave del payload congelado, con respaldo (patrón MedevacPoster::pdata): la vista NUNCA
     * toca $this->payload['x'] a pelo, para que un payload de otra versión no reviente el
     * documento que existe justamente para poder abrirse años después.
     */
    public function pdata($key, $default = null)
    {
        $p = $this->payload;
        if (! is_array($p) || ! array_key_exists($key, $p)) {
            return $default;
        }
        return $p[$key];
    }
}
