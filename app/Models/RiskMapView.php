<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Una PÁGINA del mapeo (delta #50). Imagen anotada + tres narrativas cortas.
 *
 * La imagen ORIGINAL (`image_original_path`) es INMUTABLE: nunca se sobrescribe.
 * El realce opcional vive aparte (`image_enhanced_path`, fuera de alcance hoy).
 * Las rutas se guardan RELATIVAS (/storage/...) para que se vean en cualquier
 * dirección y sobrevivan a la impresión.
 */
class RiskMapView extends Model
{
    protected $table = 'risk_map_views';

    protected $fillable = [
        'risk_map_id', 'sort_order',
        'view_type', 'label',
        'image_original_path', 'image_enhanced_path', 'image_source',
        'narrative_what', 'narrative_decision', 'narrative_action',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /** Tipo de vista => etiqueta propuesta (editable). */
    const VIEW_TYPES = [
        'satelital'          => 'Satelital',
        'aerea'              => 'Aérea (dron)',
        'fachada_calle'      => 'Fachada / calle',
        'acceso_circulacion' => 'Acceso y circulación',
        'set'                => 'Set',
        'basecamp'           => 'Basecamp',
        'detalle'            => 'Detalle',
        'otro'               => 'Otro',
    ];

    /** Origen de la imagen => etiqueta (para el pie "Croquis del safety", etc.). */
    const SOURCE_LABELS = [
        'scouting_photo' => 'Foto del scouting',
        'upload'         => 'Imagen subida',
        'satelital'      => 'Imagen satelital',
        'dron'           => 'Toma de dron',
    ];

    public function riskMap()
    {
        return $this->belongsTo(RiskMap::class, 'risk_map_id');
    }

    public function markers()
    {
        return $this->hasMany(RiskMapMarker::class, 'view_id')
            ->orderBy('sort_order')->orderBy('id');
    }

    /** Etiqueta a mostrar: la editada o, si no hay, la propuesta del tipo. */
    public function displayLabel(): string
    {
        $label = trim((string) $this->label);
        if ($label !== '') {
            return $label;
        }
        return self::VIEW_TYPES[$this->view_type] ?? 'Vista';
    }

    public function typeLabel(): string
    {
        return self::VIEW_TYPES[$this->view_type] ?? ucfirst((string) $this->view_type);
    }

    public function sourceLabel(): string
    {
        return self::SOURCE_LABELS[$this->image_source] ?? '';
    }

    /** URL mostrable de la imagen original (relativa /storage/...; tolera rutas bare). */
    public function imageUrl(): string
    {
        return self::resolveUrl($this->image_original_path);
    }

    /** ¿Tiene algún marcador de peligro? (para pintar su lista de normas). */
    public function hasHazards(): bool
    {
        foreach ($this->markers as $m) {
            if ($m->kind === 'hazard') {
                return true;
            }
        }
        return false;
    }

    /** ¿Tiene algún área trazada? (leyenda de colores; fuera de alcance hoy). */
    public function hasAreas(): bool
    {
        foreach ($this->markers as $m) {
            if ($m->kind === 'area') {
                return true;
            }
        }
        return false;
    }

    protected static function resolveUrl($path): string
    {
        $p = (string) $path;
        if ($p === '') {
            return '';
        }
        if (strpos($p, '/storage/') === 0 || strpos($p, 'http') === 0 || strpos($p, 'data:') === 0) {
            return $p;
        }
        return Storage::url($p);
    }
}
