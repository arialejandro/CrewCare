<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * TransportOrderRun — LA CORRIDA (renglón de la orden, Bloque 2 §2).
 *
 * TIPO: normal | aeropuerto | aplicacion. El vehículo es obligatorio salvo `aplicacion`
 * (único caso con corrida SIN vehículo registrado). El pick up se guarda con el lenguaje del
 * motor de horarios del llamado (offset + literal + lugar) pero la orden es DUEÑA de su valor:
 * sólo lo COMPARA contra el back, no lo escribe (decisión §5 = cruce + aviso).
 */
class TransportOrderRun extends Model
{
    protected $table = 'transport_order_runs';

    protected $guarded = ['id'];

    protected $casts = [
        'sort_order'            => 'integer',
        'pickup_offset_minutes' => 'integer',
        'travel_minutes'        => 'integer',
        'travel_adjust_minutes' => 'integer',
        'pickup_point_id'       => 'integer',
        'dest_location_ref'     => 'integer',
        'is_discreet'           => 'boolean',
        'equipment'             => 'array',
        'is_active'             => 'boolean',
    ];

    // EJE 1 · ¿deriva el pick up?  set = calculado · fuera = a mano (default para filas legadas).
    public const CLASS_SET   = 'set';
    public const CLASS_FUERA = 'fuera';

    // EJE 2 · qué movimiento es (INDEPENDIENTE de la clase). aplicacion = único SIN vehículo.
    public const TYPE_NORMAL     = 'normal';
    public const TYPE_AEROPUERTO = 'aeropuerto';
    public const TYPE_APLICACION = 'aplicacion';

    public const TYPES = [self::TYPE_NORMAL, self::TYPE_AEROPUERTO, self::TYPE_APLICACION];

    // Lugares: una corrida apunta a un lugar del llamado, una dirección privada, o texto libre.
    public const PLACE_CALL    = 'call';    // call_places
    public const PLACE_PRIVATE = 'private'; // transport_addresses
    public const PLACE_TEXT    = 'text';

    /** Identidad estable: se asigna al crear, persiste al editar, se copia al clonar la versión. */
    protected static function booted()
    {
        static::creating(function (self $run) {
            try {
                if (empty($run->run_key) && \Illuminate\Support\Facades\Schema::hasColumn($run->getTable(), 'run_key')) {
                    $run->run_key = (string) \Illuminate\Support\Str::uuid();
                }
            } catch (\Throwable $e) {
                // Columna inexistente o driver sin introspección: no romper.
            }
        });
    }

    // ── Relaciones ───────────────────────────────────────────────────────────
    public function order(): BelongsTo
    {
        return $this->belongsTo(TransportOrder::class, 'transport_order_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_user_id');
    }

    public function occupants(): HasMany
    {
        return $this->hasMany(TransportRunOccupant::class, 'transport_order_run_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /** Origen del catálogo (Fase 1) para las corridas de SET. */
    public function point(): BelongsTo
    {
        return $this->belongsTo(TransportPickupPoint::class, 'pickup_point_id');
    }

    // ── Reglas ───────────────────────────────────────────────────────────────
    /** ¿El pick up se deriva? (EJE 1) */
    public function isSet(): bool
    {
        return $this->run_class === self::CLASS_SET;
    }

    /** Sólo el transporte de aplicación puede ir sin vehículo registrado (EJE 2, aplica en ambas clases). */
    public function requiresVehicle(): bool
    {
        return $this->run_type !== self::TYPE_APLICACION;
    }

    public function isAirport(): bool
    {
        return $this->run_type === self::TYPE_AEROPUERTO;
    }

    /** Etiqueta legible del tipo (para chips / encabezados). */
    public function typeLabel(): string
    {
        return match ($this->run_type) {
            self::TYPE_AEROPUERTO => 'Aeropuerto',
            self::TYPE_APLICACION => 'Transporte de aplicación',
            default               => 'Normal',
        };
    }
}
