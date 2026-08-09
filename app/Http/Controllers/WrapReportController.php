<?php

namespace App\Http\Controllers;

use App\Models\WrapReport;
use App\Support\CurrentProduction;
use App\Support\ImageCompressor;
use App\Support\WrapReportBuilder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * WrapReportController — REPORTE FINAL DE WRAP (2026-07-24).
 *
 * Cuatro acciones y una idea: el wrap se PREVISUALIZA vivo mientras se decide, y se EMITE una vez.
 * Emitir = congelar el cálculo en `wrap_reports.payload` y sellarlo. Después ya no se recalcula.
 *
 * ── POR QUÉ HAY BORRADOR Y EMISIÓN, Y NO SÓLO UNA PANTALLA ─────────────────────────────────
 * El wrap se entrega a un tercero. Si fuera una vista viva, cada visita daría cifras distintas
 * conforme alguien corrigiera un DSR, y nadie —ni la casa productora ni el estudio— podría citar
 * "el reporte" porque no habría tal cosa. El borrador sirve para revisar antes de comprometerse;
 * la emisión produce EL documento, con su folio, su hash y su QR.
 *
 * El borrador se pinta con la MISMA vista, marcado como tal y sin sello. Eso es deliberado: lo que
 * se revisa tiene que ser exactamente lo que se va a emitir, no una aproximación.
 *
 * ── SÓLO LEE LOS 5 REPORTES ────────────────────────────────────────────────────────────────
 * Ni una escritura fuera de `wrap_reports` y su firma. Los DSR, scoutings, gemelos, accidentes y
 * consultas se leen y se sueltan.
 */
class WrapReportController extends Controller
{
    /** Motivo del sello. Queda en `role_at_signing` y la vista lo lee para explicarlo. */
    const MOTIVO_SELLO = 'sistema:wrap';

    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Listado de documentos de cierre emitidos para la producción vigente.
     */
    public function index()
    {
        abort_unless(WrapReport::supported(), 404);

        $produccion = CurrentProduction::get();
        $reportes = WrapReport::when($produccion, function ($q) use ($produccion) {
                $q->where('production_id', $produccion->id);
            })
            ->where('kind', WrapReport::KIND_FINAL)
            ->with('addendums')
            ->orderByDesc('id')->get();

        return view('admin.wrap.index', [
            'produccion' => $produccion,
            'reportes'   => $reportes,
        ]);
    }

    /**
     * BORRADOR: calcula en vivo y pinta el documento sin sellar.
     *
     * Acepta ?desde= y ?hasta= para que el usuario vea el efecto del rango ANTES de emitir. Sin
     * ellos, el rango lo deduce el builder desde `productions`.
     */
    public function preview(Request $request)
    {
        abort_unless(WrapReport::supported(), 404);

        $produccion = CurrentProduction::get();
        abort_unless($produccion, 404, 'No hay producción vigente que cerrar.');

        // QUIÉN: generar el borrador ya es parte del proceso del wrap y queda para el safety. El
        // borrador NO congela nada, así que no lleva guarda de fecha — el safety puede ver la forma
        // del documento en cualquier momento. La fecha sólo bloquea la EMISIÓN (el botón de abajo).
        abort_unless(WrapReport::issuableBy(auth()->user(), $produccion), 403,
            'Sólo el safety manager o el safety asignado a la producción puede generar el reporte de wrap.');

        list($desde, $hasta) = $this->rango($request, $produccion);

        $payload = WrapReportBuilder::build($produccion, $desde, $hasta);
        $periodo = $this->periodoDe($payload, $desde, $hasta);

        // Modelo EN MEMORIA, jamás guardado: la vista es la misma para borrador y documento, así
        // que necesita un objeto con la misma forma. Sin `exists`, verifyLatestSignature() no
        // encuentra firma y el documento se pinta sin sello, que es exactamente lo correcto para
        // un borrador. No hay ruta por la que este objeto llegue a save().
        $borrador = new WrapReport([
            'production_id' => $produccion->id,
            'kind'          => WrapReport::KIND_FINAL,
            'period_start'  => $periodo[0],
            'period_end'    => $periodo[1],
            'payload'       => $payload,
        ]);

        return view('admin.wrap.show', [
            'wrap'       => $borrador,
            'payload'    => $payload,
            'produccion' => $produccion,
            'borrador'   => true,
            // Motivo por el que el botón "Emitir" NO debe aparecer todavía (null = ventana abierta).
            // Se calcula contra end_date de la producción, que es lo que store() va a exigir.
            'emitBloqueado' => WrapReport::windowBlockedReason($produccion->end_date),
        ]);
    }

