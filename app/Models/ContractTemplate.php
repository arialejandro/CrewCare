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

    /** Modo de autoría: body HTML redactado (default) vs PDF subido con etiquetas colocadas encima. */
    public const SOURCE_HTML = 'html';
    public const SOURCE_PDF  = 'pdf';

    /** Categoría del documento: el CONTRATO principal (default) o un ANEXO (documento adicional). */
    public const CATEGORY_CONTRACT = 'contrato';
    public const CATEGORY_ANNEX    = 'anexo';

    protected $fillable = [
        'production_id', 'name', 'applies_to', 'category', 'sort_order', 'body', 'language', 'architecture', 'bilingual',
        'page_size', 'font_family', 'font_size', 'initials_each_page', 'version', 'is_active', 'created_by_id',
        'source_kind', 'pdf_path', 'pdf_original_name', 'field_map',
    ];

    protected $casts = [
        'applies_to'         => 'array',
        'version'            => 'integer',
        'sort_order'         => 'integer',
        'is_active'          => 'boolean',
        'bilingual'          => 'boolean',
        'initials_each_page' => 'boolean',
        'field_map'          => 'array',
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
     * La plantilla-CONTRATO ACTIVA de la producción que aplica a este subtipo (o null). La más reciente
     * gana. Filtra por CATEGORÍA (default 'contrato') para que un anexo NUNCA se elija como el contrato
     * principal. `applies_to` es JSON → se filtra en PHP. Usada por el sobre (Fase 1c/1d) y la emisión.
     */
    public static function activeFor($productionId, ?string $concept, string $category = self::CATEGORY_CONTRACT): ?self
    {
        return static::forProduction($productionId)->active()
            ->where('category', $category)
            ->orderByDesc('id')->get()
            ->first(fn ($t) => $t->appliesToSubtype($concept));
    }

    /** Las plantillas-ANEXO activas que aplican al subtipo, en orden (sort_order). */
    public static function activeAnnexes($productionId, ?string $concept)
    {
        return static::forProduction($productionId)->active()
            ->where('category', self::CATEGORY_ANNEX)
            ->orderBy('sort_order')->orderBy('id')->get()
            ->filter(fn ($t) => $t->appliesToSubtype($concept))
            ->values();
    }

    public function isAnnex(): bool
    {
        return ($this->category ?? self::CATEGORY_CONTRACT) === self::CATEGORY_ANNEX;
    }

    /** ¿Esta plantilla es un PDF subido (con etiquetas colocadas encima) en vez de body HTML? */
    public function isPdfSource(): bool
    {
        return ($this->source_kind ?? self::SOURCE_HTML) === self::SOURCE_PDF;
    }

    public function isHtmlSource(): bool
    {
        return ! $this->isPdfSource();
    }

    /**
     * Las etiquetas colocadas sobre el PDF, normalizadas: [{page, x_pct, y_pct, w_pct, type, key}].
     * Siempre un array (aunque field_map venga null o basura). Coordenadas en % del tamaño de página.
     */
    public function placedFields(): array
    {
        $map = $this->field_map;
        if (! is_array($map)) {
            return [];
        }
        return array_values(array_filter($map, fn ($f) => is_array($f)
            && isset($f['page'], $f['x_pct'], $f['y_pct'], $f['type'], $f['key'])));
    }

    /** Etiquetas de DATO (se auto-llenan con el trato). */
    public function dataFields(): array
    {
        return array_values(array_filter($this->placedFields(), fn ($f) => ($f['type'] ?? null) === 'data'));
    }

    /** Etiquetas de FIRMA (ancla de un firmante de la ruta). */
    public function signFields(): array
    {
        return array_values(array_filter($this->placedFields(), fn ($f) => ($f['type'] ?? null) === 'sign'));
    }
}
