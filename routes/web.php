<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return redirect('/home');
});

// Cambia el idioma (ES/EN). Valida contra la lista blanca config('app.locales') para no
// guardar un locale basura en la sesión, y vuelve a la página anterior SOLO si es del mismo
// host (evita open-redirect vía Referer). Fuera de 'auth' para que el login también lo use. (2026-07-07)
Route::get('/locale/{locale}', function ($locale) {
    if (in_array($locale, config('app.locales', []), true)) {
        session()->put('locale', $locale);
    }
    $back = url()->previous();
    if (parse_url($back, PHP_URL_HOST) !== request()->getHost()) {
        $back = url('/home');
    }
    return redirect($back);
})->name('locale.switch')->where('locale', '[a-z]{2}')
  // (2026-07-24) throttle: es pública y ESCRIBE en sesión, así que sin límite se puede martillar
  // para forzar la creación de sesiones. 30/min le sobra a alguien alternando ES/EN.
  ->middleware('throttle:30,1');

Route::get('/offline', function () {
    return view('/vendor/laravelpwa/offline');
});

// SEGURIDAD/LIMPIEZA (2026-06-26): `/newdayRep` y `/newWR` ELIMINADOS — eran DUPLICADOS GET-sin-auth
// del comando programado `encuestas:task`, que reseteaba `encuestadiaria=0` de TODOS los activos.
// Cualquiera con la URL podía dispararlo.
// (2026-07-24) El propio `encuestas:task` quedó RETIRADO: el expediente clínico se llena UNA VEZ,
// no cada mañana (ver app/Console/Kernel.php y _legacy_backup/covid-daily-2026-07-24/LEEME.md).
// La reapertura es MANUAL: POST /activarencuesta/{id}.
// `/PhotoReminder` ELIMINADO (2026-06-26) — recordatorio de FOTO DE GAFETE (age=0), GET-sin-auth y rústico.
// A petición del owner se REIMPLEMENTARÁ como comando programado `badge:reminder` (pendiente en PROGRESS.md).
// --- /newTD, /Nresult (resets de cola PCR, COVID) ELIMINADOS — Lote 3b (2026-06-25) ---

// SEGURIDAD (2026-07-06): sistema CERRADO — las cuentas las crea un admin vía /adduser.
// Se deshabilita el auto-registro público (rutas GET/POST /register) para no exponer alta libre.
Auth::routes(['register' => false]);
Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
// SEGURIDAD (2026-06-26): `/dailyreport` estaba SIN `auth`. `viewencuesta()` usa
// `auth()->user()->encuestadiaria` y `auth()->user()->id` → sin sesión era null-deref (500)
// además de quedar abierto. Ahora exige `auth` (es el cuestionario clínico del crew logueado).
// (2026-07-24) `/dailyreport/{id}` ELIMINADA: apuntaba al MISMO método, que IGNORA el {id} y
// siempre sirve el expediente de auth()->user(). No era una fuga, era una promesa falsa —
// una URL que parece dar acceso al expediente de otro. El expediente ajeno se ve por
// /historialWR/{id}, que sí valida alcance (canManageCrewMember).
// (2026-07-24 · PIEZA 3) `privacidad` = PASO 0. El cuestionario RECABA datos personales
// SENSIBLES (tabaquismo, alcoholismo, toxicomanías, antecedentes de cáncer), que en México
// exigen consentimiento EXPRESO del titular ANTES de recabarlos. El middleware va SÓLO aquí y
// en el POST de abajo, no en un grupo global: no se aceptó el aviso ≠ no se puede trabajar.
Route::middleware(['auth', 'privacidad'])->group(function () {
    Route::get('/dailyreport',[App\Http\Controllers\encuestasController::class,'viewencuesta'])->name('encuesta');
});

// El aviso en sí NO puede llevar el middleware `privacidad` (sería un bucle: se te pide aceptar
// para poder ver lo que tienes que aceptar).
Route::middleware(['auth'])->group(function () {
    Route::get('/aviso-privacidad',[App\Http\Controllers\PrivacyConsentController::class,'show'])->name('privacidad.aviso');
    Route::post('/aviso-privacidad',[App\Http\Controllers\PrivacyConsentController::class,'store'])->name('privacidad.aceptar');
});

// --- /negative-mail (MailController@sendMail, COVID) ELIMINADO — Lote 3 COVID-DECOMMISSION (2026-06-25), respaldo en _legacy_backup/ ---
// LIMPIEZA (2026-07-24): `/reminder-mail` ELIMINADA con su controlador y su job
// (App\Jobs\ReminderEmail). Era la TERCERA copia del recordatorio COVID diario: encolaba a
// TODOS los activos el mismo `correos.recordatorio` que `encuestas:task` y `crew:daily-reminder`
// — una vista que nunca pudo enviarse (usa $email y sólo se le pasa `nombre`) y que traía una
// contraseña en claro. Ver _legacy_backup/covid-daily-2026-07-24/LEEME.md.


/*
|--------------------------------------------------------------------------
| RBAC fino (spatie `permission:`) — verticales migrados desde el flag `admin`
|--------------------------------------------------------------------------
| ESTRANGULAMIENTO (vertical por vertical): estas rutas ya NO viven bajo el
| middleware binario `admin` (auth()->user()->admin == 1). Cada una exige el
| PERMISO que le corresponde + `auth`. Así un coordinador/HOD/médico/crew
| (admin = 0) puede usar exactamente lo que su rol permite, y el super-admin
| (que tiene los 42 permisos) sigue pasando en todas.
|
| Regla de consistencia: el permiso de la ruta = el permiso con el que el
| sidebar (resources/views/layouts/sidebar.blade.php) muestra ese ítem. Por
| eso ningún ítem visible cae en 403. Sub-acciones más sensibles (dar de baja,
| asignar rol) piden un permiso mayor → 403 correcto para quien no lo tiene.
|
| Lo que se QUEDA en `admin` por ahora (siguiente incremento / decommission):
| catálogos (departamentos/puestos/notificaciones), todo lo COVID/PCR/lab/queue,
| import/export y correos. El super-admin (admin = 1) las sigue viendo.
*/

// ---- CREW / USERS ----
Route::middleware(['auth','permission:users.view'])->group(function () {
    // Listados de crew (SOLO LECTURA) → extraídos a CrewListController (strangler 2026-06-27).
    Route::get('/usuarioscrud',[App\Http\Controllers\CrewListController::class,'usuarioscrud'])->name('usuarioscrud');
    // RETIRADA (2026-07-19): `GET /comcrud` → CrewListController@comcrud. Pantalla huérfana
    // (sin enlace en el sidebar ni en ninguna vista) y redundante: `usuarioscrud` usa la misma
    // consulta, muestra más columnas y sus toggles de grupo SÍ funcionan (el parcial
    // `componentes/_group-toggles` usa route(), mientras comcrud posteaba a url('/putga/…'),
    // que es un NOMBRE de ruta y no una URI → 404). Copia en
    // _legacy_backup/cleanup-2026-07-19/ (ver NOTA.md).
    Route::get('/idcardscrud',[App\Http\Controllers\CrewListController::class,'idcardscrud'])->name('idcardscrud');
    Route::get('/idcard/{id}',[App\Http\Controllers\CrewListController::class,'idcard'])->name('idcard');

    // ===== Gafetes configurables (ID-Badge) =====
    // Descargas (individual PDF, bulk PDF, bulk JPG-ZIP) → mismo permiso que ver la lista de gafetes.
    Route::get('/idcard/{id}/pdf',[App\Http\Controllers\BadgeController::class,'pdf'])->name('badge.pdf');
    Route::get('/gafetes/pdf',[App\Http\Controllers\BadgeController::class,'bulkPdf'])->name('badge.bulk.pdf');
    Route::get('/gafetes/jpg',[App\Http\Controllers\BadgeController::class,'bulkJpg'])->name('badge.bulk.jpg');
    // Diseñador de plantilla → super-admin, line-producer y coordinador (permiso badge.design, revocable).
    Route::get('/gafetes/plantilla',[App\Http\Controllers\BadgeController::class,'designer'])->middleware('permission:badge.design')->name('badge.designer');
    Route::post('/gafetes/plantilla',[App\Http\Controllers\BadgeController::class,'saveTemplate'])->middleware('permission:badge.design')->name('badge.save');
    Route::get('/gafetes/plantilla-pdf',[App\Http\Controllers\BadgeController::class,'downloadTemplate'])->middleware('permission:badge.design')->name('badge.template');
});
Route::middleware(['auth','permission:users.create'])->group(function () {
    Route::get('/adduser',[App\Http\Controllers\CrewController::class,'adduser'])->name('adduser');
    Route::post('/newuser',[App\Http\Controllers\CrewController::class,'newuser'])->name('newuser');
});
Route::middleware(['auth','permission:users.update'])->group(function () {
    Route::get('/useredit/{id}',[App\Http\Controllers\CrewController::class,'useredit'])->name('useredit');
    Route::post('/acountupdate/{id}',[App\Http\Controllers\CrewController::class,'acountupdate'])->name('account.update');
    // Toggles de estatus/gafete/cuestionario → CrewStatusController (corte #5, 2026-06-28).
    // activarusuario/activarencuesta CONVERTIDOS GET→POST+CSRF (antes mutaban por GET sin token).
    Route::post('/checkgft/{id}',[App\Http\Controllers\CrewStatusController::class,'checkgft'])->name('checkgft');
    Route::post('/uncheckgft/{id}',[App\Http\Controllers\CrewStatusController::class,'uncheckgft'])->name('uncheckgft');
    Route::post('/activarusuario/{id}',[App\Http\Controllers\CrewStatusController::class,'activarusuario'])->name('activarusuario');
    Route::post('/activarencuesta/{id}',[App\Http\Controllers\CrewStatusController::class,'activarencuesta'])->name('activarencuesta');

    // ---- Cédula profesional del médico (PASO B, 2026-07-19) ----
    // Viven en el grupo `users.update` porque su UI es la ficha de edición de crew
    // (admin/useredit) y solo tiene sentido para quien ya puede editar a esa persona.
    // La AUTORIZACIÓN FINA de cada acción NO está aquí, y es deliberado — las dos son
    // asimétricas y dependen del usuario destino, cosa que un middleware de ruta no puede
    // expresar: CAPTURAR lo puede el propio médico sobre su ficha o quien tenga
    // `medic.credential.manage` con alcance sobre él; VALIDAR exige `medic.credential.manage`
    // Y NO SER EL TITULAR. Ambas se comprueban en el controlador (canCapture / verify).
    Route::post('/medic-credential/{id}',[App\Http\Controllers\MedicCredentialController::class,'save'])->name('medic-credential.save')->whereNumber('id');
    Route::post('/medic-credential/{id}/verify',[App\Http\Controllers\MedicCredentialController::class,'verify'])->name('medic-credential.verify')->whereNumber('id');
});
Route::middleware(['auth','permission:users.deactivate'])->group(function () {
    Route::post('/desactivarusuario/{id}',[App\Http\Controllers\CrewStatusController::class,'desactivarusuario'])->name('desactivarusuario');
});
Route::middleware(['auth','permission:users.assign-role'])->group(function () {
    // Rol legacy `admin` → CrewStatusController (corte #6, 2026-06-28). SIGUE VIVO: la columna
    // users.admin gatea /importcrew (AdminMiddleware), User::canSeePanel() y el rótulo del sidebar.
    // Su toggle salió del menú (2026-08-07) pero la ruta se conserva para no romper contrato.
    Route::post('/activaradmin/{id}',[App\Http\Controllers\CrewStatusController::class,'activaradmin'])->name('activaradmin');
    Route::post('/desactivaradmin/{id}',[App\Http\Controllers\CrewStatusController::class,'desactivaradmin'])->name('desactivaradmin');
    // (2026-08-07) ELIMINADAS `POST /putadm` (putga) y `POST /putsup` (putgg): escribían el grupo
    // legacy `daytest`, bandera MUERTA que ningún código leía para decidir nada. Se borraron sus
    // métodos en CrewStatusController y el parcial componentes/_group-toggles. La columna daytest
    // se conserva (dato), pendiente de un DROP en owner-apply. (`putgb`/daytest=2 ya se había ido.)
});
// LIMPIEZA (2026-07-06): ruta `POST /selectpuesto/{id}` ELIMINADA junto con el método muerto
// CrewStatusController@selectpuesto (único lector de los modelos legacy puesto/departamento/
// userpuesto; ningún formulario ni enlace la invocaba). Las tablas DB se conservan.
Route::middleware(['auth','permission:crew.view'])->group(function () {
    Route::get('/searchusers/{valor}/',[App\Http\Controllers\SearchController::class,'users'])->name('searchusers');
    Route::get('/searchidcard/{valor}/',[App\Http\Controllers\SearchController::class,'idcards'])->name('searchidcard');
});

