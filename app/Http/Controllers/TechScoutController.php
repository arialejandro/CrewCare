<?php

namespace App\Http\Controllers;

use App\Models\TechScout;
use App\Models\TechScoutNote;
use App\Support\CurrentProduction;
use App\Support\CurrentUnit;
use App\Support\ImageCompressor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * TECH SCOUT — recorrido técnico del departamento de Locaciones.
 *
 * Sustituye el trabajo que hoy se hace a mano: fotografiar la locación, anotar en libreta, luego
 * descargar las fotos, pegarlas en Word y reescribir las notas. Aquí la nota se escribe JUNTO a la
 * foto, en el momento, y el documento sale en PDF.
 *
 * ── LO QUE NO ES ────────────────────────────────────────────────────────────────────────────
 * No es el Scouting H&S: aquél evalúa riesgos, se sella y tiene valor probatorio. Éste es de
 * trabajo, se edita a diario y no lleva hash. Separados a propósito.
 *
 * ── CONCURRENCIA ────────────────────────────────────────────────────────────────────────────
 * Dos scouters recorren la misma locación a la vez. NO se guarda un formulario compartido —el
 * segundo en guardar pisaría al primero, sin error y sin aviso—: el documento sólo tiene cabecera,
 * y todo lo demás son NOTAS independientes, cada una de quien la puso. Colaborar es añadir.
 */
class TechScoutController extends Controller
{
    /** Listado de recorridos de la producción en curso. */
    public function index()
    {
        $scouts = TechScout::query()
            ->when(
                Schema::hasColumn('tech_scouts', 'production_id') && CurrentProduction::id(),
                fn ($q) => $q->where('production_id', CurrentProduction::id())
            )
            ->withCount('notes')
            ->latest('id')
            ->paginate(20);

        return view('techscout.index', compact('scouts'));
    }

    public function create()
    {
        return view('techscout.create');
    }

    /**
     * Alta del recorrido: SÓLO la cabecera (dónde estamos). Deliberadamente mínimo — el trabajo
     * real son las notas, y pedir un formulario largo antes de dejar capturar es justo lo que hace
     * que la gente vuelva a la libreta.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'location_name'    => 'required|string|max:255',
            'location_address' => 'nullable|string|max:500',
            'latitude'         => 'nullable|numeric|between:-90,90',
            'longitude'        => 'nullable|numeric|between:-180,180',
        ]);

        $data['created_by_id'] = auth()->id();
        $data['production_id'] = CurrentProduction::id();
        if (Schema::hasColumn('tech_scouts', 'unit_id')) {
            $data['unit_id'] = CurrentUnit::id();
        }

        $scout = TechScout::create($data);

        return redirect()->route('techscout.show', $scout->id)
            ->with('success', 'Recorrido iniciado. Empieza a capturar notas.');
    }

    /**
     * Guarda el PANEL GENERAL del recorrido (datos de cabecera, viabilidad y acuerdos).
     *
     * Va aparte de las notas a propósito: las notas son de quien las pone y se añaden; esto es la
     * cabecera compartida, que cambia poco y la puede completar cualquiera del departamento. Si
     * ambos estuvieran en el mismo formulario, guardar una nota arrastraría toda la cabecera y dos
     * personas capturando a la vez se pisarían — que es justo lo que este módulo evita por diseño.
     */
    public function update(Request $request, $id)
    {
        $scout = TechScout::findOrFail($id);

        $data = $request->validate([
            'location_name'    => 'required|string|max:255',
            'location_address' => 'nullable|string|max:500',
            'latitude'         => 'nullable|numeric|between:-90,90',
            'longitude'        => 'nullable|numeric|between:-180,180',
            'production_type'  => 'nullable|string|max:60',
            'manager_name'     => 'nullable|string|max:255',
            'loc_setting'      => 'nullable|string|max:60',
            'shoot_time'       => 'nullable|string|max:60',
            'date_prep'        => 'nullable|date',
            'date_shoot'       => 'nullable|date',
            // Igual que en el scouting: el fin no puede ser ANTERIOR al inicio; igual sí se acepta.
            'date_shoot_end'   => 'nullable|date|after_or_equal:date_shoot',
            'date_wrap'        => 'nullable|date',
            'viability'        => 'nullable|array',
            'agreements'       => 'nullable|array',
            'hero_image'       => 'nullable|mimes:jpeg,png,jpg,gif,webp,heic,heif|heic_ok|max:12288',
        ]);

        $scout->fill(collect($data)->except(['viability', 'agreements', 'hero_image'])->all());

        // Imagen de portada del documento. Se reemplaza sólo si suben una nueva: guardar el panel
        // sin tocar el archivo NO puede borrar la que ya había.
        if ($request->hasFile('hero_image') && $request->file('hero_image')->isValid()) {
            $scout->hero_image_path = $this->storePhoto($request->file('hero_image'));
        }

        $scout->viability_checklist = $this->cleanRows($request->input('viability', []));
        $scout->agreements          = $this->cleanRows($request->input('agreements', []));
        $scout->save();

        return redirect()->route('techscout.show', $scout->id)->with('success', 'Datos del recorrido guardados.');
    }

