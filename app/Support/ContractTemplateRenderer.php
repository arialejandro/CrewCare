<?php

namespace App\Support;

use App\Models\ContractTemplate;
use App\Models\PayeeContract;
use Illuminate\Support\Carbon;

/**
 * CONTRACT BUILDER · FASE 1 — motor de render de la plantilla.
 *
 * Toma el `body` (HTML autorizado) y resuelve DOS marcadores:
 *   · `{{campo}}`        → valor del TRATO (escapado). Catálogo en fieldCatalog().
 *   · `[[firma:CLAVE]]`  → sello de firma en su lugar: autógrafa CONGELADA + su hash (verde=íntegra),
 *                          o "pendiente de firma" si aún no firma ese puesto.
 *
 * Las firmas se pasan como MAPA CLAVE→props (o null = pendiente): el motor NO conoce el sobre — quien
 * llama arma el mapa (para el preview con datos de ejemplo, o desde el sobre en la integración).
 * Los sellos van con estilos INLINE porque el documento final se imprime por Browsershot/dompdf,
 * donde los @push('styles') no existen. Emula el bloque _signature-block, autocontenido.
 */
class ContractTemplateRenderer
{
    /** `{{token}}` disponibles → etiqueta legible (para el menú del editor). */
    public static function fieldCatalog(): array
    {
        return [
            'payee_nombre'        => 'Nombre del contratado',
            'payee_rfc'           => 'RFC del contratado',
            'puesto'              => 'Puesto',
            'actividad'           => 'Actividad / entregable',
            'credito'             => 'Nombre en créditos',
            'honorarios'          => 'Honorarios (total)',
            'moneda'              => 'Moneda',
            'vigencia_inicio'     => 'Inicio de vigencia',
            'vigencia_fin'        => 'Fin de vigencia',
            'empresa'             => 'Empresa contratante',
            'representante_legal' => 'Representante legal',
            'domicilio_empresa'   => 'Domicilio de la empresa',
            'fecha_hoy'           => 'Fecha de hoy',
        ];
    }

    /**
     * Anclas de firma disponibles (para el menú del editor): CLAVE → etiqueta. Deriva de "Firmas
     * Prod." (la lista configurable de firmantes) + el contratado. Sin lista → sugerencias clásicas.
     */
    public static function anchorCatalog(): array
    {
        $out = ['contratado' => __('Contratado')];

        foreach (SignaturePositions::signerEntries() as $entry) {
            if ($entry === SignaturePositions::DEPT_HOD) {
                $out['dept_hod'] = __('HOD del departamento del contrato');
            } else {
                $out['puesto:' . (int) $entry] = SignaturePositions::positionLabel((int) $entry);
            }
        }

        if (count($out) === 1) {   // sin lista configurada → sugerencias útiles
            $out['line_producer'] = __('Productor en Línea');
            $out['dept_hod']      = __('HOD del departamento del contrato');
        }

        return $out;
    }

    /** Valores del TRATO para llenar los `{{campos}}` (mismo origen que la carátula). */
    public static function valuesFor(PayeeContract $contract): array
    {
        $payee = $contract->payee;
        $fin   = $contract->definitive_end_date ?: $contract->estimated_end_date;
        $fmt   = fn ($d) => $d ? Carbon::parse($d)->format('d/m/Y') : null;

        return [
            'payee_nombre'        => optional($payee)->name,
            'payee_rfc'           => optional($payee)->rfc,
            'puesto'              => $contract->title,
            'actividad'           => $contract->crew_activity,
            'credito'             => $contract->credit_name,
            'honorarios'          => $contract->fee_amount !== null ? '$' . number_format((float) $contract->fee_amount, 2) : null,
            'moneda'              => $contract->fee_currency,
            'vigencia_inicio'     => $fmt($contract->effective_date),
            'vigencia_fin'        => $fmt($fin),
            'empresa'             => Branding::get('company_name'),
            'representante_legal' => Branding::get('representante_legal'),
            'domicilio_empresa'   => Branding::get('office_address'),
            'fecha_hoy'           => now()->format('d/m/Y'),
        ];
    }

