<?php

namespace App\Support;

use App\Models\PayeeContract;
use App\Models\Unit;
use App\Models\UnitMember;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ContractStatus — FUENTE ÚNICA del "estado de contrato" de una persona del crew, para pintarlo
 * junto a cada quien en el Crew List / buscador / Dados de baja, filtrar por él y contarlo.
 *
 * DOS EJES INDEPENDIENTES por persona (un mismo quien puede tener los dos):
 *
 *  1) COBERTURA — ¿tiene contrato y está capturado el trato?
 *     · SIN_CONTRATO — no existe ningún contrato crew_work ACTIVO (nunca se emitió).
 *     · INCOMPLETO   — existe pero le falta el mínimo del trato. REÚSA
 *                      {@see InfosheetSigning::missingToAuthorize/missingLabel} (puesto, depto,
 *                      honorarios > 0, fecha de inicio) — sin inventar un concepto nuevo.
 *     · OK           — al menos un contrato crew_work activo COMPLETO.
 *
 *  2) VIGENCIA — ¿el contrato llega al final de rodaje? (2026-09-10, §4)
 *     · EXP_VENCE     — todos sus contratos terminan ANTES del último día marcado en el calendario.
 *                       El caso dominante: la producción se atrasa o agrega días y el papel queda corto.
 *                       Trae la fecha de fin más lejana que tiene. Se recalcula solo cuando el
 *                       calendario cambia (se lee `ProductionCalendar::shootDates()` en vivo, no se guarda).
 *     · EXP_SIN_FECHA — tiene contrato pero NINGUNO trae fecha de fin. Estado PROPIO, no un default
 *                       silencioso: producción sabe que no puede cruzar la vigencia.
 *     · EXP_COVERED   — algún contrato cubre hasta el wrap (o después). Sin marca.
 *     · EXP_NA        — no aplica (sin contrato, o sin calendario con qué comparar).
 *     🔑 POR UNIDAD: se compara contra el wrap de SU unidad (`shootDates($unitId)`), no el de la
 *        producción. Con una sola unidad, todos caen en la principal → el wrap de siempre.
 *     🔑 QUÉ FECHA MANDA: `definitive_end_date` (la firme) gana; si no hay, `estimated_end_date`.
 *
 * 🔴 NO BLOQUEA NADA: sólo informa; producción decide. Correlaciona por `payees.user_id`; acota a la
 * producción vigente y a `is_active = 1` (un contrato apagado no cuenta como cobertura).
 *
 * 🔎 DESALINEADO ("revisar"): se maneja aparte (necesita el nombre compuesto de unidad en el título del
 * contrato). Ver §2/§3 del pedido.
 */
class ContractStatus
{
    // Eje 1 · cobertura
    const OK           = 'ok';
    const INCOMPLETO   = 'incompleto';
    const SIN_CONTRATO = 'sin_contrato';

    // Eje 2 · vigencia
    const EXP_COVERED  = 'covered';
    const EXP_VENCE    = 'vence';
    const EXP_SIN_FECHA = 'sin_fecha';
    const EXP_NA       = 'na';

    // Eje 3 · desalineación de unidad ("revisar")
    const REV_REVISAR = 'revisar';
    const REV_OK      = 'ok';

    // Claves de filtro (query param ?contract=...).
    const FILTER_SIN        = 'sin';         // sólo SIN_CONTRATO
    const FILTER_INCOMPLETO = 'incompleto';  // sólo INCOMPLETO
    const FILTER_FALTA      = 'falta';       // unión: sin contrato COMPLETO (SIN o INCOMPLETO)
    const FILTER_VENCE      = 'vence';        // vence antes del wrap
    const FILTER_SIN_FECHA  = 'sinfecha';     // contrato sin fecha de fin
    const FILTER_REVISAR    = 'revisar';      // la unidad del contrato ya no corresponde

    /** Filtros aceptados (para validar el query param y evitar valores raros). */
    public static function filters(): array
    {
        return [
            self::FILTER_SIN, self::FILTER_INCOMPLETO, self::FILTER_FALTA,
            self::FILTER_VENCE, self::FILTER_SIN_FECHA, self::FILTER_REVISAR,
        ];
    }

