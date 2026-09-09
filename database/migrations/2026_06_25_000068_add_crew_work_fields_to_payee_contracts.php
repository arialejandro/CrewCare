<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EL CONTRATO · PASO A — LA CARÁTULA. Extiende `payee_contracts` en su subtipo `crew_work`
 * con los campos de las carátulas reales. TODO NULLABLE y ADITIVO: en `equipment_rental` /
 * `service` (los que crea AmbulancePayeeLink) quedan NULL y nada se rompe (precedente:
 * `fiscal_regime_id`). `payee_contracts` NO usa HasDigitalSignatures → columnas nuevas no
 * tocan ningún sello.
 *
 * La FRECUENCIA de honorarios reusa la columna existente `payment_frequency` (la misma que
 * consume el bloque de periodos) — no se duplica.
 *
 * Congelados (se llenan al emitir/firmar, no se capturan sueltos):
 *  - `credit_name` (nombre en créditos, del alta) — el clausulado dice que el crédito se lee así.
 *  - `beneficiary_*` (del intake) — de él depende el pago del seguro.
 *  - `contractor_*` (razón social/RFC/domicilio/representante/correo, de la config global) — un
 *    contrato ya emitido debe seguir diciendo lo que dijo aunque después editen settings.
 */
class AddCrewWorkFieldsToPayeeContracts extends Migration
{
    public function up()
    {
        $cols = [
            // Actividad + departamento + créditos (congelado)
            'crew_activity'              => "ADD COLUMN `crew_activity` VARCHAR(255) NULL AFTER `title`",
            'department_id'              => "ADD COLUMN `department_id` BIGINT UNSIGNED NULL AFTER `crew_activity`, ADD KEY `payee_contracts_dept_idx` (`department_id`)",
            'credit_name'                => "ADD COLUMN `credit_name` VARCHAR(191) NULL AFTER `department_id`",
            // Tres fechas
            'effective_date'             => "ADD COLUMN `effective_date` DATE NULL",
            'estimated_end_date'         => "ADD COLUMN `estimated_end_date` DATE NULL",
            'definitive_end_date'        => "ADD COLUMN `definitive_end_date` DATE NULL",
            // Honorarios (la frecuencia reusa payment_frequency)
            'fee_amount'                 => "ADD COLUMN `fee_amount` DECIMAL(12,2) NULL",
            'fee_currency'               => "ADD COLUMN `fee_currency` VARCHAR(3) NULL",
            'issues_own_cfdi'            => "ADD COLUMN `issues_own_cfdi` TINYINT(1) NULL",
            // Sindicato (todos opcionales)
            'union_payroll'              => "ADD COLUMN `union_payroll` VARCHAR(160) NULL",
            'union_is_member'            => "ADD COLUMN `union_is_member` TINYINT(1) NULL",
            'union_retention_pct'        => "ADD COLUMN `union_retention_pct` DECIMAL(5,2) NULL",
            // Viáticos por fase: comidas diarias + semanal DISTINTO prep/wrap vs shoot
            'perdiem_breakfast'          => "ADD COLUMN `perdiem_breakfast` DECIMAL(10,2) NULL",
            'perdiem_lunch'              => "ADD COLUMN `perdiem_lunch` DECIMAL(10,2) NULL",
            'perdiem_dinner'             => "ADD COLUMN `perdiem_dinner` DECIMAL(10,2) NULL",
            'perdiem_weekly_prep'        => "ADD COLUMN `perdiem_weekly_prep` DECIMAL(10,2) NULL",
            'perdiem_weekly_shoot'       => "ADD COLUMN `perdiem_weekly_shoot` DECIMAL(10,2) NULL",
            // Hospedaje + vuelos + presupuesto
            'lodging_type'               => "ADD COLUMN `lodging_type` VARCHAR(20) NULL",
            'lodging_monthly_supplement' => "ADD COLUMN `lodging_monthly_supplement` DECIMAL(10,2) NULL",
            'round_flights'              => "ADD COLUMN `round_flights` SMALLINT UNSIGNED NULL",
            'budget_account'             => "ADD COLUMN `budget_account` VARCHAR(80) NULL",
            // Beneficiario congelado (del intake)
            'beneficiary_name'           => "ADD COLUMN `beneficiary_name` VARCHAR(160) NULL",
            'beneficiary_relationship'   => "ADD COLUMN `beneficiary_relationship` VARCHAR(80) NULL",
            'beneficiary_phone'          => "ADD COLUMN `beneficiary_phone` VARCHAR(40) NULL",
            // Contratante congelado (de la config global, al emitir)
            'contractor_legal_name'      => "ADD COLUMN `contractor_legal_name` VARCHAR(191) NULL",
            'contractor_rfc'             => "ADD COLUMN `contractor_rfc` VARCHAR(20) NULL",
            'contractor_address'         => "ADD COLUMN `contractor_address` VARCHAR(255) NULL",
            'contractor_representative'  => "ADD COLUMN `contractor_representative` VARCHAR(160) NULL",
            'contractor_email'           => "ADD COLUMN `contractor_email` VARCHAR(160) NULL",
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
            'crew_activity', 'credit_name', 'effective_date', 'estimated_end_date', 'definitive_end_date',
            'fee_amount', 'fee_currency', 'issues_own_cfdi', 'union_payroll', 'union_is_member',
            'union_retention_pct', 'perdiem_breakfast', 'perdiem_lunch', 'perdiem_dinner',
            'perdiem_weekly_prep', 'perdiem_weekly_shoot', 'lodging_type', 'lodging_monthly_supplement',
            'round_flights', 'budget_account', 'beneficiary_name', 'beneficiary_relationship',
            'beneficiary_phone', 'contractor_legal_name', 'contractor_rfc', 'contractor_address',
            'contractor_representative', 'contractor_email',
        ];
        foreach ($drop as $name) {
            if (Schema::hasColumn('payee_contracts', $name)) {
                DB::statement("ALTER TABLE `payee_contracts` DROP COLUMN `{$name}`");
            }
        }
        if (Schema::hasColumn('payee_contracts', 'department_id')) {
            DB::statement("ALTER TABLE `payee_contracts` DROP COLUMN `department_id`");
        }
    }
}