// ---- RBAC: asignación de rol + departamento (pantalla #3) ----
Route::middleware(['auth','permission:users.assign-role'])->group(function () {
    Route::get('/rolescrud',[App\Http\Controllers\RoleAssignmentController::class,'index'])->name('roles.index');
    Route::post('/rolescrud/{id}',[App\Http\Controllers\RoleAssignmentController::class,'update'])->name('roles.update');
    // (2026-07-24 · PASO 1/3 médico, item 7) OTORGAR/REVOCAR acceso clínico DIRECTO a una persona.
    // El grupo exige users.assign-role, pero estas dos acciones exigen además SUPER-ADMIN dentro del
    // controlador (abort_unless): otorgar acceso al expediente clínico es más sensible que asignar rol.
    Route::post('/rolescrud/{id}/medical-grant',[App\Http\Controllers\RoleAssignmentController::class,'grantMedical'])->name('roles.medical.grant')->whereNumber('id');
    Route::post('/rolescrud/{id}/medical-revoke',[App\Http\Controllers\RoleAssignmentController::class,'revokeMedical'])->name('roles.medical.revoke')->whereNumber('id');
});

// ---- RBAC: editor EN VIVO de la matriz rol->permiso (pantalla "Permisos por rol") ----
// Gate propio `roles.manage-permissions` (por defecto solo super-admin) — mas sensible que
// asignar roles a personas, asi que NO comparte el gate de users.assign-role.
Route::middleware(['auth','permission:roles.manage-permissions'])->group(function () {
    Route::get('/permisoscrud',[App\Http\Controllers\RolePermissionController::class,'edit'])->name('roles.permissions.edit');
    Route::post('/permisoscrud',[App\Http\Controllers\RolePermissionController::class,'update'])->name('roles.permissions.update');
});

// ---- MEDICAL (Consultas Médicas) ----
Route::middleware(['auth','permission:medical.view'])->group(function () {
    Route::get('/medicocrud',[App\Http\Controllers\CrewListController::class,'medicocrud'])->name('medicocrud'); // extraído a CrewListController (strangler 2026-06-27)
    // historialWR MIGRADO (2026-06-28) del grupo legacy `admin` a `permission:medical.view` — es
    // historial médico, su permiso natural (igual que medicocrud). CAMBIA quién accede: antes el
    // flag binario admin=1; ahora el permiso medical.view. Los enlaces en vistas no-médicas se
    // envuelven en @can('medical.view') para no mostrar un link que daría 403.
    Route::get('/historialWR/{id}/',[App\Http\Controllers\cmedicController::class,'historialWR'])->name('historialwr');
    // (2026-08-09) La "vista de impresión" standalone chrome-v2 se RETIRÓ: el owner pidió que el
    // historial médico NO se imprimiera como los demás documentos, sino conservando el formato de
    // la propia pantalla (componentes/historiamr) ajustado para imprimir. La impresión ahora es
    // window.print() sobre esa vista (su @media print aísla el reporte). Ver [[health-record-module]].
    // (2026-07-25) DOCUMENTO SELLADO de UNA consulta (crew o lite) + PDF (window.print). El gate de
    // ruta es medical.view; el candado DOCTOR-ONLY (isClinician, igual que la consulta) lo pone el
    // controlador — un HOD/producción con medical.view NO abre expedientes clínicos individuales.
    Route::get('/consulta/{id}/documento',[App\Http\Controllers\cmedicController::class,'consultaDoc'])->whereNumber('id')->name('consulta.documento');
    // (2026-07-24 · PASO 3/3) Buscador propio de la lista médica (cards). NO reusa /searchusers:
    // aquel devuelve el parcial del DIRECTORIO, que pinta teléfono/email a quien tenga
    // crew.view.contact — y el médico lo tiene, así que buscar exponía contacto que la lista
    // médica no muestra. Preset propio = proyección explícita sin PII de contacto.
    Route::get('/searchmedico/{valor}/',[App\Http\Controllers\SearchController::class,'medical'])->name('searchmedico');
    // --- /historial (PCR history, COVID) ELIMINADO — Lote 3 COVID-DECOMMISSION (2026-06-25) ---
});

// (2026-08-11 · BUG-01, opción B) BITÁCORA CONSOLIDADA — grupo PROPIO gateado SÓLO por
// `medical.consolidate` (NO medical.view). La bitácora concentra las consultas de TODOS los
// médicos con su nota privada, así que la ve/emite únicamente el KEY MEDIC. El permiso es DIRECTO
// y el super-admin lo otorga desde /rolescrud a médico, safety-officer o producción (él mismo pasa
// por Gate::before). Va en grupo aparte para NO exigir además medical.view: un consolidador
// safety-officer/producción puede no tenerlo.
Route::middleware(['auth','permission:medical.consolidate'])->group(function () {
    Route::get('/medico/bitacora',[App\Http\Controllers\MedicalReportController::class,'weekly'])->name('medical.bitacora');
    Route::get('/medico/bitacora/pdf',[App\Http\Controllers\MedicalReportController::class,'weeklyPdf'])->name('medical.bitacora.pdf');
});
Route::middleware(['auth','permission:medical.create'])->group(function () {
    Route::get('/consulta/{id}',[App\Http\Controllers\cmedicController::class,'create'])->name('cmedica.create');
    Route::post('/cmedica/{id_user}', [App\Http\Controllers\cmedicController::class, 'store'])->name('cmedica.store');

    // (2026-07-24 · PIEZA 3) ANEXOS AL EXPEDIENTE CLÍNICO. El expediente es inmutable: ésta es
    // la única vía por la que su contenido puede actualizarse, y no reescribe nada.
    // ⚠ `medical.create` es la BASELINE, no la autorización: lo tienen super-admin y medic, y un
    // super-admin no médico no puede figurar como autor de una valoración clínica. El rol `medic`
    // (User::isMedic) y el alcance por departamento los exige el CONTROLADOR, en las dos rutas.
    Route::get('/expediente/{id}/anexo', [App\Http\Controllers\HealthRecordAddendumController::class, 'create'])->name('expediente.anexo.create');
    Route::post('/expediente/{id}/anexo', [App\Http\Controllers\HealthRecordAddendumController::class, 'store'])->name('expediente.anexo.store');
});

// ---- MEDICAL — Conteo de medicamentos (USO INTERNO: producción + salud y seguridad) ----
Route::middleware(['auth','permission:medical.materials'])->group(function () {
    Route::get('/medico/materiales',[App\Http\Controllers\MedicalReportController::class,'materials'])->name('medical.materials');
    Route::get('/medico/materiales/pdf',[App\Http\Controllers\MedicalReportController::class,'materialsPdf'])->name('medical.materials.pdf');
    // (2026-07-19) MATERIALIDAD (evidencia fiscal SAT): galería + subida + PDF; MISMO gate.
    Route::get('/medico/materialidad',[App\Http\Controllers\MedicalReportController::class,'materiality'])->name('medical.materiality');
    Route::post('/medico/materialidad',[App\Http\Controllers\MedicalReportController::class,'materialityStore'])->name('medical.materiality.store');
    Route::get('/medico/materialidad/pdf',[App\Http\Controllers\MedicalReportController::class,'materialityPdf'])->name('medical.materiality.pdf');
});

