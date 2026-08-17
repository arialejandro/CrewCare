<?php

namespace App\Support;

use App\Models\ContractEnvelope;
use App\Models\ContractEnvelopeEvent;
use App\Models\ContractTemplate;
use Illuminate\Support\Facades\Storage;

/**
 * EL CONTRATO · PASO C · FASE 3 — DOCUMENTO FIRMADO REAL. Toma el contrato armado con la plantilla
 * activa + las AUTÓGRAFAS congeladas de cada destinatario (la misma composición que muestra
 * ContractEnvelopeController::templateDocument, pero aquí se CONGELA), lo pasa a PDF por Chrome
 * headless (Browsershot, vía PdfExporter) y lo guarda en el disco privado. Deja su hash en la
 * bitácora (evento 'sealed') → la integridad del firmado se ancla en la cadena inmutable, no en el
 * sello del paquete.
 *
 * DEFENSIVO por diseño: si la producción no tiene plantilla activa para el subtipo, no hay nada que
 * congelar (se sigue entregando el paquete byte-intact) → devuelve null sin tocar nada. El motor PDF
 * es una costura compartida ({@see ContractPdf}) para poder probar el flujo sin levantar Chrome.
 */
class ContractSignedRenderer
{
    /**
     * El CONTRATO FIRMADO como HTML autónomo (listo para PDF), o null si no hay plantilla activa para
     * el subtipo del contrato. Idéntica composición a templateDocument(), pero como servicio reusable.
     */
    public static function renderHtml(ContractEnvelope $envelope): ?string
    {
        $contract = $envelope->contract;
        if (! $contract) {
            return null;
        }

        $template = ContractTemplate::activeFor($envelope->production_id, $contract->concept);
        if (! $template || $template->isPdfSource()) {
            return null;   // sin plantilla (o PDF subido, que no tiene HTML) → se congela por otra vía
        }

        $envelope->loadMissing('recipients');
        $inner = ContractTemplateRenderer::render(
            $template,
            ContractTemplateRenderer::valuesFor($contract),
            ContractTemplateRenderer::sigMapForEnvelope($envelope)
        );

        return ContractTemplateRenderer::page(
            $inner, $template->architecture, $template->page_size, null, $template->font_family, $template->font_size
        );
    }

    /**
     * Renderiza + CONGELA el contrato firmado: guarda el PDF, escribe `signed_document` y registra el
     * evento 'sealed' con el hash. Devuelve la metadata { path, hash, bytes, rendered_at, engine } o
     * null si no había plantilla / el motor no devolvió bytes. Nunca lanza por sí mismo el fallo del
     * motor: eso lo envuelve el Job que la llama.
     */
    public static function store(ContractEnvelope $envelope): ?array
    {
        $contract = $envelope->contract;
        if (! $contract) {
            return null;
        }
        $template = ContractTemplate::activeFor($envelope->production_id, $contract->concept);
        if (! $template) {
            return null;   // sin plantilla → nada que congelar; se entrega el paquete byte-intact
        }

        $envelope->loadMissing('recipients');

        // PDF FILLABLE: la plantilla es un PDF subido → se estampa ENCIMA (FPDI), conservando el texto.
        // Plantilla HTML → se renderiza por Chrome headless (costura ContractPdf). Mismo carril después:
        // guardar byte-intact + hash en la bitácora.
        if ($template->isPdfSource()) {
            $bytes  = ContractPdfStamper::stampForEnvelope($envelope, $template);
            $engine = 'fpdi-overlay';
        } else {
            $html = self::renderHtml($envelope);
            if ($html === null) {
                return null;
            }
            $bytes  = ContractPdf::render($html);
            $engine = 'browsershot';
        }

        if ($bytes === '' || $bytes === null) {
            return null;
        }

        $path = 'contracts/signed/env-' . $envelope->id . '.pdf';
        Storage::disk('local')->put($path, $bytes);

        $meta = [
            'path'        => $path,
            'hash'        => hash('sha256', $bytes),
            'bytes'       => strlen($bytes),
            'rendered_at' => now()->toDateTimeString(),
            'engine'      => $engine,
        ];
        $envelope->update(['signed_document' => $meta]);

        // Ancla la integridad del firmado en la cadena inmutable (el sello del paquete no lo cubre).
        ContractEventLog::record($envelope, ContractEnvelopeEvent::SEALED, [
            'payload' => ['hash' => $meta['hash'], 'bytes' => $meta['bytes'], 'path' => $path],
        ]);

        return $meta;
    }
}
