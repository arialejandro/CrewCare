<?php

namespace App\Http\Controllers;

/**
 * PREVISUALIZACIÓN de correos transaccionales. Los correos son 100% código (Blade) y no tienen
 * muestra visual: esto renderiza la MISMA vista que envía el mailer, con datos de ejemplo, para
 * revisar que se ve bien ANTES de mandar de verdad. NO envía nada. Gated a settings.manage.
 *
 * Agregar un correo = una entrada en samples(). El `view` debe ser la MISMA que usa Mail::send.
 */
class EmailPreviewController extends Controller
{
    /** Correos previsualizables → vista real + datos de ejemplo representativos. */
    public static function samples(): array
    {
        return [
            'welcomeuser' => [
                'view'  => 'correos.welcomeuser',
                'label' => 'Bienvenida (alta de crew)',
                'data'  => [
                    'nombre'   => 'María González Ríos',
                    'email'    => 'maria.gonzalez@ejemplo.mx',
                    'resetUrl' => 'https://demo.crewcare.mx/password/reset/EJEMPLO-TOKEN',
                ],
            ],
            'contract-turn' => [
                'view'  => 'correos.contract-turn',
                'label' => 'Es tu turno de firmar (aviso "te toca")',
                'data'  => [
                    'toName'       => 'María González Ríos',
                    'cargo'        => 'Contratista',
                    'payeeName'    => 'María González Ríos',
                    'conceptLabel' => 'Miembro de crew',
                    'natureLabel'  => 'Persona física',
                    'key'          => [
                        'fee' => 45000, 'currency' => 'MXN', 'rfc' => 'XAXX010101000',
                        'regime' => 'Sueldos y salarios', 'start' => '2026-02-01', 'end' => '2026-08-31',
                    ],
                    'signUrl'      => 'https://demo.crewcare.mx/contratos/firma/123?expires=1788000000&signature=EJEMPLO',
                ],
            ],
            'contract-signed' => [
                'view'  => 'correos.contract-signed',
                'label' => 'Contrato firmado (entrega al contratado)',
                'data'  => [
                    'toName'      => 'María González Ríos',
                    'payeeName'   => 'María González Ríos',
                    'completedAt' => '14/08/2026 18:30',
                    'signers'     => [
                        ['name' => 'María González Ríos', 'role' => 'Contratista',            'signed_at' => '14/08/2026 18:12', 'method' => 'signed_link_2fa', 'hash' => 'a1b2c3d4e5f6a1b2c3d4e5f6'],
                        ['name' => 'Ana Pérez López',     'role' => 'Productor en Línea',      'signed_at' => '14/08/2026 18:20', 'method' => 'authenticated',   'hash' => 'ff00ee11dd22cc33bb44aa55'],
                        ['name' => 'Jorge Ruiz Mena',     'role' => 'Gerente de Producción',   'signed_at' => '14/08/2026 18:30', 'method' => 'authenticated',   'hash' => '123456789abcdef012345678'],
                    ],
                ],
            ],
        ];
    }

    /** Índice: lista los correos previsualizables (HTML mínimo, sin vista aparte). */
    public function index()
    {
        $links = collect(self::samples())->map(function ($s, $key) {
            $url = route('emails.preview.show', ['view' => $key]);
            return '<li style="margin:.4rem 0"><a href="'.e($url).'">'.e($s['label']).'</a> <code style="opacity:.6">('.e($key).')</code></li>';
        })->implode('');

        $html = '<!doctype html><meta charset="utf-8"><title>Preview de correos</title>'
            .'<div style="font-family:system-ui,Segoe UI,sans-serif;max-width:640px;margin:2rem auto;padding:0 1rem">'
            .'<h1 style="font-size:1.3rem">Previsualización de correos</h1>'
            .'<p style="color:#666">Se renderiza la misma vista que envía el sistema, con datos de ejemplo. No se envía nada.</p>'
            .'<ul style="list-style:none;padding:0">'.$links.'</ul></div>';

        return response($html);
    }

    /** Renderiza un correo con sus datos de ejemplo (lo que vería el destinatario). */
    public function show(string $view)
    {
        $samples = self::samples();
        abort_unless(isset($samples[$view]), 404);

        return view($samples[$view]['view'], $samples[$view]['data']);
    }
}