// ---- MEDICAL — REGISTRO LITE (pacientes NO-crew: extras/visitantes/proveedores) · 2026-07-24 ----
// Superficie CORE del rol `medic` real: atender extras/visitantes/proveedores sin cuenta. El registro
// vive en LitePatientController; la CONSULTA reusa cmedicController (sello v2, cintillo, cédula obligatoria).
Route::middleware(['auth','permission:medical.view'])->group(function () {
    Route::get('/pacientes-lite',[App\Http\Controllers\LitePatientController::class,'index'])->name('lite.index');
    // (2026-07-25) HISTORIAL COMPLETO del paciente sin cuenta (antes NO existía — sólo el cintillo al
    // atender). Une la persona y sus duplicados fundidos (identityGroupIds). DOCTOR-ONLY en el método.
    Route::get('/pacientes-lite/{id}/historial',[App\Http\Controllers\cmedicController::class,'historialLite'])->whereNumber('id')->name('lite.historial');
});
Route::middleware(['auth','permission:medical.create'])->group(function () {
    Route::post('/pacientes-lite',[App\Http\Controllers\LitePatientController::class,'store'])->name('lite.store');
    Route::post('/pacientes-lite/{id}/fusionar',[App\Http\Controllers\LitePatientController::class,'fusionar'])->whereNumber('id')->name('lite.merge');
    Route::get('/pacientes-lite/{id}/consulta',[App\Http\Controllers\cmedicController::class,'createLite'])->whereNumber('id')->name('lite.consulta.create');
    Route::post('/pacientes-lite/{id}/consulta',[App\Http\Controllers\cmedicController::class,'storeLite'])->whereNumber('id')->name('lite.consulta.store');
});

// ---- SCOUTING (módulo H&S — ÚNICO scouting tras decomisionar el legacy de 161 campos) ----
// 2026-06-28: el scouting viejo (locationController/locationController2 + vistas location*) fue
// DECOMISIONADO a pedido del owner y movido a _legacy_backup/decommission-2026-06-28/. El modelo
// `locationreport` se CONSERVA solo porque el dashboard (HomeController) lo cuenta; migrar esa
// métrica a scouting_reports = follow-up. Este módulo LEAN lo reemplaza: encabezado de emergencia
// + ~13 categorías de riesgo + catálogo normativo + gatillo SB132 + AUTOFIRMA del usuario logueado.
// Reutiliza permisos locations.*. OJO orden: `/scoutings/create` (fijo) va ANTES que
// `/scoutings/{id}`. Requiere tabla `scouting_reports` (el owner aplica el CREATE TABLE).
Route::middleware(['auth','permission:locations.create'])->group(function () {
    Route::get('/scoutings/create', [App\Http\Controllers\ScoutingReportController::class, 'create'])->name('scoutings.create');
    Route::post('/scoutings', [App\Http\Controllers\ScoutingReportController::class, 'store'])->name('scoutings.store');
    // Edición: mismo nivel de permiso que crear (no existe locations.edit y NO se
    // crean permisos nuevos). Se declara ANTES del show `/scoutings/{id}` de abajo
    // para que `{id}/edit` nunca sea capturado por el patrón del show.
    Route::get('/scoutings/{id}/edit', [App\Http\Controllers\ScoutingReportController::class, 'edit'])->name('scoutings.edit')->whereNumber('id');
    Route::put('/scoutings/{id}', [App\Http\Controllers\ScoutingReportController::class, 'update'])->name('scoutings.update')->whereNumber('id');

    // (2026-08-01) El mapeo de riesgos se movió a su PROPIO módulo (ya no cuelga del scouting,
    // confundía): ver el grupo `riskmaps.*` justo abajo.
});

// ============================================================================
// MÓDULO Mapeo de riesgos y recursos (2026-08-03 · delta #50) — EDITOR APARTE.
// No cuelga del scouting: es su propio editor que produce un DOCUMENTO SELLADO
// (una página por vista) y lo referencia en SOLO LECTURA. Todo el módulo va con
// permission:riskmap.issue (safety). Los marcadores/vistas se guardan por AJAX.
// Verificador público: tipo 'rmap' (ver SealVerifier::TYPES).
// ============================================================================
Route::middleware(['auth', 'permission:riskmap.issue'])->group(function () {
    Route::get('/mapeo-riesgos', [App\Http\Controllers\RiskMapController::class, 'index'])->name('riskmaps.index');
    Route::post('/mapeo-riesgos', [App\Http\Controllers\RiskMapController::class, 'store'])->name('riskmaps.store');

    Route::get('/mapeo-riesgos/{id}/editar', [App\Http\Controllers\RiskMapController::class, 'edit'])->name('riskmaps.edit')->whereNumber('id');
    Route::put('/mapeo-riesgos/{id}', [App\Http\Controllers\RiskMapController::class, 'updateMeta'])->name('riskmaps.update')->whereNumber('id');
    Route::delete('/mapeo-riesgos/{id}', [App\Http\Controllers\RiskMapController::class, 'destroy'])->name('riskmaps.destroy')->whereNumber('id');

    Route::get('/mapeo-riesgos/{id}/documento', [App\Http\Controllers\RiskMapController::class, 'document'])->name('riskmaps.document')->whereNumber('id');
    Route::post('/mapeo-riesgos/{id}/sellar', [App\Http\Controllers\RiskMapController::class, 'seal'])->name('riskmaps.seal')->whereNumber('id');

    // Vistas (páginas)
    Route::post('/mapeo-riesgos/{id}/vistas', [App\Http\Controllers\RiskMapController::class, 'storeView'])->name('riskmaps.views.store')->whereNumber('id');
    Route::put('/mapeo-riesgos/{id}/vistas/{view}', [App\Http\Controllers\RiskMapController::class, 'updateView'])->name('riskmaps.views.update')->whereNumber('id')->whereNumber('view');
    Route::delete('/mapeo-riesgos/{id}/vistas/{view}', [App\Http\Controllers\RiskMapController::class, 'destroyView'])->name('riskmaps.views.destroy')->whereNumber('id')->whereNumber('view');
    Route::post('/mapeo-riesgos/{id}/vistas-orden', [App\Http\Controllers\RiskMapController::class, 'reorderViews'])->name('riskmaps.views.reorder')->whereNumber('id');

    // Marcadores (AJAX)
    Route::post('/mapeo-riesgos/{id}/vistas/{view}/marcadores', [App\Http\Controllers\RiskMapController::class, 'storeMarker'])->name('riskmaps.markers.store')->whereNumber('id')->whereNumber('view');
    Route::put('/mapeo-riesgos/{id}/vistas/{view}/marcadores/{marker}', [App\Http\Controllers\RiskMapController::class, 'updateMarker'])->name('riskmaps.markers.update')->whereNumber('id')->whereNumber('view')->whereNumber('marker');
    Route::delete('/mapeo-riesgos/{id}/vistas/{view}/marcadores/{marker}', [App\Http\Controllers\RiskMapController::class, 'destroyMarker'])->name('riskmaps.markers.destroy')->whereNumber('id')->whereNumber('view')->whereNumber('marker');
});
Route::middleware(['auth','permission:locations.view'])->group(function () {
    Route::get('/scoutings', [App\Http\Controllers\ScoutingReportController::class, 'index'])->name('scoutings.index');
    // Documento en formato oficial Amazon MGM Studios (declarado ANTES del show `{id}`
    // para que `{id}/amazon` no lo capture el patrón del show).
    Route::get('/scoutings/{id}/amazon', [App\Http\Controllers\ScoutingReportController::class, 'amazon'])->name('scoutings.amazon')->whereNumber('id');
    Route::get('/scoutings/{id}', [App\Http\Controllers\ScoutingReportController::class, 'show'])->name('scoutings.show')->whereNumber('id');
});
// API interna geo: sugiere el NOMBRE de la locación (scouting) más cercana a unas
// coordenadas. Solo pide sesión (sin permission:locations.*): la usan los formularios
// de seguridad y quien reporta una condición insegura no siempre tiene esos permisos.
Route::middleware(['auth'])->get('/geo/scoutings-nearby', [App\Http\Controllers\ScoutingReportController::class, 'nearby'])->name('geo.scoutings.nearby');

// ---- HAZARDS (Actos inseguros + Condiciones inseguras) ----
Route::middleware(['auth','permission:hazards.create'])->group(function () {
    Route::get('/hazardnotification', [App\Http\Controllers\HazardNotificationController::class, 'create'])->name('hazard_notifications.create');
    Route::post('/hazard-notifications', [App\Http\Controllers\HazardNotificationController::class, 'store'])->name('hazard_notifications.store');
    Route::get('/unsafenotifications/create', [App\Http\Controllers\unsafecondNotificationController::class, 'create'])->name('unsafenotifications.create');
    Route::post('/unsafenotifications/store', [App\Http\Controllers\unsafecondNotificationController::class, 'store'])->name('unsafenotifications.store');
});
Route::middleware(['auth','permission:hazards.view'])->group(function () {
    Route::get('/unsafeacts', [App\Http\Controllers\HazardNotificationController::class, 'index'])->name('hazard_notifications.index');
    Route::get('/unsafeact/{id}', [App\Http\Controllers\HazardNotificationController::class, 'show'])->name('hazard_notifications.show');
    Route::get('/unsafeconds', [App\Http\Controllers\unsafecondNotificationController::class, 'index'])->name('unsafenotifications.index');
    Route::get('/unsafecond/{id}', [App\Http\Controllers\unsafecondNotificationController::class, 'show'])->name('unsafenotifications.show');
});

// Cierre del lazo de acción correctiva: actualizar action_status (Abierto/En proceso/Cerrado).
// Mutación → permiso hazards.manage (line-producer/safety-officer/super-admin).
Route::post('/unsafeact/{id}/status', [App\Http\Controllers\HazardNotificationController::class, 'updateStatus'])->middleware(['auth','permission:hazards.manage'])->name('hazard_notifications.status')->whereNumber('id');
Route::post('/unsafecond/{id}/status', [App\Http\Controllers\unsafecondNotificationController::class, 'updateStatus'])->middleware(['auth','permission:hazards.manage'])->name('unsafenotifications.status')->whereNumber('id');

// (2026-07-09) Motor PDCA: cerrar/reabrir acciones correctivas (action_items). Mismo permiso
// que el cierre de estado. Cerrar todas es requisito para poder Cerrar/Finalizar el reporte padre.
Route::post('/action-items/{id}/close', [App\Http\Controllers\ActionItemController::class, 'close'])->middleware(['auth','permission:hazards.manage'])->name('action_items.close')->whereNumber('id');
Route::post('/action-items/{id}/reopen', [App\Http\Controllers\ActionItemController::class, 'reopen'])->middleware(['auth','permission:hazards.manage'])->name('action_items.reopen')->whereNumber('id');

