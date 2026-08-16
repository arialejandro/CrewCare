<?php

namespace App\Console\Commands;

use App\Models\ContractEnvelope;
use App\Models\ContractEnvelopeEvent;
use App\Models\ContractEnvelopeRecipient;
use App\Models\Department;
use App\Models\Payee;
use App\Models\PayeeContract;
use App\Models\User;
use App\Support\ContractEventLog;
use App\Support\CurrentProduction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * DEMO REVERSIBLE — siembra contratos "por firmar" para VER poblada la bandeja (Contratos por firmar)
 * y el tablero (Seguimiento de firmas). Cada firma queda en TURNO del usuario destino (por defecto el
 * admin) para que su bandeja los muestre. Guarda un manifiesto con los ids creados; `--undo` borra
 * EXACTAMENTE eso y nada más. No sella nada, no toca datos reales.
 *
 * ⚖ Datos genéricos a propósito (regla anti-piratería): personas evidentes + "Producciones Genéricas
 * S.A. de C.V." + RFC genérico del SAT (XAXX010101000). Cero nombres/RFC reales.
 *
 *   php artisan contracts:seed-pending-demo          # siembra
 *   php artisan contracts:seed-pending-demo --undo   # revierte
 */
class SeedPendingSignaturesDemo extends Command
{
    protected $signature = 'contracts:seed-pending-demo {--undo : Borra exactamente lo sembrado por este comando}';

    protected $description = 'Siembra contratos DEMO por firmar (reversible) para ver poblada la bandeja y el tablero.';

    private string $manifest = 'demo/pending-signatures.json';

    public function handle(): int
    {
        return $this->option('undo') ? $this->undo() : $this->seed();
    }

