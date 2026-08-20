<?php

namespace App\Support;

use App\Models\PayeeContract;

/**
 * LA HOJA DE INFORMACIÓN del trato como PDF. Reusa la MISMA vista que se ve en la ficha
 * (componentes/_infosheet-document, envuelta en infosheet/sheet-pdf) y la pasa por el motor
 * HTML→PDF {@see ContractPdf} (Chrome headless en prod; doble determinista en pruebas). Se
 * congela byte-intact en el sobre para que el CONTRATADO la firme junto al contrato y sus anexos.
 */
class InfosheetSheet
{
    /** Bytes del PDF de la Hoja de Información de este contrato. */
    public static function renderPdf(PayeeContract $contract): string
    {
        // Relaciones que consume el documento (evita nulls sorpresa / N+1).
        $contract->loadMissing(['payee.fiscalRegimes', 'payee.beneficiaries', 'payee.documents.documentType', 'department']);

        $html = view('infosheet.sheet-pdf', ['contract' => $contract])->render();

        return ContractPdf::render($html);
    }
}
