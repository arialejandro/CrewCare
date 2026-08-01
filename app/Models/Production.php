<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * RBAC foundation — NEW first-class entity. A user can have a different role in each
 * production via the production_user pivot (withPivot role/department/position/is_lead).
 */
class Production extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'code', 'client_name', 'start_date', 'end_date',
        'status', 'active', 'settings',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'active' => 'boolean',
        'settings' => 'array',
    ];

    public function members()
    {
        return $this->belongsToMany(User::class, 'production_user')
            ->withPivot(['department_id', 'position_id', 'role', 'is_lead'])
            ->withTimestamps();
    }
}
