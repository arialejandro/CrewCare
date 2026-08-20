<?php

namespace App\Support;

use App\Models\ContractEnvelope;
use App\Models\ContractEnvelopeRecipient;
use App\Models\ContractTemplate;
use Illuminate\Support\Facades\URL;

/**
 * EL CONTRATO · PASO C · CEREMONIA DE FIRMA (DocuSign-like). Arma el PAQUETE COMPLETO que ve y firma
 * un destinatario, en orden: el CONTRATO (plantilla principal armada) + cada ANEXO (plantilla-anexo
 * armada) + la Hoja de información. Cada documento trae su MODO de render y MIS lugares de firma:
 *
 *   · plantilla HTML → se embebe el documento armado (mismos datos + firmas del sobre); mis `[[firma]]`
 *     pendientes quedan clicables (por su `data-anchor`) para estampar mi autógrafa en su lugar.
 *   · plantilla PDF  → se sirve el PDF armado (FPDI, texto intacto) y se superponen mis etiquetas de
 *     firma por COORDENADAS (% de página), igual que el editor.
 *   · Hoja / carátula → documento del paquete byte-intact, solo LECTURA (su firma se resuelve aparte).
 *
 * Reusa EXACTAMENTE las costuras del documento firmado final ({@see ContractSignedRenderer},
 * {@see ContractPdfStamper}, {@see ContractTemplateRenderer}): lo que el contratado firma en pantalla
 * es lo mismo que se congela al completar. El servidor, al componer, estampa mi ÚNICA autógrafa en
 * TODAS mis anclas (misma clave) → los taps de la ceremonia son el consentimiento informado, no N
 * firmas distintas.
 */
class ContractCeremony
{
    /** Anclas que le tocan a ESTE destinatario: su ancla + la rúbrica si es el contratado. */
    public static function anchorsFor(ContractEnvelopeRecipient $recipient): array
    {
        $key = (string) $recipient->anchor_key;
        if ($key === '') {
            return [];
        }
        $anchors = [$key];
        if ($key === 'contratado') {
            $anchors[] = 'rubrica';   // la rúbrica es la inicial del contratado
        }

        return $anchors;
    }

    /**
     * El paquete ordenado para la ceremonia. Cada entrada:
     *   ['key','name','mode'=>'html'|'pdf','html'?,'url'?,'anchors'?(html),'tags'?(pdf)]
     */
    public static function documents(ContractEnvelope $envelope, ContractEnvelopeRecipient $recipient): array
    {
        $contract = $envelope->contract;
        if (! $contract) {
            return [];
        }

        $prod    = (int) $envelope->production_id;
        $concept = $contract->concept;
        $mine    = self::anchorsFor($recipient);
        $envelope->loadMissing('recipients');

        $docs = [];

        // ── EL CONTRATO (plantilla principal activa) ──
        $main = ContractTemplate::activeFor($prod, $concept);
        if ($main) {
            $docs[] = self::templateDoc($main, $envelope, $recipient, $mine, __('Contrato'));
        } elseif (($i = self::docIndex($envelope, 'caratula')) !== null) {
            // sin plantilla → la carátula emitida es el contrato (byte-intact del paquete)
            $docs[] = ['key' => 'caratula', 'name' => __('Carátula'), 'mode' => 'pdf', 'url' => self::sealedUrl($recipient, $i), 'tags' => []];
        }

        // ── ANEXOS (cada plantilla-anexo activa que aplica al subtipo) ──
        foreach (ContractTemplate::activeAnnexes($prod, $concept) as $anx) {
            $docs[] = self::templateDoc($anx, $envelope, $recipient, $mine, $anx->name ?: __('Anexo'));
        }

        // ── HOJA DE INFORMACIÓN — el CONTRATADO también la firma (etiqueta en su casillero); para el
        //    resto (o si ya firmó) va de LECTURA byte-intact del paquete. ──
        if (in_array('contratado', $mine, true) && ! $recipient->isSigned()) {
            $docs[] = [
                'key'     => 'hoja',
                'name'    => __('Hoja de información'),
                'mode'    => 'html',
                'html'    => InfosheetSheet::renderHtml($contract, ['contractedAnchor' => true]),
                'anchors' => ['contratado'],
            ];
        } elseif (($i = self::docIndex($envelope, 'infosheet')) !== null) {
            $docs[] = ['key' => 'hoja', 'name' => __('Hoja de información'), 'mode' => 'pdf', 'url' => self::sealedUrl($recipient, $i), 'tags' => []];
        }

        return $docs;
    }

    /** Un documento de plantilla (principal o anexo) armado, con MIS lugares de firma. */
    private static function templateDoc(ContractTemplate $tpl, ContractEnvelope $envelope, ContractEnvelopeRecipient $recipient, array $mine, string $name): array
    {
        // PDF subido: se sirve estampado + etiquetas por coordenadas (solo las de MI ancla).
        if ($tpl->isPdfSource()) {
            $tags = array_values(array_map(
                fn ($f) => [
                    'page'  => (int) $f['page'],
                    'x_pct' => (float) $f['x_pct'],
                    'y_pct' => (float) $f['y_pct'],
                    'w_pct' => (float) ($f['w_pct'] ?? 24),
                ],
                array_filter($tpl->signFields(), fn ($f) => in_array((string) ($f['key'] ?? ''), $mine, true))
            ));

            return [
                'key'  => 'tpl:' . $tpl->id,
                'name' => $name,
                'mode' => 'pdf',
                'url'  => URL::temporarySignedRoute('contracts.sign.template', now()->addHours(3), ['recipient' => $recipient->id, 'template' => $tpl->id]),
                'tags' => $tags,
            ];
        }

        // HTML: documento armado embebible. Mis anclas se FUERZAN pendientes (clicables) por si el
        // estado del sobre las diera por firmadas; el resto conserva su estado real.
        $sigMap = ContractTemplateRenderer::sigMapForEnvelope($envelope);
        foreach ($mine as $k) {
            if (array_key_exists($k, $sigMap) || $k === 'rubrica' || $k === 'contratado') {
                $sigMap[$k] = null;
            }
        }

        $html = ContractTemplateRenderer::page(
            ContractTemplateRenderer::render($tpl, ContractTemplateRenderer::valuesFor($envelope->contract), $sigMap),
            $tpl->architecture, $tpl->page_size, null, $tpl->font_family, $tpl->font_size
        );

        return [
            'key'     => 'tpl:' . $tpl->id,
            'name'    => $name,
            'mode'    => 'html',
            'html'    => $html,
            'anchors' => array_values($mine),   // el front cuenta los [data-anchor] que coincidan
        ];
    }

    /** Índice del primer documento del paquete de cierto `kind` (o null). */
    private static function docIndex(ContractEnvelope $envelope, string $kind): ?int
    {
        foreach (($envelope->documents ?? []) as $i => $d) {
            if (($d['kind'] ?? null) === $kind) {
                return (int) $i;
            }
        }

        return null;
    }

    /** URL firmada para servir un documento SELLADO del paquete (byte-intact). */
    private static function sealedUrl(ContractEnvelopeRecipient $recipient, int $index): string
    {
        return URL::temporarySignedRoute('contracts.sign.document', now()->addHours(3), ['recipient' => $recipient->id, 'index' => $index]);
    }
}
