<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\BadgePrint;

/**
 * CrewStatusController — CORTE #5 + #6 del God Object AdminController (strangler, 2026-06-28).
 *
 * Acciones de MUTACIÓN puntual sobre UN miembro de crew (un solo {id}): toggles de estatus
 * (activo, gafete impreso → tabla badge_prints, reset de cuestionario `encuestadiaria`), rol legacy `admin`,
 * grupo legacy `daytest`, y asignación de puesto/departamento. Todas comparten la misma forma:
 * `findOrFail($id)` → guarda de scope → muta un campo → guarda.
 *
 * ENDURECIMIENTO al mover (cierra H2 — IDOR de scope en acciones sobre {id}):
 * cada acción ahora exige `auth()->user()->canManageCrewMember($target)` (mismo criterio que
 * los listados vía applyDepartmentScope). Para los roles actuales con `users.assign-role`/
 * `assign-department` la guarda es un no-op (todos tienen `crew.view.all-departments`), pero
 * blinda contra {id} manipulados a mano y contra futuros roles acotados. La autorización por
 * permiso (`users.update` / `users.deactivate` / `users.assign-role` / `users.assign-department`)
 * sigue viviendo en routes/web.php; esto es la capa de scope por departamento encima.
 *
 * Las vistas solo enlazan a usuarios visibles (listas ya acotadas), así que un operador
 * legítimo nunca topa con el 403; solo lo recibe una petición fuera de scope.
 */
class CrewStatusController extends Controller
{
    /**
     * Helper DEDUP (2026-07-06): forma común de los ~11 toggles → findOrFail + guarda de scope
     * (canManageCrewMember, 403) + set de UNA columna + update(). Cada método público delega aquí
     * conservando su nombre, ruta, columna y valor exactos (las vistas postean a esos contratos).
     */
    private function setFlag($id, $column, $value)
    {
        $producto = User::findOrFail($id);
        abort_unless(auth()->user()->canManageCrewMember($producto), 403);
        $producto->{$column} = $value;
        $producto->update();
    }

    // ---- #5: estatus / gafete / cuestionario ------------------------------------------

    /**
     * Marca el gafete como IMPRESO → crea/actualiza la fila en badge_prints (auditable:
     * cuándo + quién). Reemplaza el viejo setFlag('age', 1). Mismo guard de scope.
     */
    public function checkgft($id)
    {
        $user = User::findOrFail($id);
        abort_unless(auth()->user()->canManageCrewMember($user), 403);
        BadgePrint::updateOrCreate(
            ['user_id' => $user->id],
            ['printed_at' => now(), 'printed_by_id' => auth()->id()]
        );

        return back();
    }

    /** Desmarca el gafete impreso → borra la fila en badge_prints. */
    public function uncheckgft($id)
    {
        $user = User::findOrFail($id);
        abort_unless(auth()->user()->canManageCrewMember($user), 403);
        BadgePrint::where('user_id', $user->id)->delete();

        return back();
    }

    public function activarusuario($id)
    {
        $this->setFlag($id, 'activo', 1);
        return redirect('/usuarioscrud');
    }

    public function activarencuesta($id)
    {
        $this->setFlag($id, 'encuestadiaria', 0);
        return redirect('/usuarioscrud');
    }

    public function desactivarusuario($id)
    {
        $this->setFlag($id, 'activo', 0);
        return redirect('/usuarioscrud');
    }

    // ---- #6: rol legacy `admin` -------------------------------------------------------
    // `admin` SIGUE VIVO: gatea /importcrew (AdminMiddleware), User::canSeePanel() y el rótulo
    // del sidebar. Su toggle salió del menú de acciones (2026-08-07) pero estas rutas/métodos se
    // conservan intactos para no romper el contrato de la columna.

    public function activaradmin($id)
    {
        $this->setFlag($id, 'admin', 1);
        return redirect('/usuarioscrud');
    }

    public function desactivaradmin($id)
    {
        $this->setFlag($id, 'admin', 0);
        return redirect('/usuarioscrud');
    }

    // (2026-08-07) ELIMINADOS putga()/putgg() — escribían el grupo legacy `daytest` (0/1), una
    // bandera MUERTA que ningún código leía para decidir nada. Se borraron junto con sus rutas
    // (POST /putadm, /putsup) y el parcial componentes/_group-toggles ("Supervisor"/"Admin").
    // La columna `daytest` se conserva como dato (pendiente de DROP en owner-apply).
    // putgb() (daytest=2, "Convertir a Médico") ya se había retirado el 2026-07-19; la identidad
    // médica es hoy el rol Spatie `medic` (User::isMedic()), asignado desde /rolescrud.
    // setFlag() NO se toca: lo comparten activarusuario/activarencuesta/desactivarusuario/
    // activaradmin/desactivaradmin y es donde vive la guarda canManageCrewMember.
}
