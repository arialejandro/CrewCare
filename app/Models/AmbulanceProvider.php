<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * EMPRESA prestadora de ambulancias. Se califica LA PRIMERA VEZ que se necesita
 * (puede ser en prep o el mismo día que llega la unidad); una vez calificada NO se
 * vuelve a preguntar cada día. Su padrón vive en {@see AmbulanceCrew}.
 *
 * PASO 5 (quien cobra): el proveedor es la capa "OPERAR" (documentos para operar +
 * inspecciones) y se LIGA a su identidad {@see Payee} (capa "COBRAR"). `payee_id` es el
 * puente; los documentos de EMPRESA cuelgan del payee (base única) — ver companyDocuments().
 * El proveedor NO se destruye: las actas selladas referencian su id y congelan su nombre.
 */
class AmbulanceProvider extends Model
{
    protected $table = 'ambulance_providers';

    protected $fillable = ['payee_id', 'name', 'rfc', 'contact_phone', 'sanitary_manager', 'notes', 'is_active', 'created_by_id'];

    protected $casts = ['is_active' => 'boolean'];

    public function crew(): HasMany
    {
        return $this->hasMany(AmbulanceCrew::class, 'provider_id');
    }

    /** Identidad en la base única (persona MORAL) — capa "quién cobra". */
    public function payee(): BelongsTo
    {
        return $this->belongsTo(Payee::class, 'payee_id');
    }

    /**
     * Documentos que colgaban directo del proveedor (legado / transición). Tras el Paso 5 los
     * de EMPRESA viven en el payee; este morphMany se conserva para el fallback y para no romper
     * filas históricas cuyo holder siga siendo el proveedor.
     */
    public function authorizations(): MorphMany
    {
        return $this->morphMany(ExternalAuthorization::class, 'holder');
    }

    /**
     * Documentos de OPERAR de nivel EMPRESA. FUENTE ÚNICA para la ficha del proveedor: si hay
     * payee ligado, cuelgan de él (base única, Paso 5); si aún no (transición), del proveedor.
     * Devuelve un Query builder (se le encadena ->get() con el orden que quiera la vista).
     */
    public function companyDocuments()
    {
        if ($this->payee_id) {
            return ExternalAuthorization::query()
                ->where('holder_type', (new Payee)->getMorphClass())
                ->where('holder_id', $this->payee_id)
                ->where('level', ExternalAuthorization::LEVEL_COMPANY);
        }

        return $this->authorizations()->where('level', ExternalAuthorization::LEVEL_COMPANY);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }
}