    /**
     * Estado (cobertura + vigencia) para un conjunto de user_ids, SIN N+1. Devuelve
     * [user_id => descriptor]. Un user sin fila = SIN_CONTRATO. La lista lo pinta junto a cada persona.
     *
     * @param  array<int>  $userIds
     * @return array<int,array>
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

        // Contratos crew_work ACTIVOS de esos payees en la producción vigente. Columnas: el mínimo del
        // trato (missingToAuthorize) + las dos fechas de fin (vigencia).
        $byUser = []; // user_id => [PayeeContract, ...]
        if (! empty($payeeToUser)) {
            $contracts = PayeeContract::query()
                ->crewWork()
                ->where('is_active', 1)
                ->when($prodId, fn ($q) => $q->where('production_id', $prodId))
                ->whereIn('payee_id', array_keys($payeeToUser))
                ->get(['id', 'payee_id', 'title', 'department_id', 'fee_amount', 'emitted_at', 'unit_number',
                       'effective_date', 'estimated_end_date', 'definitive_end_date']);

            foreach ($contracts as $c) {
                $uid = $payeeToUser[(int) $c->payee_id] ?? null;
                if ($uid) {
                    $byUser[$uid][] = $c;
                }
            }
        }

        // Contexto de unidad por persona: wrap (para la vigencia) + la unidad EXCLUSIVA viva (para el
        // sufijo mostrado y para "revisar"). Con una sola unidad, todos caen en la principal.
        $ctx    = self::unitContextFor($userIds, $prodId);
        $format = Unit::labelFormatFor($prodId);

        $out = [];
        foreach ($userIds as $uid) {
            $contracts = $byUser[$uid] ?? [];
            $exclNum   = $ctx[$uid]['excl_number'] ?? null;
            $out[$uid] = self::descriptor(
                self::coverageState($contracts),
                self::expiry($contracts, $ctx[$uid]['wrap'] ?? null),
                self::review($contracts, $exclNum),
                Unit::contractLabel($exclNum, $format)   // etiqueta viva (vacía si principal/compartido)
            );
        }

        return $out;
    }

    /** Cobertura de una persona a partir de sus contratos crew_work activos. */
    protected static function coverageState(array $contracts): array
    {
        if (empty($contracts)) {
            return [self::SIN_CONTRATO, ''];
        }
        $bestIncomplete = null;
        $bestMissing    = PHP_INT_MAX;
        foreach ($contracts as $c) {
            $missing = InfosheetSigning::missingToAuthorize($c);
            if (empty($missing)) {
                return [self::OK, ''];
            }
            if (count($missing) < $bestMissing) {
                $bestMissing    = count($missing);
                $bestIncomplete = $c;
            }
        }

        return [self::INCOMPLETO, InfosheetSigning::missingLabel($bestIncomplete)];
    }

    /**
     * Vigencia de una persona: ¿algún contrato llega al $wrap? La fecha efectiva de cada contrato es
     * `definitive_end_date` (la firme) o, si no hay, `estimated_end_date`. $wrap = último día de rodaje
     * de SU unidad (Carbon) o null si no hay calendario con qué comparar.
     *
     * @return array{0:string,1:?string} [estado, fecha efectiva 'Y-m-d' cuando aplica]
     */
    protected static function expiry(array $contracts, ?Carbon $wrap): array
    {
        if (empty($contracts) || $wrap === null) {
            return [self::EXP_NA, null];
        }

        $ends = [];
        foreach ($contracts as $c) {
            $end = $c->definitive_end_date ?: $c->estimated_end_date;
            if ($end) {
                $ends[] = $end instanceof Carbon ? $end->copy()->startOfDay() : Carbon::parse($end)->startOfDay();
            }
        }

        if (empty($ends)) {
            return [self::EXP_SIN_FECHA, null];   // tiene contrato, ninguna fecha de fin
        }

        // El fin MÁS LEJANO: hasta ahí está cubierta. Si ni ese llega al wrap → vence antes.
        usort($ends, fn ($a, $b) => $a <=> $b);
        $furthest = end($ends);
        if ($furthest->gte($wrap->copy()->startOfDay())) {
            return [self::EXP_COVERED, null];
        }

        return [self::EXP_VENCE, $furthest->toDateString()];
    }

