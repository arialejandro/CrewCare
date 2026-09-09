<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EL INFOSHEET · FASE 1 — el DATO del trato que faltaba. Extiende `payee_contracts` (crew_work)
 * con el importe POR FASE: {semanas · tarifa semanal · importe} × 4 fases (soft_prep/prep/shoot/
 * wrap). Son 3 capturas por fase —no una— porque la tarifa puede cambiar entre fases; el importe
 * se captura (no solo se deriva) para no descuadrar cuando la tarifa varía a media fase.
 * Además: desglose fiscal (IVA / retención ISR / retención IVA) que contabilidad necesita y hoy
 * no existe, factura vs recibo, y administra caja chica.
 *
 * TODO NULLABLE y ADITIVO: en equipment_rental/service quedan NULL y nada se rompe (mismo
 * precedente que el Paso A). `payee_contracts` NO usa HasDigitalSignatures → columnas nuevas no
 * tocan ningún sello. El honorario TOTAL (suma de las fases) sigue viviendo en `fee_amount`
 * (Paso A) — no se duplica. `payment_document_type` (factura|recibo) es DISTINTO de
 * `issues_own_cfdi` (quién emite el CFDI).
 */
class AddInfosheetFieldsToPayeeContracts extends Migration
{
    public function up()
    {
        $cols = [
            // Importe por fase: {semanas · tarifa semanal · importe} × 4 fases.
            'fee_soft_prep_weeks'   => "ADD COLUMN `fee_soft_prep_weeks` DECIMAL(5,2) NULL",
            'fee_soft_prep_rate'    => "ADD COLUMN `fee_soft_prep_rate` DECIMAL(12,2) NULL",
            'fee_soft_prep_amount'  => "ADD COLUMN `fee_soft_prep_amount` DECIMAL(12,2) NULL",
            'fee_prep_weeks'        => "ADD COLUMN `fee_prep_weeks` DECIMAL(5,2) NULL",
            'fee_prep_rate'         => "ADD COLUMN `fee_prep_rate` DECIMAL(12,2) NULL",
            'fee_prep_amount'       => "ADD COLUMN `fee_prep_amount` DECIMAL(12,2) NULL",
            'fee_shoot_weeks'       => "ADD COLUMN `fee_shoot_weeks` DECIMAL(5,2) NULL",
            'fee_shoot_rate'        => "ADD COLUMN `fee_shoot_rate` DECIMAL(12,2) NULL",
            'fee_shoot_amount'      => "ADD COLUMN `fee_shoot_amount` DECIMAL(12,2) NULL",
            'fee_wrap_weeks'        => "ADD COLUMN `fee_wrap_weeks` DECIMAL(5,2) NULL",
            'fee_wrap_rate'         => "ADD COLUMN `fee_wrap_rate` DECIMAL(12,2) NULL",
            'fee_wrap_amount'       => "ADD COLUMN `fee_wrap_amount` DECIMAL(12,2) NULL",
            // Desglose fiscal.
            'tax_iva'               => "ADD COLUMN `tax_iva` DECIMAL(12,2) NULL",
            'tax_isr_retention'     => "ADD COLUMN `tax_isr_retention` DECIMAL(12,2) NULL",
            'tax_iva_retention'     => "ADD COLUMN `tax_iva_retention` DECIMAL(12,2) NULL",
            // Comprobante + caja chica.
            'payment_document_type' => "ADD COLUMN `payment_document_type` VARCHAR(20) NULL",
            'manages_petty_cash'    => "ADD COLUMN `manages_petty_cash` TINYINT(1) NULL",
        ];

        foreach ($cols as $name => $ddl) {
            if (! Schema::hasColumn('payee_contracts', $name)) {
                DB::statement("ALTER TABLE `payee_contracts` {$ddl}");
            }
        }
    }

    public function down()
    {
        $drop = [
            'fee_soft_prep_weeks', 'fee_soft_prep_rate', 'fee_soft_prep_amount',
            'fee_prep_weeks', 'fee_prep_rate', 'fee_prep_amount',
            'fee_shoot_weeks', 'fee_shoot_rate', 'fee_shoot_amount',
            'fee_wrap_weeks', 'fee_wrap_rate', 'fee_wrap_amount',
            'tax_iva', 'tax_isr_retention', 'tax_iva_retention',
            'payment_document_type', 'manages_petty_cash',
        ];
        foreach ($drop as $name) {
            if (Schema::hasColumn('payee_contracts', $name)) {
                DB::statement("ALTER TABLE `payee_contracts` DROP COLUMN `{$name}`");
            }
        }
    }
}
