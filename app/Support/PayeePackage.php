<?php

namespace App\Support;

use App\Models\DocumentRequirement;
use App\Models\DocumentType;
use App\Models\ExternalAuthorization;
use App\Models\PayeeContract;
use App\Models\ProductionDocumentSetting;
use Illuminate\Support\Collection;

/**
 * PASO 2 — resuelve QUIÉN PIDE QUÉ (los paquetes configurables por producción) y evalúa
 * el cumplimiento de un documento CONTRA su requisito. Es lectura/derivación: NO captura
 * ni sella nada (la captura y el estado RECIBIDO son el Paso 3). Aquí solo se calcula
 * "en regla" (existe + 32-D positiva + no caducado según la fecha de corte de la producción).
 *
 * Ejes que respeta:
 *  - IDENTIDAD (fiscal) vs CONTRATO (facturas + REPSE). No se mezclan.
 *  - REPSE es del CONTRATO: sus dos tandas entran solo si el contrato es REPSE; apagarlo
 *    las quita del REQUISITO (no borra lo ya capturado).
 *  - ANTES / DESPUÉS DEL PAGO se DERIVA del tipo (repse_phase), no se duplica.
 */
class PayeePackage
{
    // Estado de cumplimiento de un requisito (derivado, no almacenado).
    const ST_MISSING      = 'missing';       // no se recibió
    const ST_RECEIVED     = 'received';      // recibido y en regla
    const ST_NOT_POSITIVE = 'not_positive';  // recibido pero la 32-D no vino positiva
    const ST_EXPIRED      = 'expired';       // recibido pero caducado

    /**
     * Tipos exigidos a la IDENTIDAD (paquete fiscal), por naturaleza y NACIONALIDAD. El
     * extranjero recibe su paquete alterno (pasaporte/visa/residencia) en vez de INE/CSF/32-D,
     * así llega al 100% sin `missing` permanente. Los tipos con nationality NULL aplican a ambos.
     */
    public static function identityRequirements($productionId, string $legalNature, string $nationality = 'mexicana'): Collection
    {
        $ids = DocumentRequirement::required()
            ->where('production_id', $productionId)
            ->whereIn('applies_to', [DocumentRequirement::APPLIES_AMBAS, $legalNature])
            ->pluck('document_type_id');

        return DocumentType::whereIn('id', $ids)
            ->where('family', DocumentType::FAMILY_BILLING)
            ->where('scope', DocumentType::SCOPE_IDENTITY)
            ->where(fn ($q) => $q->whereNull('nationality')->orWhere('nationality', $nationality))
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Tipos exigidos al CONTRATO (facturas + REPSE si aplica). REPSE solo si el contrato
     * es REPSE → apagarlo los quita del requisito. $phase opcional: 'before' | 'after'.
     */
    public static function contractRequirements($productionId, PayeeContract $contract, ?string $phase = null): Collection
    {
        $nature = optional($contract->payee)->legal_nature ?? DocumentRequirement::APPLIES_AMBAS;

        $ids = DocumentRequirement::required()
            ->where('production_id', $productionId)
            ->whereIn('applies_to', [DocumentRequirement::APPLIES_AMBAS, $nature])
            ->pluck('document_type_id');

        $q = DocumentType::whereIn('id', $ids)
            ->where('family', DocumentType::FAMILY_BILLING)
            ->where('scope', DocumentType::SCOPE_CONTRACT);

        if (! $contract->is_repse) {
            $q->where('is_repse', 0);   // sin REPSE no entran las dos tandas
        }
        if ($phase === DocumentType::PHASE_AFTER) {
            $q->where('repse_phase', DocumentType::PHASE_AFTER);
        } elseif ($phase === DocumentType::PHASE_BEFORE) {
            $q->where(function ($w) {
                $w->whereNull('repse_phase')->orWhere('repse_phase', DocumentType::PHASE_BEFORE);
            });
        }

        return $q->orderBy('sort_order')->get();
    }

    /**
     * VENTANA DE RECEPCIÓN — tipos que un PERIODO DE PAGO espera de un contrato: el paquete
     * "para cobrar" RECURRENTE (los que CADUCAN y se re-piden cada periodo: CSF, 32-D, factura)
     * MÁS la tanda REPSE "DESPUÉS del pago" si el contrato es REPSE (cuelga del periodo EN QUE
     * se pagó, §5 → se consume `repse_phase`, no se duplica).
     *
     * NO entra lo de ALTA (una sola vez): los identitarios PERMANENTES (INE, acta constitutiva)
     * ni la tanda REPSE "ANTES del pago". Deriva del catálogo; no almacena nada.
     */
    public static function periodRequirements($productionId, PayeeContract $contract): Collection
    {
        $payee       = $contract->payee;
        $nature      = optional($payee)->legal_nature ?? DocumentRequirement::APPLIES_AMBAS;
        $nationality = optional($payee)->nationality ?: 'mexicana';

        // Identidad RECURRENTE (los permanentes/sin vigencia son de alta, no de periodo).
        $identity = self::identityRequirements($productionId, $nature, $nationality)
            ->reject(fn ($t) => $t->validity_shape === DocumentType::V_PERMANENT || $t->validity_shape === null);

        // Contrato: facturas + REPSE "después" (se quita la tanda "antes", que es de alta).
        $contractTypes = self::contractRequirements($productionId, $contract)
            ->reject(fn ($t) => $t->repse_phase === DocumentType::PHASE_BEFORE);

        return $identity->concat($contractTypes)->unique('id')->values();
    }

    /**
     * Evalúa un tipo requerido contra los documentos capturados de un titular. $documents
     * = colección de ExternalAuthorization del holder. $cutDay = fecha de corte de la 32-D
     * de la producción (cambia el cálculo de caducidad de los "mes corriente").
     */
    public static function evaluate(DocumentType $type, $documents, int $cutDay): string
    {
        $doc = collect($documents)
            ->where('document_type_id', $type->id)
            ->where('is_active', true)
            ->sortByDesc('id')
            ->first();

        if ($doc === null) {
            return self::ST_MISSING;
        }

        // La 32-D exige POSITIVA: existir no basta (se consume requires_positive_status).
        if ($type->requires_positive_status && $doc->result_status !== ExternalAuthorization::RESULT_POSITIVE) {
            return self::ST_NOT_POSITIVE;
        }

        $expiry = $doc->valid_until
            ? ($doc->valid_until instanceof \Carbon\Carbon ? $doc->valid_until : \Carbon\Carbon::parse($doc->valid_until))
            : $type->expiryFrom($doc->issued_at, $cutDay);

        if ($expiry !== null && $expiry->startOfDay()->lessThan(now()->startOfDay())) {
            return self::ST_EXPIRED;
        }

        return self::ST_RECEIVED;
    }

    /** Atajo: fecha de corte de la 32-D para una producción. */
    public static function cutDay($productionId): int
    {
        return ProductionDocumentSetting::cutDayFor($productionId);
    }
}