    /**
     * Normaliza las filas de viabilidad / acuerdos: {item, detail}, sin renglones vacíos.
     *
     * Las filas se auto-agregan en pantalla (siempre hay una en blanco al final), así que llegan
     * vacías casi siempre. Guardarlas ensuciaría el documento y haría ruido en la revisión.
     */
    private function cleanRows($rows): array
    {
        $out = [];
        foreach ((array) $rows as $r) {
            $item   = trim((string) ($r['item'] ?? ''));
            $detail = trim((string) ($r['detail'] ?? ''));
            if ($item === '' && $detail === '') {
                continue;
            }
            $out[] = ['item' => mb_substr($item, 0, 255), 'detail' => mb_substr($detail, 0, 2000)];
        }

        return $out;
    }

    /** La vista de trabajo: cabecera + notas en orden de recorrido + captura de la siguiente. */
    public function show($id)
    {
        $scout = TechScout::with(['notes.author'])->findOrFail($id);

        // La etiqueta del guión se arrastra de la nota anterior: si están una mañana entera en
        // "Depa Pablo", se escribe UNA vez. Se borra a mano al cambiar de espacio.
        $lastLabel = $scout->lastStoryLabel();

        return view('techscout.show', compact('scout', 'lastLabel'));
    }

    /**
     * EL DOCUMENTO — lo que sustituye al Word.
     *
     * Hoy los scouters descargan las fotos, las pegan a mano y reescriben lo de sus libretas. Aquí
     * sale en un clic. Con `?pdf=1` se baja por Browsershot (Chrome headless) reusando ESTA MISMA
     * vista: el PDF y la pantalla no se pueden separar porque son el mismo HTML.
     */
    public function document($id)
    {
        $scout = TechScout::with(['notes.author'])->findOrFail($id);

        if (request()->boolean('pdf')) {
            $html = view('techscout.documento', compact('scout'))->render();

            return \App\Support\PdfExporter::download(
                $html,
                'TECHSCOUT-' . str_pad((string) $scout->id, 4, '0', STR_PAD_LEFT),
                [0, 0, 0, 0]   // el @page manda los márgenes
            );
        }

        return view('techscout.documento', compact('scout')
            + ['pdfUrl' => request()->fullUrlWithQuery(['pdf' => 1])]);
    }

