<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * UNA NOTA del recorrido: una foto y lo que hay que resolver en ella.
 *
 * La unidad de trabajo es la nota, no el documento. Por eso dos scouters pueden estar en la misma
 * locación a la vez sin pisarse: cada quien AÑADE las suyas y nadie guarda un formulario entero.
 *
 * `created_by_id` es dato INTERNO: en la app se ve quién reportó qué (para que entre ellos se
 * entiendan), pero NO se imprime en el PDF que va a arte — ahí el documento lo firma "Locaciones"
 * como departamento, no una persona. Decisión del owner.
 */
class TechScoutNote extends Model
{
    protected $table = 'tech_scout_notes';

    protected $fillable = ['tech_scout_id', 'photo_path', 'note', 'story_label', 'created_by_id', 'edited_at'];

    protected $casts = ['edited_at' => 'datetime'];

    public function scout(): BelongsTo
    {
        return $this->belongsTo(TechScout::class, 'tech_scout_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * ¿La tocaron después de capturarla?
     *
     * El owner usa este documento para zanjar discusiones: «si no está en las notas, no se pidió».
     * Ese argumento sólo se sostiene si una nota no se puede cambiar en silencio — si no,
     * cualquiera podría añadir o reescribir después y decir que lo anotó en el recorrido. No se
     * sella (sería pesado para algo que se edita a diario), pero editar SÍ deja marca.
     */
    public function wasEdited(): bool
    {
        return $this->edited_at !== null;
    }

    /** Nombre corto de quien la puso — sólo para la vista interna, nunca para el PDF. */
    public function authorName(): string
    {
        return $this->author ? User::displayName($this->author) : '—';
    }
}
