<?php

namespace App\Http\Controllers;

use App\Models\ActionItem;
use Illuminate\Support\Facades\Schema;

/**
 * (2026-07-09) Cierre del ciclo PDCA: marcar acciones correctivas como cerradas
 * (con verificador + fecha) o reabrirlas. Es lo que permite volver a Cerrar/Finalizar
 * el reporte padre (assertActionItemsClosed lo bloquea mientras haya acciones abiertas).
 * Protegido por permiso hazards.manage (igual que updateStatus de Hazard/Cond.Insegura).
 */
class ActionItemController extends Controller
{
    /**
     * Cierra una acción correctiva (verified_by + closed_at).
     */
    public function close($id)
    {
        if (!Schema::hasTable('action_items')) {
            return back()->with('error', 'El módulo de acciones correctivas aún no está disponible.');
        }

        $item = ActionItem::findOrFail($id);
        $item->status         = ActionItem::STATUS_CLOSED;
        $item->verified_by_id = auth()->id();
        $item->closed_at      = now();
        $item->save();

        // (2026-07-26) Si la acción cuelga de un acta de inspección con PARO, cerrarla LEVANTA
        // el paro (con autor y hora, y re-sella el acta). "El paro se levanta al cerrar el item."
        $actionable = $item->actionable;
        if ($actionable instanceof \App\Models\ToolInspection && $actionable->isBlocked()) {
            $actionable->unblock(auth()->user());
            return back()->with('success', 'Acción cerrada y PARO levantado: el acta quedó re-sellada.');
        }

        return back()->with('success', 'Acción correctiva marcada como cerrada.');
    }

    /**
     * Reabre una acción correctiva (por si se cerró por error).
     */
    public function reopen($id)
    {
        if (!Schema::hasTable('action_items')) {
            return back()->with('error', 'El módulo de acciones correctivas aún no está disponible.');
        }

        $item = ActionItem::findOrFail($id);
        $item->status         = ActionItem::STATUS_OPEN;
        $item->verified_by_id = null;
        $item->closed_at      = null;
        $item->save();

        return back()->with('success', 'Acción correctiva reabierta.');
    }
}
