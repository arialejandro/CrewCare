<?php

namespace App\Support;

use App\Exceptions\ContractEnvelopeException;
use App\Models\ContractEnvelope;
use App\Models\PayeeContract;
use App\Models\User;

/**
 * EL CONTRATO · PASO C · B2 — INSTANCIACIÓN MASIVA. Crea (y opcionalmente envía) el sobre de VARIOS
 * contratos de una sola vez, en lugar de uno por uno. Es N sobres INDEPENDIENTES (uno por persona),
 * no un sobre con N personas: cada quien firma su propio contrato.
 *
 * AÍSLA el fallo por contrato: uno con puesto VACANTE/DUPLICADO, ya con sobre en curso, o sin permiso
 * de captura, NO detiene el lote — se salta con su motivo. Reutiliza el camino de UNO
 * ({@see ContractEnvelopeBuilder::build} + {@see ContractSigning::send}): mismas guardas, mismos
 * eventos. La elegibilidad y el permiso de captura se resuelven por contrato (nadie emite lo que no ve).
 */
class ContractBatchEmitter
{
    /**
     * Contratos ELEGIBLES para emitir sobre: crew_work EMITIDOS (con carátula) de la producción, sin
     * un sobre EN CURSO (draft/sent), del departamento dado (o todos), y que el actor PUEDE capturar.
     *
     * @return \Illuminate\Support\Collection<int, PayeeContract>
     */
    public static function eligible(int $prodId, ?int $deptId, User $actor)
    {
        return PayeeContract::query()
            ->where('production_id', $prodId)
            ->where('concept', PayeeContract::CONCEPT_CREW)
            ->whereNotNull('emitted_at')
            ->when($deptId, fn ($q) => $q->where('department_id', $deptId))
            ->whereDoesntHave('envelopes', fn ($q) => $q->whereIn('status', [
                ContractEnvelope::STATUS_DRAFT, ContractEnvelope::STATUS_SENT,
            ]))
            ->with(['payee', 'department'])
            ->get()
            ->filter(fn ($c) => $c->payee && $actor->can('capture', $c->payee))
            ->sortBy(fn ($c) => optional($c->payee)->name)
            ->values();
    }

    /**
     * Emite el lote. `$send`: además de crear el sobre, lo envía a firma. Devuelve el resumen para
     * mostrarlo al usuario (qué se creó, qué se saltó y por qué).
     *
     * @param iterable<PayeeContract> $contracts
     * @return array{created: array<int, array>, skipped: array<int, array>}
     */
    public static function run(iterable $contracts, ?User $actor, bool $send): array
    {
        $created = [];
        $skipped = [];

        foreach ($contracts as $contract) {
            if (! $actor || ! $contract->payee || ! $actor->can('capture', $contract->payee)) {
                $skipped[] = self::row($contract, __('Sin permiso de captura'));
                continue;
            }
            try {
                $env = ContractEnvelopeBuilder::build($contract, $actor);
                if ($send) {
                    ContractSigning::send($env);
                }
                $created[] = self::row($contract, null, $env->id);
            } catch (ContractEnvelopeException $e) {
                // Vacante/duplicado/ya-con-sobre/sin-emitir: motivo claro, el lote sigue.
                $skipped[] = self::row($contract, $e->getMessage());
            } catch (\Throwable $e) {
                $skipped[] = self::row($contract, __('Error inesperado al crear el sobre.'));
            }
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    private static function row(PayeeContract $c, ?string $reason, ?int $envId = null): array
    {
        return [
            'contract' => $c->id,
            'payee'    => optional($c->payee)->name,
            'puesto'   => $c->title,
            'reason'   => $reason,
            'envelope' => $envId,
        ];
    }
}
