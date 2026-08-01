<?php

namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\departamento;
use App\Models\puesto;
use App\Models\usuariosnotificacione;
// Imports retirados (2026-06-28) al vaciar el God Object: User/Carbon/Hash/Mail/userpuesto/cmedic
// migraron con sus métodos a CrewController/CrewStatusController/CrewMailController/cmedicController.


class AdminController extends Controller
{
    // Candado global RETIRADO (RBAC fino). Antes: $this->middleware('admin') forzaba
    // `admin == 1` en TODOS los métodos, lo que impedía migrar rutas a permisos por
    // vertical (un coordinador con users.view seguía bloqueado). Ahora el control de
    // acceso vive en las rutas (routes/web.php): los verticales migrados exigen su
    // `permission:` y los aún-no-migrados (catálogos/COVID) siguen bajo el grupo `admin`.
    // Cada método de este controller queda cubierto por uno u otro.
    public function __construct(){
        //
    }
    // --- usuarioscrud / comcrud / medicocrud / idcardscrud EXTRAÍDOS (2026-06-27) a
    //     App\Http\Controllers\CrewListController (strangler — primer corte del God Object).
    //     Listados read-only; movimiento puro, rutas repuntadas en routes/web.php.
    // --- TOGGLES de un solo {id} EXTRAÍDOS (2026-06-28) a App\Http\Controllers\CrewStatusController
    //     (strangler cortes #5 y #6): checkgft/uncheckgft/activarusuario/activarencuesta/
    //     desactivarusuario (estatus·gafete·cuestionario) + activaradmin/desactivaradmin/putga/
    //     putgb/putgg (rol legacy·grupos daytest). Endurecidos al mover: guarda canManageCrewMember
    //     (H2) + activarusuario/activarencuesta convertidos GET→POST. Rutas repuntadas en web.php.
    // --- checkpoint (control de temperatura COVID) ELIMINADO — Lote 4 COVID-DECOMMISSION (2026-06-25), respaldo en _legacy_backup/ ---
    // --- notsick (reseteaba users.enfermo, COVID) ELIMINADO — desacople COVID (2026-06-25) ---


     public function creardepartamento(Request $request)
    {
        $datos = $request->all();
        departamento::create($datos);
        return redirect('/departamentocrud');
    }
    public function departamentocrud()
    {
        $departamentos = DB::table('departamentos')->paginate(7);
        return view ('admin/departamentocrud', compact('departamentos'));
    }
    public function activardepartamento($id)
    {
        $departamentos = departamento::find($id);
        $departamentos->activo=1;
        $departamentos->update();
        return redirect('/departamentocrud');
    }
    public function desactivardepartamento($id)
    {
        $departamentos = departamento::find($id);
        $departamentos->activo=0;
        $departamentos->update();
        return redirect('/departamentocrud');
    }
    public function editardepartamento($id)
    {
        $item = departamento::find($id);
        /* dd($productos, $categorias, $subcategorias); */
        return view("admin/editardepartamento")->with('item', $item);
    }
    public function savedepartamento(Request $request, $id)
    {
        $datos = request()->except('_token');
        departamento::where('id_departamentos', "=" , $id)->update($datos);
        return redirect('/departamentocrud');
        //return redirect()->action('AdminController@editardepartamento',$id);
    }
     //notificaciones
    public function crearnotificacion(Request $request)
    {
        $datos = $request->all();
        usuariosnotificacione::create($datos);
        return redirect('/notificacioncrud');
    }
    public function notificacioncrud()
    {
        $notificaciones = DB::table('usuariosnotificaciones')->paginate(7);
        return view ('admin/notificacioncrud', compact('notificaciones'));
    }
    public function activarnotificacion($id)
    {
        $notificaciones = usuariosnotificacione::find($id);
        $notificaciones->activo=1;
        $notificaciones->update();
        return redirect('/notificacioncrud');
    }
    public function desactivarnotificacion($id)
    {
        $notificaciones = usuariosnotificacione::find($id);
        $notificaciones->activo=0;
        $notificaciones->update();
        return redirect('/notificacioncrud');
    }
    public function editarnotificacion($id)
    {
        $item = usuariosnotificacione::find($id);
        return view("admin/editarnotificacion",compact('item'));
    }
    public function savenotificacion(Request $request, $id){
        $datos = request()->except('_token');
        usuariosnotificacione::where('id_usernotificacion', "=" , $id)->update($datos);
        return redirect('/notificacioncrud');
    }

    // --- useredit (form) / acountupdate (persistencia) EXTRAÍDOS (2026-06-28) a
    //     App\Http\Controllers\CrewController (strangler — paso #4, perfil de crew).
    //     Endurecidos al moverse: guarda de scope por depto (H2) + whitelist de
    //     mass-assignment (H4). Rutas repuntadas en routes/web.php (permission:users.update).

