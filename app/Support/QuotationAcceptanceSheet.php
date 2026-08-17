<?php

namespace App\Support;

use App\Models\Quotation;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * COTIZACIÓN · HOJA DE ACEPTACIÓN (dompdf). NO se estampa nada sobre el PDF subido (eso lo volvería
 * no byte-intact y se perdería la prueba de qué se recibió). En su lugar, esta hoja SEPARADA carga:
 * el HASH del documento aceptado, el total, quién aceptó (Line Producer), cuándo y su AUTÓGRAFA.
 * La hoja es un RENDER de datos YA sellados (el sello vive en la fila de la cotización); el original
 * y la hoja viajan juntos, como la carátula y el clausulado del contrato.
 */
class QuotationAcceptanceSheet
{
    /** Bytes del PDF de la hoja de aceptación. */
    public static function pdf(Quotation $quotation): string
    {
        $quotation->loadMissing('acceptedVersion.items', 'currentVersion.items', 'acceptedBy', 'department');
        $version = $quotation->acceptedVersion ?: $quotation->currentVersion;
        $sig     = $quotation->signatures()->latest('id')->first();

        return Pdf::loadView('quotations.acceptance-sheet', [
            'q'         => $quotation,
            'version'   => $version,
            'signature' => $sig,
            'acceptor'  => $quotation->acceptedBy,
            'verifyUrl' => SealVerifier::urlFor($quotation),
            'identicon' => $sig ? SealVerifier::identiconSvg($sig->document_hash, 60) : null,
        ])->setPaper('letter', 'portrait')->output();
    }
}
