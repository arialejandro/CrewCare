<?php

namespace App\Http\Controllers;

use App\Models\ContractTemplate;
use App\Models\PayeeContract;
use App\Support\ContractArchitectures;
use App\Support\ContractFonts;
use App\Support\ContractPageSizes;
use App\Support\ContractTemplateRenderer;
use App\Support\CurrentProduction;
use Illuminate\Http\Request;

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
            PayeeContract::CONCEPT_CREW    => __('Trabajo de crew'),
            PayeeContract::CONCEPT_RENTAL  => __('Renta de equipo'),
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

    private function editView(ContractTemplate $template)
    {
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
            'puesto'              => 'Puesto del contratado', 'actividad' => 'Actividad / entregable del contrato',
            'credito'             => 'Crédito en pantalla', 'honorarios' => '$00,000.00', 'moneda' => 'MXN',
            'vigencia_inicio'     => '01/01/2026', 'vigencia_fin' => '31/12/2026',
        ];
    }

    /** Firmas de muestra: toda ancla conocida aparece FIRMADA (para ver el estampado). */
    private static function sampleSigMap(): array
    {
        $auto = function ($name) {
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="300" height="70">'
                . '<text x="8" y="48" font-family="Segoe Script,Brush Script MT,cursive" font-size="36" font-style="italic" fill="#0f1115">'
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

}
