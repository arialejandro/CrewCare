<?php

namespace App\Policies;

use App\Models\Addendum;
use App\Models\InjuryReport;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * AddendumPolicy — PROPIEDAD del anexo médico (Paso Injury, 2026-07-20).
 *
 * Regla de dominio (item 11): un addendum pertenece a su AUTOR-MÉDICO. Sólo un
 * médico (rol Spatie `medic`, fuente única User::isMedic()) crea un anexo, y sólo
 * el médico que lo creó puede editarlo/eliminarlo — NINGÚN otro médico toca un
 * anexo ajeno. La creación no exige "haber creado antes uno": cada anexo nace con
 * su propio autor y su propia cédula/sello, de modo que un médico de relevo puede
 * agregar un seguimiento legítimo; lo que se prohíbe es EDITAR el de otro.
 *
 * La autenticación base y el permiso de módulo los da el middleware de ruta
 * (permission:medical.create) + el feature flag 'medical_addendum'; esta policy
 * añade la capa de PROPIEDAD clínica encima.
 *
 * OJO al probar: AuthServiceProvider::boot() tiene un Gate::before que hace que el
 * super-admin pase TODA habilidad sin entrar aquí. Para ejercitar estas reglas hay
 * que usar un usuario `medic` NO super-admin (y un segundo medic no-dueño).
 */
class AddendumPolicy
{
    use HandlesAuthorization;

    /**
     * ¿Puede crear un anexo? Debe ser médico. El $report llega por contexto
     * (authorize('create', [Addendum::class, $report])) pero la regla no depende
     * de él: quien crea se vuelve el autor-médico de ESE anexo.
     *
     * @param  \App\Models\User               $user
     * @param  \App\Models\InjuryReport|null  $report
     * @return bool
     */
    public function create(User $user, InjuryReport $report = null): bool
    {
        return $user->isMedic();
    }

    /**
     * ¿Puede editar este anexo? Sólo el médico que lo creó (dueño por created_by_id).
     *
     * @param  \App\Models\User      $user
     * @param  \App\Models\Addendum  $addendum
     * @return bool
     */
    public function update(User $user, Addendum $addendum): bool
    {
        return $user->isMedic()
            && $addendum->created_by_id !== null
            && (int) $addendum->created_by_id === (int) $user->id;
    }

    /**
     * ¿Puede eliminar este anexo? Misma regla de propiedad que editar (los anexos
     * son append-only por diseño; esta habilidad queda lista por si se habilita).
     *
     * @param  \App\Models\User      $user
     * @param  \App\Models\Addendum  $addendum
     * @return bool
     */
    public function delete(User $user, Addendum $addendum): bool
    {
        return $this->update($user, $addendum);
    }
}
