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
 * ── EMISIONES INDEPENDIENTES (decisión del owner) ───────────────────────────────
 * Cada emisión es un documento NUEVO y SIN RELACIÓN con los anteriores: sin cadena
 * de versiones, sin vínculo "sustituye a". Por eso NO implementa sealRetirement():
 * el verificador lo lee en dos estados (vigente / alterado), nunca "retirado".
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
        'uuid', 'production_id',
        'shoot_day', 'plan_date', 'unit_name', 'plan_label',
        'payload',
        'issued_by_id', 'issued_by_name', 'issued_at', 'is_active',
    ];

    protected $casts = [
        'payload'    => 'array',
        'plan_date'  => 'date',
        'issued_at'  => 'datetime',
        'is_active'  => 'boolean',
        'shoot_day'  => 'integer',
    ];

    /**
     * FUERA del hash: `is_active` es estado de listado posterior a la emisión, no
     * contenido sellado. Apagar un PAE (is_active=0) no lo marca ALTERADO.
     */
    protected $signatureExcludes = ['is_active'];

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

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_id');
    }

    /** Folio estable para el verificador público y la cadena CFDI. */
    public function folio(): string
    {
        return 'PAE-' . str_pad((string) $this->getKey(), 4, '0', STR_PAD_LEFT);
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