    /**
     * "REVISAR" (§3): la unidad del contrato EMITIDO ya no corresponde a dónde vive la persona hoy.
     * Compara la unidad CONGELADA en el contrato (`unit_number`, NULL = principal) contra la unidad
     * EXCLUSIVA viva ($exclNumber, NULL = principal/compartido), en las DOS direcciones:
     *   · contrato "Unidad 2" y hoy principal/compartido → revisar.
     *   · contrato sin sufijo (principal) y hoy exclusivo de una adicional → revisar.
     * SÓLO cuenta contratos EMITIDOS (ahí se congela la unidad); los no emitidos tomarán la correcta al
     * emitir. NO dice "falta contrato": es "revisar" — puede requerir anexo o no, según la productora.
     *
     * @return array{0:string} [estado]
     */
    protected static function review(array $contracts, ?int $exclNumber): array
    {
        foreach ($contracts as $c) {
            if ($c->emitted_at === null) {
                continue;   // la unidad se congela al emitir; un borrador no dispara "revisar"
            }
            $contractNum = $c->unit_number ? (int) $c->unit_number : null;   // NULL = principal
            if ($contractNum !== $exclNumber) {
                return [self::REV_REVISAR];
            }
        }

        return [self::REV_OK];
    }

    /**
     * Contexto de unidad por persona: wrap (último día de rodaje de SU unidad, para la vigencia) y el
     * NÚMERO de la unidad de la que es EXCLUSIVA (para el sufijo mostrado y "revisar"). Con una sola
     * unidad, todos en la principal. Degrada a principal si no está la pivote de unidades.
     *
     * @param  array<int>  $userIds
     * @return array<int,array{wrap:?Carbon,excl_number:?int}>
     */
    protected static function unitContextFor(array $userIds, ?int $prodId): array
    {
        // Filas de pertenencia por persona + mapa id→número de unidad.
        $rowsByUser = [];
        $numberById = [];
        if (Schema::hasTable('unit_members')) {
            foreach (UnitMember::whereIn('user_id', $userIds)->get(['user_id', 'unit_id', 'exclusive']) as $r) {
                $rowsByUser[(int) $r->user_id][] = $r;
            }
            $unitIds = collect($rowsByUser)->flatten(1)->pluck('unit_id')->unique()->filter()->all();
            if (! empty($unitIds) && Schema::hasColumn('units', 'number')) {
                $numberById = Unit::whereIn('id', $unitIds)->pluck('number', 'id')
                    ->map(fn ($v) => $v === null ? null : (int) $v)->all();
            }
        }

        // Wrap por unidad (cacheado): último día de shootDates() de esa unidad (null=principal).
        $wrapCache = [];
        $wrapOf = function ($unitId) use (&$wrapCache) {
            $key = $unitId === null ? 'n' : (string) $unitId;
            if (! array_key_exists($key, $wrapCache)) {
                $dates = ProductionCalendar::shootDates($unitId);
                $wrapCache[$key] = ! empty($dates) ? Carbon::parse(end($dates))->startOfDay() : null;
            }

            return $wrapCache[$key];
        };

        $out = [];
        foreach ($userIds as $uid) {
            $rows = $rowsByUser[$uid] ?? [];

            // Unidades que trabaja (para el wrap) + la EXCLUSIVA (para sufijo/revisar).
            $units       = [];
            $hasExclusive = false;
            $exclNumber  = null;
            foreach ($rows as $r) {
                $units[] = (int) $r->unit_id;
                if ($r->exclusive) {
                    $hasExclusive = true;
                    $exclNumber   = $numberById[(int) $r->unit_id] ?? $exclNumber;
                }
            }
            if (! $hasExclusive) {
                $units[] = null;   // principal / compartido → también principal
            }
            if (empty($rows)) {
                $units = [null];
            }
            $units = array_values(array_unique($units, SORT_REGULAR));

            $max = null;
            foreach ($units as $unitId) {
                $w = $wrapOf($unitId);
                if ($w !== null && ($max === null || $w->gt($max))) {
                    $max = $w;
                }
            }

            $out[$uid] = ['wrap' => $max, 'excl_number' => $exclNumber];
        }

        return $out;
    }

