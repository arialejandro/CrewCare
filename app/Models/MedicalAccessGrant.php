<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * MedicalAccessGrant — BITÁCORA de otorgamiento de acceso médico DIRECTO a una persona (item 7).
 *
 * QUÉ ES Y QUÉ NO: el permiso EFECTIVO lo guarda Spatie en `model_has_permissions` (givePermissionTo
 * / revokePermissionTo). Esta tabla NO es la fuente de autorización — es la TRAZABILIDAD: quién
 * otorgó/revocó el acceso al expediente clínico y cuándo. El día que haya una pregunta de privacidad
 * ("¿quién tuvo acceso y desde cuándo?"), la respuesta vive aquí.
 *
 * MODELO append-ish: un otorgamiento activo tiene revoked_at NULL. Revocar NO borra la fila: le pone
 * revoked_at + revoked_by_id, para conservar el historial. Un nuevo otorgamiento tras una revocación
 * es una fila NUEVA.
 *
 * DEGRADACIÓN: sin la tabla (SQL no aplicado) todo el registro es no-op y la UI del toggle no rompe.
 */
class MedicalAccessGrant extends Model
{
    protected $table = 'medical_access_grants';

    protected $fillable = [
        'user_id',
        'permission',
        'granted_by_id',
        'granted_at',
        'revoked_by_id',
        'revoked_at',
        'note',
    ];

    protected $casts = [
        'granted_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /** ¿La BD ya tiene la tabla? Memo estático (mismo patrón que MedicCredential::supportsCredentials). */
    protected static $supported = null;

    public static function supportsGrants()
    {
        if (self::$supported === null) {
            self::$supported = Schema::hasTable('medical_access_grants');
        }
        return self::$supported;
    }

    /** La persona a la que se le otorgó el acceso clínico. */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Quién lo otorgó (super-admin). Sin FK dura (convención del repo). */
    public function grantedBy()
    {
        return $this->belongsTo(User::class, 'granted_by_id');
    }

    /** Quién lo revocó. */
    public function revokedBy()
    {
        return $this->belongsTo(User::class, 'revoked_by_id');
    }

    /** Solo los otorgamientos ACTIVOS (aún no revocados). */
    public function scopeActive($query)
    {
        return $query->whereNull('revoked_at');
    }
}
