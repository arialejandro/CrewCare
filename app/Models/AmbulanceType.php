<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Tipo de ambulancia (NOM-034-SSA3-2013), AMB-01..AMB-06. Catálogo de FONDO:
 * se consulta, no se edita (ver 2026-08-08-ambulance-catalog.sql, delta #51).
 * Gemelo de {@see Tool}.
 *
 *   terrestre: traslado (L1/A) ⊆ básica (L2/B) ⊆ avanzada (L3/C) ⊆ UCI (L4/D)
 *   aérea (E) y marítima (F): rama propia, `level` NULL — NO son peldaños de la
 *   escalera terrestre; cumplen A-D según su CAPACIDAD RESOLUTIVA (dato del acta).
 *
 * `verified_at` NULL = derivado del texto de la NOM, sin auditar. El estado se
 * DECLARA; nadie nace verificado.
 */
class AmbulanceType extends Model
{
    protected $table = 'ambulance_types';

    // verified_at / verified_by_id son estado server-only: fuera del mass-assign.
    protected $guarded = ['id', 'verified_at', 'verified_by_id'];

    protected $casts = [
        'level'       => 'integer',
        'is_active'   => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    public function isTerrestre(): bool
    {
        return $this->rama === 'terrestre';
    }

    /**
     * Los puntos de verificación que le TOCAN a este tipo, DERIVADOS del catálogo
     * (no duplicados): la pertenencia sale de rama + nivel, con herencia A⊆B⊆C⊆D.
     *
     * TERRESTRE: puntos de rama {terrestre, todas} con min_level <= su level y
     *   (max_level NULL o su level <= max_level). Así AMB-03 (avanzada, L3) hereda
     *   A+B+C; AMBV-104 (DEA, max_level=2) NO aparece en C/D (lo sustituye C.1.1).
     *
     * AÉREA/MARÍTIMA (level NULL): sus PROPIOS puntos (rama E/F) SIEMPRE, más los
     *   puntos 'todas' según la CAPACIDAD RESOLUTIVA declarada ($capacityLevel;
     *   apéndice A por defecto si no se declara). Los puntos rama 'terrestre'
     *   (sirena, torreta, llanta) NO aplican a una aeronave/embarcación — correcto.
     *
     * @param  int|null $capacityLevel  Solo aplica a aérea/marítima (1..4).
     * @return \Illuminate\Support\Collection<int,AmbulanceInspectionPoint>
     */
    public function applicablePoints(?int $capacityLevel = null): Collection
    {
        $q = AmbulanceInspectionPoint::query()->where('is_active', 1);

        if ($this->isTerrestre()) {
            $lvl = (int) $this->level;
            $q->whereIn('rama', ['terrestre', 'todas'])
              ->where(function ($w) use ($lvl) {
                  $w->whereNull('min_level')->orWhere('min_level', '<=', $lvl);
              })
              ->where(function ($w) use ($lvl) {
                  $w->whereNull('max_level')->orWhere('max_level', '>=', $lvl);
              });
        } else {
            $lvl = $capacityLevel ?? 1;
            $q->where(function ($w) use ($lvl) {
                // Puntos propios de la rama (aérea E.* / marítima F.*), sin filtro de nivel.
                $w->where('rama', $this->rama)
                  // + los transversales 'todas' según la capacidad resolutiva declarada.
                  ->orWhere(function ($t) use ($lvl) {
                      $t->where('rama', 'todas')
                        ->where(function ($a) use ($lvl) {
                            $a->whereNull('min_level')->orWhere('min_level', '<=', $lvl);
                        })
                        ->where(function ($a) use ($lvl) {
                            $a->whereNull('max_level')->orWhere('max_level', '>=', $lvl);
                        });
                  });
            });
        }

        return $q->orderBy('sort_order')->orderBy('code')->get();
    }
}
