<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BORRADOR de scouting (guardado parcial EN SERVIDOR, del AUTOR).
 *
 * NO es un scouting: no se sella, no entra en ningún hash, no sale en listados ni documentos.
 * Existe para que cerrar la pestaña no cueste el trabajo del día — sobre todo las FOTOS, que el
 * borrador local no puede guardar. Se BORRA en cuanto el scouting real se guarda.
 *
 * Mismo contrato que [[VehicleInspectionDraft]], que lleva funcionando desde agosto.
 */
class ScoutingDraft extends Model
{
    protected $table = 'scouting_drafts';

    protected $fillable = ['production_id', 'created_by_id', 'client_key', 'values', 'photos'];

    protected $casts = [
        'values' => 'array',
        'photos' => 'array',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * El borrador de ESTE autor con ESTA clave local, o null.
     *
     * Siempre acotado por autor: la clave la propone el navegador, así que sin este filtro una
     * clave adivinada dejaría leer (o pisar) el borrador de otra persona.
     */
    public static function forAuthor(?string $clientKey, $userId): ?self
    {
        if (! $clientKey || ! $userId) {
            return null;
        }

        return static::where('created_by_id', $userId)
            ->where('client_key', $clientKey)
            ->first();
    }

    /** Fotos ya subidas, normalizadas a [{path, caption, risk_map}]. */
    public function photoList(): array
    {
        $out = [];
        foreach ((array) $this->photos as $p) {
            $path = is_array($p) ? ($p['path'] ?? null) : null;
            if (! $path) {
                continue;
            }
            $out[] = [
                'path'     => (string) $path,
                'caption'  => (string) ($p['caption'] ?? ''),
                'risk_map' => ! empty($p['risk_map']),
            ];
        }

        return $out;
    }

    /**
     * ¿Esta ruta fue subida DENTRO de este borrador?
     *
     * 🔒 El guardián del carril. Al guardar el scouting, el formulario manda RUTAS en vez de
     * archivos (las fotos ya viajaron). Sin esta comprobación, cualquiera podría mandar una ruta
     * arbitraria y colarla como evidencia de su documento —o apuntar a un archivo ajeno—. Sólo se
     * aceptan rutas que este mismo borrador, de este mismo autor, registró al subirlas.
     */
    public function owns(string $path): bool
    {
        foreach ($this->photoList() as $p) {
            if ($p['path'] === $path) {
                return true;
            }
        }

        return false;
    }
}
