<?php

namespace App\Http\Controllers;

use App\Models\ContractTemplate;
use App\Models\PayeeContract;
use App\Support\ContractArchitectures;
use App\Support\ContractFonts;
use App\Support\ContractPageSizes;
use App\Support\ContractTemplateRenderer;
use App\Support\CurrentProduction;
use App\Support\PdfNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * CONTRACT BUILDER · EDITOR de plantillas de contrato.
 *
 * Se ELIGE un FORMATO (arquitectura: carátula numerada / ficha / declaraciones) y el canvas carga su
 * andamiaje real; se redacta el cuerpo e insertan `{{campos}}` y `[[firma:...]]` desde un menú +
 * PREVIEW server-side con datos de ejemplo (el mismo render que el documento real, con el CSS del
 * formato). Gated a `contracts.author` (Line Producer / representante-legal). No emite ni firma nada.
 */
class ContractTemplateController extends Controller
{
    /** Subtipos de contrato que una plantilla puede cubrir. */
    private function subtypes(): array
    {
        return [
            PayeeContract::CONCEPT_CREW    => __('Miembro de crew'),
            PayeeContract::CONCEPT_RENTAL  => __('Equipo'),
            PayeeContract::CONCEPT_SERVICE => __('Servicio'),
        ];
    }

    public function index()
    {
        $prod = CurrentProduction::id();
        $templates = ContractTemplate::forProduction($prod)->orderByDesc('id')->get();

        return view('contracts.templates.index', [
            'templates' => $templates,
            'subtypes'  => $this->subtypes(),
        ]);
    }

    public function create(Request $request)
    {
        // El FORMATO puede venir preelegido (?arch=…) desde el índice; el canvas nace con su andamiaje.
        $arch = ContractArchitectures::normalize($request->query('arch'));

        return $this->editView(new ContractTemplate([
            'name' => '', 'applies_to' => [PayeeContract::CONCEPT_CREW], 'language' => 'es',
            'architecture' => $arch, 'bilingual' => false, 'page_size' => ContractPageSizes::DEFAULT,
            'font_family' => ContractFonts::DEFAULT, 'font_size' => ContractFonts::SIZE_DEFAULT,
            'body' => ContractArchitectures::starter($arch), 'is_active' => false,
        ]));
    }

    public function edit(ContractTemplate $template)
    {
        return $this->editView($template);
    }

    // ── PDF FILLABLE · subir PDF + colocar etiquetas (segundo modo de autoría) ──────────────────
    /** Form de alta: nombre + subtipos + archivo PDF. Al subir, se abre el editor de etiquetas. */
    public function createPdf()
    {
        return view('contracts.templates.create-pdf', ['subtypes' => $this->subtypes()]);
    }

