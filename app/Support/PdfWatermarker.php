<?php

namespace App\Support;

/**
 * MARCA DE AGUA por DESTINATARIO — motor reusable (no depende de ningún módulo).
 *
 * Toma los BYTES de un PDF y devuelve otros bytes con el nombre EN CRÉDITOS de quien lo recibe en
 * UNA SOLA LÍNEA grande y tenue, en diagonal, sobre cada página — tal como se ve en los llamados
 * reales (Scenechronize). FPDI importa cada página tal cual → el texto original queda intacto y
 * seleccionable; la marca va ENCIMA. Sirve para trazar filtraciones: cada copia enviada lleva
 * grabado a quién se le entregó.
 *
 * Decisiones (2026-08-24, owner): UNA sola línea (nombre en créditos), grande, diagonal, tenue —
 * NO tapizada, NO línea al pie. Gris claro en vez de alpha real (ExtGState) para no arriesgar el PDF
 * en un cron desatendido. PDFs cifrados/protegidos: el parser libre de FPDI no los abre → se devuelve
 * el original SIN marca (nunca rompe el envío) y se deja rastro en el log.
 *
 * Uso:
 *   $bytes = PdfWatermarker::diagonal($pdfBytes, \App\Models\User::displayName($user));
 */
class PdfWatermarker
{
    /** Gris de la marca (tenue, como los llamados reales, pero aún trazable). */
    private const INK = [200, 200, 200];

    /**
     * Devuelve el PDF con $text en UNA línea diagonal grande y tenue sobre cada página.
     *
     * @param string $pdfBytes  bytes del PDF base
     * @param string $text      texto de la marca (el nombre en créditos del destinatario)
     * @param float  $angle     inclinación en grados (45 por defecto)
     * @return string           bytes del PDF marcado; el original si no se pudo procesar
     */
    public static function diagonal(string $pdfBytes, string $text, float $angle = 45.0): string
    {
        $text = trim($text);
        if ($text === '' || $pdfBytes === '') {
            return $pdfBytes;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'ccwm');
        if ($tmp === false || @file_put_contents($tmp, $pdfBytes) === false) {
            return $pdfBytes;
        }

        try {
            return self::render($tmp, $text, $angle);
        } catch (\Throwable $e) {
            // Cifrado / corrupto / cualquier sorpresa → se entrega el original sin marca (best-effort).
            \Illuminate\Support\Facades\Log::warning('PdfWatermarker: sin marca — ' . $e->getMessage());
            return $pdfBytes;
        } finally {
            @unlink($tmp);
        }
    }

    /** Núcleo: importa cada página y estampa UNA línea diagonal grande y centrada. */
    private static function render(string $abs, string $text, float $angle): string
    {
        $pdf = new WatermarkFpdi('P', 'pt');
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);

        $pageCount = $pdf->setSourceFile($abs);
        $label     = self::win1252($text);

        for ($p = 1; $p <= $pageCount; $p++) {
            $tid  = $pdf->importPage($p);
            $size = $pdf->getTemplateSize($tid);
            $w    = (float) $size['width'];
            $h    = (float) $size['height'];

            $pdf->AddPage($size['orientation'], [$w, $h]);
            $pdf->useTemplate($tid);

            $pdf->SetTextColor(self::INK[0], self::INK[1], self::INK[2]);

            // Que la línea abarque ~0.7 de la diagonal de la hoja: medir a un tamaño de referencia y
            // escalar (FPDF escala lineal). Acotado para que un nombre corto no crezca sin fin ni uno
            // muy largo quede ilegible.
            $target = 0.70 * sqrt($w * $w + $h * $h);
            $pdf->SetFont('Helvetica', 'B', 10);
            $ref = max(1.0, $pdf->GetStringWidth($label));   // ancho a 10pt
            $fontSize = max(26.0, min(96.0, $target * 10 / $ref));
            $pdf->SetFont('Helvetica', 'B', $fontSize);

            // Centrar la línea sobre el centro de la hoja y rotarla sobre ese mismo punto.
            $cx = $w / 2; $cy = $h / 2;
            $tw = $pdf->GetStringWidth($label);
            $pdf->rotate($angle, $cx, $cy);
            $pdf->Text($cx - $tw / 2, $cy + $fontSize * 0.32, $label);
            $pdf->rotate(0);
        }

        return (string) $pdf->Output('S');
    }

    /** UTF-8 → Windows-1252 (fuentes core de FPDF): conserva acentos y ñ. */
    private static function win1252(string $s): string
    {
        return mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
    }
}
