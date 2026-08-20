<?php

namespace App\Support;

use App\Models\ContractEnvelope;
use App\Models\ContractTemplate;
use App\Models\PayeeContract;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;

/**
 * PDF FILLABLE · F2 — MOTOR DE ESTAMPADO. Toma el PDF ORIGINAL subido por la productora y sobrepone,
 * encima y CONSERVANDO EL TEXTO (FPDI importa las páginas tal cual), dos cosas en las coordenadas del
 * `field_map`:
 *   · DATOS  → el valor del trato (mismo origen que la carátula: ContractTemplateRenderer::valuesFor).
 *   · FIRMAS → la autógrafa CONGELADA de cada firmante del sobre (misma fuente que el render HTML:
 *              sigMapForEnvelope). Pendiente / sin imagen → no se dibuja.
 *
 * Coordenadas: el `field_map` guarda x/y/w en % del tamaño de página; aquí se convierten a PUNTOS con
 * el tamaño real de cada página (getTemplateSize). El origen de FPDF es la esquina superior izquierda
 * (y crece hacia abajo), igual que el editor → mapeo directo.
 *
 * CrewCare no redacta: solo ubica y estampa (frontera legal, ver contract-builder-legal-boundary).
 * PDFs CIFRADOS/protegidos: el parser libre de FPDI no los abre → error claro para que se re-guarde
 * sin candado (no se corrompe nada).
 */
class ContractPdfStamper
{
    /** Estampa el PDF de un SOBRE: resuelve datos + firmas reales y devuelve los bytes del PDF final. */
    public static function stampForEnvelope(ContractEnvelope $envelope, ContractTemplate $template): string
    {
        $contract = $envelope->contract;
        $values   = $contract ? ContractTemplateRenderer::valuesFor($contract) : [];
        $sigMap   = ContractTemplateRenderer::sigMapForEnvelope($envelope);

        return self::stamp($template, $values, $sigMap);
    }

    /** Estampa con datos de EJEMPLO + firmas de muestra (previsualización del editor, sin sobre). */
    public static function stampSample(ContractTemplate $template, array $values, array $sigMap): string
    {
        return self::stamp($template, $values, $sigMap);
    }

    /**
     * Núcleo: importa el PDF original y sobrepone las etiquetas del `field_map`.
     *
     * @param array $values  token → valor (para las etiquetas de DATO)
     * @param array $sigMap   clave → ['image'=>dataURI, 'signer'=>...]|null (para las de FIRMA)
     */
    public static function stamp(ContractTemplate $template, array $values, array $sigMap): string
    {
        return self::stampSource(self::sourcePath($template), $template->placedFields(), $values, $sigMap);
    }

    /**
     * Estampa las etiquetas sobre los BYTES de un PDF YA RENDERIZADO (p.ej. un contrato HTML pasado por
     * Chrome). Igual que {@see stamp()} pero la "hoja base" no es un archivo subido sino bytes en memoria
     * (se materializan a un temporal que FPDI pueda importar). Es la costura que unifica plantillas HTML
     * y PDF: ambas terminan siendo "hoja fija + etiquetas por coordenadas".
     */
    public static function stampBytes(string $pdfBytes, array $fields, array $values, array $sigMap): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ccbase');
        if ($tmp === false || @file_put_contents($tmp, $pdfBytes) === false) {
            throw new \RuntimeException(__('No se pudo preparar el PDF base para estampar.'));
        }

