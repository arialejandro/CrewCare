<?php

namespace App\Support;

use App\Models\PaymentPeriod;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * EL TABLERO DE QUIÉN FALTA — el entregable que importa: por PERIODO, cuántos entregaron,
 * cuántos faltan y QUIÉNES. Es LECTURA/derivación pura:
 *
 *  - Reusa los estados que YA resuelve {@see PayeePackage} (missing/received/not_positive/
 *    expired); NO los colapsa en "completo". El estado dice RECIBIDO, nunca "vigente"/"cumple".
 *  - Una 32-D NO POSITIVA sigue contando como FALTANTE (no entregó): `not_positive` ≠ recibido.
 *  - Visibilidad: "quien contrata es quien ve" ({@see User::applyContractingScope}). Falla
 *    cerrado → un HOD de transpo NO ve el tablero de arte.
 *  - Filtrable por departamento y por tipo de documento.
 *
 * NO decide nada del negocio (no recuerda, no cierra, no reabre): solo pinta el estado real.
 */
class PeriodBoard
{
    /**
     * @param  array{department_id?:int|null, document_type_id?:int|null}  $filters
     * @return array{columns: Collection, rows: array, tally: array}
     */
    public static function build(PaymentPeriod $period, User $viewer, array $filters = []): array
    {
        $productionId = (int) $period->production_id;
        $cutDay       = PayeePackage::cutDay($productionId);
        $deptFilter   = $filters['department_id'] ?? null;
        $typeFilter   = $filters['document_type_id'] ?? null;

        // Contratos a los que aplica el periodo (por frecuencia), ACOTADOS a quien puede ver.
        $q = $period->matchingContracts();
        User::applyContractingScope($q, $viewer);           // "quien contrata es quien ve" (falla cerrado)

        if ($deptFilter) {                                   // sub-filtro por departamento (§4)
            $q->whereExists(function ($s) use ($deptFilter) {
                $s->selectRaw('1')->from('production_user')
                  ->whereColumn('production_user.user_id', 'payee_contracts.contracted_by_user_id')
                  ->where('production_user.department_id', $deptFilter);
            });
        }

        $contracts = $q->with(['payee.documents', 'documents', 'contractedBy'])
            ->orderBy('payee_id')->get();

        $columns = collect();   // tipos-columna (unión de todos los requisitos del periodo)
        $rows    = [];

        foreach ($contracts as $contract) {
            if (! $contract->payee) {
                continue;   // contrato huérfano (payee borrado): no se pinta
            }

            // Requisitos COMPLETOS del periodo para este contrato (el desplegable ve todos los tipos).
            $types = PayeePackage::periodRequirements($productionId, $contract);
            foreach ($types as $t) {
                if (! $columns->contains('id', $t->id)) {
                    $columns->push($t);
                }
            }

            // Filtro por tipo: oculta filas a las que ese documento NO se les pide (no son "faltantes").
            if ($typeFilter && ! $types->contains('id', (int) $typeFilter)) {
                continue;
            }

            // Documentos evaluables: los de la IDENTIDAD (payee) + los del CONTRATO. evaluate()
            // filtra por document_type_id, así que sobrar no daña.
            $docs = $contract->payee->documents->merge($contract->documents);

            $cells = [];
            foreach ($types as $type) {
                $cells[$type->id] = PayeePackage::evaluate($type, $docs, $cutDay);
            }

            // El conteo de "quién falta" respeta el filtro por tipo (una 32-D filtrada, p.ej.).
            $scope   = $typeFilter ? $types->where('id', (int) $typeFilter)->values() : $types;
            $missing = 0;
            foreach ($scope as $type) {
                if (($cells[$type->id] ?? null) !== PayeePackage::ST_RECEIVED) {
                    $missing++;      // not_positive / expired / missing → NO entregó ese doc
                }
            }

            // ¿Algún documento del alcance llegó FUERA de ventana? (marca, no rechazo)
            $scopeIds    = $scope->pluck('id')->all();
            $outOfWindow = ! empty($scopeIds) && $docs->contains(
                fn ($d) => in_array($d->document_type_id, $scopeIds, true) && $d->received_out_of_window
            );

            $total     = $scope->count();
            $delivered = ($total > 0 && $missing === 0) || $total === 0;

            $rows[] = [
                'contract'      => $contract,
                'payee'         => $contract->payee,
                'cells'         => $cells,
                'delivered'     => $delivered,
                'missing_count' => $missing,
                'total'         => $total,
                'out_of_window' => $outOfWindow,
                'no_reqs'       => $total === 0,
            ];
        }

        $expected  = count($rows);
        $delivered = collect($rows)->where('delivered', true)->count();

        return [
            'columns' => $columns->sortBy('sort_order')->values(),
            'rows'    => $rows,
            'tally'   => [
                'expected'    => $expected,
                'delivered'   => $delivered,
                'missing'     => $expected - $delivered,
                'who_missing' => collect($rows)->where('delivered', false)
                                    ->map(fn ($r) => $r['payee']->name)->values()->all(),
            ],
        ];
    }
}
