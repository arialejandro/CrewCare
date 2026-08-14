<?php

namespace App\Support;

use App\Exceptions\ContractEmitException;
use App\Models\ContractClause;
use App\Models\PayeeContract;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * EL CONTRATO · PASO B — EMITIR. Produce el documento (la carátula) y CONGELA lo que el contrato
 * debe seguir diciendo aunque después cambie la fuente:
 *  - clausulado + VERSIÓN exacta e IDIOMA con que se emitió,
 *  - datos del CONTRATANTE (de settings; si falta alguno, NO emite y lo dice claro),
 *  - nombre en CRÉDITOS (del alta) y BENEFICIARIO (del intake).
 *
 * NO concatena: la carátula es un PDF propio; el clausulado se conserva byte-intact aparte. Ambos
 * viajan como dos documentos del SOBRE (Paso C). Un contrato ya emitido NO se re-emite.
 */
class ContractEmitter
{
    public static function emit(PayeeContract $contract, ContractClause $clause, ?string $language = null, ?User $actor = null): PayeeContract
    {
        // (Paso C) También se emiten y firman contratos NO-crew (renta/servicio: ambulancia,
        // proveedores, casas de renta, seguridad fílmica). La carátula oculta los renglones de
        // crew vacíos, así que no hace falta gatear por concepto.
        if ($contract->isEmitted()) {
            throw new ContractEmitException('Este contrato ya fue emitido; un emitido no se re-emite ni se edita.');
        }
        if (! $clause->appliesToSubtype($contract->concept)) {
            throw new ContractEmitException('El clausulado seleccionado no aplica a este subtipo de contrato.');
        }

        // El idioma se HEREDA del clausulado pero se puede cambiar antes de emitir.
        $language = $language ?: ($contract->language ?: $clause->language);
        if (! array_key_exists($language, ContractClause::languages())) {
            $language = $clause->language;
        }

        // CONTRATANTE completo o no se emite (se avisa claro).
        $missing = ContractCoverSheet::missingContractor();
        if (! empty($missing)) {
            throw ContractEmitException::missingContractor($missing);
        }

        // ── Congelar (en memoria; luego se guarda) ──
        $ct = ContractCoverSheet::contractorFromSettings();
        $contract->contractor_legal_name    = $ct['legal_name'];
        $contract->contractor_rfc           = $ct['rfc'];
        $contract->contractor_address       = $ct['address'];
        $contract->contractor_representative = $ct['representative'];
        $contract->contractor_email         = $ct['email'];

        // Nombre en créditos (del alta): ncreditos del usuario ligado, si no el nombre del payee.
        if (! trim((string) $contract->credit_name)) {
            $credit = trim((string) optional(optional($contract->payee)->user)->ncreditos);
            $contract->credit_name = $credit !== '' ? $credit : optional($contract->payee)->name;
        }

        // Beneficiario (del intake): nombre + parentesco. El teléfono NO se captura hoy en el intake
        // (PayeeBeneficiary no tiene teléfono) → queda null hasta que el intake lo capture (aditivo futuro).
        if (! trim((string) $contract->beneficiary_name) && $contract->payee) {
            $b = $contract->payee->beneficiaries()->orderBy('sort_order')->first();
            if ($b) {
                $contract->beneficiary_name         = $b->full_name;
                $contract->beneficiary_relationship = $b->relationship;
            }
        }

        $contract->clause_id     = $clause->id;
        $contract->language      = $language;
        $contract->emitted_at    = now();
        $contract->emitted_by_id = optional($actor)->id;

        // Generar la carátula (usa lo ya congelado en memoria) y guardarla.
        $bytes = ContractCoverSheet::render($contract, $language);
        $path  = 'contracts/caratula/' . $contract->id . '_' . uniqid() . '.pdf';
        Storage::disk('local')->put($path, $bytes);
        $contract->caratula_path = $path;

        $contract->save();

        return $contract;
    }
}
