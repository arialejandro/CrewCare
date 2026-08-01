<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * RBAC foundation — new department catalog (PascalCase, Linux/AWS-safe). Additive;
 * does NOT replace the legacy lowercase `departamento` model / `departamentos` table.
 */
class Department extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'radio_channel', 'active', 'sort_order'];

    protected $casts = ['active' => 'boolean', 'sort_order' => 'integer'];

    public function positions()
    {
        return $this->hasMany(Position::class);
    }

    /**
     * (2026-07-13) MÓDULO 14 — nombre de departamento localizado.
     * Regresa name_en cuando el locale es 'en' y la columna existe y no está
     * vacía; si no, cae a name. Guard defensivo de columna (PROD aún sin ALTER).
     */
    public function getNameLocalizedAttribute()
    {
        if (app()->getLocale() === 'en'
            && \Illuminate\Support\Facades\Schema::hasColumn('departments', 'name_en')
            && !empty($this->name_en)) {
            return $this->name_en;
        }

        return $this->name;
    }
}
