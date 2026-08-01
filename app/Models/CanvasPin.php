<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un PIN sobre un lienzo del mapeo (delta #48). Pertenece al lienzo, no al scouting.
 *
 * Coordenadas RELATIVAS a la imagen (porcentaje 0–100), nunca píxeles, para que el
 * pin no se mueva al cambiar de tamaño en pantalla o en la imagen compuesta del PAE.
 *
 * Dos familias:
 *   - 'recurso': lista cerrada y corta (extintor, botiquín, salida, punto de reunión,
 *     tablero eléctrico, toma de agua, desfibrilador, acceso de ambulancia). Guarda
 *     `resource_type`.
 *   - 'peligro': REFERENCIA por `hazard_event_id` un peligro YA evaluado en el
 *     `risk_assessment` del scouting. NO abre una segunda lista ni crea peligros
 *     nuevos — el mapa no es una vía paralela para registrar peligros sin evaluar.
 */
class CanvasPin extends Model
{
    protected $table = 'canvas_pins';

    protected $fillable = [
        'scouting_canvas_id',
        'family',
        'resource_type',
        'hazard_event_id',
        'x_pct',
        'y_pct',
        'note',
        'photo_path',
        'geo_lat',
        'geo_lng',
    ];

    protected $casts = [
        'x_pct'   => 'float',
        'y_pct'   => 'float',
        'geo_lat' => 'float',
        'geo_lng' => 'float',
    ];

    /**
     * Recursos de emergencia — lista CERRADA y corta (clave interna => etiqueta).
     * Si el owner quiere agregar tipos después es trivial (una fila más); no hay
     * administrador de tipos por ahora, a propósito.
     */
    const RESOURCES = [
        'extintor'          => 'Extintor',
        'botiquin'          => 'Botiquín',
        'salida_emergencia' => 'Salida de emergencia',
        'punto_reunion'     => 'Punto de reunión',
        'tablero_electrico' => 'Tablero eléctrico / corte de energía',
        'toma_agua'         => 'Toma de agua',
        'desfibrilador'     => 'Desfibrilador (DEA)',
        'acceso_ambulancia' => 'Acceso de ambulancia',
    ];

    public function canvas()
    {
        return $this->belongsTo(ScoutingCanvas::class, 'scouting_canvas_id');
    }

    public function hazardEvent()
    {
        return $this->belongsTo(HazardEvent::class, 'hazard_event_id');
    }
}