    /**
     * EMITE el documento de cierre: congela el cálculo y lo sella.
     */
    public function store(Request $request)
    {
        abort_unless(WrapReport::supported(), 404);

        $produccion = CurrentProduction::get();
        abort_unless($produccion, 404, 'No hay producción vigente que cerrar.');

        // QUIÉN: sólo el safety manager o el safety asignado a la producción emite. Server-side, no
        // sólo el botón oculto: un POST directo también choca aquí.
        abort_unless(WrapReport::issuableBy(auth()->user(), $produccion), 403,
            'Sólo el safety manager o el safety asignado a la producción puede emitir el reporte de wrap.');

        // CUÁNDO: no antes de la fecha de finalización. ABSOLUTO — ningún rol lo salta. Es la
        // protección contra el clic accidental que congelaría la producción antes de tiempo.
        $bloqueo = WrapReport::windowBlockedReason($produccion->end_date);
        if ($bloqueo !== null) {
            return redirect()->route('wrap.index')->with('error', $bloqueo);
        }

        list($desde, $hasta) = $this->rango($request, $produccion);

        $payload = WrapReportBuilder::build($produccion, $desde, $hasta);
        $periodo = $this->periodoDe($payload, $desde, $hasta);

        // Elecciones del editor (apartados a omitir + notas por apartado + CORRECCIONES de texto).
        // Van DENTRO del payload a propósito: son parte del documento que se entrega, así que las
        // protege el mismo sello. El borrador NO las lleva (se editan en vivo); aquí se congelan.
        $editor = $this->editorChoices($request);
        $editor['overrides'] = $this->editorOverrides($request);
        $editor['images'] = $this->editorImages($request);
        $payload['editor'] = $editor;

        $wrap = WrapReport::create([
            'production_id' => $produccion->id,
            'kind'          => WrapReport::KIND_FINAL,
            'period_start'  => $periodo[0],
            'period_end'    => $periodo[1],
            'payload'       => $payload,
            'issued_at'     => Carbon::now(),
            'issued_by_id'  => auth()->id(),
        ]);

        $this->sellar($wrap);

        return redirect()->route('wrap.show', $wrap->id)
            ->with('status', 'Reporte de wrap ' . $wrap->folio() . ' emitido y sellado.');
    }

    /**
     * ANEXO por regrabaciones posteriores (SB 132).
     *
     * NO reescribe el documento original: crea uno nuevo que lo referencia y cubre SÓLO los días
     * de la regrabación. El original conserva su folio, su hash y su QR — reescribirlo rompería su
     * sello, que es la única prueba de que existió antes tal como se entregó.
     */
    public function storeAddendum(Request $request, $id)
    {
        abort_unless(WrapReport::supported(), 404);

        $padre = WrapReport::where('kind', WrapReport::KIND_FINAL)->findOrFail($id);

        $produccion = $padre->production ?: CurrentProduction::get();

        // QUIÉN: mismo candado que el cierre. El anexo también se sella y va a un tercero.
        abort_unless(WrapReport::issuableBy(auth()->user(), $produccion), 403,
            'Sólo el safety manager o el safety asignado a la producción puede emitir un anexo de wrap.');

        $datos = $request->validate([
            'desde'  => 'required|date',
            'hasta'  => 'required|date|after_or_equal:desde',
            'reason' => 'nullable|string|max:255',
        ], [], [
            'desde' => 'fecha inicial de la regrabación',
            'hasta' => 'fecha final de la regrabación',
        ]);

        // CUÁNDO: un anexo documenta una regrabación YA OCURRIDA. La ventana se mide contra su
        // fecha FINAL (la "finalización" del anexo), no contra la de la producción original —esa ya
        // pasó por definición—. No se puede sellar un reshoot que todavía no termina.
        $bloqueo = WrapReport::windowBlockedReason($datos['hasta']);
        if ($bloqueo !== null) {
            return redirect()->route('wrap.show', $padre->id)->with('error', $bloqueo);
        }

        $payload = WrapReportBuilder::build($produccion, $datos['desde'], $datos['hasta']);
        // El folio del documento al que complementa viaja DENTRO del payload, no sólo en la FK: el
        // anexo se imprime y se entrega en papel, y ahí no hay base de datos que consultar para
        // saber a qué cierre pertenece.
        $payload['anexo_de'] = ['folio' => $padre->folio(), 'uuid' => (string) $padre->uuid];

        $anexo = WrapReport::create([
            'production_id' => $padre->production_id,
            'kind'          => WrapReport::KIND_ADDENDUM,
            'parent_id'     => $padre->id,
            'period_start'  => $datos['desde'],
            'period_end'    => $datos['hasta'],
            'reason'        => isset($datos['reason']) ? $datos['reason'] : null,
            'payload'       => $payload,
            'issued_at'     => Carbon::now(),
            'issued_by_id'  => auth()->id(),
        ]);

        $this->sellar($anexo);

        return redirect()->route('wrap.show', $anexo->id)
            ->with('status', 'Anexo ' . $anexo->folio() . ' emitido y sellado. El documento ' . $padre->folio() . ' no se modificó.');
    }

