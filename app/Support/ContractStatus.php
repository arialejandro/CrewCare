<?php

namespace App\Support;

use App\Models\PayeeContract;
use Illuminate\Support\Facades\DB;

/**
 * ContractStatus — FUENTE ÚNICA del "estado de contrato" de una persona del crew, para pintarlo
 * junto a cada quien en el Crew List y en Dados de baja, filtrar por él y contarlo (2026-09-10).
 *
 * LA PREGUNTA que contesta: ¿quién de este crew NO tiene contrato, y a quién le falta capturar el
 * trato? Hoy eso sólo se veía entrando a la ficha de una persona, de una en una — con 150 no se hace.
 *
 * TRES ESTADOS (los dos primeros SÍ se construyen; el tercero —DESALINEADO— se REPORTÓ y NO se
 * construye por ahora, ver nota abajo):
 *   · SIN_CONTRATO — no existe ningún contrato crew_work ACTIVO de la persona (nunca se emitió).
 *   · INCOMPLETO   — existe contrato crew_work pero le falta algo del trato. NO inventa un concepto
 *                    nuevo de "sin contrato": REÚSA {@see InfosheetSigning::missingToAuthorize()}
 *                    (puesto, departamento, honorarios > 0, fecha de inicio) — el MISMO mínimo que
 *                    ya gatea la autorización. El detalle sale de {@see InfosheetSigning::missingLabel()}.
 *   · OK           — tiene al menos un contrato crew_work activo COMPLETO (con ese mínimo capturado).
 *
 * 🔴 NO BLOQUEA NADA: es sólo un marcador informativo. Ni el llamado, ni crear documentos, ni el
 * acceso dependen de esto. Producción decide.
 *
 * 🔎 DESALINEADO (§2c del pedido) — REPORTADO, NO CONSTRUIDO. El puesto del contrato vive en
 * `payee_contracts.title` como el NOMBRE del puesto del catálogo (lo puebla el select `position_id`
 * → `positions.name`; el sistema ya casa `title` ↔ puesto por nombre exacto en
 * InfosheetController::positionIdFor). Es comparable con el puesto vivo SÓLO cuando ambos lados
 * salen del catálogo; en filas legacy de texto libre (`users.puestodepartamento`) da falsos
 * positivos. Y el motor del pedido —el CAMBIO DE UNIDAD— NO está en el nombre del puesto en
 * CrewCare (la unidad vive en `unit_members`, no en `positions.name`), y el contrato no guarda
 * ninguna referencia de unidad → una comparación por nombre de puesto NO detecta mudanzas de unidad
 * (el caso que más importa) y daría falsa tranquilidad. Por eso: dos estados buenos, no tres con
 * ruido. Cuando el owner decida, aquí es donde entraría.
 *
 * Correlaciona por `payees.user_id` (la liga opcional del crew con "quien cobra"). Acotado a la
 * producción vigente y a `is_active = 1` (un contrato apagado no cuenta como cobertura).
 */
class ContractStatus
{
    const OK           = 'ok';
    const INCOMPLETO   = 'incompleto';
    const SIN_CONTRATO = 'sin_contrato';

    // Claves de filtro (query param ?contract=...).
    const FILTER_SIN        = 'sin';         // sólo SIN_CONTRATO
    const FILTER_INCOMPLETO = 'incompleto';  // sólo INCOMPLETO
    const FILTER_FALTA      = 'falta';       // unión: sin contrato COMPLETO (SIN o INCOMPLETO)

    /** Filtros aceptados (para validar el query param y evitar valores raros). */
    public static function filters(): array
    {
        return [self::FILTER_SIN, self::FILTER_INCOMPLETO, self::FILTER_FALTA];
    }

    /**
     * Closure de subconsulta correlacionada: ¿existe un contrato crew_work ACTIVO de esta persona
     * en la producción vigente? Con $complete=true exige además el mínimo del trato (mismo criterio
     * que missingToAuthorize). $userCol = columna del user en la consulta externa (p.ej. 'users.id').
     */
    protected static function existsClosure(string $userCol, bool $complete, ?int $prodId): \Closure
    {
        return function ($s) use ($userCol, $complete, $prodId) {
            $s->selectRaw('1')->from('payee_contracts')
              ->join('payees', 'payees.id', '=', 'payee_contracts.payee_id')
              ->whereColumn('payees.user_id', $userCol)
              ->where('payee_contracts.concept', PayeeContract::CONCEPT_CREW)
              ->where('payee_contracts.is_active', 1);

            if ($prodId) {
                $s->where('payee_contracts.production_id', $prodId);
            }

            if ($complete) {
                $s->whereNotNull('payee_contracts.title')->where('payee_contracts.title', '!=', '')
                  ->whereNotNull('payee_contracts.department_id')
                  ->where('payee_contracts.fee_amount', '>', 0)
                  ->whereNotNull('payee_contracts.effective_date');
            }
        };
    }