    /** Descriptor listo para la vista (etiquetas traducibles). */
    protected static function descriptor(array $coverage, array $expiry, array $review = [self::REV_OK], string $unitLabel = ''): array
    {
        [$state, $detail]   = $coverage;
        [$expState, $expAt] = $expiry;
        [$revState]         = $review;

        $labels = [
            self::SIN_CONTRATO => __('Sin contrato'),
            self::INCOMPLETO   => __('Contrato incompleto'),
            self::OK           => __('Con contrato'),
        ];
        $expLabels = [
            self::EXP_VENCE     => __('Vence antes del wrap'),
            self::EXP_SIN_FECHA => __('Sin fecha de fin'),
        ];

        $expLabel = $expLabels[$expState] ?? '';
        if ($expState === self::EXP_VENCE && $expAt) {
            $expLabel = __('Vence :fecha', ['fecha' => Carbon::parse($expAt)->format('d/m/Y')]);
        }

        return [
            'state'      => $state,
            'label'      => $labels[$state] ?? '',
            'detail'     => $detail,
            'exp_state'  => $expState,
            'exp_label'  => $expLabel,
            'exp_date'   => $expAt,
            'rev_state'  => $revState,
            'rev_label'  => $revState === self::REV_REVISAR ? __('Revisar unidad') : '',
            'unit_label' => $unitLabel,   // etiqueta de unidad viva para el CrewList (vacía si principal)
        ];
    }

    /** Conteo por estado a partir de un mapa de descriptores (todo el alcance). */
    public static function tally(array $descriptors): array
    {
        $c = ['sin' => 0, 'incompleto' => 0, 'ok' => 0, 'vence' => 0, 'sin_fecha' => 0, 'revisar' => 0, 'activos' => count($descriptors)];
        foreach ($descriptors as $d) {
            if ($d['state'] === self::SIN_CONTRATO)      $c['sin']++;
            elseif ($d['state'] === self::INCOMPLETO)    $c['incompleto']++;
            elseif ($d['state'] === self::OK)            $c['ok']++;

            if ($d['exp_state'] === self::EXP_VENCE)          $c['vence']++;
            elseif ($d['exp_state'] === self::EXP_SIN_FECHA)  $c['sin_fecha']++;

            if (($d['rev_state'] ?? self::REV_OK) === self::REV_REVISAR) $c['revisar']++;
        }
        $c['falta'] = $c['sin'] + $c['incompleto'];

        return $c;
    }

    /**
     * Los user_ids que casan un filtro, a partir del mapa de descriptores. Devuelve null si el filtro
     * no aplica (→ el llamador no acota). Exacto: el conjunto ES la lista de ids.
     *
     * @return array<int>|null
     */
    public static function idsMatching(array $descriptors, ?string $filter): ?array
    {
        if (! in_array($filter, self::filters(), true)) {
            return null;
        }
        $ids = [];
        foreach ($descriptors as $uid => $d) {
            $hit = match ($filter) {
                self::FILTER_SIN        => $d['state'] === self::SIN_CONTRATO,
                self::FILTER_INCOMPLETO => $d['state'] === self::INCOMPLETO,
                self::FILTER_FALTA      => $d['state'] === self::SIN_CONTRATO || $d['state'] === self::INCOMPLETO,
                self::FILTER_VENCE      => $d['exp_state'] === self::EXP_VENCE,
                self::FILTER_SIN_FECHA  => $d['exp_state'] === self::EXP_SIN_FECHA,
                self::FILTER_REVISAR    => ($d['rev_state'] ?? self::REV_OK) === self::REV_REVISAR,
                default                 => false,
            };
            if ($hit) {
                $ids[] = (int) $uid;
            }
        }

        return $ids;
    }
}