        try {
            return self::stampSource($tmp, $fields, $values, $sigMap);
        } finally {
            @unlink($tmp);
        }
    }

    /** Núcleo compartido: importa el PDF de $abs y sobrepone $fields (datos + firmas) por coordenadas. */
    private static function stampSource(string $abs, array $fields, array $values, array $sigMap): string
    {
        $pdf = new Fpdi('P', 'pt');
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);

        try {
            $pageCount = $pdf->setSourceFile($abs);
        } catch (\Throwable $e) {
            // Cifrado / protegido / corrupto: el parser libre no puede. Mensaje accionable.
            throw new \RuntimeException(
                __('No se pudo leer el PDF (¿está protegido con contraseña o candado?). Re-guárdalo sin protección y vuelve a subirlo.'),
                0,
                $e
            );
        }

        // Etiquetas agrupadas por página (fuera de rango → se ignoran, no rompen).
        $byPage = [];
        foreach ($fields as $f) {
            $byPage[(int) $f['page']][] = $f;
        }

        $tmp = [];   // archivos temporales de firma, a limpiar al final
        try {
            for ($p = 1; $p <= $pageCount; $p++) {
                $tid  = $pdf->importPage($p);
                $size = $pdf->getTemplateSize($tid);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($tid);

                foreach (($byPage[$p] ?? []) as $f) {
                    $x = ((float) $f['x_pct'] / 100) * $size['width'];
                    $y = ((float) $f['y_pct'] / 100) * $size['height'];
                    $w = ((float) $f['w_pct'] / 100) * $size['width'];

                    if ($f['type'] === 'sign') {
                        self::drawSignature($pdf, $sigMap[$f['key']] ?? null, $x, $y, $w, $tmp);
                    } else {
                        $val = $values[$f['key']] ?? null;
                        if ($val !== null && $val !== '') {
                            self::drawText($pdf, (string) $val, $x, $y, $w);
                        }
                    }
                }
            }

            return (string) $pdf->Output('S');
        } finally {
            foreach ($tmp as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
    }

    /** Ruta absoluta del PDF original en el disco privado; 404 lógico si falta. */
    private static function sourcePath(ContractTemplate $template): string
    {
        if (! $template->isPdfSource() || ! $template->pdf_path
            || ! Storage::disk('local')->exists($template->pdf_path)) {
            throw new \RuntimeException(__('La plantilla no tiene un PDF cargado.'));
        }

        return Storage::disk('local')->path($template->pdf_path);
    }

    /** Texto de dato en (x,y) con ancho w. Fuente base WinAnsi → convertir para acentos/ñ. */
    private static function drawText(Fpdi $pdf, string $text, float $x, float $y, float $w): void
    {
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetTextColor(15, 17, 21);
        $pdf->SetXY($x, $y);
        $pdf->Cell($w, 12, self::win1252($text), 0, 0, 'L');
    }

    /**
     * Firma en (x,y) con ancho w y alto según proporción. Si no hay imagen embebible (pendiente, o
     * formato no rasterizable como SVG), cae a escribir el nombre del firmante para no dejar el espacio
     * vacío. Debajo estampa el SELLO tipo DocuSign — "Firmado por: … + HASH" — para que TODA firma
     * muestre su integridad (misma exigencia que el render HTML). Sin hash (preview/pendiente) no se
     * dibuja el sello.
     */
    private static function drawSignature(Fpdi $pdf, $sig, float $x, float $y, float $w, array &$tmp): void
    {
        if (! is_array($sig) || empty($sig['image'])) {
            return;   // pendiente de firma → no se dibuja
        }

        [$file, $type] = self::materialize((string) $sig['image']);
        if ($file) {
            $tmp[] = $file;
            // Alto real según la proporción de la imagen (para saber dónde cae el sello debajo).
            $info = @getimagesize($file);
            $h = ($info && ! empty($info[0])) ? $w * ($info[1] / $info[0]) : $w * 0.34;
            $h = min($h, $w * 0.7);   // cota: una autógrafa no debe crecer sin límite
            $pdf->Image($file, $x, $y, $w, $h, $type);
            self::drawSeal($pdf, $sig, $x, $y + $h + 1.5, $w);

            return;
        }

        // Fallback legible: el nombre en cursiva, con una línea base + el sello debajo.
        $name = self::win1252((string) ($sig['signer'] ?? ''));
        if ($name !== '') {
            $pdf->SetFont('Helvetica', 'I', 12);
            $pdf->SetTextColor(15, 17, 21);
            $pdf->SetXY($x, $y);
            $pdf->Cell($w, 14, $name, 0, 0, 'L');
            self::drawSeal($pdf, $sig, $x, $y + 15, $w);
        }
    }

    /**
     * SELLO bajo la firma: "Firmado por: <nombre>" + el HASH completo del sello (verde=íntegra). Las
     * fuentes core de FPDF (WinAnsi) no tienen ✓, así que se usa texto ("Integra"/"ALTERADA"). El hash
     * hexadecimal (64) se envuelve con MultiCell dentro del ancho de la firma.
     */
    private static function drawSeal(Fpdi $pdf, array $sig, float $x, float $y, float $w): void
    {
        $hash = trim((string) ($sig['hash'] ?? ''));
        if ($hash === '') {
            return;   // sin sello (preview/pendiente) → nada que mostrar
        }

        $verified = $sig['verified'] ?? null;
        $signer   = self::win1252((string) ($sig['signer'] ?? ''));

        // "Firmado por: Nombre" en gris.
        $pdf->SetFont('Helvetica', '', 5.5);
        $pdf->SetTextColor(107, 116, 130);
        $pdf->SetXY($x, $y);
        $pdf->Cell($w, 6, self::win1252(__('Firmado por:') . ' ') . $signer, 0, 2, 'L');

        // Estado de integridad (verde íntegra / rojo alterada / gris firmada).
        if ($verified === true) {
            $pdf->SetTextColor(21, 128, 61);
            $state = __('Integra');
        } elseif ($verified === false) {
            $pdf->SetTextColor(185, 28, 28);
            $state = __('ALTERADA');
        } else {
            $pdf->SetTextColor(107, 116, 130);
            $state = __('Firmada');
        }
        $pdf->SetX($x);
        $pdf->Cell($w, 5, self::win1252($state) . ' - ' . self::win1252(__('Sello SHA-256')) . ':', 0, 2, 'L');

        // El hash completo, envuelto dentro del ancho de la firma.
        $pdf->SetFont('Courier', '', 5);
        $pdf->SetTextColor(91, 100, 114);
        $pdf->SetX($x);
        $pdf->MultiCell($w, 5, $hash, 0, 'L');
    }

    /**
     * data:image/...;base64,... → archivo PNG temporal NORMALIZADO con GD (garantiza un PNG que FPDF
     * parsea sin sorpresas y conserva la transparencia de la autógrafa). No rasterizable (SVG, basura,
     * vacío) → [null, null] y el llamador cae al nombre. Sin GD, embebe PNG/JPG tal cual.
     */
    private static function materialize(string $dataUri): array
    {
        if (! preg_match('#^data:image/([a-z0-9.+-]+);base64,(.+)$#is', trim($dataUri), $m)) {
            return [null, null];
        }
        $bin = base64_decode($m[2], true);
        if ($bin === false || $bin === '') {
            return [null, null];
        }

        // Sin GD: solo se puede embeber PNG/JPG crudo (FPDF no lee SVG/WebP igual).
        if (! function_exists('imagecreatefromstring')) {
            $sub = strtolower($m[1]);
            if ($sub !== 'png' && $sub !== 'jpeg' && $sub !== 'jpg') {
                return [null, null];
            }
            $file = tempnam(sys_get_temp_dir(), 'ccsig');
            if ($file === false || @file_put_contents($file, $bin) === false) {
                return [null, null];
            }

            return [$file, $sub === 'png' ? 'PNG' : 'JPG'];
        }

        // Normaliza con GD: cualquier formato que GD lea → PNG limpio con alpha para FPDF.
        $img = @imagecreatefromstring($bin);
        if ($img === false) {
            return [null, null];   // SVG / corrupto → el llamador escribe el nombre
        }
        imagealphablending($img, false);
        imagesavealpha($img, true);
        $file = tempnam(sys_get_temp_dir(), 'ccsig');
        $ok = $file !== false && @imagepng($img, $file);
        imagedestroy($img);

        return $ok ? [$file, 'PNG'] : [null, null];
    }

    /** UTF-8 → Windows-1252 (encoding de las fuentes core de FPDF): conserva acentos, ñ, guion largo. */
    private static function win1252(string $s): string
    {
        return mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
    }
}
