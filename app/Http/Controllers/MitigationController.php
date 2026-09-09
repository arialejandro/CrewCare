<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Schema;
use App\Models\ActionItem;
use App\Support\Features;

/**
 * MitigationController — Pilar 1b: cierre de acciones correctivas vía Magic Link.
 *
 * Página PÚBLICA (sin auth). La ruta impone el middleware 'signed', así que la
 * seguridad viene de la firma + expiración de la URL (7 días), no de sesión.
 * El responsable llega por wa.me, ve la acción y sube la foto de mitigación.
 *
 * NO cierra el ActionItem: el cierre (verificación) se gestiona dentro del sistema
 * por un usuario autenticado. Aquí sólo se recibe la evidencia.
 *
 * Feature-gated: si 'magic_links' está apagada, ambos endpoints devuelven 404.
 */
class MitigationController extends Controller
{
    /**
     * Muestra la página pública con la acción correctiva y el form de subida.
     * El form debe postear a una URL FIRMADA de mitigation.store para que el POST
     * pase el middleware 'signed'; se genera aquí y se pasa a la vista como $storeUrl.
     */
    public function show(Request $request, $action)
    {
        if (! Features::enabled('magic_links')) {
            abort(404);
        }

        $item = ActionItem::findOrFail($action);

        // (2026-07-24) El enlace muere cuando el hallazgo se CIERRA. Mensaje claro, no un error:
        // del otro lado hay alguien en set que hizo lo que se le pidió y merece saber por qué ya
        // no aplica, no un 403 en blanco.
        if (! $item->acceptsMitigation()) {
            return response()->view('public.mitigation', ['item' => $item, 'closed' => true], 410);
        }

        // URL firmada y expirable para el POST (misma vigencia que el GET).
        $storeUrl = URL::temporarySignedRoute(
            'mitigation.store',
            now()->addDays(7),
            ['action' => $item->id]
        );

        return view('public.mitigation', [
            'item'     => $item,
            'storeUrl' => $storeUrl,
            'badges'   => $item->regulatoryBadges(),
        ]);
    }

    /**
     * Recibe y guarda la foto de mitigación. SIEMPRE valida; nunca confía en el input.
     * La firma+expiración de la ruta ya la impuso el middleware 'signed'.
     */
    public function store(Request $request, $action)
    {
        if (! Features::enabled('magic_links')) {
            abort(404);
        }

        $item = ActionItem::findOrFail($action);

        // Mismo candado que show(), aquí como defensa en profundidad: el POST no depende de que
        // la UI haya ocultado el formulario (alguien pudo tener la página abierta desde antes
        // del cierre).
        if (! $item->acceptsMitigation()) {
            return response()->view('public.mitigation', ['item' => $item, 'closed' => true], 410);
        }

        $request->validate([
            'mitigation_image' => 'required|mimes:jpeg,png,jpg,heic,heif|heic_ok|max:12288',
            'mitigation_note'  => 'nullable|string|max:1000',
        ]);

        // Guarda la imagen en public (patrón del proyecto: mover a public/uploads/…).
        // (2026-07-21) Ahora pasa por ImageCompressor: esta foto dejó de ser sólo un
        // registro interno — el acta del DSR la imprime como EVIDENCIA DE LA SOLUCIÓN,
        // así que sin comprimir reintroduciría en el documento el mismo peso que este
        // paso vino a quitar. La sube alguien desde su celular por un magic link, sin
        // sesión: es justo el caso donde llega una foto de 4000 px y varios MB.
        $relative = \App\Support\ImageCompressor::storePublic(
            $request->file('mitigation_image'),
            'uploads/mitigations'
        );
        if ($relative === null) {
            return back()->with('error', 'No se pudo guardar la foto. Inténtalo de nuevo.');
        }

        // Set defensivo: cada columna sólo si existe (owner pudo no aplicar el SQL).
        if (Schema::hasColumn('action_items', 'mitigation_image_path')) {
            $item->mitigation_image_path = $relative;
        }
        if (Schema::hasColumn('action_items', 'mitigation_note')) {
            $item->mitigation_note = $request->input('mitigation_note');
        }
        if (Schema::hasColumn('action_items', 'mitigation_uploaded_at')) {
            $item->mitigation_uploaded_at = now();
        }
        $item->save();

        // NO se cierra el action_item: el cierre se gestiona dentro del sistema.
        return view('public.mitigation', [
            'item' => $item,
            'done' => true,
        ]);
    }
}
