<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Mapeo de riesgos (2026-08-01): imágenes valiosas de la locación por TIPO
 * (plano | satelital | dron/aéreo | foto). Sección APARTE y editable del scouting
 * (no pines: el motor de pines del delta #48 se retiró). Reusa la tabla
 * `scouting_canvases` que ya existe (id, scouting_report_id, type, name, image_path)
 * → sin SQL nuevo. El mapeo también incluye las fotos del scouting marcadas con el
 * check "Mapeo de riesgos" (ver ScoutingReport::additionalImagesList): así, sin más
 * imágenes, con esas se genera el mapeo.
 */
class RiskMapImage extends Model
{
    protected $table = 'scouting_canvases';

    protected $fillable = ['scouting_report_id', 'type', 'name', 'image_path'];

    /** Tipos ofrecidos (clave en BD => etiqueta de UI). 'aereo' se muestra como Dron/aéreo. */
    const TYPES = [
        'plano'     => 'Plano',
        'satelital' => 'Satelital',
        'aereo'     => 'Dron / aéreo',
        'foto'      => 'Foto',
    ];

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst((string) $this->type);
    }

    /**
     * URL MOSTRABLE de la imagen. Las nuevas se guardan ya como ruta relativa
     * `/storage/...` (Storage::url) — que se ve en cualquier dirección; el render
     * absoluto `http://127.0.0.1/...` era el bug del módulo viejo. Tolera además
     * rutas "bare" heredadas (p. ej. `scouting_canvases/x.jpg`) resolviéndolas.
     */
    public function imageUrl(): string
    {
        $p = (string) $this->image_path;
        if ($p === '') {
            return '';
        }
        if (strpos($p, '/storage/') === 0 || strpos($p, 'http') === 0 || strpos($p, 'data:') === 0) {
            return $p;
        }
        return Storage::url($p); // heredada sin prefijo → relativa /storage/...
    }
}
