<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EL CONTRATO · PASO B — lo que el contrato CONGELA AL EMITIR. Columnas ADITIVAS nullable en
 * `payee_contracts`:
 *  - `clause_id`      → el clausulado + VERSIÓN EXACTA que usó (subir uno nuevo no lo altera).
 *  - `language`       → idioma con el que se emitió (heredado del clausulado, editable antes).
 *  - `caratula_path`  → el PDF de la carátula generado y guardado (byte del emitido, inmutable).
 *  - `emitted_at` / `emitted_by_id` → cuándo y quién emitió.
 * (Los datos del contratante y credit_name/beneficiary_* ya son columnas del Paso A; el EMIT los
 *  llena desde settings/alta y ahí quedan congelados.)
 *
 * payee_contracts NO se sella → agregar columnas no toca ningún hash.
 */
class AddEmitFieldsToPayeeContracts extends Migration
{
    public function up()
    {
        $cols = [
            'clause_id'      => "ADD COLUMN `clause_id` BIGINT UNSIGNED NULL, ADD KEY `payee_contracts_clause_idx` (`clause_id`)",
            'language'       => "ADD COLUMN `language` VARCHAR(12) NULL",
            'caratula_path'  => "ADD COLUMN `caratula_path` VARCHAR(500) NULL",
            'emitted_at'     => "ADD COLUMN `emitted_at` DATETIME NULL",
            'emitted_by_id'  => "ADD COLUMN `emitted_by_id` BIGINT UNSIGNED NULL",
        ];
        foreach ($cols as $name => $ddl) {
            if (! Schema::hasColumn('payee_contracts', $name)) {
                DB::statement("ALTER TABLE `payee_contracts` {$ddl}");
            }
        }
    }

    public function down()
    {
        foreach (['clause_id', 'language', 'caratula_path', 'emitted_at', 'emitted_by_id'] as $name) {
            if (Schema::hasColumn('payee_contracts', $name)) {
                DB::statement("ALTER TABLE `payee_contracts` DROP COLUMN `{$name}`");
            }
        }
    }
}