    // --- idcard($id) EXTRAÍDO (2026-06-27) a App\Http\Controllers\CrewListController (strangler). ---

    public function positionscrud()
    {
        $puestos = DB::table('puestos')->paginate(7);
        $departamentos  = departamento::all();

        return view ('admin/positionscrud', compact('puestos','departamentos'));
    }
    public function crearpositions(Request $request)
    {
        $datos = $request->all();
        puesto::create(['name' => $request->name,'id_departamento' => $request->id_departamento]);
        return redirect('/positionscrud');
    }
    public function editpositions($id)
    {
        $item = puesto::find($id);
        $departamentos  = departamento::all();
        /* dd($productos, $categorias, $subcategorias); */
        return view("admin/editarposition",compact('item','departamentos'));
    }
    public function saveposition(Request $request, $id)
    {
        $datos = request()->except('_token');
        puesto::where('id_puestos', "=" , $id)->update($datos);
        return redirect('/positionscrud');
    }
    // --- selectpuesto EXTRAÍDO (2026-06-28) a App\Http\Controllers\CrewStatusController
    //     (strangler corte #6): asignación de puesto/departamento. Endurecido con guarda
    //     canManageCrewMember (H2). Ruta repuntada en web.php (permission:users.assign-department).
    // --- adduser (form) / newuser (persistencia) EXTRAÍDOS (2026-06-28) a
    //     App\Http\Controllers\CrewController (strangler — segundo corte del God Object).
    //     Alta de crew; movimiento puro, rutas repuntadas en routes/web.php (permission:users.create).
    // --- historial (PCR history, COVID) ELIMINADO — Lote 3 COVID-DECOMMISSION (2026-06-25), respaldo en _legacy_backup/ ---

    // --- historialWR EXTRAÍDO (2026-06-28) a App\Http\Controllers\cmedicController (strangler
    //     corte #7): historial médico/WR, su dominio natural (cmedicController ya sirve
    //     componentes.historiamr). Movimiento puro; ruta repuntada en web.php (sigue gate `admin`).

    // NOTA: `searchusers()` se RETIRÓ — la búsqueda de usuarios la sirve ahora
    // SearchController@users (motor por preset, seguro). Verificado en vivo.

    // NOTA: `searcheckpoint()` se RETIRÓ (COVID — control de temperatura). Borrados
    // método + ruta + vista componentes/searcheckpoint. Página anfitriona COVID
    // pendiente (admin/checkpoint) → COVID-DECOMMISSION.

    // NOTA: `searchidcard()` se RETIRÓ — la búsqueda de gafetes la sirve ahora
    // SearchController@idcards (motor por preset, seguro). Verificado en vivo.

    // NOTA: `searchlab()` se RETIRÓ (COVID — laboratorio PCR, con bug de precedencia OR).
    // Borrados método + ruta + vista componentes/searchlab. Página anfitriona COVID
    // pendiente (pcrtest/listcrew) → COVID-DECOMMISSION.

    // --- temperature (ultimatemperatura COVID) ELIMINADO — Lote 4 COVID-DECOMMISSION (2026-06-25), respaldo en _legacy_backup/ ---

    // --- testpcrcrud / mainlab / positivepcrO / notodays / notoday / positivepcr (COVID) ELIMINADO — Lote 3 COVID-DECOMMISSION (2026-06-25), respaldo en _legacy_backup/ ---

    // --- welcomeresend / sendreminder EXTRAÍDOS (2026-06-28) y CONVERTIDOS a comandos Artisan
    //     (`crew:welcome-resend {id}` / `crew:daily-reminder`, en app/Console/Commands/). Eran GET
    //     con efecto colateral y sin UI; las rutas web se retiraron. (Pasaron breve por
    //     CrewMailController, hoy en _legacy_backup/.)

    // --- negativepcr / negativeantg (COVID) ELIMINADO — Lote 3 COVID-DECOMMISSION (2026-06-25), respaldo en _legacy_backup/ ---


    // --- PCR CRUD (COVID) ELIMINADO — Lote 2 COVID-DECOMMISSION (2026-06-25) ---
    // Métodos pcrcrud/crearpcr/editarpcr/savepcr/eliminarpcr + modelo `pcr` + vistas
    // admin/pcrcrud·pcredit + 5 rutas retirados. Respaldo en _legacy_backup/.

    // --- exportCsv() / expCsv() (CSV) ELIMINADOS (2026-06-28) — CÓDIGO MUERTO sin ruta.
    //     Verificado (route:list + grep): NINGUNA ruta apuntaba a AdminController@exportCsv ni
    //     @expCsv. El export CSV vivo es resetController@expCsv → /nophoto (name `expCsv`,
    //     permission:reports.export), usado por usuarioscrud/medicocrud/comcrud. Respaldo del
    //     AdminController completo previo en _legacy_backup/.
}
