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
    /**
     * HTML autónomo de la Hoja (para embeberla en la ceremonia de firma / pasarla a PDF). $opts admite:
     *   · 'contractedAnchor' => true  → pinta el recuadro clicable "Firma aquí" del contratado (ceremonia).
     *   · 'contractedSig'    => [...] → estampa su autógrafa + hash en el documento final.
     */
    public static function renderHtml(PayeeContract $contract, array $opts = []): string
    {
        // Relaciones que consume el documento (evita nulls sorpresa / N+1).
        $contract->loadMissing(['payee.fiscalRegimes', 'payee.beneficiaries', 'payee.documents.documentType', 'department']);

        return view('infosheet.sheet-pdf', array_merge(['contract' => $contract], $opts))->render();
    }

    /** Bytes del PDF de la Hoja de Información de este contrato. */
    public static function renderPdf(PayeeContract $contract, array $opts = []): string
    {
        return ContractPdf::render(self::renderHtml($contract, $opts));
    }
}
