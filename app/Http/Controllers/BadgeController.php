<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\BadgeTemplate;
use App\Models\User;
use App\Support\Branding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * BadgeController — sistema de gafetes (ID-Badge) configurable.
 *
 * - designer/saveTemplate: el super-admin edita la ÚNICA plantilla activa (gate settings.manage).
 * - pdf: gafete individual → dompdf (papel exacto del gafete: 108mm×172mm).
 * - bulkPdf/bulkJpg: todos los gafetes del scope del usuario (por departamento).
 *
 * dompdf v2 NO soporta CSS variables → las vistas usan estilos inline concretos (_card).
 */
class BadgeController extends Controller
{
    // 108mm×172mm en puntos (1mm = 2.83465pt) → papel exacto del gafete.
    private const PAPER = [0, 0, 306.14, 487.56];

    /** Diseñador de plantilla (gate settings.manage). */
    public function designer()
    {
        $template = BadgeTemplate::current();
        $tpl = array_merge(BadgeTemplate::DEFAULTS, is_array($template->config) ? $template->config : []);
        $sample = User::query()->orderBy('id', 'desc')->first();

        return view('admin.badge.designer', compact('template', 'tpl', 'sample'));
    }

    /** Guarda la plantilla activa (upsert de la única fila active=1). Gate badge.design. */
    public function saveTemplate(Request $r)
    {
        $data = $r->validate([
            'project_name'          => ['nullable', 'string', 'max:60'],
            'card_bg'               => ['nullable', 'string', 'max:255'],
            'card_bg_file'          => ['nullable', 'mimes:jpg,jpeg,png,gif,bmp,svg,webp,heic,heif', 'heic_ok', 'max:8192'],
            'production_logo'       => ['nullable', 'string', 'max:255'],
            'production_logo_file'  => ['nullable', 'mimes:png,jpg,jpeg,webp,svg,heic,heif', 'heic_ok', 'max:8192'],
            'font_family'           => ['required', 'in:' . implode(',', BadgeTemplate::FONTS)],
            'text_color'            => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'photo_shape'           => ['required', 'in:' . implode(',', BadgeTemplate::PHOTO_SHAPES)],
            'photo_size'            => ['required', 'numeric'],
            'photo_top'             => ['required', 'numeric'],
            'photo_left'            => ['required', 'numeric'],
            'production_logo_top'   => ['required', 'numeric'],
            'production_logo_left'  => ['required', 'numeric'],
            'production_logo_width' => ['required', 'numeric'],
            'brand_top'             => ['required', 'numeric'],
            'brand_left'            => ['required', 'numeric'],
            'brand_size'            => ['required', 'numeric'],
            'brand_weight'          => ['required', 'in:' . implode(',', array_keys(BadgeTemplate::FONT_WEIGHTS))],
            'label_name'            => ['required', 'string', 'max:40'],
            'label_name_top'        => ['required', 'numeric'],
            'name_top'              => ['required', 'numeric'],
            'name_size'             => ['required', 'numeric'],
            'label_position'        => ['required', 'string', 'max:40'],
            'label_position_top'    => ['required', 'numeric'],
            'position_top'          => ['required', 'numeric'],
            'position_size'         => ['required', 'numeric'],
            'powered_tone'          => ['required', 'in:' . implode(',', BadgeTemplate::POWERED_TONES)],
            'powered_top'           => ['required', 'numeric'],
            'consecutive_top'       => ['required', 'numeric'],
        ]);

        // Normalizar numéricos a float.
        $numeric = [
            'photo_size', 'photo_top', 'photo_left',
            'production_logo_top', 'production_logo_left', 'production_logo_width',
            'brand_top', 'brand_left', 'brand_size',
            'label_name_top', 'name_top', 'name_size',
            'label_position_top', 'position_top', 'position_size',
            'powered_top', 'consecutive_top',
        ];
        foreach ($numeric as $k) {
            $data[$k] = (float) $data[$k];
        }
        $data['brand_weight'] = (int) $data['brand_weight'];

        // Base = config actual, para que GUARDAR sin volver a subir NO borre el fondo/logo ya cargados.
        $current = BadgeTemplate::activeConfig();

        // Fondo: si suben archivo, se valida la proporción y reemplaza; si no, se conserva.
        $bgRel = $current['card_bg'];
        if ($r->hasFile('card_bg_file')) {
            $ratioError = $this->badBgRatio($r->file('card_bg_file'));
            if ($ratioError !== null) {
                return back()->withErrors(['card_bg_file' => $ratioError])->withInput();
            }
            $bgRel = $this->storeUpload($r->file('card_bg_file'), 'bg');
        }
        $data['card_bg'] = $bgRel ?: BadgeTemplate::DEFAULTS['card_bg'];

        // Logo de producción: archivo reemplaza; "Quitar" lo borra; si no, se conserva.
        $logoRel = $current['production_logo'] ?? '';
        if ($r->hasFile('production_logo_file')) {
            $logoRel = $this->storeUpload($r->file('production_logo_file'), 'logo');
        } elseif ($r->boolean('production_logo_clear')) {
            $logoRel = '';
        }
        $data['production_logo'] = $logoRel;

        $data['project_name'] = trim((string) ($data['project_name'] ?? ''));
        $data['show_consecutive'] = true; // el consecutivo SIEMPRE se muestra.
        $data['label_name_show'] = $r->boolean('label_name_show');
        $data['label_position_show'] = $r->boolean('label_position_show');

        // No persistir los inputs de archivo dentro de config.
        unset($data['card_bg_file'], $data['production_logo_file']);

        // Config final = DEFAULTS fusionado con lo capturado (mapa completo).
        $config = array_merge(BadgeTemplate::DEFAULTS, $data);

        $template = BadgeTemplate::where('active', 1)->first();
        if (! $template) {
            $template = new BadgeTemplate();
            $template->name = 'default';
            $template->active = true;
        }
        $template->config = $config;
        $template->active = true;
        $template->save();

        return redirect()->route('badge.designer')->with('success', 'Plantilla de gafete guardada.');
    }

