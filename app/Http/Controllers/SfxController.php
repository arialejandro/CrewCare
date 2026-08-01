<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use App\Models\SfxEvent;
use App\Models\Consumable;
use App\Support\Features;
use App\Support\DsrHub;

/**
 * SfxController — Panel TOGGLE de efectos especiales en vivo (Pilar 3, flag 'sds_sfx').
 *
 * start()/stop() disparan y detienen un efecto y, en cada transición, INYECTAN un
 * daily_log al DSR del día vía DsrHub (Pilar 2). DsrHub de-duplica por el modelo fuente
 * ($sfx): por eso stop() vuelve a inyectar con el MISMO $sfx (y el DSR del día en que
 * inició) para ACTUALIZAR ese mismo log concatenando el fin, sin perder la traza de
 * inicio. Guardas: feature apagada → 404; tabla ausente → redirect suave.
 */
class SfxController extends Controller
{
    /**
     * @return \Illuminate\Http\RedirectResponse|null
     */
    protected function guard()
    {
        if (!Features::enabled('sds_sfx')) {
            abort(404);
        }
        if (!Schema::hasTable('sfx_events')) {
            return redirect()->route('home')
                ->with('error', 'El módulo de efectos especiales (SFX) aún no está disponible (falta la migración de base de datos).');
        }
        return null;
    }

    public function index()
    {
        if ($resp = $this->guard()) {
            return $resp;
        }

        // Efectos EN CURSO (indicadores "● Activo") y una cola corta de recientes ya cerrados.
        $active = SfxEvent::active()->with('consumable')->latest('started_at')->get();
        $recent = SfxEvent::where('status', 'ended')->with('consumable')->latest('ended_at')->limit(15)->get();

        // Consumibles activos para el <select> del form de inicio (defensivo: tabla puede faltar).
        $consumables = Schema::hasTable('consumables')
            ? Consumable::active()->orderBy('type')->orderBy('name')->get()
            : collect();

        return view('admin.sfx.index', compact('active', 'recent', 'consumables'));
    }

    public function start(Request $request)
    {
        if ($resp = $this->guard()) {
            return $resp;
        }

        $data = $request->validate([
            'consumable_id'   => 'nullable|integer',
            'effect_label'    => 'required|string|max:255',
            'safety_criteria' => 'nullable|string',
            'production_ref'  => 'nullable|string|max:120',
        ]);

        $sfx = SfxEvent::create([
            'consumable_id'   => $data['consumable_id'] ?? null,
            'production_ref'  => $data['production_ref'] ?? null,
            'effect_label'    => $data['effect_label'],
            'safety_criteria' => $data['safety_criteria'] ?? null,
            'status'          => 'active',
            'started_by_id'   => auth()->id(),
            'started_at'      => now(),
        ]);

        // Inyección al DSR del día (Pilar 2). DsrHub es defensivo: nunca lanza excepción.
        $desc = '▶ Inicio SFX: ' . $sfx->effect_label
              . ($sfx->safety_criteria ? ' — Criterio: ' . $sfx->safety_criteria : '');

        $log = DsrHub::inject($sfx, now()->toDateString(), [
            'log_time'    => now()->format('H:i'),
            'description' => $desc,
        ], auth()->id());

        if ($log) {
            $sfx->daily_report_id = $log->daily_report_id;
            $sfx->save();
        }

        return redirect()->route('sfx.index')->with('success', 'Efecto SFX iniciado y registrado en el DSR.');
    }

    public function stop(Request $request, $id)
    {
        if ($resp = $this->guard()) {
            return $resp;
        }

        $sfx = SfxEvent::findOrFail($id);
        $sfx->ended_at = now();
        $sfx->status   = 'ended';
        $sfx->save();

        // Resolvemos el DSR del día en que INICIÓ el efecto (por si cruzó medianoche) para
        // que DsrHub actualice el MISMO log de esta fuente concatenando el fin al inicio.
        $startDate = $sfx->started_at ? $sfx->started_at->toDateString() : now()->toDateString();
        $startTime = $sfx->started_at ? $sfx->started_at->format('H:i') : now()->format('H:i');

        $desc = '▶ Inicio SFX: ' . $sfx->effect_label
              . ($sfx->safety_criteria ? ' — Criterio: ' . $sfx->safety_criteria : '')
              . ' | ⏹ Fin: ' . now()->format('H:i');

        DsrHub::inject($sfx, $startDate, [
            'log_time'    => $startTime,
            'description' => $desc,
        ], auth()->id());

        return redirect()->route('sfx.index')->with('success', 'Efecto SFX detenido.');
    }
}
