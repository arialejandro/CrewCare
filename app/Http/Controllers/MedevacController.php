<?php

namespace App\Http\Controllers;

use App\Models\MedevacPoster;
use App\Models\ScoutingReport;
use App\Support\MedevacContacts;
use App\Support\MedevacPosterBuilder;
use Illuminate\Http\Request;

/**
 * MedevacController — EMISIÓN del póster MEDEVAC (delta #46, 2026-07-31).
 *
 * Primera plantilla del motor de salida de documentos. NO es captura nueva: RENDERIZA
 * desde el SCOUTING (locación autoritativa) + los tres contactos de emergencia. El flujo:
 *   create  → formulario de emisión (datos del scouting en solo-lectura + contactos
 *             pre-llenados desde el crew, editables; revisión = consecutivo por locación)
 *   store   → CONGELA el payload (MedevacPosterBuilder), lo crea y lo SELLA (firma el safety
 *             que emite, como issued_permits) → redirige al póster
 *   show    → el póster sellado, sobre el chrome v2, listo para window.print()
 *
 * QUIÉN: sólo el safety (permission:medevac.issue en las rutas — cubre también la URL
 * directa). El verificador PÚBLICO (QR) vive en su ruta sin sesión (SealVerifier 'mdvc').
 */
class MedevacController extends Controller
{
    /**
     * Formulario de emisión para una locación (scouting). Pre-llena los contactos desde el
     * crew y muestra la revisión que tocaría. Lista los pósters ya emitidos de esta locación.
     */
    public function create(ScoutingReport $scouting)
    {
        abort_unless(MedevacPoster::supported(), 404);

        $contacts = MedevacContacts::resolve($scouting->production_id);

        // Revisión que TOCARÍA si se emitiera ahora. Se compara el contenido del scouting contra
        // el último póster: si no cambió, es el MISMO número (no un consecutivo por click). El
        // payload de vista previa lleva los campos del scouting (contactos/mapa no afectan la huella).
        $preview  = MedevacPosterBuilder::build($scouting, [], null);
        $revision = MedevacPoster::revisionFor($scouting->production_id, $scouting->location_name, $scouting->id, $preview);
        $prior    = $this->priorFor($scouting);

        return view('admin.medevac.create', compact('scouting', 'contacts', 'revision', 'prior'));
    }

    /**
     * Emite: congela el payload, crea el póster y lo sella con la firma del safety.
     */
    public function store(Request $request, ScoutingReport $scouting)
    {
        abort_unless(MedevacPoster::supported(), 404);

        $request->validate([
            'contacts'         => 'nullable|array',
            'contacts.*.name'  => 'nullable|string|max:255',
            'contacts.*.phone' => 'nullable|string|max:50',
            // Mapa OPCIONAL: captura de Google Maps (la misma que se pega hoy a mano). Se sella
            // DENTRO del payload como data-URI → offline y a prueba de manipulación.
            'map_image'        => 'nullable|image|mimes:jpeg,jpg,png,webp|max:8192',
        ]);

        // Contactos ORDENADOS por slot, tal como el emisor los confirmó (pre-llenados del crew
        // o escritos a mano). El builder los vuelve a normalizar; aquí sólo se ensamblan.
        $raw = (array) $request->input('contacts', []);
        $contacts = [];
        foreach (MedevacContacts::SLOTS as $slot) {
            $c = (array) ($raw[$slot['key']] ?? []);
            $contacts[] = [
                'key'   => $slot['key'],
                'label' => $slot['label'],
                'name'  => trim((string) ($c['name'] ?? '')),
                'phone' => trim((string) ($c['phone'] ?? '')),
            ];
        }

        // Mapa opcional → data-URI comprimido. Si viene pero no se pudo procesar (muy pesado / GD
        // ausente), se emite SIN mapa y se avisa (nunca se bloquea). Al subir uno nuevo se GUARDA
        // en el scouting para que NO se pierda al re-emitir; si no suben, el builder reutiliza el
        // guardado. El póster sigue congelando el mapa sellado en su propio payload.
        $mapUri = null; $mapDropped = false;
        if ($request->hasFile('map_image')) {
            $mapUri = \App\Support\ImageCompressor::compressToDataUri($request->file('map_image'));
            $mapDropped = ($mapUri === null);
            if ($mapUri !== null) {
                $scouting->hospital_map = $mapUri;
                $scouting->save();
            }
        }

        // Congelar el payload PRIMERO; la revisión se decide comparando su contenido de scouting
        // contra el último póster (sube sólo si cambió; si no, conserva el número anterior).
        $payload  = MedevacPosterBuilder::build($scouting, $contacts, $mapUri);
        $revision = MedevacPoster::revisionFor($scouting->production_id, $scouting->location_name, $scouting->id, $payload);

        $poster = MedevacPoster::create([
            'production_id'      => $scouting->production_id,
            'scouting_report_id' => $scouting->id,
            'revision'           => $revision,
            'location_label'     => (string) $scouting->location_name,
            'payload'            => $payload,
            'issued_by_id'       => auth()->id(),
            'issued_by_name'     => optional(auth()->user())->name,
            'issued_at'          => now(),
            'is_active'          => 1,
        ]);

        // Sellar sobre el estado CANÓNICO en BD (refresh → hash → firma): así el recompute del
        // verificador, que carga fresco, casa exactamente. Firma el safety que emite (hay un
        // firmante real, a diferencia del wrap derivado que se auto-sella como sistema).
        $poster->refresh();
        $poster->signDocument(auth()->user(), $request);

        $msg = 'Póster MEDEVAC emitido y sellado (' . $poster->folio() . ').';
        if ($mapDropped) {
            $msg .= ' La imagen del mapa era muy pesada o no se pudo procesar; se emitió sin ella.';
        }

        return redirect()->route('medevac.show', $poster->uuid)->with('success', $msg);
    }

    /**
     * El póster sellado (ligado por uuid, no por id secuencial). Se lee del payload congelado.
     */
    public function show(MedevacPoster $poster)
    {
        abort_unless(MedevacPoster::supported(), 404);

        return view('admin.medevac.show', compact('poster'));
    }

    /**
     * Pósters ya emitidos de esta locación (por nombre exacto dentro de la producción; si no hay
     * etiqueta, por scouting). Sólo para mostrar el historial en el formulario de emisión.
     */
    private function priorFor(ScoutingReport $scouting)
    {
        $q = MedevacPoster::query()->where('is_active', 1);
        $label = trim((string) $scouting->location_name);
        if ($label !== '') {
            $q->where('location_label', $label);
            if ($scouting->production_id) {
                $q->where('production_id', $scouting->production_id);
            }
        } else {
            $q->where('scouting_report_id', $scouting->id);
        }
        return $q->orderByDesc('id')->get();
    }
}
