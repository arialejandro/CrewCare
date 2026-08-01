<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * RBAC foundation — reusable position catalog.
 *
 * production_id NULL = global catalog template (reusable by any production);
 * production_id set  = position custom to that production. is_hod mirrors the
 * [HOD] markers from ORG-TAXONOMY.md.
 */
class Position extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'department_id', 'production_id', 'is_hod', 'active', 'sort_order'];

    protected $casts = [
        'is_hod' => 'boolean',
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function production()
    {
        return $this->belongsTo(Production::class);
    }

    /** Global catalog templates (not specific to any production). */
    public function scopeGlobal($query)
    {
        return $query->whereNull('production_id');
    }

    /**
     * (2026-07-13) MÓDULO 14 — nombre de posición localizado.
     * Regresa name_en cuando el locale es 'en' y la columna existe y no está
     * vacía; si no, cae a name. Guard defensivo de columna (PROD aún sin ALTER).
     */
    public function getNameLocalizedAttribute()
    {
        if (app()->getLocale() === 'en'
            && \Illuminate\Support\Facades\Schema::hasColumn('positions', 'name_en')
            && !empty($this->name_en)) {
            return $this->name_en;
        }

        return $this->name;
    }
}
