<?php

namespace App\Support;

use setasign\Fpdi\Fpdi;

/**
 * Ensamble del PAQUETE del llamado: une el FRONT (PDF externo del 2nd AD) con el BACK (CrewCare) en un
 * solo PDF, conservando cada página tal cual (FPDI, igual que {@see ContractPdfStamper}). Las firmas de
 * las 3 figuras se estampan sobre el front reusando el motor de contratos (sin sello cripto para el back,
 * como decidió el owner). No redacta ni altera contenido: solo importa páginas y sobrepone la rúbrica.
 */
class CallPackageAssembler
{
    /** Une varios PDFs (bytes) en uno solo, en orden, conservando cada página. */
    public static function merge(array $pdfBytesList): string
    {
        $pdf = new Fpdi('P', 'pt');
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);

        $tmps = [];
        try {
            foreach ($pdfBytesList as $bytes) {
                if ($bytes === null || $bytes === '') {
                    continue;
                }
                $tmp = tempnam(sys_get_temp_dir(), 'ccpkg');
                file_put_contents($tmp, $bytes);
                $tmps[] = $tmp;

                $count = $pdf->setSourceFile($tmp);
                for ($p = 1; $p <= $count; $p++) {
                    $tid  = $pdf->importPage($p);
                    $size = $pdf->getTemplateSize($tid);
                    $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $pdf->useTemplate($tid);
                }
            }

            return (string) $pdf->Output('S');
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                __('No se pudo unir el PDF (¿el front está protegido con contraseña?). Re-guárdalo sin candado y vuelve a subirlo.'),
                0,
                $e
            );
        } finally {
            foreach ($tmps as $f) {
                @unlink($f);
            }
        }
    }

    /** Estampa las firmas (rúbricas) sobre el front por coordenadas — reusa el motor de contratos. */
    public static function stampFront(string $frontBytes, array $fieldMap, array $sigMap): string
    {
        return ContractPdfStamper::stampBytes($frontBytes, $fieldMap, [], $sigMap);
    }

    /** Número de páginas de un PDF (para saber sobre cuántas hojas colocar las firmas). */
    public static function pageCount(string $bytes): int
    {
        $pdf = new Fpdi();
        $tmp = tempnam(sys_get_temp_dir(), 'ccpc');
        file_put_contents($tmp, $bytes);
        try {
            return (int) $pdf->setSourceFile($tmp);
        } catch (\Throwable $e) {
            return 0;
        } finally {
            @unlink($tmp);
        }
    }
}