    /**
     * Muestra un documento emitido, tal como se congeló.
     */
    public function show($id)
    {
        abort_unless(WrapReport::supported(), 404);

        $wrap = WrapReport::with(['production', 'parent', 'addendums'])->findOrFail($id);

        return view('admin.wrap.show', [
            'wrap'       => $wrap,
            'payload'    => is_array($wrap->payload) ? $wrap->payload : [],
            'produccion' => $wrap->production,
            'borrador'   => false,
        ]);
    }

    // -------------------------------------------------------------------------------------

    /**
     * Sella el documento recién creado.
     *
     * ⚠ REFRESH ANTES DE SELLAR. El hash se calcula sobre attributesToArray(), y tras create() los
     * atributos en memoria no coinciden con los de la base (el JSON del payload viene ya
     * serializado por el driver, los timestamps traen la precisión del motor). Sellar sin releer
     * produce un hash que NUNCA vuelve a coincidir y el documento nace acusándose de ALTERADO.
     * Es el mismo tropiezo que ya se corrigió en el Scouting y en los gemelos.
     *
     * Se sella COMO SISTEMA (user_id NULL) a propósito: ver el comentario de App\Models\WrapReport.
     */
    private function sellar(WrapReport $wrap)
    {
        $wrap->refresh();
        $wrap->signDocumentAsSystem(self::MOTIVO_SELLO);
    }

    /**
     * Rango cubierto. Sin parámetros, lo deduce el builder desde `productions`; se devuelve null
     * para no fijar aquí una fecha que el builder sabe calcular mejor (incluye la prep).
     *
     * @return array [desde|null, hasta|null]
     */
    private function rango(Request $request, $produccion)
    {
        $desde = $request->input('desde');
        $hasta = $request->input('hasta');

        $desde = $desde ? Carbon::parse($desde)->toDateString() : null;
        $hasta = $hasta ? Carbon::parse($hasta)->toDateString() : null;

        // Rango invertido: se ignora en vez de producir un reporte vacío que se leería como
        // "no pasó nada en toda la producción".
        if ($desde && $hasta && $desde > $hasta) {
            return [null, null];
        }

        return [$desde, $hasta];
    }

    /**
     * Periodo REAL que quedó cubierto, leído del payload.
     *
     * Cuando no se pide rango, el builder lo deduce —y lo abre hacia atrás hasta la prep, que
     * empieza antes de `productions.start_date`. Guardar aquí los nulos que entraron dejaría la
     * fila diciendo "sin periodo" mientras el documento impreso declara uno: dos verdades para el
     * mismo papel. El payload manda.
     *
     * @return array [desde, hasta]
     */
    private function periodoDe(array $payload, $desde, $hasta)
    {
        $p = isset($payload['meta']['periodo']) ? $payload['meta']['periodo'] : [];
        return [
            isset($p['desde']) ? $p['desde'] : $desde,
            isset($p['hasta']) ? $p['hasta'] : $hasta,
        ];
    }

    /**
     * Elecciones del editor en el borrador, saneadas y listas para congelar en el payload.
     *
     * `include[]` trae los apartados MARCADOS (los checkbox no marcados no viajan), así que lo
     * omitido = los apartados omitibles que NO están en include. `note[sN]` trae la nota por
     * apartado. Se SANEA (no se valida-para-fallar): la emisión de un wrap no debe reventar por un
     * campo suelto; lo que no reconoce, lo ignora.
     *
     * Apartados: s1 (identificación) y el sello son FIJOS — no se pueden omitir. s2..s8 sí.
     *
     * @return array{omit: array<string>, notes: array<string,string>}
     */
    private function editorChoices(Request $request)
    {
        $omitibles = ['s2', 's3', 's4', 's5', 's6', 's7', 's8'];
        $todos     = array_merge(['s1'], $omitibles);

        $incluir = array_values(array_intersect((array) $request->input('include', []), $omitibles));
        $omit    = array_values(array_diff($omitibles, $incluir));

        $notasIn = (array) $request->input('note', []);
        $notes = [];
        foreach ($todos as $k) {
            $t = isset($notasIn[$k]) ? trim((string) $notasIn[$k]) : '';
            if ($t !== '') {
                $notes[$k] = mb_substr($t, 0, 500);
            }
        }

        return ['omit' => $omit, 'notes' => $notes];
    }

