<?php

namespace App\Traits;

use App\Models\ActionItem;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Trait TracksCorrectiveActions — Motor de Acciones Correctivas (PDCA).
 *
 * Da a un reporte (Hazard / Unsafe / Injury / DailyLog):
 *   - actionItems()            relación polimórfica morphMany.
 *   - syncAutoActionItem()     crea/actualiza un ActionItem desde texto correctivo
 *                              libre, con SLA (due_date) según el nivel de riesgo.
 *   - correctiveActionDueDate() SLA: Alto/Extremo → +24 h; resto → +3 días.
 *   - assertActionItemsClosed() bloqueo de estado: lanza ValidationException si
 *                              existen acciones 'open' (impide cerrar/finalizar).
 *
 * DEFENSIVO: si la tabla action_items aún no existe (owner no aplicó el SQL),
 * todos los métodos son no-ops seguros → nada truena.
 */
trait TracksCorrectiveActions
{
    /**
     * Acciones correctivas asociadas a este reporte.
     */
    public function actionItems()
    {
        return $this->morphMany(ActionItem::class, 'actionable');
    }

    /**
     * SLA del due_date según el nivel de riesgo del reporte padre.
     * Alto/Extremo → 24 h; Bajo/Medio (o sin nivel) → 3 días.
     *
     * @return \Illuminate\Support\Carbon
     */
    public function correctiveActionDueDate()
    {
        $level = isset($this->risk_level) ? $this->risk_level : null;
        if (in_array($level, ['Alto', 'Extremo'], true)) {
            return now()->addHours(24);
        }
        return now()->addDays(3);
    }

    /**
     * Crea o actualiza un ActionItem AUTO-generado desde un texto correctivo libre.
     * Idempotente por (source='auto', source_field): re-guardar el reporte NO duplica.
     * Si el texto queda vacío, el auto-item ABIERTO se elimina (no ensucia el ciclo).
     *
     * @param  string|null $text
     * @param  string       $field    nombre del campo origen (idempotencia)
     * @param  int|null     $ownerId  (2026-07-24) responsable elegido en el form; si null, cae al
     *                                autor del reporte (created_by_id). Backward-compat: los
     *                                llamadores viejos (2 args) conservan el comportamiento previo.
     * @param  mixed        $dueDate  (2026-07-24) fecha compromiso del form; si null, cae al SLA.
     * @return \App\Models\ActionItem|null
     */
    public function syncAutoActionItem($text, $field = 'corrective_action', $ownerId = null, $dueDate = null)
    {
        if (!Schema::hasTable('action_items')) {
            return null; // owner aún no aplicó el delta → no-op seguro
        }

        $text = is_string($text) ? trim($text) : '';

        $existing = $this->actionItems()
            ->where('source', 'auto')
            ->where('source_field', $field)
            ->first();

        // Texto borrado → limpiar el auto-item si seguía abierto.
        if ($text === '') {
            if ($existing && $existing->status === ActionItem::STATUS_OPEN) {
                $existing->delete();
            }
            return null;
        }

        // Responsable: el elegido en el form, o el autor del reporte por defecto.
        $resolvedOwner = $ownerId !== null ? $ownerId : (isset($this->created_by_id) ? $this->created_by_id : null);
        // Fecha compromiso: la del form, o el SLA por nivel de riesgo.
        $resolvedDue   = $dueDate !== null ? $dueDate : $this->correctiveActionDueDate();

        if ($existing) {
            // Actualiza el texto; NO reabre si ya se cerró manualmente. Si el form trae
            // responsable/fecha explícitos, se aplican también al item existente.
            $existing->description = $text;
            if ($ownerId !== null) { $existing->owner_id = $ownerId; }
            if ($dueDate !== null) { $existing->due_date = $dueDate; }
            $existing->save();
            return $existing;
        }

        return $this->actionItems()->create([
            'description'  => $text,
            'owner_id'     => $resolvedOwner,
            'due_date'     => $resolvedDue,
            'status'       => ActionItem::STATUS_OPEN,
            'source'       => 'auto',
            'source_field' => $field,
        ]);
    }

    /**
     * Bloqueo de estado (PDCA): impide cerrar/finalizar el reporte mientras haya
     * acciones correctivas abiertas. Lanza ValidationException (se muestra vía
     * el partial componentes/_form-feedback en la vista).
     *
     * @param  string $attribute  clave del error (para el campo del form)
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function assertActionItemsClosed($attribute = 'action_status')
    {
        if (!Schema::hasTable('action_items')) {
            return;
        }

        $open = $this->actionItems()->where('status', ActionItem::STATUS_OPEN)->count();
        if ($open > 0) {
            throw ValidationException::withMessages([
                $attribute => "No se puede cerrar/finalizar: hay {$open} acción(es) correctiva(s) abierta(s) pendiente(s) de verificación.",
            ]);
        }
    }
}
