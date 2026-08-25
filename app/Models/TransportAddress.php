<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * TransportAddress — dirección PRIVADA que presetea transpo (Bloque 2 §3).
 *
 * El PDF y la mayoría ven `publicLabel()` ('CASA' por defecto). La calle real (`address`)
 * la ve SÓLO el driver asignado a la corrida que la usa y los miembros de transpo en la
 * allowlist (`viewers`). Es un señalamiento simple, no una jerarquía.
 */
class TransportAddress extends Model
{
    protected $table = 'transport_addresses';

    protected $guarded = ['id'];

    protected $casts = [
        'is_private' => 'boolean',
        'is_active'  => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    public function scopeForProduction($query, ?int $productionId)
    {
        return $query->where('production_id', $productionId);
    }

    /** Miembros de transpo autorizados a ver la calle real (allowlist, sin jerarquía). */
    public function viewers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'transport_address_viewers', 'transport_address_id', 'user_id')
            ->withTimestamps();
    }

    /** Lo que ve TODO el mundo en el documento: 'CASA' salvo que transpo fije otro rótulo. */
    public function publicLabel(): string
    {
        if (! $this->is_private) {
            return $this->label;
        }

        return trim((string) $this->public_label) !== '' ? $this->public_label : 'CASA';
    }

    /**
     * ¿Este viewer puede ver la calle real?
     *   - Si la dirección NO es privada → sí.
     *   - El DRIVER asignado a la corrida que la usa → sí (lo resuelve quien llama, $isRunDriver).
     *   - Un miembro de transpo en la allowlist → sí.
     * Cualquier otro → no (ve 'CASA').
     */
    public function realAddressVisibleTo(?User $user, bool $isRunDriver = false): bool
    {
        if (! $this->is_private) {
            return true;
        }
        if ($isRunDriver) {
            return true;
        }
        if (! $user) {
            return false;
        }

        return $this->viewers()->where('users.id', $user->id)->exists();
    }
}