    /**
     * Añadir una nota. Esta es la unidad de trabajo del módulo.
     *
     * La foto es opcional a propósito: a veces la nota es sobre algo que no se puede fotografiar
     * ("el vecino de al lado ensaya batería por las tardes"). Lo que no puede faltar es que haya
     * ALGO — foto o texto—; una nota vacía no dice nada y ensucia el documento.
     */
    public function storeNote(Request $request, $id)
    {
        $scout = TechScout::findOrFail($id);

        $data = $request->validate([
            'note'        => 'nullable|string|max:2000',
            'story_label' => 'nullable|string|max:120',
            'photo'       => 'nullable|mimes:jpeg,png,jpg,gif,webp,heic,heif|heic_ok|max:12288',
        ]);

        if (blank($data['note'] ?? null) && ! $request->hasFile('photo')) {
            // 🪤 EL RECHAZO NO PUEDE SER MUDO. El 2026-09-16 el owner no pudo agregar NINGUNA nota
            // y sólo veía este mensaje; sin rastro de qué llegó al servidor, diagnosticarlo fue
            // adivinar. Una validación que rechaza tiene que dejar dicho QUÉ recibió, o el
            // siguiente que la tropiece vuelve a empezar de cero.
            \Illuminate\Support\Facades\Log::info('tech scout: nota rechazada por vacía', [
                'scout'      => $scout->id,
                'campos'     => array_keys($request->except(['_token', 'photo'])),
                'note_len'   => strlen((string) $request->input('note')),
                'tiene_file' => $request->hasFile('photo'),
                'files'      => array_keys($request->allFiles()),
                'tipo'       => $request->header('Content-Type'),
            ]);

            return back()->withInput()->withErrors([
                'note' => 'Escribe la nota o adjunta una foto — una nota vacía no dice nada.',
            ]);
        }

        $path = null;
        if ($request->hasFile('photo') && $request->file('photo')->isValid()) {
            $path = $this->storePhoto($request->file('photo'));
        }

        TechScoutNote::create([
            'tech_scout_id' => $scout->id,
            'photo_path'    => $path,
            'note'          => $data['note'] ?? null,
            'story_label'   => trim((string) ($data['story_label'] ?? '')) ?: null,
            'created_by_id' => auth()->id(),
        ]);

        return redirect()->route('techscout.show', $scout->id)->with('success', 'Nota agregada.');
    }

    /**
     * Editar una nota. SÓLO su autor.
     *
     * 🪤 Y deja marca. El owner usa este documento para zanjar discusiones —«si no está en las
     * notas, no se pidió»—, y ese argumento se voltea si una nota se puede reescribir en silencio
     * después del recorrido. No se sella (sería pesado para algo que se edita a diario), pero
     * editar estampa `edited_at` y la vista lo muestra.
     */
    public function updateNote(Request $request, $id, $noteId)
    {
        $note = TechScoutNote::where('tech_scout_id', $id)->findOrFail($noteId);

        abort_unless((int) $note->created_by_id === (int) auth()->id(), 403,
            'Cada quien edita sus propias notas.');

        $data = $request->validate([
            'note'        => 'nullable|string|max:2000',
            'story_label' => 'nullable|string|max:120',
        ]);

        $cambio = trim((string) ($data['note'] ?? '')) !== trim((string) $note->note)
            || trim((string) ($data['story_label'] ?? '')) !== trim((string) $note->story_label);

        $note->note        = $data['note'] ?? null;
        $note->story_label = trim((string) ($data['story_label'] ?? '')) ?: null;

        // Sólo si cambió el CONTENIDO: reguardar lo mismo no debería marcarla como editada.
        if ($cambio) {
            $note->edited_at = now();
        }
        $note->save();

        return redirect()->route('techscout.show', $id)->with('success', 'Nota actualizada.');
    }

    /** Misma tubería de imagen que el scouting: normaliza HEIC, comprime y guarda en 'public'. */
    private function storePhoto($image): string
    {
        $image    = ImageCompressor::normalizeForUpload($image);
        $filename = time() . '_techscout_' . uniqid() . '.' . ImageCompressor::safeExtensionOrBin($image);

        return Storage::url($image->storeAs('scouting_images', $filename, 'public'));
    }
}
