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
     * Renderiza + CONGELA el CONJUNTO firmado: el CONTRATO principal (plantilla-contrato → `signed_document`)
     * y cada ANEXO (plantilla-anexo → lista `signed_annexes`), cada uno estampado con los MISMOS datos +
     * firmas del sobre. Registra el evento 'sealed' con el hash del principal + el conteo. Devuelve la
     * metadata del principal (o, si solo hubo anexos, un resumen), o null si no había plantillas.
     * Nunca lanza por sí mismo el fallo del motor: eso lo envuelve el Job que la llama.
     */
    public static function store(ContractEnvelope $envelope): ?array
    {
        $contract = $envelope->contract;
        if (! $contract) {
            return null;
        }

        $envelope->loadMissing('recipients');
        $values = ContractTemplateRenderer::valuesFor($contract);
        $sigMap = ContractTemplateRenderer::sigMapForEnvelope($envelope);

        // ── Documento PRINCIPAL (plantilla-contrato activa) ──
        $mainMeta = null;
        if ($main = ContractTemplate::activeFor($envelope->production_id, $contract->concept)) {
            $bytes = self::bytesFor($main, $envelope, $values, $sigMap);
            if ($bytes !== '') {
                $path = 'contracts/signed/env-' . $envelope->id . '.pdf';
                Storage::disk('local')->put($path, $bytes);
                $mainMeta = self::meta($path, $bytes, $main);
            }
        }

        // ── ANEXOS (cada plantilla-anexo activa, estampada igual) ──
        $annexMetas = [];
        foreach (ContractTemplate::activeAnnexes($envelope->production_id, $contract->concept) as $i => $annex) {
            $bytes = self::bytesFor($annex, $envelope, $values, $sigMap);
            if ($bytes === '') {
                continue;
            }
            $path = 'contracts/signed/env-' . $envelope->id . '-anexo-' . ($i + 1) . '.pdf';
            Storage::disk('local')->put($path, $bytes);
            $annexMetas[] = self::meta($path, $bytes, $annex);
        }

        // ── HOJA firmada: si el paquete llevaba Hoja y el contratado ya firmó, se re-renderiza con su
        //    autógrafa + sello y se anexa al conjunto firmado (así la Hoja final SÍ muestra su firma). ──
        $hasHoja = collect($envelope->documents ?? [])->contains(fn ($d) => ($d['kind'] ?? null) === 'infosheet');
        $contracted = $envelope->recipients->firstWhere('anchor_key', 'contratado');
        if ($hasHoja && $contracted && $contracted->isSigned() && $contracted->signature_image) {
            $csig = $contracted->signatures()->latest('id')->first();
            try {
                $bytes = InfosheetSheet::renderPdf($contract, ['contractedSig' => [
                    'image'    => $contracted->signature_image,
                    'name'     => $contracted->name,
                    'hash'     => optional($csig)->document_hash,
                    'verified' => $contracted->verifyLatestSignature(),
                ]]);
                if ($bytes !== '') {
                    $path = 'contracts/signed/env-' . $envelope->id . '-hoja.pdf';
                    Storage::disk('local')->put($path, $bytes);
                    $annexMetas[] = [
                        'path' => $path, 'hash' => hash('sha256', $bytes), 'bytes' => strlen($bytes),
                        'rendered_at' => now()->toDateTimeString(), 'engine' => 'browsershot',
                        'name' => __('Hoja de información'), 'template_id' => null,
                    ];
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('ContractSignedRenderer: la Hoja firmada falló — ' . $e->getMessage());
            }
        }

        if (! $mainMeta && empty($annexMetas)) {
            return null;   // sin plantillas → nada que congelar; se entrega el paquete byte-intact
        }

        if ($mainMeta) {
            $envelope->signed_document = $mainMeta;
        }
        $envelope->signed_annexes = $annexMetas;
        $envelope->save();

        // Ancla la integridad del conjunto en la cadena inmutable (el sello del paquete no lo cubre).
        ContractEventLog::record($envelope, ContractEnvelopeEvent::SEALED, [
            'payload' => [
                'hash'      => $mainMeta['hash'] ?? null,
                'annexes'   => count($annexMetas),
                'documents' => ($mainMeta ? 1 : 0) + count($annexMetas),
            ],
        ]);

        return $mainMeta ?: ['annexes' => count($annexMetas)];
    }

    /**
     * Bytes del PDF firmado de UNA plantilla — vía el motor UNIFICADO: hoja base (PDF subido o HTML por
     * Chrome) + etiquetas por coordenadas (field_map). Idéntico a antes para plantillas sin field_map.
     */
    private static function bytesFor(ContractTemplate $template, ContractEnvelope $envelope, array $values, array $sigMap): string
    {
        return ContractDocRenderer::renderPdf($template, $values, $sigMap);
    }

    /** Metadata de un documento firmado congelado. */
    private static function meta(string $path, string $bytes, ContractTemplate $template): array
    {
        return [
            'path'        => $path,
            'hash'        => hash('sha256', $bytes),
            'bytes'       => strlen($bytes),
            'rendered_at' => now()->toDateTimeString(),
            'engine'      => $template->isPdfSource() ? 'fpdi-overlay' : 'browsershot',
            'name'        => $template->name,
            'template_id' => $template->id,
        ];
    }
}