// ---- DAILY SAFETY REPORT (DSR) ----
// OJO orden: `/dsr-reports/create` (fijo) va ANTES que `/dsr-reports/{id}`.
Route::middleware(['auth','permission:dsr.create'])->group(function () {
    Route::get('/dsr-reports/create', [App\Http\Controllers\DailyReportController::class, 'create'])->name('daily_reports.create');
    Route::post('/dsr-reports', [App\Http\Controllers\DailyReportController::class, 'store'])->name('daily_reports.store');
    Route::post('/dsr-reports/{id}/log', [App\Http\Controllers\DailyReportController::class, 'storeLog'])->name('daily_logs.store');
});
Route::middleware(['auth','permission:dsr.update'])->group(function () {
    Route::post('/dsr-reports/{id}/update', [App\Http\Controllers\DailyReportController::class, 'update'])->name('daily_reports.update');
});
Route::middleware(['auth','permission:dsr.view'])->group(function () {
    Route::get('/dsr-reports', [App\Http\Controllers\DailyReportController::class, 'index'])->name('daily_reports.index');
    Route::get('/dsr-reports/{id}', [App\Http\Controllers\DailyReportController::class, 'show'])->name('daily_reports.show');
});

// ---- INSPECCIÓN DE HERRAMIENTA (2026-07-26 · delta #42) ----
// Vertical de enforcement: buscar → ejecutar checklist → veredicto sellado (acta).
// El acta entra al verificador PÚBLICO (SealVerifier 'insp'), cuya ruta va sin sesión
// más abajo. Gate único tools.inspect. Orden: los prefijos fijos (buscar/acta/herramienta)
// desambiguan; el acta se liga por uuid (no expone id secuencial).
Route::middleware(['auth','permission:tools.inspect'])->group(function () {
    Route::get('/inspeccion', [App\Http\Controllers\InspectionController::class, 'index'])->name('tools.index');
    Route::get('/inspeccion/buscar/{q?}', [App\Http\Controllers\InspectionController::class, 'search'])->name('tools.search');
    // Consulta del histórico de actas (delta #47) + admin de imágenes genéricas del tipo.
    // Prefijos fijos → van ANTES de las rutas herramienta/{tool} numéricas (mismo criterio del grupo).
    Route::get('/inspeccion/actas', [App\Http\Controllers\InspectionController::class, 'records'])->name('tools.records');
    Route::get('/inspeccion/imagenes', [App\Http\Controllers\InspectionController::class, 'toolImages'])->name('tools.images');
    Route::get('/inspeccion/acta/{inspection:uuid}', [App\Http\Controllers\InspectionController::class, 'acta'])->name('tools.inspection.show');
    Route::post('/inspeccion/acta/{inspection:uuid}/desbloquear', [App\Http\Controllers\InspectionController::class, 'unblock'])->name('tools.inspection.unblock');
    Route::post('/inspeccion/acta/{inspection:uuid}/retirar', [App\Http\Controllers\InspectionController::class, 'retire'])->name('tools.inspection.retire');
    Route::get('/inspeccion/herramienta/{tool}', [App\Http\Controllers\InspectionController::class, 'show'])->name('tools.show')->whereNumber('tool');
    Route::get('/inspeccion/herramienta/{tool}/inspeccionar', [App\Http\Controllers\InspectionController::class, 'create'])->name('tools.inspect.form')->whereNumber('tool');
    Route::post('/inspeccion/herramienta/{tool}/inspeccionar', [App\Http\Controllers\InspectionController::class, 'store'])->name('tools.inspect.store')->whereNumber('tool');
    Route::post('/inspeccion/herramienta/{tool}/imagen', [App\Http\Controllers\InspectionController::class, 'storeToolImage'])->name('tools.image.store')->whereNumber('tool');
});

// ---- EMISIÓN DE PERMISOS DE TRABAJO (2026-07-30 · delta #44) ----
// Ciclo completo: emitir (compuerta + doble firma + autorización externa declarada) →
// reverificar en sitio / suspender → CERRAR. El permiso emitido entra al verificador PÚBLICO
// (SealVerifier 'perm'), cuya ruta va sin sesión más abajo. Gate único permits.issue.
// Orden: el prefijo fijo /permisos/emitir desambigua del /permisos/{uuid} (el emitido se liga
// por uuid, no expone id secuencial). EMITIR ≠ INSPECCIONAR → permiso distinto.
Route::middleware(['auth','permission:permits.issue'])->group(function () {
    Route::get('/permisos', [App\Http\Controllers\PermitController::class, 'index'])->name('permits.index');
    Route::get('/permisos/emitir/{permit}', [App\Http\Controllers\PermitController::class, 'create'])->name('permits.create')->whereNumber('permit');
    Route::post('/permisos/emitir/{permit}', [App\Http\Controllers\PermitController::class, 'store'])->name('permits.store')->whereNumber('permit');
    Route::get('/permisos/{issued:uuid}', [App\Http\Controllers\PermitController::class, 'show'])->name('permits.show')->where('issued', '[0-9a-fA-F-]{36}');
    Route::post('/permisos/{issued:uuid}/reverificar', [App\Http\Controllers\PermitController::class, 'reverify'])->name('permits.reverify')->where('issued', '[0-9a-fA-F-]{36}');
    Route::post('/permisos/{issued:uuid}/cerrar', [App\Http\Controllers\PermitController::class, 'close'])->name('permits.close')->where('issued', '[0-9a-fA-F-]{36}');
    Route::post('/permisos/{issued:uuid}/suspender', [App\Http\Controllers\PermitController::class, 'suspend'])->name('permits.suspend')->where('issued', '[0-9a-fA-F-]{36}');
});

// ---- VERIFICACIÓN DE AMBULANCIAS (2026-08-08 · deltas #51/#52) ----
// Recurso de traslado del DÍA (3 estados; solo el 1 lleva badge) + proveedor/padrón/documentos
// (validación MANUAL con quién-validó, como la cédula) + ACTA sellada en sitio (verificador PÚBLICO
// 'ambu', cuya ruta va sin sesión más abajo). TODO POR EL SAFETY: gate único ambulance.manage.
// Los prefijos fijos van ANTES de los {param} para desambiguar; el acta se liga por uuid.
// LECTURA (hub, actas, proveedores): visible para producción y safety → `ambulance.manage|ambulance.view`.
// Transpo (HOD de transporte) no tiene ninguno → 403 hasta por URL directa.
Route::middleware(['auth','permission:ambulance.manage|ambulance.view'])->group(function () {
    Route::get('/ambulancia', [App\Http\Controllers\AmbulanceController::class, 'index'])->name('ambulance.index');
    Route::get('/ambulancia/actas', [App\Http\Controllers\AmbulanceController::class, 'records'])->name('ambulance.records');
    Route::get('/ambulancia/acta/{inspection:uuid}', [App\Http\Controllers\AmbulanceController::class, 'actaShow'])->name('ambulance.acta')->where('inspection', '[0-9a-fA-F-]{36}');
    Route::get('/ambulancia/proveedores', [App\Http\Controllers\AmbulanceController::class, 'providers'])->name('ambulance.providers');
    Route::get('/ambulancia/proveedor/{provider}', [App\Http\Controllers\AmbulanceController::class, 'providerShow'])->name('ambulance.provider.show')->whereNumber('provider');
});

// ESCRITURA (verificar, sellar, validar, padrón): SOLO el safety → `ambulance.manage`.
Route::middleware(['auth','permission:ambulance.manage'])->group(function () {
    // Recurso del día (Parte A)
    Route::get('/ambulancia/recurso', [App\Http\Controllers\AmbulanceController::class, 'dayResourceForm'])->name('ambulance.day.form');
    Route::post('/ambulancia/recurso', [App\Http\Controllers\AmbulanceController::class, 'storeDayResource'])->name('ambulance.day.store');
    // Verificación en sitio + acta (Parte C)
    Route::get('/ambulancia/verificar', [App\Http\Controllers\AmbulanceController::class, 'inspectForm'])->name('ambulance.inspect.form');
    Route::post('/ambulancia/verificar', [App\Http\Controllers\AmbulanceController::class, 'storeInspection'])->name('ambulance.inspect.store');
    Route::post('/ambulancia/acta/{inspection:uuid}/desbloquear', [App\Http\Controllers\AmbulanceController::class, 'unblock'])->name('ambulance.unblock')->where('inspection', '[0-9a-fA-F-]{36}');
    // Proveedor / padrón / documentos (Parte B)
    Route::post('/ambulancia/proveedores', [App\Http\Controllers\AmbulanceController::class, 'storeProvider'])->name('ambulance.provider.store');
    Route::post('/ambulancia/documento', [App\Http\Controllers\AmbulanceController::class, 'storeDocument'])->name('ambulance.document.store');
    Route::post('/ambulancia/documento/{doc}/validar', [App\Http\Controllers\AmbulanceController::class, 'validateDocument'])->name('ambulance.document.validate')->whereNumber('doc');
    Route::post('/ambulancia/proveedor/{provider}/tripulante', [App\Http\Controllers\AmbulanceController::class, 'storeCrew'])->name('ambulance.crew.store')->whereNumber('provider');
});

// ---- VIGILANCIA EPIDEMIOLÓGICA: panel silencioso + estudio de brote (2026-07-31 · delta #45) ----
// SILENCIOSO: no manda correos, no alerta, no declara brotes. Solo LEE consultas selladas y muestra
// conteos AGREGADOS (nunca nombres). Gate único epi.view (solo safety y médico). El estudio de brote
// entra al verificador PÚBLICO (SealVerifier 'brote'), cuya ruta va sin sesión más abajo. La EMISIÓN
// del estudio la restringe el controlador al clínico (documento clínico firmado).
Route::middleware(['auth','permission:epi.view'])->group(function () {
    Route::get('/vigilancia', [App\Http\Controllers\EpiController::class, 'index'])->name('epi.index');
    Route::get('/vigilancia/estudio/nuevo', [App\Http\Controllers\EpiController::class, 'outbreakCreate'])->name('epi.outbreak.create');
    Route::post('/vigilancia/estudio', [App\Http\Controllers\EpiController::class, 'outbreakStore'])->name('epi.outbreak.store');
    Route::get('/vigilancia/estudio/{study:uuid}', [App\Http\Controllers\EpiController::class, 'outbreakShow'])->name('epi.outbreak.show')->where('study', '[0-9a-fA-F-]{36}');
});

