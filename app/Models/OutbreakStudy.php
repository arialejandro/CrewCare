<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\HasDigitalSignatures;
use App\Traits\GeneratesUuidKey;

/**
 * ESTUDIO DE BROTE (delta #45): el documento que el MÉDICO decide emitir cuando ÉL lo juzga.
 *
 * El sistema NUNCA lo genera solo ni sugiere que hay brote: aporta los CONTEOS (counts_snapshot,
 * congelado) y el médico pone el criterio. Estructura NOM-017-SSA2-2012: definición operacional
 * de casos, descripción por tiempo/lugar/persona, tasa de ataque, hipótesis y medidas de control.
 *
 * Se sella con HasDigitalSignatures sobre el DATO (nunca sobre el render) y entra al verificador
 * público como 'brote'. A diferencia de la GRÁFICA (agregada, sin nombres), este documento SÍ
 * puede llevar NOMBRES: es un documento clínico firmado por quien responde por él.
 */
class OutbreakStudy extends Model
{
    use HasDigitalSignatures, GeneratesUuidKey;

    protected $table = 'outbreak_studies';

    protected $fillable = [
        'uuid', 'production_id',
        'created_by_id', 'medic_name', 'medic_cedula', 'medic_cedula_verified',
        'title', 'group_key', 'period_from', 'period_to',
        'case_definition', 'time_description', 'place_description', 'person_description',
        'attack_rate_cases', 'attack_rate_population', 'attack_rate_note',
        'hypothesis', 'control_measures',
        'counts_snapshot',
        'is_active',
    ];

    protected $casts = [
        'period_from'     => 'date',
        'period_to'       => 'date',
        'counts_snapshot' => 'array',
        'is_active'       => 'boolean',
        // medic_cedula_verified se deja SIN cast: tri-estado (null = no aplica) como en cmedic.
    ];

    /**
     * FUERA del hash: solo la bandera de estado. Todo el contenido (definición, descripciones,
     * tasa, hipótesis, medidas, snapshot de conteos e identidad del médico) SE FIRMA.
     */
    protected $signatureExcludes = ['is_active'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /** Folio estable para el verificador público y la cadena CFDI. */
    public function folio(): string
    {
        return 'BRO-' . str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }
}
