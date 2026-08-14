<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CONTRACT BUILDER · FASE 1 — plantilla de contrato con anclas de firma (DocuSign).
 *
 * El `body` es HTML autorizado por producción (settings.manage) con dos tipos de marcador:
 *   · `{{campo}}`        → se llena con el trato (mismo catálogo que la carátula). Ver ContractTemplateRenderer.
 *   · `[[firma:CLAVE]]`  → ancla de firma de un puesto de la ruta (contratado / dept_hod / line_producer /
 *                          puesto:ID). Al firmar, se rellena con la autógrafa CONGELADA + su hash.
 *
 * Es ALTERNATIVA al clausulado subido: mismos scopes que ContractClause (forProduction/active/
 * appliesToSubtype) para poder elegirse igual.
 */
class ContractTemplate extends Model
{
    protected $table = 'contract_templates';

    protected $fillable = [
        'production_id', 'name', 'applies_to', 'body', 'language', 'version', 'is_active', 'created_by_id',
    ];

    protected $casts = [
        'applies_to' => 'array',
        'version'    => 'integer',
        'is_active'  => 'boolean',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /** Plantillas de la producción (o globales, production_id NULL). */
    public function scopeForProduction($q, $productionId)
    {
        return $q->where(fn ($w) => $w->whereNull('production_id')->orWhere('production_id', $productionId));
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', 1);
    }

    /** ¿Aplica a este subtipo de contrato (crew_work / rental / service)? */
    public function appliesToSubtype(?string $concept): bool
    {
        $list = $this->applies_to;
        return is_array($list) && in_array($concept, $list, true);
    }

    /**
     * La plantilla ACTIVA de la producción que aplica a este subtipo (o null). La más reciente gana.
     * `applies_to` es JSON → se filtra en PHP (no como scope SQL). Usada por el sobre (Fase 1c/1d).
     */
    public static function activeFor($productionId, ?string $concept): ?self
    {
        return static::forProduction($productionId)->active()->orderByDesc('id')->get()
            ->first(fn ($t) => $t->appliesToSubtype($concept));
    }
}