// ---- PÓSTER MEDEVAC: primera plantilla del MOTOR DE DOCUMENTOS (2026-07-31 · delta #46) ----
// El protocolo de emergencias por LOCACIÓN, RENDERIZADO desde el scouting (no captura nueva). Cada
// emisión CONGELA su payload y se sella; entra al verificador PÚBLICO (SealVerifier 'mdvc'), cuya
// ruta va sin sesión más abajo. EMITE SÓLO EL SAFETY: permiso PROPIO `medevac.issue` (no se recicla
// ninguno), que también blinda la URL directa. Requiere la tabla `medevac_posters` (owner-apply #46);
// sin ella MedevacPoster::supported() hace que el controlador responda 404.
// Orden: el prefijo fijo /medevac/emitir desambigua del /medevac/{uuid} (el póster se liga por uuid,
// no expone id secuencial). EMITIR ≠ los demás documentos → permiso distinto.
Route::middleware(['auth','permission:medevac.issue'])->group(function () {
    Route::get('/medevac/emitir/{scouting}', [App\Http\Controllers\MedevacController::class, 'create'])->name('medevac.create')->whereNumber('scouting');
    Route::post('/medevac/emitir/{scouting}', [App\Http\Controllers\MedevacController::class, 'store'])->name('medevac.store')->whereNumber('scouting');
    Route::get('/medevac/{poster:uuid}', [App\Http\Controllers\MedevacController::class, 'show'])->name('medevac.show')->where('poster', '[0-9a-fA-F-]{36}');
});

// ---- PAE · PLAN DE ATENCIÓN A EMERGENCIAS (2026-08-06) ----
// Documento UNO por llamado (día de rodaje), puede cubrir DOS locaciones (company move).
// RENDERIZADO desde el/los scouting elegidos (no captura nueva): organigrama del crew + riesgos
// evaluados + hospital por locación. Cada emisión CONGELA su payload y se sella; entra al
// verificador PÚBLICO (SealVerifier 'pae'), cuya ruta va sin sesión más abajo. EMITE SÓLO EL
// SAFETY: permiso PROPIO `pae.issue`, que también blinda la URL directa. Requiere la tabla
// `emergency_action_plans` (owner-apply 2026-08-06); sin ella EmergencyActionPlan::supported()
// hace que el controlador responda 404. Orden: /pae/emitir (fijo) antes de /pae/{uuid}; el PAE se
// liga por uuid, no expone id secuencial.
Route::middleware(['auth','permission:pae.issue'])->group(function () {
    Route::get('/pae', [App\Http\Controllers\PaeController::class, 'index'])->name('pae.index');
    Route::get('/pae/emitir', [App\Http\Controllers\PaeController::class, 'create'])->name('pae.create');
    Route::post('/pae', [App\Http\Controllers\PaeController::class, 'store'])->name('pae.store');
    // Editar = emitir una REVISIÓN nueva que supersede a la anterior. /editar antes de /{uuid}.
    Route::get('/pae/{pae:uuid}/editar', [App\Http\Controllers\PaeController::class, 'edit'])->name('pae.edit')->where('pae', '[0-9a-fA-F-]{36}');
    Route::get('/pae/{pae:uuid}', [App\Http\Controllers\PaeController::class, 'show'])->name('pae.show')->where('pae', '[0-9a-fA-F-]{36}');
});

// ---- REPORTE FINAL DE WRAP (2026-07-24) ----
// El documento de cierre de la producción: contrasta lo que el scouting predijo contra lo que
// realmente pasó. Requiere la tabla `wrap_reports` (el owner aplica 2026-07-24-wrap-reports.sql);
// sin ella, WrapReport::supported() hace que estas rutas respondan 404 y el menú no las ofrezca.
//
// PERMISOS. Todas las rutas comparten la baseline `dsr.view` (leer reportes de seguridad); el wrap
// no revela nada que sus fuentes no revelen ya —al contrario, quita los nombres—, así que un
// permiso nuevo habría dejado el documento invisible hasta correr un seeder.
//
// ⚠ EMITIR NO ES UN PERMISO, ES UNA REGLA DE NEGOCIO, y por eso NO va en el middleware:
//   · QUIÉN — sólo el SAFETY MANAGER o el SAFETY ASIGNADO A LA PRODUCCIÓN (WrapReport::issuableBy).
//     No `dsr.export`: eso lo tiene también line-producer, y emitir queda fuera del alcance de un
//     productor. Un safety asignado por pivote puede ni tener dsr.export, así que la baseline debe
//     ser dsr.view y la autorización real la impone el controlador.
//   · CUÁNDO — no antes de la fecha de finalización (WrapReport::windowBlockedReason), para que un
//     clic accidental no dispare el congelamiento antes de tiempo.
//   Ambas se aplican en el controlador (server-side), no en la ruta: son condiciones sobre el
//   estado de la producción y el usuario, no un flag estático de permiso.
// OJO orden: las rutas fijas van ANTES que `/wrap/{id}`.
Route::middleware(['auth', 'permission:dsr.view'])->group(function () {
    Route::get('/wrap/borrador', [App\Http\Controllers\WrapReportController::class, 'preview'])->name('wrap.preview');
    Route::post('/wrap', [App\Http\Controllers\WrapReportController::class, 'store'])->name('wrap.store');
    Route::post('/wrap/{id}/anexo', [App\Http\Controllers\WrapReportController::class, 'storeAddendum'])->name('wrap.addendum')->whereNumber('id');
    Route::get('/wrap', [App\Http\Controllers\WrapReportController::class, 'index'])->name('wrap.index');
    Route::get('/wrap/{id}', [App\Http\Controllers\WrapReportController::class, 'show'])->name('wrap.show')->whereNumber('id');
});

// ---- INJURIES (Accidentes) ----
Route::middleware(['auth','permission:injury.create'])->group(function () {
    Route::get('/accident', [App\Http\Controllers\InjuryReportController::class, 'create'])->name('injury_reports.create');
    Route::post('/accidentCreate', [App\Http\Controllers\InjuryReportController::class, 'store'])->name('injury_reports.store');
});
Route::middleware(['auth','permission:injury.view'])->group(function () {
    Route::get('/accidents', [App\Http\Controllers\InjuryReportController::class, 'inicial'])->name('injury_reports.index');
    Route::get('/accident/{id}', [App\Http\Controllers\InjuryReportController::class, 'show'])->name('injury_reports.show')->whereNumber('id');
    // (2026-07-20) DOS SALIDAS: el expediente COMPLETO. El permiso de módulo (injury.view) lo
    // da este grupo; el silo médico (policy viewMedical) lo aplica showComplete() con authorize().
    Route::get('/accident/{id}/completo', [App\Http\Controllers\InjuryReportController::class, 'showComplete'])->name('injury_reports.show_complete')->whereNumber('id');
    Route::get('/users/search', [App\Http\Controllers\InjuryReportController::class, 'searchUsers'])->name('users.search');

    // ---- SANDBOX EXPERIMENTAL (2026-07-15) — rediseño de reporte v2 ----
    // Ruta closure de DISEÑO: renderiza admin.injuryreport-v2 (alias de admin.injuryreport, ya
    // promovido = el EXPEDIENTE COMPLETO). Se conserva por el esquema de reversión de diseño.
    // (2026-07-20) SEGURIDAD: como injuryreport es ahora el expediente completo (causa raíz,
    // declaración de testigos, anexos), esta ruta DEBE aplicar el MISMO gate viewMedical que
    // showComplete(); si no, filtraría el silo médico a cualquiera con injury.view.
    Route::get('/accident/{id}/v2', function ($id) {
        $injuryReport = \App\Models\InjuryReport::with('user')->findOrFail($id);

        // Mismo silo médico que el expediente completo (Gate::authorize lanza 403 si no pasa).
        \Illuminate\Support\Facades\Gate::authorize('viewMedical', $injuryReport);

        $standardUrl = null;
        if (\Illuminate\Support\Facades\Schema::hasColumn('injury_reports', 'regulation_code')
            && \Illuminate\Support\Facades\Schema::hasColumn('safety_standards', 'reference_url')
            && $injuryReport->regulation_code) {
            $standardUrl = \App\Models\SafetyStandard::where('regulation_code', $injuryReport->regulation_code)->value('reference_url');
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('standardables')) { $injuryReport->load('standards'); }
        if (\Illuminate\Support\Facades\Schema::hasTable('action_items'))  { $injuryReport->load('actionItems'); }
        if (\Illuminate\Support\Facades\Schema::hasTable('witnesses'))      { $injuryReport->load('witnesses'); }
        if (\Illuminate\Support\Facades\Schema::hasTable('addendums'))      { $injuryReport->load('addendums.createdBy'); }

        return view('admin.injuryreport-v2', compact('injuryReport', 'standardUrl'));
    })->whereNumber('id')->name('injury_reports.show_v2');
});

// ---- CALL SHEET ("el llamado") — EN PAUSA (2026-06-28) ----
// El módulo inicial se RETIRÓ a _legacy_backup/decommission-2026-06-28/. Razón (owner): un
// callsheet real parte de guion(es) + cast list + números de personaje + necesidades por
// departamento; se reconstruirá "cuando lleguemos con dirección". Sin rutas hasta entonces.

// ---- CONFIGURACIÓN / MARCA (super-admin) ----
// Panel de branding en vivo: logo del cliente, nombre de marca, título/PWA, color primario.
// Guarda en la tabla `settings`; gateado por permission:settings.manage (solo super-admin).
Route::middleware(['auth','permission:settings.manage'])->group(function () {
    Route::get('/settings/branding', [App\Http\Controllers\BrandingController::class, 'edit'])->name('settings.branding.edit');
    Route::post('/settings/branding', [App\Http\Controllers\BrandingController::class, 'update'])->name('settings.branding.update');
});