    /**
     * Valida la proporción de la imagen de fondo (~108×172mm, ratio 0.628) con ±5% de
     * tolerancia. Devuelve un mensaje si NO cumple, o null si está bien / no se pudo leer.
     */
    private function badBgRatio($file)
    {
        $size = @getimagesize($file->getRealPath());
        if (! $size || empty($size[1])) {
            return null; // ilegible → no bloquear (la regla 'image' ya filtró el tipo)
        }
        $ratio = $size[0] / $size[1];
        $target = 108 / 172;
        if (abs($ratio - $target) / $target > 0.05) {
            return 'La imagen de fondo debe ser vertical con la proporción del gafete '
                . '(recomendado 1276 × 2032 px, equivale a 108 × 172 mm a 300 DPI). '
                . 'La imagen recibida mide ' . $size[0] . ' × ' . $size[1] . ' px.';
        }

        return null;
    }

    /** Guarda una imagen subida en public/img/badges y devuelve su ruta relativa. */
    private function storeUpload($file, $prefix)
    {
        $dir = public_path('img/badges');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        // (2026-07-24) La extensión sale del CONTENIDO, no del nombre del cliente. Esto escribe
        // DENTRO de public/ y el servidor lo entrega tal cual: con la extensión del cliente, un
        // JPEG válido llamado "poc.html" quedaba servido como text/html desde el propio dominio
        // = XSS almacenado. Misma corrección que ya tenían el DSR y la mitigación pública.
        // HEIC (iPhone) → JPEG si el servidor puede convertir; si no, la validación ya lo rechazó.
        $file = \App\Support\ImageCompressor::normalizeForUpload($file);
        $ext = \App\Support\ImageCompressor::safeExtensionOrBin($file);
        $name = $prefix . '-' . time() . '-' . substr(md5(uniqid('', true)), 0, 8) . '.' . $ext;
        $file->move($dir, $name);

        return 'img/badges/' . $name;
    }