    /**
     * Aplica el filtro por estado de contrato a una consulta de `users` (SQL, para que la paginación
     * y el conteo sean exactos). MUTA y devuelve la misma consulta. Filtro nulo/desconocido = sin tocar.
     */
    public static function applyFilter($query, ?string $filter, string $userCol = 'users.id', ?int $prodId = null)
    {
        $prodId = $prodId ?? CurrentProduction::id();
        $any      = self::existsClosure($userCol, false, $prodId);
        $complete = self::existsClosure($userCol, true, $prodId);

        switch ($filter) {
            case self::FILTER_SIN:
                return $query->whereNotExists($any);
            case self::FILTER_INCOMPLETO:
                return $query->whereExists($any)->whereNotExists($complete);
            case self::FILTER_FALTA:
                return $query->whereNotExists($complete);
            default:
                return $query;
        }
    }

    /**
     * Conteos por estado sobre TODO el alcance del visor (no sólo la página), para el resumen/filtro.
     * NO muta la consulta base: clona por cada conteo. Devuelve ['sin','incompleto','falta','activos'].
     */
    public static function counts($baseUsersQuery, string $userCol = 'users.id', ?int $prodId = null): array
    {
        $prodId = $prodId ?? CurrentProduction::id();
        $any      = self::existsClosure($userCol, false, $prodId);
        $complete = self::existsClosure($userCol, true, $prodId);

        $sin        = (clone $baseUsersQuery)->whereNotExists($any)->count();
        $incompleto = (clone $baseUsersQuery)->whereExists($any)->whereNotExists($complete)->count();
        $activos    = (clone $baseUsersQuery)->count();

        return [
            'sin'        => $sin,
            'incompleto' => $incompleto,
            'falta'      => $sin + $incompleto,
            'activos'    => $activos,
        ];
    }

    /**
     * Estado de contrato para un conjunto de user_ids, SIN N+1 (2 consultas + PHP). Devuelve
     * [user_id => ['state','label','detail']]. Un user sin fila = SIN_CONTRATO. La lista lo pinta
     * junto a cada persona; el detalle (missingLabel) va como tooltip del chip INCOMPLETO.
     *
     * @param  array<int>  $userIds
     * @return array<int,array{state:string,label:string,detail:string}>
     */
    public static function forUserIds(array $userIds, ?int $prodId = null): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if (empty($userIds)) {
            return [];
        }
        $prodId = $prodId ?? CurrentProduction::id();

        // payee_id => user_id (la liga puede no existir; entonces la persona no tiene contrato).
        $payeeToUser = [];
        foreach (DB::table('payees')->whereIn('user_id', $userIds)->get(['id', 'user_id']) as $r) {
            $payeeToUser[(int) $r->id] = (int) $r->user_id;
        }

        // Contratos crew_work ACTIVOS de esos payees en la producción vigente. Sólo las columnas del
        // mínimo del trato (las que lee missingToAuthorize) → barato aunque haya varios por persona.
        $byUser = []; // user_id => [PayeeContract, ...]
        if (! empty($payeeToUser)) {
            $contracts = PayeeContract::query()
                ->crewWork()
                ->where('is_active', 1)
                ->when($prodId, fn ($q) => $q->where('production_id', $prodId))
                ->whereIn('payee_id', array_keys($payeeToUser))
                ->get(['id', 'payee_id', 'title', 'department_id', 'fee_amount', 'effective_date']);

            foreach ($contracts as $c) {
                $uid = $payeeToUser[(int) $c->payee_id] ?? null;
                if ($uid) {
                    $byUser[$uid][] = $c;
                }
            }
        }

        $out = [];
        foreach ($userIds as $uid) {
            $contracts = $byUser[$uid] ?? [];

            if (empty($contracts)) {
                $out[$uid] = self::descriptor(self::SIN_CONTRATO);
                continue;
            }

            // ¿Alguno COMPLETO? (mínimo del trato capturado). Si ninguno, se toma el "más completo"
            // (menos faltantes) para mostrar en qué se quedó.
            $hasComplete   = false;
            $bestIncomplete = null;
            $bestMissing    = PHP_INT_MAX;
            foreach ($contracts as $c) {
                $missing = InfosheetSigning::missingToAuthorize($c);
                if (empty($missing)) {
                    $hasComplete = true;
                    break;
                }
                if (count($missing) < $bestMissing) {
                    $bestMissing    = count($missing);
                    $bestIncomplete = $c;
                }
            }

            $out[$uid] = $hasComplete
                ? self::descriptor(self::OK)
                : self::descriptor(self::INCOMPLETO, InfosheetSigning::missingLabel($bestIncomplete));
        }

        return $out;
    }

    /** Descriptor listo para la vista (etiqueta traducible + detalle opcional). */
    protected static function descriptor(string $state, string $detail = ''): array
    {
        $labels = [
            self::SIN_CONTRATO => __('Sin contrato'),
            self::INCOMPLETO   => __('Contrato incompleto'),
            self::OK           => __('Con contrato'),
        ];

        return [
            'state'  => $state,
            'label'  => $labels[$state] ?? '',
            'detail' => $detail,
        ];
    }
}