// ---- CATÁLOGOS (departamentos / puestos / notificaciones) ----
// PASO #2 (2026-06-28): repuntados de AdminController (RETIRADO → _legacy_backup) a controladores.
// AFINADO (2026-07-07): el CatalogController mono-clase se PARTIÓ en 3 verticales dedicados
// (DepartmentController / PositionController / NotificationController), como el resto de verticales.
// Métodos movidos VERBATIM; nombres/URLs/permiso IDÉNTICOS (vistas y sidebar intactos).
// Operan sobre las tablas NUEVAS departments/positions (+ sort_order). LISTAS = catalogs.view;
// MUTACIONES/edición = catalogs.manage. Las activaciones son POST (ya no mutan por GET).
Route::middleware(['auth','permission:catalogs.view'])->group(function () {
    Route::get('/departamentocrud',[App\Http\Controllers\DepartmentController::class,'departamentocrud'])->name('departamentocrud');
    Route::get('/positionscrud',[App\Http\Controllers\PositionController::class,'positionscrud'])->name('positionscrud');
    Route::get('/notificacioncrud',[App\Http\Controllers\NotificationController::class,'notificacioncrud'])->name('notificacioncrud');
});
Route::middleware(['auth','permission:catalogs.manage'])->group(function () {
    // Departamentos
    Route::post('/creardepartamento',[App\Http\Controllers\DepartmentController::class,'creardepartamento'])->name('creardepartamento');
    Route::post('/activardepartamento/{id}',[App\Http\Controllers\DepartmentController::class,'activardepartamento'])->name('activardepartamento')->whereNumber('id');
    Route::post('/desactivardepartamento/{id}',[App\Http\Controllers\DepartmentController::class,'desactivardepartamento'])->name('desactivardepartamento')->whereNumber('id');
    Route::get('/editardepartamento/{id}/edit',[App\Http\Controllers\DepartmentController::class,'editardepartamento'])->name('editardepartamento')->whereNumber('id');
    Route::post('/savedepartamento/{id}',[App\Http\Controllers\DepartmentController::class,'savedepartamento'])->name('savedepartamento')->whereNumber('id');
    // Puestos
    Route::post('/crearpositions',[App\Http\Controllers\PositionController::class,'crearpositions'])->name('crearpositions');
    Route::post('/activarposition/{id}',[App\Http\Controllers\PositionController::class,'activarposition'])->name('activarposition')->whereNumber('id');
    Route::post('/desactivarposition/{id}',[App\Http\Controllers\PositionController::class,'desactivarposition'])->name('desactivarposition')->whereNumber('id');
    Route::get('/editpositions/{id}',[App\Http\Controllers\PositionController::class,'editpositions'])->name('editpositions')->whereNumber('id');
    Route::post('/saveposition/{id}',[App\Http\Controllers\PositionController::class,'saveposition'])->name('saveposition')->whereNumber('id');
    // Notificaciones
    Route::post('/crearnotificacion',[App\Http\Controllers\NotificationController::class,'crearnotificacion'])->name('crearnotificacion');
    Route::post('/activarnotificacion/{id}',[App\Http\Controllers\NotificationController::class,'activarnotificacion'])->name('activarnotificacion')->whereNumber('id');
    Route::post('/desactivarnotificacion/{id}',[App\Http\Controllers\NotificationController::class,'desactivarnotificacion'])->name('desactivarnotificacion')->whereNumber('id');
    Route::get('/editarnotificacion/{id}',[App\Http\Controllers\NotificationController::class,'editarnotificacion'])->name('editarnotificacion')->whereNumber('id');
    Route::post('/savenotificacion/{id}',[App\Http\Controllers\NotificationController::class,'savenotificacion'])->name('savenotificacion')->whereNumber('id');
});


/*
|--------------------------------------------------------------------------
| Grupo `admin` (legacy) — pendiente de migrar por vertical / decommission
|--------------------------------------------------------------------------
| Sigue exigiendo el flag binario `admin` (AdminMiddleware). Aquí quedan, por
| ahora: TODO lo COVID/PCR/lab/queue (se va con COVID-DECOMMISSION), import/export
| y correos. (Catálogos YA migrados a permission:catalogs.* arriba.) El super-admin
| (admin = 1) las sigue viendo.
*/
Route::group(['middleware' => 'admin'], function () {

    // --- Acciones COVID / fila de pruebas ---
    // --- /notoday, /notodays (Lote 3), /checkpoint, /temperature (Lote 4), /notsick (desacople) — COVID ELIMINADOS (2026-06-25) ---
    // historialWR MIGRADO a grupo permission:medical.view (arriba) — 2026-06-28.

    // --- PCR CRUD (COVID) ELIMINADO — Lote 2 (2026-06-25): pcrcrud/crearpcr/editarpcr/savepcr/eliminarpcr ---

    // --- Catálogos: MIGRADOS a permission:catalogs.* (definidos arriba). Ya no viven aquí. ---

    // --- Búsquedas COVID ---
    // `searchlab`, `searcheckpoint` y `searchqueue` (cola virtual) BORRADOS.
    // Páginas anfitrionas COVID restantes (pcrtest/listcrew, admin/checkpoint) → COVID-DECOMMISSION.

    // --- Correos / recordatorios ---
    // `mymail` y `enviarcorreos` (queueController) ELIMINADOS con el Lote 1 (cola virtual).
    // `/sendreminder` y `/welcomeresend/{id}` CONVERTIDOS (2026-06-28) a comandos Artisan
    // (`crew:daily-reminder` y `crew:welcome-resend {id}`) — eran GET con efecto colateral y sin UI.
    // Ver app/Console/Commands/. CrewMailController retirado a _legacy_backup/.

    // --- Laboratorio / PCR / antígeno (COVID): /lab /listcrew /positivepcr /negativepcr /negativeantg ELIMINADO — Lote 3 COVID-DECOMMISSION (2026-06-25), respaldo en _legacy_backup/ ---

    // --- Import de crew ---
    // `/testexport` (exportController + AntigenTestExport) ELIMINADO con el Lote 1 (cola virtual).
    Route::get('/importcrew',[App\Http\Controllers\importController::class,'importcrew'])->name('importcrew');
    Route::post('/crewstore',[App\Http\Controllers\importController::class,'store'])->name('crewstore');

    // --- Fila virtual / pruebas (COVID) — queueController ELIMINADO ---
    // Lote 1 COVID-DECOMMISSION (2026-06-25): scheduletest/antigentest/crudtest/selecta/
    // selectb/selectglobal/userqueue/usertested/remindertest retirados. Respaldo en _legacy_backup/.

    // NOTE: `/profile` (name `perfil`) se declara una sola vez, abajo, dentro del
    // grupo `auth` para que cualquier usuario autenticado vea su PROPIO perfil.
});

// SEGURIDAD (2026-06-26): `/nophoto` (export CSV) estaba SIN `auth` — exportaba PII de TODO el
// crew activo (nombre, apellidos, fecha de nacimiento, sexo, teléfono, email, puesto) a cualquiera.
// Ahora exige sesión + `reports.export`. (2026-07-06) Repuntado a ExportController@expCsv, que ya
// aplica el scope por departamento (super-admin ve todo; roles acotados solo su depto). Mismo URI/nombre.
Route::middleware(['auth','permission:reports.export'])->group(function () {
    Route::get('/nophoto',[App\Http\Controllers\ExportController::class,'expCsv'])->name('expCsv');
    // (2026-08-07) Export del Crew List como DOCUMENTO vertical (bandas de departamento en orden
    // canónico). Reemplaza al CSV en la UI; el CSV /nophoto se conserva como endpoint sin enlace.
    Route::get('/crew/export',[App\Http\Controllers\CrewListController::class,'crewExport'])->name('crew.export');
});

// SEGURIDAD (2026-06-25): estas rutas estaban SIN `auth` — cualquiera podía cambiar la
// contraseña de CUALQUIER usuario por id (account-takeover/IDOR) o enviar formularios
// clínicos sin sesión. Ahora exigen sesión. `updatepassword` ya NO recibe {id}: opera
// sobre auth()->user() (ver PerfilController) → sin IDOR.
Route::middleware(['auth'])->group(function () {
    Route::get('/changepassword',[App\Http\Controllers\PerfilController::class,'changepassword'])->name('changepassword');
    Route::post('/updatepassword',[App\Http\Controllers\PerfilController::class,'updatepassword'])->name('updatepassword');
    // (2026-07-24) `privacidad`: el POST también, no sólo la pantalla. Ocultar el formulario no
    // es una guarda — sin esto, un POST directo recabaría los datos igual.
    Route::post('/formularios/registro',[App\Http\Controllers\FormulariosController::class, 'newformulario'])
        ->middleware('privacidad')->name('expediente.store');
    // LIMPIEZA (2026-07-24): `POST /formularios/medicos` ELIMINADA junto con
    // FormulariosController@registrarformulario. Era INCAPAZ de funcionar: el método pedía 49
    // parámetros POSICIONALES y la URI no declaraba ninguno → ArgumentCountError fatal en cuanto
    // alguien la posteara. Su único "cliente" era un fetch() de formulario.blade.php a
    // `/registrarformulario/…`, ruta que nunca existió (404). Ambos se retiraron.
    Route::get('/profile',[App\Http\Controllers\PerfilController::class,'indexb'])->name('perfil');
    // SEGURIDAD (2026-07-06): subida del avatar (cropper) — ahora exige sesión (antes iba SIN auth).
    Route::post('/crop-image-upload',[App\Http\Controllers\cropimageController::class,'uploadCropImage'])->name('uploadCropImage');
});
// SEGURIDAD/LIMPIEZA (2026-06-26): `/pruebachedule` ELIMINADO — era otro DUPLICADO GET-sin-auth del
// reset de `encuestadiaria` (nombre de prueba, ni retornaba). (2026-07-24) Ya no hay reset automático.
// SEGURIDAD/LIMPIEZA (2026-07-06): `GET /crop-image` ELIMINADO (renderizaba una vista inexistente
// y su método index() se retiró); `POST /crop-image-upload` movido al grupo `auth` de arriba.

