<?php

namespace App\Support;

use App\Models\AmbulanceProvider;
use App\Models\ExternalAuthorization;
use App\Models\Payee;
use App\Models\PayeeContract;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * PASO 5 · liga un PROVEEDOR de ambulancias a su identidad en la base única (quien cobra).
 * FUENTE ÚNICA del vínculo — la usan el ALTA de proveedor (nace ligado) y el backfill de los
 * existentes. Idempotente: si el proveedor ya tiene payee, no duplica nada.
 *
 * Qué hace al ligar:
 *   1) crea el {@see Payee} persona MORAL con lo del proveedor (nombre, RFC);
 *   2) crea un {@see PayeeContract} (concepto SERVICIO) con `contracted_by_user_id` = quien lo
 *      registró / producción-safety → así la visibilidad del Paso 4 funciona y transpo NO lo ve;
 *   3) re-apunta los documentos de OPERAR (holder = proveedor) al payee — "solo cambia el holder";
 *   4) fija `ambulance_providers.payee_id`.
 *
 * NO toca las actas selladas (referencian el id del proveedor, que no cambia) ni el padrón
 * (`ambulance_crew`, que NO es quien cobra) ni el catálogo.
 */
class AmbulancePayeeLink
{
    public static function ensureFor(AmbulanceProvider $provider, ?int $contractedByUserId = null): Payee
    {
        // Idempotente: ya ligado → devolver su payee.
        if ($provider->payee_id) {
            return $provider->payee()->firstOrFail();
        }

        $by = $contractedByUserId
            ?? $provider->created_by_id
            ?? optional(User::role('super-admin')->first())->id;

        return DB::transaction(function () use ($provider, $by) {
            $payee = Payee::create([
                'legal_nature' => Payee::NATURE_MORAL,
                'name'         => $provider->name,
                'rfc'          => $provider->rfc,
                'is_active'    => 1,
                'created_by_id' => $provider->created_by_id,
            ]);

            // El CONTRATO: la ambulancia la contrata producción o safety (5.5).
            $payee->contracts()->create([
                'concept'               => PayeeContract::CONCEPT_SERVICE,
                'title'                 => 'Servicio de ambulancia',
                'contracted_by_user_id' => $by,
                'is_repse'              => 0,
                'is_active'             => 1,
                'created_by_id'         => $provider->created_by_id,
            ]);

            // Los documentos de OPERAR cambian de holder al payee (base única). Persona-docs
            // (holder = AmbulanceCrew) no matchean aquí y se quedan en el padrón.
            ExternalAuthorization::where('holder_type', $provider->getMorphClass())
                ->where('holder_id', $provider->id)
                ->update([
                    'holder_type' => (new Payee)->getMorphClass(),
                    'holder_id'   => $payee->id,
                ]);

            $provider->payee_id = $payee->id;
            $provider->save();

            return $payee;
        });
    }
}
