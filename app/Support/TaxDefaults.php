<?php

namespace App\Support;

use App\Models\Payee;
use App\Models\PayeeContract;

/**
 * DESGLOSE FISCAL SUGERIDO (2026-08-25) — las tasas que aplican a un trato según el RÉGIMEN
 * FISCAL de quien cobra y el tipo de comprobante.
 *
 * Por qué existe: el Infosheet pedía IVA y retenciones como tres campos en blanco, cuando en la
 * práctica salen solos del régimen que ya está capturado en el registro de la persona. Aquí viven
 * las tasas; el Infosheet las PRELLENA y quien captura puede ajustar el importe si el trato lo
 * pide (un ajuste no cambia la tasa, solo ese contrato).
 *
 * ⚠ Es una SUGERENCIA de captura, no un dictamen fiscal: los casos raros (extranjeros,
 * plataformas, asimilados con tarifa) salen en cero y con nota para capturarlos a mano.
 */
class TaxDefaults
{
    /** Retención de IVA de servicios profesionales: 2/3 del 16% = 10.6667%. */
    public const IVA_RET_TWO_THIRDS = 0.106667;

    /**
     * Tasas por régimen SAT. [clave => [iva, retención ISR, retención IVA]].
     * Solo los regímenes con un desglose ESTÁNDAR; el resto cae al default en cero + nota.
     */
    private const RATES = [
        // Personas físicas que facturan honorarios/arrendamiento a una persona moral.
        '612' => [0.16, 0.10, self::IVA_RET_TWO_THIRDS],
        '606' => [0.16, 0.10, self::IVA_RET_TWO_THIRDS],
        // RESICO persona física: la moral retiene 1.25% de ISR y NO retiene IVA.
        '626_fisica' => [0.16, 0.0125, 0.0],
        // Personas morales: trasladan IVA y no se les retiene.
        '601' => [0.16, 0.0, 0.0],
        '603' => [0.16, 0.0, 0.0],
        '620' => [0.16, 0.0, 0.0],
        '623' => [0.16, 0.0, 0.0],
        '624' => [0.16, 0.0, 0.0],
        '626_moral' => [0.16, 0.0, 0.0],
        // Sin IVA por naturaleza del ingreso.
        '605' => [0.0, 0.0, 0.0],
        '616' => [0.0, 0.0, 0.0],
    ];

    /** Regímenes cuyo ISR NO es una tasa fija (tarifa/caso especial): salen en cero, con aviso. */
    private const MANUAL_NOTE = [
        '605' => 'Asimilados a salarios: el ISR va por tarifa, captúralo a mano.',
        '610' => 'Residente en el extranjero: el desglose depende del tratado, captúralo a mano.',
        '611' => 'Dividendos: captura el desglose a mano.',
        '615' => 'Premios: captura el desglose a mano.',
        '625' => 'Plataformas tecnológicas: la retención depende del ingreso, captúrala a mano.',
    ];

    /**
     * Tasas aplicables a un contrato.
     *
     * @return array{iva:float,isr_ret:float,iva_ret:float,regime:?string,regime_name:?string,note:?string,known:bool}
     */
    public static function forContract(PayeeContract $contract): array
    {
        $payee  = $contract->payee;
        $code   = self::regimeCode($contract, $payee);
        $moral  = $payee ? $payee->isMoral() : false;
        $rates  = self::ratesFor($code, $moral);

        // Un RECIBO (asimilado) no traslada IVA ni genera retención de IVA.
        if ($contract->payment_document_type === 'recibo') {
            $rates = [0.0, 0.0, 0.0];
        }

        return [
            'iva'         => $rates[0],
            'isr_ret'     => $rates[1],
            'iva_ret'     => $rates[2],
            'regime'      => $code,
            'regime_name' => $code ? SatCatalogs::regimenName($code) : null,
            'note'        => $code ? (self::MANUAL_NOTE[$code] ?? null) : 'Captura el régimen fiscal en el registro de la persona para sugerir el desglose.',
            'known'       => $code !== null && ($rates[0] > 0 || $rates[1] > 0 || $rates[2] > 0),
        ];
    }

    /**
     * Importes sugeridos sobre una base (el total de honorarios).
     *
     * @return array{tax_iva:float,tax_isr_retention:float,tax_iva_retention:float}
     */
    public static function amountsFor(PayeeContract $contract, ?float $base = null): array
    {
        $base  = $base ?? (float) $contract->fee_amount;
        $rates = self::forContract($contract);

        return [
            'tax_iva'               => round($base * $rates['iva'], 2),
            'tax_isr_retention'     => round($base * $rates['isr_ret'], 2),
            'tax_iva_retention'     => round($base * $rates['iva_ret'], 2),
        ];
    }

    /** La clave SAT que aplica: la del contrato si la eligió, si no la 1ª activa del registro. */
    private static function regimeCode(PayeeContract $contract, ?Payee $payee): ?string
    {
        if ($contract->fiscalRegime && $contract->fiscalRegime->code) {
            return (string) $contract->fiscalRegime->code;
        }
        if ($payee) {
            $r = $payee->fiscalRegimes()->where('is_active', 1)->orderBy('sort_order')->orderBy('id')->first();
            if ($r && $r->code) {
                return (string) $r->code;
            }
        }

        return null;
    }

    /** @return array{0:float,1:float,2:float} */
    private static function ratesFor(?string $code, bool $moral): array
    {
        if ($code === null) {
            return [0.0, 0.0, 0.0];
        }
        // 626 (RESICO) aplica a física y a moral con desgloses distintos.
        $key = $code === '626' ? ('626_' . ($moral ? 'moral' : 'fisica')) : $code;

        return self::RATES[$key] ?? [0.0, 0.0, 0.0];
    }

    /** Etiqueta corta para la UI: "IVA 16% · ISR 10% · IVA ret. 10.67%". */
    public static function label(array $rates): string
    {
        $pct = fn (float $r) => rtrim(rtrim(number_format($r * 100, 4, '.', ''), '0'), '.') . '%';

        return 'IVA ' . $pct($rates['iva'])
            . ' · ISR ' . $pct($rates['isr_ret'])
            . ' · IVA ret. ' . $pct($rates['iva_ret']);
    }
}