    private function seed(): int
    {
        if (Storage::disk('local')->exists($this->manifest)) {
            $this->warn('Ya hay una demo sembrada. Revierte primero: php artisan contracts:seed-pending-demo --undo');
            return self::FAILURE;
        }

        $user = User::where('email', 'admin@127.0.0.1')->first()
            ?: User::where('admin', 1)->first()
            ?: User::first();
        if (! $user) {
            $this->error('No hay usuario destino.');
            return self::FAILURE;
        }

        $prod     = CurrentProduction::id();
        $deptId   = optional(Department::where('name', 'Producción')->first())->id;
        $regimeId = class_exists(\App\Models\PayeeFiscalRegime::class)
            ? optional(\App\Models\PayeeFiscalRegime::first())->id : null;

        // [nombre payee, naturaleza, rfc, cargo(figura interna), concepto, fee, moneda, inicio, fin]
        $rows = [
            ['Juan Pérez López',                    'fisica', 'XAXX010101000', 'Gerente de Producción',    'crew_work',         45000, 'MXN', '2026-02-01', '2026-08-31'],
            ['María Fernanda Ruiz',                 'fisica', 'XAXX010101000', 'Contador Fiscal',          'crew_work',        120000, 'MXN', '2026-01-15', '2026-12-15'],
            ['Carlos Hernández Gómez',              'fisica', 'XAXX010101000', 'Representante Legal',       'service',           78000, 'MXN', '2026-03-01', '2026-09-30'],
            ['Producciones Genéricas S.A. de C.V.', 'moral',  'XAXX010101000', 'Productor en Línea',       'equipment_rental', 250000, 'MXN', '2026-02-10', '2026-07-20'],
            ['Ana García Torres',                   'fisica', 'XAXX010101000', 'Coordinador de Producción', 'crew_work',         30000, 'MXN', '2026-04-01', '2026-10-31'],
            ['Luis Ramírez Soto',                   'fisica', 'XAXX010101000', 'Gerente de Producción',    'crew_work',         60000, 'USD', '2026-05-01', '2026-11-30'],
        ];

        $man = [
            'payees' => [], 'contracts' => [], 'envelopes' => [], 'recipients' => [],
            'user_id' => $user->id, 'created_at' => now()->toDateTimeString(),
        ];

        DB::transaction(function () use ($rows, $user, $prod, $deptId, $regimeId, &$man) {
            foreach ($rows as $r) {
                [$name, $nature, $rfc, $cargo, $concept, $fee, $cur, $ini, $fin] = $r;

                $payee = Payee::create(['legal_nature' => $nature, 'name' => $name, 'rfc' => $rfc]);

                $contract = $payee->contracts()->create([
                    'concept'             => $concept,
                    'is_active'           => 1,
                    'production_id'       => $prod,
                    'fee_amount'          => $fee,
                    'fee_currency'        => $cur,
                    'effective_date'      => $ini,
                    'definitive_end_date' => $fin,
                    'department_id'       => $concept === 'crew_work' ? $deptId : null,
                    'fiscal_regime_id'    => $nature === 'fisica' ? $regimeId : null,
                ]);

                $env = ContractEnvelope::create([
                    'payee_contract_id' => $contract->id,
                    'production_id'     => $prod,
                    'status'            => ContractEnvelope::STATUS_SENT,
                    'sent_at'           => now(),
                ]);

                // #0 el contratado ya firmó → el turno pasa a la figura interna.
                $c0 = $env->recipients()->create([
                    'role' => ContractEnvelopeRecipient::ROLE_CONTRACTED, 'sort_order' => 0,
                    'name' => $name, 'cargo' => 'Contratista',
                    'status' => ContractEnvelopeRecipient::STATUS_SIGNED, 'signed_at' => now()->subDay(),
                ]);
                // #1 la figura interna = el usuario destino → su TURNO ahora.
                $rec = $env->recipients()->create([
                    'role' => ContractEnvelopeRecipient::ROLE_SIGNER, 'sort_order' => 1,
                    'name' => trim($user->name.' '.$user->lname), 'cargo' => $cargo,
                    'user_id' => $user->id,
                    'status' => ContractEnvelopeRecipient::STATUS_SENT, 'sent_at' => now(),
                ]);
                $env->update(['current_recipient_id' => $rec->id]);

                // Bitácora demo: un rastro realista para que la Bitácora del sobre se vea poblada.
                $ip = '187.190.0.1';
                ContractEventLog::record($env, ContractEnvelopeEvent::CREATED, ['actor_id' => $user->id, 'ip' => $ip, 'payload' => ['recipients' => 2, 'documents' => 0]]);
                ContractEventLog::record($env, ContractEnvelopeEvent::SENT, ['actor_id' => $user->id, 'ip' => $ip, 'recipient' => $c0]);
                ContractEventLog::record($env, ContractEnvelopeEvent::VIEWED, ['recipient' => $c0, 'actor_label' => $name, 'ip' => $ip]);
                ContractEventLog::record($env, ContractEnvelopeEvent::CONSENTED, ['recipient' => $c0, 'actor_label' => $name, 'ip' => $ip]);
                ContractEventLog::record($env, ContractEnvelopeEvent::SIGNED, ['recipient' => $c0, 'actor_label' => $name, 'ip' => $ip, 'payload' => ['method' => 'autograph', 'role' => 'contracted']]);

                $man['payees'][]     = $payee->id;
                $man['contracts'][]  = $contract->id;
                $man['envelopes'][]  = $env->id;
                $man['recipients'][] = $c0->id;
                $man['recipients'][] = $rec->id;
            }
        });

        Storage::disk('local')->put($this->manifest, json_encode($man, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info(count($rows).' contratos DEMO por firmar sembrados para '.$user->email.'.');
        $this->line('  Bandeja:  /contratos/firmas-pendientes');
        $this->line('  Tablero:  /contratos/firmas-por-figura');
        $this->line('  Revertir: php artisan contracts:seed-pending-demo --undo');

        return self::SUCCESS;
    }

    private function undo(): int
    {
        if (! Storage::disk('local')->exists($this->manifest)) {
            $this->warn('No hay manifiesto de demo; nada que revertir.');
            return self::SUCCESS;
        }

        $man = json_decode(Storage::disk('local')->get($this->manifest), true) ?: [];

        DB::transaction(function () use ($man) {
            // Sellos (defensivo: no sellamos, así que normalmente cero filas).
            foreach ([[ContractEnvelope::class, 'envelopes'], [ContractEnvelopeRecipient::class, 'recipients']] as [$type, $key]) {
                if (! empty($man[$key])) {
                    DB::table('digital_signatures')->where('documentable_type', $type)->whereIn('documentable_id', $man[$key])->delete();
                }
            }
            // Bitácora demo (raw DB: el modelo es append-only por diseño).
            if (! empty($man['envelopes'])) {
                DB::table('contract_envelope_events')->whereIn('envelope_id', $man['envelopes'])->delete();
            }
            if (! empty($man['recipients'])) {
                ContractEnvelopeRecipient::whereIn('id', $man['recipients'])->delete();
            }
            if (! empty($man['envelopes'])) {
                ContractEnvelope::whereIn('id', $man['envelopes'])->delete();
            }
            if (! empty($man['contracts'])) {
                PayeeContract::whereIn('id', $man['contracts'])->delete();
            }
            if (! empty($man['payees'])) {
                Payee::whereIn('id', $man['payees'])->delete();
            }
        });

        Storage::disk('local')->delete($this->manifest);
        $this->info('Demo revertida: se borraron exactamente los contratos DEMO sembrados.');

        return self::SUCCESS;
    }
}