// ---- SYNC / API (Módulo 12 — offline-first, UPSERT idempotente por uuid) ----
// El PWA/Service Worker envía un LOTE de reportes creados offline (cada uno con su
// uuid generado en el cliente); SyncController@up hace UPSERT por uuid, así que un
// reintento del SW ACTUALIZA en vez de DUPLICAR. Va en routes/WEB (no en api.php) a
// propósito: usa el middleware 'auth' de SESIÓN (mismo origen, cookie ya presente),
// evitando el guard `auth:sanctum` que aún NO está configurado en este proyecto.
// CSRF: al vivir en 'web', el POST exige token CSRF → el PWA mismo-origen debe mandar
// el X-CSRF-TOKEN (meta) en el fetch. PRODUCCIÓN: endurecer a token Bearer (Sanctum)
// para clientes que sincronicen sin sesión web viva (ver comentario del controlador).
Route::post('/api/sync/up', [\App\Http\Controllers\Api\SyncController::class, 'up'])->middleware('auth')->name('api.sync.up');

// ============================================================================
// (2026-07-13) PILARES 3 / 4 / 1b / 5 — módulos nuevos. Controladores/vistas
// gatean además con @feature('...') / Features::enabled(): apagar el flag oculta
// y bloquea el módulo aunque la ruta exista.
// ============================================================================

// ---- Pilar 3: SDS / consumibles (biblioteca viva) + dinámica de Efectos Especiales ----
Route::middleware(['auth'])->group(function () {
    // Lectura del catálogo: consultar una SDS antes de disparar un efecto.
    Route::middleware('permission:sds.view')->group(function () {
        Route::get('/consumables',          [\App\Http\Controllers\ConsumableController::class, 'index'])->name('consumables.index');
        // ⚠ ORDEN — ESTA RUTA SE DECLARA ANTES QUE `/consumables/create` (grupo sds.create,
        // aquí abajo) y Laravel casa la PRIMERA que empate, no la más específica. Sin el
        // whereNumber('id'), la palabra «create» entraría como {id} y el alta moriría en el
        // findOrFail de show() con un 404 inexplicable. La restricción numérica es LO ÚNICO
        // que mantiene viva a su hermana: no la quites al reordenar este bloque.
        Route::get('/consumables/{id}',     [\App\Http\Controllers\ConsumableController::class, 'show'])->name('consumables.show')->whereNumber('id');

        // Capa A (Paso 4a): catálogo de TIPOS de efecto — la doctrina reutilizable. Ojo,
        // NO es `/sfx` (bitácora de disparos EN VIVO, más abajo): aquí nada ocurre ni se
        // dispara. Asociar insumos es autoridad y vive en el grupo sds.manage.
        Route::get('/sfx-effects',          [\App\Http\Controllers\SfxEffectTypeController::class, 'index'])->name('sfx-effects.index');
        Route::get('/sfx-effects/{id}',     [\App\Http\Controllers\SfxEffectTypeController::class, 'show'])->name('sfx-effects.show')->whereNumber('id');
    });

    // Alta y mantenimiento: capturar/corregir fichas (incluye al Safety en set; lo que
    // capture nace PENDIENTE de verificación si no es además autoridad verificadora).
    Route::middleware('permission:sds.create')->group(function () {
        Route::get('/consumables/create',   [\App\Http\Controllers\ConsumableController::class, 'create'])->name('consumables.create');
        Route::post('/consumables',         [\App\Http\Controllers\ConsumableController::class, 'store'])->name('consumables.store');
        Route::get('/consumables/{id}/edit',[\App\Http\Controllers\ConsumableController::class, 'edit'])->name('consumables.edit')->whereNumber('id');
        Route::put('/consumables/{id}',     [\App\Http\Controllers\ConsumableController::class, 'update'])->name('consumables.update')->whereNumber('id');
    });

    // Autoridad verificadora: valida las fichas capturadas en campo y depura el catálogo.
    Route::middleware('permission:sds.manage')->group(function () {
        Route::post('/consumables/{id}/verify', [\App\Http\Controllers\ConsumableController::class, 'verify'])->name('consumables.verify')->whereNumber('id');
        Route::delete('/consumables/{id}',  [\App\Http\Controllers\ConsumableController::class, 'destroy'])->name('consumables.destroy')->whereNumber('id');

        // Puente N:M efecto↔insumo (Capa A ↔ Capa B). Decir "este efecto se hace con este
        // material" es doctrina, no captura: por eso es sds.manage y no sds.create. Las dos
        // son IDEMPOTENTES (ver el controlador): repetirlas no duplica ni truena.
        Route::post('/sfx-effects/{id}/consumables', [\App\Http\Controllers\SfxEffectTypeController::class, 'attach'])->name('sfx-effects.consumables.attach')->whereNumber('id');
        Route::delete('/sfx-effects/{id}/consumables/{consumable}', [\App\Http\Controllers\SfxEffectTypeController::class, 'detach'])->name('sfx-effects.consumables.detach')->whereNumber('id')->whereNumber('consumable');
    });

    // Papelera de consumibles RETIRADOS (Paso 1b, soft delete). Conservar el registro es
    // la razón de ser del retiro, así que restaurarlo o destruirlo en firme es autoridad
    // de super-admin — que además es el único que ve las fichas retiradas. Se usa
    // `role:super-admin` (no un permiso) porque es explícitamente "solo el dueño".
    Route::middleware('role:super-admin')->group(function () {
        Route::put('/consumables/{id}/restore', [\App\Http\Controllers\ConsumableController::class, 'restore'])->name('consumables.restore')->whereNumber('id');
        Route::delete('/consumables/{id}/force', [\App\Http\Controllers\ConsumableController::class, 'forceDestroy'])->name('consumables.forceDestroy')->whereNumber('id');
    });

    Route::get('/sfx',                      [\App\Http\Controllers\SfxController::class, 'index'])->name('sfx.index');
    Route::post('/sfx/start',               [\App\Http\Controllers\SfxController::class, 'start'])->name('sfx.start');
    Route::post('/sfx/{id}/stop',           [\App\Http\Controllers\SfxController::class, 'stop'])->name('sfx.stop')->whereNumber('id');
});

// ---- Normas del catálogo normativo (`safety_standards`, Paso 4a) — gateo ASIMÉTRICO ----
// Calca la estructura del bloque SDS de arriba (grupo auth externo + subgrupos permission
// anidados), pero el permiso NO es simétrico: standards.view lee; standards.create SOLO
// agrega (el safety-officer añade normas pero NO las edita); standards.manage es la única
// autoridad que corrige / verifica / retira / reactiva. "Retirar" NO borra: pone is_active=0
// (ver SafetyStandardController::deactivate) para no romper el histórico vivo de Injury/Scouting.
Route::middleware(['auth'])->group(function () {
    // Lectura del catálogo normativo.
    Route::middleware('permission:standards.view')->group(function () {
        Route::get('/standards',            [\App\Http\Controllers\SafetyStandardController::class, 'index'])->name('standards.index');
        // ⚠ ORDEN — ESTA RUTA SE DECLARA ANTES QUE `/standards/create` (grupo standards.create,
        // aquí abajo) y Laravel casa la PRIMERA que empate. Sin el whereNumber('id'), la palabra
        // «create» entraría como {id} y el alta moriría en el findOrFail de show() con un 404
        // inexplicable. La restricción numérica es LO ÚNICO que mantiene viva a su hermana.
        Route::get('/standards/{id}',       [\App\Http\Controllers\SafetyStandardController::class, 'show'])->name('standards.show')->whereNumber('id');
    });

    // Alta: capturar normas nuevas (el safety-officer AGREGA pero NO edita; lo que capture
    // nace PENDIENTE de verificación si no es además autoridad verificadora).
    Route::middleware('permission:standards.create')->group(function () {
        Route::get('/standards/create',     [\App\Http\Controllers\SafetyStandardController::class, 'create'])->name('standards.create');
        Route::post('/standards',           [\App\Http\Controllers\SafetyStandardController::class, 'store'])->name('standards.store');
    });

    // Autoridad verificadora: corrige, valida las normas capturadas, y retira/reactiva del
    // catálogo de captura. deactivate/reactivate son PUT (mutan estado, idempotentes).
    Route::middleware('permission:standards.manage')->group(function () {
        Route::get('/standards/{id}/edit',        [\App\Http\Controllers\SafetyStandardController::class, 'edit'])->name('standards.edit')->whereNumber('id');
        Route::put('/standards/{id}',             [\App\Http\Controllers\SafetyStandardController::class, 'update'])->name('standards.update')->whereNumber('id');
        Route::post('/standards/{id}/verify',     [\App\Http\Controllers\SafetyStandardController::class, 'verify'])->name('standards.verify')->whereNumber('id');
        Route::put('/standards/{id}/deactivate',  [\App\Http\Controllers\SafetyStandardController::class, 'deactivate'])->name('standards.deactivate')->whereNumber('id');
        Route::put('/standards/{id}/reactivate',  [\App\Http\Controllers\SafetyStandardController::class, 'reactivate'])->name('standards.reactivate')->whereNumber('id');
    });
});

