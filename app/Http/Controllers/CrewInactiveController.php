<?php

namespace App\Http\Controllers;

use App\Models\UnitMember;
use App\Models\User;
use App\Support\CrewRosterBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CrewInactiveController — la LISTA DE DADOS DE BAJA (2026-09-08) y la REINTEGRACIÓN.
 *
 * POR QUÉ EXISTE: al dar de baja a alguien (individual o al apagar una unidad, que apaga a varios de golpe)
 * desaparecía de TODOS los listados (todos filtran activo=1) y sólo se reencontraba en la búsqueda global,
 * que exige saber el nombre. Esta lista lo pone a la vista, POR DEPARTAMENTO, con el MISMO alcance que el
 * crew list (HOD ve su depto, producción ve todo — `User::applyDepartmentScope`, vía CrewRosterBuilder).
 *
 * 🔑 REINTEGRAR es un ACTO PROPIO, no un efecto colateral del alta: reactiva a la persona y la saca del
 * ciclo de la unidad, y EVIDENCIA que falta emitir contrato y condiciones nuevas — NO emite el contrato
 * (la ceremonia de firma va aparte). La validación del alta (CrewController::newuser) NO se toca.
 */
class CrewInactiveController extends Controller
{
    /** Lista de crew inactivo por departamento + el motivo de la baja (unidad vs individual). */
    public function index()
    {
        $viewer = auth()->user();

        // Mismo builder que el crew list, pero activo=0 y sin acotar por unidad (un apagado exclusivo de
        // una unidad debe verse igual). Mantiene agrupado por depto, orden jerárquico y scope del visor.
        $roster = CrewRosterBuilder::build($viewer, false, true);

        // MOTIVO por persona: si tiene una membresía marcada `deactivated_with_unit`, cayó CON esa unidad;
        // si no, fue baja individual. El marcador ya existe (unit_members). Degrade-safe si no está la tabla.
        $reasons = collect();
        if (Schema::hasTable('unit_members') && Schema::hasColumn('unit_members', 'deactivated_with_unit')) {
            $reasons = DB::table('unit_members')
                ->join('units', 'units.id', '=', 'unit_members.unit_id')
                ->where('unit_members.deactivated_with_unit', 1)
                ->pluck('units.name', 'unit_members.user_id');   // [user_id => nombre de unidad]
        }

        return view('admin.crew-inactive', ['roster' => $roster, 'reasons' => $reasons]);
    }

    /**
     * REINTEGRAR: reactiva a la persona (activo=1, mismo mecanismo que el crew) y limpia el marcador de
     * "apagado con la unidad" (ya se le atiende individualmente, no vuelve con la unidad). NO emite contrato:
     * deja la necesidad VISIBLE (flash + la persona reaparece como "sin contrato vigente" donde ya se pinta
     * ese estado). Guarda de scope por departamento (un HOD sólo reintegra a los suyos), igual que el resto.
     */
    public function reintegrate(Request $request, $id)
    {
        $user = User::findOrFail($id);
        abort_unless(auth()->user()->canManageCrewMember($user), 403);

        DB::transaction(function () use ($user) {
            $user->activo = 1;
            $user->save();
            if (Schema::hasTable('unit_members') && Schema::hasColumn('unit_members', 'deactivated_with_unit')) {
                UnitMember::where('user_id', $user->id)->where('deactivated_with_unit', 1)
                    ->update(['deactivated_with_unit' => 0]);
            }
        });

        return redirect()->route('crew.inactive')
            ->with('reintegrated', User::displayName($user));
    }
}
