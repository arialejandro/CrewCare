<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un marcador sobre una vista (delta #50): gota + icono + chip de etiqueta.
 *
 * UN SOLO estilo de pin en todo el documento. La etiqueta NO se teclea: se deriva
 * de `resource_type` (recurso) o del nombre del evento del catálogo (peligro).
 * Posición RELATIVA a la imagen (x_pct/y_pct, 0..100), inmune al tamaño y a la
 * impresión. `polygon` (área) queda para después.
 */
class RiskMapMarker extends Model
{
    protected $table = 'risk_map_markers';

    protected $fillable = [
        'view_id', 'sort_order',
        'kind', 'resource_type', 'event_id',
        'x_pct', 'y_pct', 'label_side', 'reference_text', 'polygon',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'event_id'   => 'integer',
        'polygon'    => 'array',
    ];

    /** Recursos de emergencia (lista cerrada) => etiqueta. El icono lo pinta _rm-icon. */
    const RESOURCE_TYPES = [
        'extintor'          => 'Extintor',
        'salida_emergencia' => 'Salida de emergencia',
        'botiquin'          => 'Botiquín',
        'punto_alarma'      => 'Punto de alarma',
        'manguera_hidrante' => 'Hidrante / manguera',
        'tablero_electrico' => 'Tablero eléctrico',
        'punto_reunion'     => 'Punto de reunión',
        'acceso_ambulancia' => 'Acceso de ambulancia',
    ];

    public function view()
    {
        return $this->belongsTo(RiskMapView::class, 'view_id');
    }

    /** Evento del catálogo (peligro). FK-soft: puede quedar nulo en recursos/áreas. */
    public function event()
    {
        return $this->belongsTo(HazardEvent::class, 'event_id');
    }

    /** Clave de icono para el parcial _rm-icon (tipo de recurso, o 'hazard'/'area'). */
    public function iconKey(): string
    {
        if ($this->kind === 'resource') {
            return (string) $this->resource_type;
        }
        return (string) $this->kind; // 'hazard' | 'area'
    }

    /** Etiqueta del recurso (para el chip). Los peligros la toman del evento en la vista. */
    public function resourceLabel(): string
    {
        return self::RESOURCE_TYPES[$this->resource_type] ?? '';
    }
}