// ---- Eventos posibles (`hazard_events`, Paso 4b) — gateo ASIMÉTRICO (espejo del 4a) ----
// Calca el bloque de Normas de arriba: hazardevents.view lee; hazardevents.create SOLO agrega
// (el safety-officer captura eventos + liga normas iniciales pero NO edita); hazardevents.manage
// es la única autoridad que corrige / verifica / retira / reactiva / re-liga normas (editor N:M
// en edit). "Retirar" NO borra: pone is_active=0 (ver HazardEventController::deactivate) para no
// disparar el hook `deleting` que purgaría hazard_event_standard ni romper el histórico vivo.
Route::middleware(['auth'])->group(function () {
    // Lectura del catálogo de eventos.
    Route::middleware('permission:hazardevents.view')->group(function () {
        Route::get('/hazard-events',            [\App\Http\Controllers\HazardEventController::class, 'index'])->name('hazardevents.index');
        // ⚠ ORDEN — igual que en Normas: esta ruta se declara ANTES que `/hazard-events/create`
        // (grupo hazardevents.create, abajo). Sin el whereNumber('id') la palabra «create»
        // entraría como {id} y moriría en el findOrFail de show() con un 404 inexplicable.
        Route::get('/hazard-events/{id}',       [\App\Http\Controllers\HazardEventController::class, 'show'])->name('hazardevents.show')->whereNumber('id');
    });

    // Alta: capturar eventos nuevos + ligar sus normas iniciales (picker create-gated). Lo que
    // capture un safety-officer nace PENDIENTE de verificación si no es además autoridad verificadora.
    Route::middleware('permission:hazardevents.create')->group(function () {
        Route::get('/hazard-events/create',     [\App\Http\Controllers\HazardEventController::class, 'create'])->name('hazardevents.create');
        Route::post('/hazard-events',           [\App\Http\Controllers\HazardEventController::class, 'store'])->name('hazardevents.store');
    });

    // Autoridad verificadora: corrige, valida los eventos capturados, re-liga normas (editor N:M
    // en edit) y retira/reactiva del catálogo de captura. deactivate/reactivate son PUT (mutan
    // estado, idempotentes).
    Route::middleware('permission:hazardevents.manage')->group(function () {
        Route::get('/hazard-events/{id}/edit',        [\App\Http\Controllers\HazardEventController::class, 'edit'])->name('hazardevents.edit')->whereNumber('id');
        Route::put('/hazard-events/{id}',             [\App\Http\Controllers\HazardEventController::class, 'update'])->name('hazardevents.update')->whereNumber('id');
        Route::post('/hazard-events/{id}/verify',     [\App\Http\Controllers\HazardEventController::class, 'verify'])->name('hazardevents.verify')->whereNumber('id');
        Route::put('/hazard-events/{id}/deactivate',  [\App\Http\Controllers\HazardEventController::class, 'deactivate'])->name('hazardevents.deactivate')->whereNumber('id');
        Route::put('/hazard-events/{id}/reactivate',  [\App\Http\Controllers\HazardEventController::class, 'reactivate'])->name('hazardevents.reactivate')->whereNumber('id');

        // (captura fluida · Paso 3) Medidas de control por CSV: descarga editable (ordenada por
        // uso real) e importa idempotente. Segmentos estáticos → no chocan con {id} (whereNumber).
        Route::get('/hazard-events/control-measures/export',  [\App\Http\Controllers\HazardEventController::class, 'exportControlCsv'])->name('hazardevents.control.export');
        Route::post('/hazard-events/control-measures/import', [\App\Http\Controllers\HazardEventController::class, 'importControlCsv'])->name('hazardevents.control.import');
    });
});

// ---- Pilar 4: Medical Addendum (append-only; contexto médico) ----
Route::middleware(['auth','permission:medical.create'])->group(function () {
    Route::post('/accident/{injury}/addendum', [\App\Http\Controllers\AddendumController::class, 'store'])->name('addendums.store')->whereNumber('injury');
});

// ---- Pilar 5: Feature Flags (super-admin) ----
Route::middleware(['auth','permission:settings.manage'])->group(function () {
    Route::get('/settings/features',  [\App\Http\Controllers\FeatureFlagController::class, 'index'])->name('features.index');
    Route::post('/settings/features', [\App\Http\Controllers\FeatureFlagController::class, 'update'])->name('features.update');
});

// ---- Verificador público de sellos (2026-07-24) ----
// PÚBLICO y SIN 'signed' a propósito: el QR va IMPRESO en el documento entregado, así que la URL
// es pública por diseño — firmarla no protegería nada y caducaría el papel ya emitido. La
// contención es el throttle + que la respuesta sea un ACUSE de cinco campos, sin dato sensible
// (ver SealVerifier::resolve, donde muere el modelo). El {uuid} se restringe al formato v4 para
// que la basura ni llegue a consultar la BD.
Route::middleware(['throttle:20,1'])->group(function () {
    Route::get('/verificar/{tipo}/{uuid}', [\App\Http\Controllers\SealVerificationController::class, 'show'])
        ->name('seal.verify')
        ->where('tipo', '[a-z]{3,6}')
        ->where('uuid', '[0-9a-fA-F-]{36}');
});

// ---- Pilar 1b: Magic Links (PÚBLICO, firmado + expirable + rate-limit) ----
// Sin auth: el responsable abre el link firmado (wa.me) y sube la foto de mitigación.
// La firma y la caducidad (7 días) las impone 'signed'; throttle limita abusos.
// (2026-07-24) throttle bajado de 30/min a 6/min: el POST acepta 12 MB, así que 30/min permitían
// ~360 MB por minuto y por IP contra el disco del servidor. Seis intentos por minuto le sobran a
// una persona subiendo una foto desde el celular.
Route::middleware(['signed','throttle:6,1'])->group(function () {
    Route::get('/mitigation/{action}',  [\App\Http\Controllers\MitigationController::class, 'show'])->name('mitigation.show')->whereNumber('action');
    Route::post('/mitigation/{action}', [\App\Http\Controllers\MitigationController::class, 'store'])->name('mitigation.store')->whereNumber('action');
});

// ---- Quien cobra · PASO 3: INTAKE AUTOSERVICIO (PÚBLICO, firmado + expirable) ----
// La persona invitada abre su link firmado y llena su intake. La firma es su llave (no login).
Route::middleware(['signed','throttle:20,1'])->group(function () {
    Route::get('/intake/{user}',  [\App\Http\Controllers\IntakeController::class, 'show'])->name('intake.show')->whereNumber('user');
    Route::post('/intake/{user}', [\App\Http\Controllers\IntakeController::class, 'store'])->name('intake.store')->whereNumber('user');
    // Segundo factor: coteja fecha de nacimiento antes de abrir el asistente (no es muro; intentos limitados).
    Route::post('/intake/{user}/verify', [\App\Http\Controllers\IntakeController::class, 'verify'])->name('intake.verify')->whereNumber('user');
});

// ---- Quien cobra · PASO 3: captura por QUIEN CONTRATA (autenticado; guarda de depto en el ctrl) ----
Route::middleware(['auth'])->group(function () {
    Route::get('/payees/{payee}/intake',  [\App\Http\Controllers\IntakeController::class, 'contractorForm'])->name('payee.intake.form')->whereNumber('payee');
    Route::post('/payees/{payee}/intake', [\App\Http\Controllers\IntakeController::class, 'contractorStore'])->name('payee.intake.store')->whereNumber('payee');
});

// ---- Quien cobra · PASO 4: VISIBILIDAD (SOLO LECTURA) ----
// Gate de módulo `payees.view`; el SCOPE fino ("quien contrata es quien ve") lo pone
// Payee::scopeVisibleTo + PayeePolicy. El serve de PDF va GATEADO por la misma visibilidad
// (privado, nunca /storage). Las rutas fijas van ANTES del {payee} para no ser sombreadas.
Route::middleware(['auth','permission:payees.view'])->group(function () {
    Route::get('/payees',                          [\App\Http\Controllers\PayeeController::class, 'index'])->name('payees.index');
    Route::get('/payees/{payee}',                  [\App\Http\Controllers\PayeeController::class, 'show'])->name('payees.show')->whereNumber('payee');
    Route::get('/payees/{payee}/documento/{doc}',  [\App\Http\Controllers\PayeeController::class, 'document'])->name('payees.document')->whereNumber('payee')->whereNumber('doc');
});

// ---- Quien cobra · VENTANA DE RECEPCIÓN POR PERIODO DE PAGO ----
// VER el tablero de "quién falta" (periods.view; el scope fino lo pone PeriodBoard vía
// applyContractingScope). ADMINISTRAR la ventana (periods.manage; contabilidad): abrir/cerrar/
// reabrir + asignar la frecuencia del contrato. La ruta fija va ANTES del {period} numérico.
Route::middleware(['auth','permission:periods.view'])->group(function () {
    Route::get('/periodos',           [\App\Http\Controllers\PaymentPeriodController::class, 'index'])->name('periods.index');
    Route::get('/periodos/{period}',  [\App\Http\Controllers\PaymentPeriodController::class, 'show'])->name('periods.show')->whereNumber('period');
});
Route::middleware(['auth','permission:periods.manage'])->group(function () {
    // Recordatorio manual a quienes faltan (contabilidad; un clic por persona, WhatsApp).
    Route::get('/periodos/{period}/recordatorios', [\App\Http\Controllers\PaymentPeriodController::class, 'reminders'])->name('periods.reminders')->whereNumber('period');
    Route::post('/periodos',                    [\App\Http\Controllers\PaymentPeriodController::class, 'store'])->name('periods.store');
    Route::post('/periodos/{period}/cerrar',    [\App\Http\Controllers\PaymentPeriodController::class, 'close'])->name('periods.close')->whereNumber('period');
    Route::post('/periodos/{period}/reabrir',   [\App\Http\Controllers\PaymentPeriodController::class, 'reopen'])->name('periods.reopen')->whereNumber('period');
    Route::post('/payees/contratos/{contract}/frecuencia', [\App\Http\Controllers\PaymentPeriodController::class, 'setFrequency'])->name('periods.contract.frequency')->whereNumber('contract');
});

// ---- Pilar 1: Progressive Disclosure — Fase 2 (edit/update de compliance en back-office) ----
// La captura Fase 1 (store) es ágil (mínimo indispensable); aquí se completa la carga
// burocrática (matriz 5×5, normas, causa raíz) y se limpia pending_compliance.
Route::middleware(['auth','permission:hazards.manage'])->group(function () {
    Route::get('/unsafeact/{id}/edit',  [\App\Http\Controllers\HazardNotificationController::class, 'edit'])->name('hazards.edit')->whereNumber('id');
    Route::put('/unsafeact/{id}',        [\App\Http\Controllers\HazardNotificationController::class, 'update'])->name('hazards.update')->whereNumber('id');
    Route::get('/unsafecond/{id}/edit', [\App\Http\Controllers\unsafecondNotificationController::class, 'edit'])->name('unsafenotifications.edit')->whereNumber('id');
    Route::put('/unsafecond/{id}',       [\App\Http\Controllers\unsafecondNotificationController::class, 'update'])->name('unsafenotifications.update')->whereNumber('id');
});
Route::middleware(['auth','permission:injury.create'])->group(function () {
    Route::get('/accident/{id}/edit',   [\App\Http\Controllers\InjuryReportController::class, 'edit'])->name('injury_reports.edit')->whereNumber('id');
    Route::put('/accident/{id}',         [\App\Http\Controllers\InjuryReportController::class, 'update'])->name('injury_reports.update')->whereNumber('id');
});