    /** Guarda el PDF en disco local y crea la plantilla `source_kind=pdf` (sin etiquetas todavía). */
    public function storePdf(Request $request)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:191',
            'applies_to'   => 'required|array|min:1',
            'applies_to.*' => 'in:crew_work,rental,service',
            'pdf'          => 'required|file|mimetypes:application/pdf|mimes:pdf|max:20480',
        ]);

        $prod = CurrentProduction::id();
        $file = $request->file('pdf');
        // Disco privado (storage/app): el PDF se sirve solo autenticado por pdfFile(), nunca público.
        $path = $file->store('contract-templates/' . ($prod ?: 'global'), 'local');

        // Debe ser legible por FPDI para poder estamparlo. Ghostscript (si existe) normaliza comprimidos/
        // protegidos-sin-clave; si aun así no se puede, se rechaza aquí (no se crea plantilla huérfana).
        if (! PdfNormalizer::ensureReadable($path)) {
            Storage::disk('local')->delete($path);

            return back()->withInput()->withErrors([
                'pdf' => __('No se pudo leer el PDF (¿está protegido con contraseña o candado?). Re-guárdalo sin protección y vuelve a subirlo.'),
            ]);
        }

        $tpl = ContractTemplate::create([
            'production_id'     => $prod,
            'name'              => $data['name'],
            'applies_to'        => $data['applies_to'],
            'language'          => 'es',
            'source_kind'       => ContractTemplate::SOURCE_PDF,
            'pdf_path'          => $path,
            'pdf_original_name' => $file->getClientOriginalName(),
            'field_map'         => [],
            'is_active'         => false,
            'created_by_id'     => auth()->id(),
        ]);

        return redirect()->route('contracts.templates.edit', $tpl)
            ->with('status', __('PDF cargado. Ahora coloca las etiquetas de firma y datos sobre el documento.'));
    }

    /** Sirve el PDF original (INLINE, autenticado) para que pdf.js lo dibuje en el editor. */
    public function pdfFile(ContractTemplate $template)
    {
        abort_unless(in_array($template->production_id, [null, CurrentProduction::id()], true), 404);
        abort_unless($template->isPdfSource() && $template->pdf_path
            && Storage::disk('local')->exists($template->pdf_path), 404);

        return Storage::disk('local')->response(
            $template->pdf_path,
            ($template->pdf_original_name ?: 'contrato.pdf'),
            ['Content-Type' => 'application/pdf'],
            'inline'
        );
    }

    /**
     * PREVIEW del PDF estampado con datos + firmas de EJEMPLO, en las etiquetas ACTUALES del editor
     * (llegan en el POST, sin necesidad de guardar). Sirve para "ver cómo quedaría" antes de emitir.
     */
    public function pdfPreview(Request $request, ContractTemplate $template)
    {
        abort_unless($template->isPdfSource(), 404);

        $decoded = json_decode((string) $request->input('field_map', 'null'), true);
        $map = is_array($decoded) ? self::sanitizeFieldMap($decoded) : ($template->field_map ?? []);

        // Plantilla EFÍMERA (no se guarda): mismo PDF, con las etiquetas del editor.
        $preview = new ContractTemplate([
            'source_kind' => ContractTemplate::SOURCE_PDF,
            'pdf_path'    => $template->pdf_path,
            'field_map'   => $map,
        ]);

        try {
            $bytes = \App\Support\ContractPdfStamper::stampSample($preview, self::sampleValues(), self::sampleSignatures($map));
        } catch (\Throwable $e) {
            abort(422, $e->getMessage());
        }

        return response($bytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="preview.pdf"',
        ]);
    }

    private function editView(ContractTemplate $template)
    {
        if ($template->isPdfSource()) {
            return view('contracts.templates.edit-pdf', [
                'template' => $template,
                'subtypes' => $this->subtypes(),
                'fields'   => ContractTemplateRenderer::fieldCatalog(),
                'anchors'  => ContractTemplateRenderer::anchorCatalog(),
            ]);
        }

        return view('contracts.templates.edit', [
            'template'      => $template,
            'subtypes'      => $this->subtypes(),
            'fields'        => ContractTemplateRenderer::fieldCatalog(),
            'anchors'       => ContractTemplateRenderer::anchorCatalog(),
            'architectures' => ContractArchitectures::all(),
            'starters'      => ContractArchitectures::starters(),
            'pageSizes'     => ContractPageSizes::all(),
            'fonts'         => ContractFonts::all(),
            'fontSizes'     => ContractFonts::sizes(),
            'fontFamily'    => ContractFonts::normalize($template->font_family),   // resuelve alias mono→couriernew
            'fontSize'      => ContractFonts::normalizeSize($template->font_size),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['production_id'] = CurrentProduction::id();
        $data['created_by_id'] = auth()->id();
        $tpl = ContractTemplate::create($data);

        return redirect()->route('contracts.templates.edit', $tpl)->with('status', __('Plantilla creada.'));
    }

    public function update(Request $request, ContractTemplate $template)
    {
        if ($template->isPdfSource()) {
            $template->update($this->validatedPdf($request));

            return back()->with('status', __('Etiquetas guardadas.'));
        }

        $template->update($this->validated($request));

        return back()->with('status', __('Plantilla guardada.'));
    }

    /** Activar/desactivar (una plantilla activa por subtipo es responsabilidad del que configura). */
    public function toggle(ContractTemplate $template)
    {
        $template->update(['is_active' => ! $template->is_active]);

        return back()->with('status', $template->is_active ? __('Plantilla activada.') : __('Plantilla desactivada.'));
    }

    /** PREVIEW (AJAX): renderiza el cuerpo enviado con datos de ejemplo + firmas estampadas de muestra. */
    public function preview(Request $request)
    {
        $body = (string) $request->input('body', '');
        $arch = ContractArchitectures::normalize($request->input('architecture'));
        $size = ContractPageSizes::normalize($request->input('page_size'));
        $tpl  = new ContractTemplate(['body' => $body]);
        $inner = ContractTemplateRenderer::render($tpl, self::sampleValues(), self::sampleSigMap());

        // `fragment=1`: solo el contenido (sin `<style>`/`@page`) para que el paginador del cliente
        // lo mida y lo reparta en hojas reales (inc.3b). Sin fragment: la hoja completa (PDF/impresión).
        if ($request->boolean('fragment')) {
            return response($inner);
        }

        // La "rúbrica en cada página" (initials_each_page) se coloca por coordenadas en inc.3c-2;
        // el render base ya no la pinta (ver ContractTemplateRenderer::page).
        $font = ContractFonts::normalize($request->input('font_family'));
        $fsize = ContractFonts::normalizeSize($request->input('font_size'));
        return response(ContractTemplateRenderer::page($inner, $arch, $size, null, $font, $fsize));
    }

    // ── Validación + datos de ejemplo ─────────────────────────────────────────
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name'         => 'required|string|max:191',
            'applies_to'   => 'required|array|min:1',
            'applies_to.*' => 'in:crew_work,rental,service',
            'language'     => 'nullable|string|max:5',
            'architecture' => 'nullable|string|max:32',
            'bilingual'    => 'nullable|boolean',
            'page_size'    => 'nullable|string|max:16',
            'font_family'  => 'nullable|string|max:16',
            'font_size'    => 'nullable|string|max:8',
            'initials_each_page' => 'nullable|boolean',
            'body'         => 'nullable|string',
            'is_active'    => 'nullable|boolean',
        ]);
        $data['is_active']          = $request->boolean('is_active');
        $data['language']           = ($data['language'] ?? null) ?: 'es';
        $data['architecture']       = ContractArchitectures::normalize($data['architecture'] ?? null);
        $data['bilingual']          = $request->boolean('bilingual');
        $data['page_size']          = ContractPageSizes::normalize($data['page_size'] ?? null);
        $data['font_family']        = ContractFonts::normalize($data['font_family'] ?? null);
        $data['font_size']          = ContractFonts::normalizeSize($data['font_size'] ?? null);
        $data['initials_each_page'] = $request->boolean('initials_each_page');

        return $data;
    }

    /** Validación de una plantilla PDF: metadatos + el mapa de etiquetas colocadas (saneado). */
    private function validatedPdf(Request $request): array
    {
        $data = $request->validate([
            'name'         => 'required|string|max:191',
            'applies_to'   => 'required|array|min:1',
            'applies_to.*' => 'in:crew_work,rental,service',
            'is_active'    => 'nullable|boolean',
            'field_map'    => 'nullable|string',   // JSON serializado desde el editor
        ]);

        $decoded = json_decode($data['field_map'] ?? '[]', true);

        return [
            'name'       => $data['name'],
            'applies_to' => $data['applies_to'],
            'is_active'  => $request->boolean('is_active'),
            'field_map'  => self::sanitizeFieldMap(is_array($decoded) ? $decoded : []),
        ];
    }

    /**
     * Sanea el mapa de etiquetas del editor: NO confía en el cliente. Solo pasan claves que existen en
     * el catálogo correcto (dato ∈ fieldCatalog, firma ∈ anchorCatalog), coordenadas acotadas 0–100%.
     */
    private static function sanitizeFieldMap(array $map): array
    {
        $fields  = ContractTemplateRenderer::fieldCatalog();
        $anchors = ContractTemplateRenderer::anchorCatalog();
        $clamp   = fn ($v, $min, $max) => max($min, min($max, (float) $v));
        $out = [];

        foreach ($map as $f) {
            if (! is_array($f)) {
                continue;
            }
            $type = (($f['type'] ?? null) === 'sign') ? 'sign' : 'data';
            $key  = (string) ($f['key'] ?? '');
            if ($type === 'data' && ! array_key_exists($key, $fields)) {
                continue;
            }
            if ($type === 'sign' && ! array_key_exists($key, $anchors)) {
                continue;
            }
            $out[] = [
                'page'  => max(1, (int) ($f['page'] ?? 1)),
                'x_pct' => round($clamp($f['x_pct'] ?? 0, 0, 100), 3),
                'y_pct' => round($clamp($f['y_pct'] ?? 0, 0, 100), 3),
                'w_pct' => round($clamp($f['w_pct'] ?? ($type === 'sign' ? 24 : 28), 2, 100), 3),
                'type'  => $type,
                'key'   => $key,
            ];
        }

        return $out;
    }

    /**
     * Datos de EJEMPLO para el preview. ⚖ NUNCA nombres reales de productoras ni personas (indicio de
     * piratería / uso indebido de marca). La empresa sale de la MARCA de la app; si no hay, genérico
     * “Productora S.A. de C.V.”. La persona es un genérico evidente (Juan Pérez López) + RFC genérico
     * del SAT (XAXX010101000). Los datos REALES del contrato salen de valuesFor(), no de aquí.
     */
    private static function sampleValues(): array
    {
        return [
            'fecha_hoy'           => now()->format('d/m/Y'),
            'empresa'             => \App\Support\Branding::get('company_name') ?: 'Productora S.A. de C.V.',
            'representante_legal' => \App\Support\Branding::get('representante_legal') ?: 'Representante Legal',
            'domicilio_empresa'   => \App\Support\Branding::get('office_address') ?: 'Domicilio de la Empresa',
            'payee_nombre'        => 'Juan Pérez López', 'payee_rfc' => 'XAXX010101000',
            // Datos del contratado (genéricos evidentes; los reales salen de valuesFor()).
            'domicilio_contratado'           => 'Calle Genérica No. 123, Col. Centro, C.P. 00000, Ciudad',
            'tel_contratado'                 => '55 0000 0000',
            'correo_contratado'              => 'contratado@ejemplo.mx',
            'emergencia_contratado'          => 'Contacto de emergencia — 55 0000 0000',
            'beneficiario_contratado'        => 'Beneficiario del contratado (parentesco)',
            'representante_legal_contratado' => 'Representante legal (en su caso)',
            'loanout_contratado'             => 'Empresa / loanout (en su caso)',
            'regimen_fiscal'                 => 'Régimen fiscal del contrato',
            'titulo_programa'                => 'Título del programa / producción',
            'puesto'              => 'Puesto del contratado', 'actividad' => 'Actividad / entregable del contrato',
            'credito'             => 'Crédito en pantalla', 'honorarios' => '$00,000.00', 'moneda' => 'MXN',
            'vigencia_inicio'     => '01/01/2026', 'vigencia_fin' => '31/12/2026',
        ];
    }

    /** Firmas de muestra: toda ancla conocida aparece FIRMADA (para ver el estampado). */
    private static function sampleSigMap(): array
    {
        $auto = function ($name) {
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="440" height="64">'
                . '<text x="8" y="44" font-family="Segoe Script,Brush Script MT,cursive" font-size="30" font-style="italic" fill="#0f1115">'
                . htmlspecialchars($name) . '</text></svg>';
            return 'data:image/svg+xml;base64,' . base64_encode($svg);
        };
        $labels = ContractTemplateRenderer::anchorCatalog();
        $map = [];
        $i = 0;
        foreach ($labels as $key => $label) {
            $names = ['Juan Pérez López', 'Ana García', 'Luis Martínez', 'Sofía Hernández', 'Carlos Ramírez'];
            $map[$key] = [
                'image' => $auto($names[$i % count($names)]), 'signer' => $names[$i % count($names)],
                'role' => $label, 'date' => now()->format('Y-m-d H:i'),
                'hash' => substr(hash('sha256', $key), 0, 24), 'verified' => true,
            ];
            $i++;
        }
        // En el preview, la rúbrica muestra la MISMA inicial que el contratado (fiel al render real).
        if (isset($map['contratado'])) {
            $map['rubrica'] = $map['contratado'];
        }
        $map['__labels'] = $labels;

        return $map;
    }

    /** Firmas de EJEMPLO (PNG) para las anclas colocadas en el PDF preview: clave → ['image','signer']. */
    private static function sampleSignatures(array $map): array
    {
        $names = ['Juan Pérez López', 'Ana García', 'Luis Martínez', 'Sofía Hernández'];
        $out = [];
        $i = 0;
        foreach ($map as $f) {
            if (($f['type'] ?? null) !== 'sign') {
                continue;
            }
            $key = (string) ($f['key'] ?? '');
            if ($key === '' || isset($out[$key])) {
                continue;
            }
            $name = $names[$i++ % count($names)];
            $out[$key] = ['image' => self::sampleSignaturePng($name), 'signer' => $name];
        }

        return $out;
    }

    /** Un PNG (data URI) con un trazo tipo firma + el nombre — solo para el preview del editor. */
    private static function sampleSignaturePng(string $name): string
    {
        $w = 280;
        $h = 70;
        $im = imagecreatetruecolor($w, $h);
        imagesavealpha($im, true);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 255, 255, 255, 127));
        $ink = imagecolorallocate($im, 20, 30, 60);
        imagesetthickness($im, 2);
        imageline($im, 12, $h - 22, (int) ($w * 0.55), 16, $ink);
        imageline($im, (int) ($w * 0.55), 16, $w - 16, $h - 26, $ink);
        // El texto built-in es ASCII: se translitera para no ensuciar el trazo con acentos rotos.
        $ascii = (string) @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        imagestring($im, 3, 12, $h - 18, $ascii ?: $name, $ink);
        ob_start();
        imagepng($im);
        $bin = (string) ob_get_clean();
        imagedestroy($im);

        return 'data:image/png;base64,' . base64_encode($bin);
    }

}