    /**
     * Correcciones NARRATIVAS del editor (arreglar un texto que la app redactó mal o impreciso).
     *
     * Llegan como un JSON en `editor_overrides`, que el JS del borrador arma leyendo los bloques
     * `contenteditable` (cada uno con su `data-edit="clave"`). Se SANEA fuerte:
     *   · sólo TEXTO PLANO (strip_tags) — nunca HTML, para que no se pueda inyectar marcado al doc;
     *   · clave limitada a un patrón conocido `s1..s8[.…]` (identifica el campo narrativo);
     *   · tope de largo por campo.
     * Los CONTEOS/estadísticas NO se editan aquí — sólo la narrativa (recomendaciones, notas,
     * descripciones). La vista aplica el override congelado o, si no hay, el valor automático.
     *
     * @return array<string,string>
     */
    private function editorOverrides(Request $request)
    {
        $raw = $request->input('editor_overrides');
        if (! is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $key => $val) {
            if (! is_string($key) || ! preg_match('/^s[1-8][a-z0-9._-]*$/i', $key)) {
                continue;
            }
            $t = trim(strip_tags((string) $val));
            if ($t !== '') {
                $out[$key] = mb_substr($t, 0, 2000);
            }
        }
        return $out;
    }

    /**
     * IMÁGENES del documento, subidas en el borrador y CONGELADAS al emitir: una imagen principal
     * (fondo del encabezado) y varias adicionales (evidencia).
     *
     * Van DENTRO del payload —igual que las notas y las correcciones narrativas— así que el MISMO
     * sello las cubre: cambiar la imagen de un wrap ya sellado lo marca ALTERADO, que es justo lo
     * que se quiere para evidencia. No necesitan $signatureExcludes: son contenido legítimo del doc.
     *
     * CONVENCIÓN IDÉNTICA a los demás reportes con foto (injury/scouting/ambulance): ImageCompressor
     * (GD, reduce el lado mayor y re-codifica; HEIC→JPEG si el servidor puede) al disco 'public', y
     * se guarda la ruta RAÍZ-RELATIVA (/storage/...) vía Storage::url() — NUNCA asset()/url(), que
     * fuera de una petición caen a APP_URL y romperían el <img> de un documento standalone.
     *
     * Se VALIDA (rebota al borrador si un archivo no es imagen o pesa de más) y se SANEA: lo que
     * ImageCompressor no pudo guardar (HEIC sin soporte, contenido no-imagen) se descarta en
     * silencio para no reventar la emisión por un archivo suelto. Sin imágenes → main null, extra [].
     *
     * @return array{main: string|null, extra: array<string>}
     */
    private function editorImages(Request $request)
    {
        // Mismo juego de reglas que injury/ambulance (mimes cubre jpg/png/webp + HEIC de iPhone; la
        // regla 'heic_ok' rechaza el HEIC sólo si el servidor no puede convertirlo). 8 MB por archivo.
        $regla = 'nullable|mimes:jpeg,png,jpg,webp,heic,heif|heic_ok|max:8192';
        $request->validate([
            'main_image'     => $regla,
            'extra_images.*' => $regla,
        ], [], [
            'main_image'     => 'imagen principal',
            'extra_images.*' => 'imagen adicional',
        ]);

        $out = ['main' => null, 'extra' => []];

        if ($request->hasFile('main_image')) {
            $rel = ImageCompressor::store($request->file('main_image'), 'wrap_images');
            if ($rel !== null) {
                $out['main'] = Storage::url($rel);
            }
        }

        if ($request->hasFile('extra_images')) {
            $tope = 8;   // tope sensato de adicionales: un wrap no es un álbum.
            foreach ((array) $request->file('extra_images') as $file) {
                if (! $file || count($out['extra']) >= $tope) {
                    continue;
                }
                $rel = ImageCompressor::store($file, 'wrap_images');
                if ($rel !== null) {
                    $out['extra'][] = Storage::url($rel);
                }
            }
        }

        return $out;
    }
}
