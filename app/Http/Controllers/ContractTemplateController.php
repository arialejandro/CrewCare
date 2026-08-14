<?php

namespace App\Http\Controllers;

use App\Models\ContractTemplate;
use App\Models\PayeeContract;
use App\Support\ContractTemplateRenderer;
use App\Support\CurrentProduction;
use Illuminate\Http\Request;

/**
 * CONTRACT BUILDER · FASE 1b — EDITOR de plantillas de contrato.
 *
 * Redactar el cuerpo e insertar `{{campos}}` y `[[firma:...]]` desde un menú (sin memorizar
 * sintaxis) + PREVIEW server-side con datos de ejemplo (el mismo render que el documento real).
 * Gated a settings.manage (en las rutas). No emite ni firma nada; solo define la plantilla.
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

    public function create()
    {
        return $this->editView(new ContractTemplate([
            'name' => '', 'applies_to' => [PayeeContract::CONCEPT_CREW], 'language' => 'es',
            'body' => self::starterBody(), 'is_active' => false,
        ]));
    }

    public function edit(ContractTemplate $template)
    {
        return $this->editView($template);
    }

    private function editView(ContractTemplate $template)
    {
        return view('contracts.templates.edit', [
            'template' => $template,
            'subtypes' => $this->subtypes(),
            'fields'   => ContractTemplateRenderer::fieldCatalog(),
            'anchors'  => ContractTemplateRenderer::anchorCatalog(),
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
        $tpl  = new ContractTemplate(['body' => $body]);
        $inner = ContractTemplateRenderer::render($tpl, self::sampleValues(), self::sampleSigMap());

        $page = '<!doctype html><meta charset="utf-8">'
            . '<style>body{font-family:Georgia,"Times New Roman",serif;color:#1a1a1a;margin:1.4rem;line-height:1.6}'
            . 'h1{font-size:1.3rem;text-align:center}h2{font-size:1.02rem;border-bottom:1px solid #e2e2e2;padding-bottom:3px;margin-top:1.3rem}'
            . 'table{width:100%;border-collapse:collapse}</style>' . $inner;

        return response($page);
    }

    // ── Validación + datos de ejemplo ─────────────────────────────────────────
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name'         => 'required|string|max:191',
            'applies_to'   => 'required|array|min:1',
            'applies_to.*' => 'in:crew_work,rental,service',
            'language'     => 'nullable|string|max:5',
            'body'         => 'nullable|string',
            'is_active'    => 'nullable|boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $data['language']  = ($data['language'] ?? null) ?: 'es';

        return $data;
    }

    private static function sampleValues(): array
    {
        return [
            'fecha_hoy' => now()->format('d/m/Y'),
            'empresa' => 'Pimienta Films SA de CV', 'representante_legal' => 'Jorge Ruiz Mena',
            'domicilio_empresa' => 'Av. Reforma 222, CDMX',
            'payee_nombre' => 'María González Ríos', 'payee_rfc' => 'GORM900101AB2',
            'puesto' => 'Supervisora de Salud y Seguridad', 'actividad' => 'plan de emergencias y bitácora diaria',
            'credito' => 'María G. Ríos', 'honorarios' => '$180,000.00', 'moneda' => 'MXN',
            'vigencia_inicio' => '01/09/2026', 'vigencia_fin' => '20/12/2026',
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
            $names = ['María González', 'Ana Pérez', 'Jorge Ruiz', 'Luis Mena', 'Sofía Lara'];
            $map[$key] = [
                'image' => $auto($names[$i % count($names)]), 'signer' => $names[$i % count($names)],
                'role' => $label, 'date' => now()->format('Y-m-d H:i'),
                'hash' => substr(hash('sha256', $key), 0, 24), 'verified' => true,
            ];
            $i++;
        }
        $map['__labels'] = $labels;

        return $map;
    }

    /** Cuerpo de arranque (ejemplo) para plantillas nuevas. */
    private static function starterBody(): string
    {
        return "<h1>Contrato de prestación de servicios</h1>\n"
            . "<p>En la Ciudad de México, a {{fecha_hoy}}, comparecen <strong>{{empresa}}</strong>, "
            . "representada por {{representante_legal}}, y <strong>{{payee_nombre}}</strong> (RFC {{payee_rfc}}).</p>\n"
            . "<h2>Objeto</h2>\n<p>El Contratado prestará sus servicios como <strong>{{puesto}}</strong> "
            . "({{actividad}}), del {{vigencia_inicio}} al {{vigencia_fin}}, por {{honorarios}} {{moneda}}.</p>\n"
            . "<h2>Firmas</h2>\n<table><tr>\n"
            . "  <td style=\"text-align:center;padding:10px;vertical-align:bottom\">[[firma:contratado]]<div style=\"font-size:11px;color:#555\">El Contratado</div></td>\n"
            . "  <td style=\"text-align:center;padding:10px;vertical-align:bottom\">[[firma:dept_hod]]<div style=\"font-size:11px;color:#555\">Por la Producción</div></td>\n"
            . "</tr></table>";
    }
}
