<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Motor de Acciones Correctivas (PDCA) — item polimórfico de acción/corrección.
 *
 * Pertenece (morphTo) a cualquier reporte de seguridad: Acción Insegura (Hazard),
 * Condición Insegura, Accidente (Injury) o log del Daily. Cada texto correctivo
 * libre (suggestions_corrective_action / corrective_action / preventions /
 * action_taken) genera automáticamente un ActionItem con SLA (due_date) según el
 * nivel de riesgo del reporte padre. El ciclo se cierra marcando status='closed'
 * (con verified_by_id / closed_at). Un reporte NO puede pasar a Cerrado/Final si
 * tiene ActionItems 'open' (ver App\Traits\TracksCorrectiveActions).
 *
 * Tabla: action_items (database/owner-apply/2026-07-09-structural-upgrades.sql).
 */
class ActionItem extends Model
{
    use \App\Traits\HasMagicMitigation;

    const STATUS_OPEN        = 'open';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_CLOSED      = 'closed';

    protected $fillable = [
        'actionable_type',
        'actionable_id',
        'description',
        'owner_id',
        'due_date',
        'status',
        'verified_by_id',
        'closed_at',
        'source',        // 'auto' (generado desde texto correctivo) | 'manual'
        'source_field',  // nombre del campo origen (idempotencia del auto-item)
        // (2026-07-13) Pilar 1b (Magic Links): canal del responsable + foto de mitigación.
        'responsible_name',
        'responsible_phone',
        'mitigation_image_path',
        'mitigation_note',
        'mitigation_uploaded_at',
    ];

    protected $casts = [
        'due_date'               => 'datetime',
        'closed_at'              => 'datetime',
        'mitigation_uploaded_at' => 'datetime',
    ];

    /**
     * El reporte al que pertenece esta acción correctiva (Hazard/Unsafe/Injury/DailyLog).
     */
    public function actionable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Responsable de ejecutar la acción (nullable: hoy default = autor del reporte).
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Quién verificó el cierre de la acción.
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_id');
    }

    /**
     * ¿Está vencida? (abierta/en proceso y con due_date en el pasado).
     */
    public function isOverdue(): bool
    {
        return $this->status !== self::STATUS_CLOSED
            && $this->due_date !== null
            && $this->due_date->isPast();
    }

    /**
     * (2026-07-24) ¿El magic link público sigue vivo?
     *
     * DECISIÓN: el enlace muere cuando el HALLAZGO SE CIERRA, no al primer upload. Single-use
     * sería duro — si la foto sale mala o incompleta, el responsable necesita poder corregirla
     * sin pedir un enlace nuevo. Pero que siga abierto días después de resuelto tampoco tiene
     * sentido: la caducidad de 7 días de la firma es un techo, no el criterio real. El criterio
     * es el estado del hallazgo.
     */
    public function acceptsMitigation(): bool
    {
        return $this->status !== self::STATUS_CLOSED;
    }

    /**
     * (2026-07-24) MARCOS NORMATIVOS del reporte padre, para el enlace público.
     *
     * POR QUÉ: la página pública dejó de mostrar `description` (texto libre que puede nombrar al
     * involucrado o describir el hecho; y el WhatsApp se reenvía). En su lugar muestra QUÉ HAY QUE
     * HACER + con qué norma está respaldado. Las siglas dan legitimidad a la petición —
     * "NOM-004-STPS-1999" le dice al responsable que esto no es un mensaje suelto— sin exponer nada:
     * un marco normativo no identifica a nadie.
     *
     * Devuelve sólo las SIGLAS del marco (STPS, OSHA, CSATF…), nunca el texto de la norma.
     * Degradación: si el padre no existe, no tiene evento o el evento no tiene normas → [].
     *
     * @return array<string>
     */
    public function regulatoryBadges(): array
    {
        try {
            $parent = $this->actionable;
            if (! $parent || ! method_exists($parent, 'hazardEvent')) {
                return [];
            }
            $evento = $parent->hazardEvent;
            if (! $evento || ! method_exists($evento, 'standards')) {
                return [];
            }
            $badges = $evento->standards->pluck('regulation_badge')
                ->map(function ($b) { return strtoupper(trim((string) $b)); })
                ->filter(function ($b) {
                    // 'NA' es el DEFAULT de la columna, no un marco: fuera (mismo criterio que
                    // _standards-chips, donde pintaba un chip invisible).
                    return $b !== '' && $b !== 'NA';
                })
                ->unique()->values()->all();

            return $badges;
        } catch (\Throwable $e) {
            // La página pública nunca debe reventar por un dato de adorno.
            return [];
        }
    }
}
