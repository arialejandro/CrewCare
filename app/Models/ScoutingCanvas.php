<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un LIENZO del mapeo de una locación: una imagen (satelital, foto, plano o aéreo)
 * sobre la que se colocan pines. Un scouting puede tener VARIOS lienzos y cada uno
 * lleva SUS PROPIOS pines — subir un plano o una aérea después no migra los pines
 * de las fotos: son vistas distintas del mismo lugar (delta #48).
 *
 * La imagen vive en disco (disco 'public', comprimida por ImageCompressor::store),
 * NO como data-URI: una locación puede llevar muchas y no queremos inflar filas.
 */
class ScoutingCanvas extends Model
{
    protected $table = 'scouting_canvases';

    protected $fillable = [
        'scouting_report_id',
        'type',
        'name',
        'image_path',
    ];

    /** Los cuatro tipos de lienzo (clave interna => etiqueta de usuario). */
    const TYPES = [
        'satelital' => 'Satelital',
        'foto'      => 'Foto',
        'plano'     => 'Plano',
        'aereo'     => 'Aéreo',
    ];

    public function scouting()
    {
        return $this->belongsTo(ScoutingReport::class, 'scouting_report_id');
    }

    public function pins()
    {
        return $this->hasMany(CanvasPin::class, 'scouting_canvas_id');
    }

    /**
     * Los lienzos SATELITAL y AÉREO son los únicos donde tiene sentido ofrecer
     * "colocar en mi ubicación actual" (GPS del navegador). En foto/plano no.
     */
    public function usesGeo(): bool
    {
        return in_array($this->type, ['satelital', 'aereo'], true);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
