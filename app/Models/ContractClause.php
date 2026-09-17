<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * EL CONTRATO · PASO B — un CLAUSULADO (PDF byte-intact) de una producción. Nombre LIBRE,
 * declara a qué SUBTIPOS aplica, su IDIOMA, y se VERSIONA sin alterar los contratos ya emitidos
 * (cada contrato guarda el id EXACTO de la versión que usó). Se desactiva sin borrar.
 */
class ContractClause extends Model
{
    protected $table = 'contract_clauses';

    const LANG_ES        = 'es';
    const LANG_EN        = 'en';
    const LANG_BILINGUAL = 'bilingual';

    protected $fillable = [
        'production_id', 'name', 'applies_to', 'language',
        'file_path', 'original_filename', 'file_hash',
        'version', 'root_id', 'is_active', 'uploaded_by_id',
    ];

    protected $casts = [
        'applies_to' => 'array',
        'is_active'  => 'boolean',
        'version'    => 'integer',
    ];

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    public function scopeForProduction($query, $productionId)
    {
        return $query->where('production_id', $productionId);
    }

    /** ¿Aplica este clausulado al subtipo de contrato dado (crew_work|equipment_rental|service)? */
    public function appliesToSubtype(string $subtype): bool
    {
        return in_array($subtype, (array) $this->applies_to, true);
    }

    public function isBilingual(): bool
    {
        return $this->language === self::LANG_BILINGUAL;
    }

    /** El id de la FAMILIA (la 1ª versión); si es la raíz, su propio id. */
    public function rootId(): int
    {
        return (int) ($this->root_id ?: $this->id);
    }

    /** Idiomas válidos (clave => etiqueta traducible). */
    public static function languages(): array
    {
        return [
            self::LANG_ES        => __('Español'),
            self::LANG_EN        => __('Inglés'),
            self::LANG_BILINGUAL => __('Bilingüe (doble columna)'),
        ];
    }

    /** Subtipos ofrecibles (reusa las constantes de PayeeContract, sin fijar tipos aquí). */
    public static function subtypes(): array
    {
        return [
            PayeeContract::CONCEPT_CREW    => __('Trabajo de crew'),
            PayeeContract::CONCEPT_RENTAL  => __('Renta de equipo'),
            PayeeContract::CONCEPT_SERVICE => __('Servicio'),
        ];
    }
}