    /** Render completo: llena campos + estampa firmas. $sigMap: CLAVE → props|null (pendiente). */
    public static function render(ContractTemplate $template, array $values, array $sigMap = []): string
    {
        $body = self::fill((string) $template->body, $values);
        return self::stampAnchors($body, $sigMap);
    }

    /** Reemplaza `{{token}}` por su valor (escapado). Token desconocido/vacío → cadena vacía. */
    public static function fill(string $body, array $values): string
    {
        return preg_replace_callback('/\{\{\s*([a-z0-9_.]+)\s*\}\}/i', function ($m) use ($values) {
            $key = strtolower($m[1]);
            $val = $values[$key] ?? '';
            return e((string) $val);
        }, $body);
    }

    /** Reemplaza `[[firma:CLAVE]]` por el sello (autógrafa+hash) o el placeholder de pendiente. */
    public static function stampAnchors(string $body, array $sigMap): string
    {
        return preg_replace_callback('/\[\[firma:([a-z0-9_:\-]+)\]\]/i', function ($m) use ($sigMap) {
            $key  = strtolower($m[1]);
            $data = $sigMap[$key] ?? null;
            return is_array($data) ? self::signatureStamp($data) : self::pendingStamp($sigMap['__labels'][$key] ?? $key);
        }, $body);
    }

    /** Sello de firma APLICADA (estilo DocuSign) — autocontenido con estilos inline (PDF-safe). */
    private static function signatureStamp(array $p): string
    {
        $img    = $p['image']    ?? null;
        $signer = e((string) ($p['signer'] ?? ''));
        $role   = e((string) ($p['role']   ?? ''));
        $date   = $p['date'] ? e(Carbon::parse($p['date'])->format('d/m/Y H:i')) : '';
        $hash   = $p['hash'] ? e(substr((string) $p['hash'], 0, 20)) . '…' : '';
        $ok     = ($p['verified'] ?? null) === true;
        $bad    = ($p['verified'] ?? null) === false;
        $hcolor = $ok ? '#15803d' : ($bad ? '#b91c1c' : '#6b7482');
        $hlabel = $ok ? '✓ Verificada e íntegra' : ($bad ? '⚠ Alterada' : '');

        $mark = $img
            ? '<img src="' . e($img) . '" alt="Firma" style="max-width:170px;max-height:60px;display:block;">'
            : '';

        return '<span class="cc-sig-stamp" style="display:inline-block;border:1px solid #d7dce4;border-radius:10px;'
            . 'padding:8px 12px;background:#fafbfc;vertical-align:bottom;min-width:190px;">'
            . '<span style="display:block;border-bottom:1px solid #c3c9d4;padding-bottom:4px;margin-bottom:4px;min-height:60px;">' . $mark . '</span>'
            . '<span style="display:block;font-weight:700;font-size:13px;color:#10151f;">' . $signer . '</span>'
            . ($role ? '<span style="display:block;font-size:11px;color:#6b7482;">' . $role . '</span>' : '')
            . ($date ? '<span style="display:block;font-size:11px;color:#6b7482;">' . $date . '</span>' : '')
            . ($hash ? '<span style="display:block;margin-top:2px;font-size:10px;color:' . $hcolor . ';">' . $hlabel . ' <code style="font-size:10px;">' . $hash . '</code></span>' : '')
            . '</span>';
    }

    /** Placeholder de firma PENDIENTE (aún no firma ese puesto). */
    private static function pendingStamp(string $label): string
    {
        return '<span class="cc-sig-pending" style="display:inline-block;border:1px dashed #c3c9d4;border-radius:10px;'
            . 'padding:14px 16px;min-width:190px;min-height:60px;text-align:center;color:#8a93a2;font-style:italic;font-size:12px;'
            . 'vertical-align:bottom;background:#fff;">Pendiente de firma<br><span style="font-style:normal;font-size:11px;">' . e($label) . '</span></span>';
    }
}
