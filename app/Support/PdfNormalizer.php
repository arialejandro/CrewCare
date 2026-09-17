<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use Symfony\Component\Process\Process;

/**
 * PDF FILLABLE · normaliza el PDF subido para que FPDI pueda estamparlo.
 *
 * El parser libre de FPDI abre la mayoría de PDFs (incluidos comprimidos NO cifrados), pero algunos
 * guardados con estructuras raras o "protección" ligera sin contraseña le fallan. Si hay Ghostscript
 * disponible, se re-escribe el PDF a un 1.4 limpio (best-effort). Sin `gs`, no se toca nada y el
 * estampador dará su error claro al firmar → el usuario re-guarda el PDF sin protección.
 */
class PdfNormalizer
{
    /** ¿FPDI puede abrir este PDF (ruta absoluta)? */
    public static function canParse(string $absPath): bool
    {
        try {
            (new Fpdi())->setSourceFile($absPath);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Deja el PDF (ruta RELATIVA del disco) legible por FPDI. Si ya lo es, no hace nada. Si no, y hay
     * Ghostscript, lo normaliza EN SU LUGAR. Devuelve true si al final quedó legible.
     */
    public static function ensureReadable(string $relPath, string $disk = 'local'): bool
    {
        $abs = Storage::disk($disk)->path($relPath);
        if (self::canParse($abs)) {
            return true;
        }

        $gs = self::gsBinary();
        if (! $gs) {
            return false;   // sin gs: se queda como está; el estampador avisará al firmar
        }

        $out = tempnam(sys_get_temp_dir(), 'ccgs');
        try {
            $proc = new Process([
                $gs, '-sDEVICE=pdfwrite', '-dCompatibilityLevel=1.4',
                '-dNOPAUSE', '-dBATCH', '-dQUIET', '-dSAFER',
                '-sOutputFile=' . $out, $abs,
            ]);
            $proc->setTimeout(60);
            $proc->run();

            if ($proc->isSuccessful() && is_file($out) && filesize($out) > 0 && self::canParse($out)) {
                Storage::disk($disk)->put($relPath, (string) file_get_contents($out));

                return true;
            }
        } catch (\Throwable $e) {
            // best-effort: si gs no corre, se ignora
        } finally {
            if (is_file($out)) {
                @unlink($out);
            }
        }

        return false;
    }

    /** Binario de Ghostscript utilizable, o null. Hint por env GHOSTSCRIPT_BIN; PATH; rutas típicas. */
    public static function gsBinary(): ?string
    {
        foreach (array_filter([getenv('GHOSTSCRIPT_BIN') ?: null, 'gswin64c', 'gswin32c', 'gs']) as $bin) {
            try {
                $p = new Process([$bin, '--version']);
                $p->setTimeout(10);
                $p->run();
                if ($p->isSuccessful()) {
                    return $bin;
                }
            } catch (\Throwable $e) {
                // siguiente candidato
            }
        }

        foreach (glob('C:\\Program Files\\gs\\gs*\\bin\\gswin64c.exe') ?: [] as $found) {
            return $found;
        }

        return null;
    }
}