    /**
     * Descarga la PLANTILLA actual como PDF (108×172mm) para rehacer el diseño en
     * Photoshop/Illustrator/Canva sobre las medidas exactas. Gate badge.design.
     */
    public function downloadTemplate()
    {
        $tpl = BadgeTemplate::activeConfig();
        $branding = Branding::all();

        $sample = User::query()->orderBy('id', 'desc')->first();
        if (! $sample) {
            $sample = new User();
            $sample->name = 'NOMBRE';
            $sample->lname = 'APELLIDO';
            $sample->puestodepartamento = 'PUESTO / DEPTO';
            $sample->id = 0;
        }

        return $this->renderPdf('admin.badge.single_pdf', [
            'user'     => $sample,
            'tpl'      => $tpl,
            'forPdf'   => true,
            'branding' => $branding,
        ])->download('plantilla-gafete.pdf');
    }

    /** Gafete individual en PDF (dompdf). */
    public function pdf($id)
    {
        $u = User::findOrFail($id);
        abort_unless(auth()->user()->canManageCrewMember($u), 403);

        $tpl = BadgeTemplate::activeConfig();
        $branding = Branding::all();

        return $this->renderPdf('admin.badge.single_pdf', [
            'user'     => $u,
            'tpl'      => $tpl,
            'forPdf'   => true,
            'branding' => $branding,
        ])->download('gafete-' . $id . '.pdf');
    }

    /** Gafetes LISTOS del scope (con foto, no impresos) en un PDF, uno por página. */
    public function bulkPdf()
    {
        $users = $this->printableUsers();
        if ($users->isEmpty()) {
            return $this->noPrintableRedirect();
        }
        $tpl = BadgeTemplate::activeConfig();
        $branding = Branding::all();

        return $this->renderPdf('admin.badge.bulk_pdf', [
            'users'    => $users,
            'tpl'      => $tpl,
            'forPdf'   => true,
            'branding' => $branding,
        ])->download('credenciales.pdf');
    }

    /**
     * dompdf con papel del gafete + incrustado de fuentes (Poppins/Montserrat/Roboto vía
     * @font-face local en _pdf_fonts). isFontSubsettingEnabled reduce el peso incrustando
     * sólo los glifos usados.
     */
    private function renderPdf(string $view, array $data)
    {
        return Pdf::loadView($view, $data)
            ->setPaper(self::PAPER)
            ->setOption('isFontSubsettingEnabled', true);
    }

    /** Página que dibuja los gafetes LISTOS del scope y los descarga como ZIP de JPG (cliente). */
    public function bulkJpg()
    {
        $users = $this->printableUsers();
        if ($users->isEmpty()) {
            return $this->noPrintableRedirect();
        }
        $tpl = BadgeTemplate::activeConfig();

        return view('admin.badge.bulk_jpg', compact('users', 'tpl'));
    }

    /**
     * Gafetes LISTOS para imprimir en lote: activos, del scope del usuario, CON foto de perfil
     * real (imgperfil ≠ vacío/'nofoto') y NO impresos (sin fila en badge_prints). Así el lote
     * nunca incluye gafetes incompletos ni duplica los ya impresos.
     */
    private function printableUsers()
    {
        return User::applyDepartmentScope(User::query()->where('activo', 1), auth()->user())
            ->whereNotNull('imgperfil')
            ->where('imgperfil', '!=', '')
            ->where('imgperfil', '!=', 'nofoto')
            ->whereDoesntHave('badgePrint')
            ->orderBy('id', 'desc')
            ->get();
    }

    /** Redirige a la lista con aviso cuando no hay gafetes listos. */
    private function noPrintableRedirect()
    {
        return redirect()->route('idcardscrud')->with('error',
            'No hay gafetes listos para imprimir: deben tener foto de perfil subida y no estar marcados como impresos.');
    }
}
