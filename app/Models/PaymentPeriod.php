<?php

namespace App\Models;

use App\Support\CurrentProduction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * VENTANA DE RECEPCIÓN POR PERIODO DE PAGO — la UNIDAD del bloque. Un PERIODO pertenece a una
 * producción y declara FRECUENCIA + VENTANA (apertura/cierre) + a quién le toca (por la
 * frecuencia HEREDADA del contrato, {@see PayeeContract::FREQ_*}).
 *
 *  - DAY PLAYER no es una excepción: es OTRO TIPO de periodo → sin semana, con `worked_on`
 *    (el día que trabajó) y capturado A MANO para un `payee_id` concreto (aún no hay roster).
 *  - La ventana ABRE y CIERRA pero NO RECHAZA: un documento fuera de ventana se recibe igual,
 *    MARCADO ({@see ExternalAuthorization::$received_out_of_window}). Rechazarlo mandaría a la
 *    persona de vuelta al correo y se perdería la centralización, que es todo el punto.
 *  - El estado del cumplimiento se DERIVA con {@see \App\Support\PayeePackage}; aquí NO se
 *    almacena "recibido" — el documento (ExternalAuthorization) es la prueba de recepción.
 */
class PaymentPeriod extends Model
{
    protected $table = 'payment_periods';

    const STATUS_OPEN   = 'open';
    const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'production_id', 'frequency', 'label',
        'opens_on', 'closes_on', 'worked_on', 'payee_id',
        'status', 'closed_at', 'closed_by_id', 'reopened_at', 'reopened_by_id',
        'created_by_id',
    ];

    protected $casts = [
        'opens_on'    => 'date',
        'closes_on'   => 'date',
        'worked_on'   => 'date',
        'closed_at'   => 'datetime',
        'reopened_at' => 'datetime',
    ];

    // ── Relaciones ────────────────────────────────────────────────────────────
    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class, 'production_id');
    }

    /** DAY PLAYER: el payee concreto capturado a mano (null en semanal/quincenal). */
    public function payee(): BelongsTo
    {
        return $this->belongsTo(Payee::class, 'payee_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_id');
    }

    // ── Estado de la ventana ──────────────────────────────────────────────────
    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isDayPlayer(): bool
    {
        return $this->frequency === PayeeContract::FREQ_DAY_PLAYER;
    }

    /** ¿La fecha cae DENTRO de la ventana de recepción [opens_on, closes_on]? */
    public function windowContains($date): bool
    {
        $d = $date instanceof Carbon ? $date->copy()->startOfDay() : Carbon::parse($date)->startOfDay();
        return $this->opens_on !== null && $this->closes_on !== null
            && $d->betweenIncluded($this->opens_on->copy()->startOfDay(), $this->closes_on->copy()->startOfDay());
    }

    public function frequencyLabel(): string
    {
        return PayeeContract::frequencies()[$this->frequency] ?? $this->frequency;
    }

    /** Etiqueta corta para pintar (usa `label` capturada; si no, deriva de frecuencia + ventana). */
    public function displayLabel(): string
    {
        if (trim((string) $this->label) !== '') {
            return $this->label;
        }
        if ($this->isDayPlayer() && $this->worked_on) {
            return $this->frequencyLabel() . ' · ' . $this->worked_on->format('d/m/Y');
        }
        return $this->frequencyLabel() . ' · ' . optional($this->opens_on)->format('d/m') . '–' . optional($this->closes_on)->format('d/m');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────
    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeForProduction($query, $productionId)
    {
        return $query->where('production_id', $productionId);
    }

    /**
     * Contratos a los que APLICA este periodo: los activos de la producción cuya frecuencia
     * coincide (heredada). En DAY PLAYER, además, acotado al `payee_id` capturado a mano.
     * Los contratos sin producción (production_id NULL) también entran (aún se está poblando).
     */
    public function matchingContracts()
    {
        $q = PayeeContract::query()->active()
            ->where('payment_frequency', $this->frequency)
            ->where(function ($w) {
                $w->where('production_id', $this->production_id)->orWhereNull('production_id');
            });

        if ($this->isDayPlayer() && $this->payee_id) {
            $q->where('payee_id', $this->payee_id);
        }

        return $q;
    }

    /**
     * VENTANA DE RECEPCIÓN — resuelve a qué periodo cuelga un documento recién recibido de un
     * payee, y si entró DENTRO o FUERA de ventana. Se usa para estampar (best-effort) al capturar.
     *
     * Prioriza el periodo ABIERTO cuya ventana contiene HOY (→ dentro). Si no hay ventana viva
     * pero sí un periodo de la frecuencia del payee, cuelga ahí MARCADO como fuera de ventana
     * (nunca rechaza). Si no hay ningún periodo aplicable, devuelve null (recibido sin periodo).
     *
     * @return array{period: ?PaymentPeriod, out_of_window: bool}
     */
    public static function resolveReception(Payee $payee, ?int $productionId = null): array
    {
        $productionId = $productionId ?: CurrentProduction::id();
        if (! $productionId) {
            return ['period' => null, 'out_of_window' => false];
        }

        // Frecuencias del payee (heredadas de sus contratos activos de esta producción).
        $freqs = PayeeContract::query()->active()
            ->where('payee_id', $payee->id)
            ->where(function ($w) use ($productionId) {
                $w->where('production_id', $productionId)->orWhereNull('production_id');
            })
            ->whereNotNull('payment_frequency')
            ->pluck('payment_frequency')->unique()->values()->all();

        if (empty($freqs)) {
            return ['period' => null, 'out_of_window' => false];
        }

        // Filtro común: frecuencia del payee y, para day player, acotado a ESTE payee.
        $applies = function ($q) use ($freqs, $payee) {
            $q->whereIn('frequency', $freqs)
              ->where(function ($w) use ($payee) {
                  $w->where('frequency', '!=', PayeeContract::FREQ_DAY_PLAYER)
                    ->orWhere(function ($x) use ($payee) {
                        $x->where('frequency', PayeeContract::FREQ_DAY_PLAYER)->where('payee_id', $payee->id);
                    });
              });
        };

        $today = Carbon::today();

        // 1) Periodo ABIERTO con la ventana viva HOY → dentro de ventana.
        $open = self::query()->forProduction($productionId)->open()
            ->where($applies)
            ->whereDate('opens_on', '<=', $today)->whereDate('closes_on', '>=', $today)
            ->orderBy('closes_on')->first();
        if ($open) {
            return ['period' => $open, 'out_of_window' => false];
        }

        // 2) Sin ventana viva: cuelga al periodo más reciente aplicable, MARCADO fuera de ventana.
        $latest = self::query()->forProduction($productionId)
            ->where($applies)
            ->orderByDesc('closes_on')->first();
        if ($latest) {
            return ['period' => $latest, 'out_of_window' => true];
        }

        return ['period' => null, 'out_of_window' => false];
    }
}
