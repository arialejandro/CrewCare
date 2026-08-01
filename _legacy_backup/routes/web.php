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

Route::get('/locale/{locale}', function ($locale) {
    session()->put('locale', $locale);
    return Redirect::back();
});

Route::get('/offline', function () {
    return view('/vendor/laravelpwa/offline');
});

Route::get('/newdayRep',[App\Http\Controllers\resetController::class,'newdayRep'])->name('newdayRep');
Route::get('/PhotoReminder',[App\Http\Controllers\resetController::class,'PhotoReminder'])->name('PhotoReminder');
Route::get('/newTD',[App\Http\Controllers\resetController::class,'newTD'])->name('newTD');
Route::get('/newWR',[App\Http\Controllers\resetController::class,'newWR'])->name('newWR');
Route::get('/Nresult',[App\Http\Controllers\resetController::class,'Nresult'])->name('Nresult');

Auth::routes();
Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
Route::get('/dailyreport',[App\Http\Controllers\encuestasController::class,'viewencuesta'])->name('encuesta');
Route::get('/dailyreport/{id}',[App\Http\Controllers\encuestasController::class,'viewencuesta'])->name('encuesta');

Route::get('/negative-mail', [App\Http\Controllers\MailController::class, 'sendMail'])->name('sendMail');
Route::get('/reminder-mail', [App\Http\Controllers\ReminderMailController::class, 'ReminderMail'])->name('ReminderMail');


Route::group(['middleware' => 'admin'], function () {
    
    Route::get('/admin/departamentocrud');
    Route::get('/usuarioscrud',[App\Http\Controllers\AdminController::class,'usuarioscrud'])->name('usuarioscrud');
    Route::get('/comcrud',[App\Http\Controllers\AdminController::class,'comcrud'])->name('comcrud');
    Route::get('/medicocrud',[App\Http\Controllers\AdminController::class,'medicocrud'])->name('medicocrud');
    Route::get('/consulta/{id}',[App\Http\Controllers\cmedicController::class,'create'])->name('cmedica.create');
    Route::post('/cmedica/{id_user}', [App\Http\Controllers\cmedicController::class, 'store'])->name('cmedica.store');
    Route::get('/idcardscrud',[App\Http\Controllers\AdminController::class,'idcardscrud'])->name('idcardscrud');
    Route::get('/checkgft/{id}',[App\Http\Controllers\AdminController::class,'checkgft'])->name('checkgft');
    Route::get('/uncheckgft/{id}',[App\Http\Controllers\AdminController::class,'uncheckgft'])->name('uncheckgft');
    Route::get('/notoday/{id}',[App\Http\Controllers\AdminController::class,'notoday'])->name('notoday');
    Route::get('/notodays/{id}',[App\Http\Controllers\AdminController::class,'notodays'])->name('notodays');
    Route::get('/checkpoint',[App\Http\Controllers\AdminController::class,'checkpoint'])->name('checkpoint');
    Route::get('/activarusuario/{id}',[App\Http\Controllers\AdminController::class,'activarusuario'])->name('activarusuario');
    Route::get('/activarencuesta/{id}',[App\Http\Controllers\AdminController::class,'activarencuesta'])->name('activarencuesta');
    Route::get('/activaradmin/{id}',[App\Http\Controllers\AdminController::class,'activaradmin'])->name('activaradmin');
    Route::get('/desactivaradmin/{id}',[App\Http\Controllers\AdminController::class,'desactivaradmin'])->name('desactivaradmin');

    Route::post('/desactivarusuario/{id}',[App\Http\Controllers\AdminController::class,'desactivarusuario'])->name('desactivarusuario');
    Route::post('/putadm/{id}',[App\Http\Controllers\AdminController::class,'putga'])->name('putga');
    Route::post('/putmed/{id}',[App\Http\Controllers\AdminController::class,'putgb'])->name('putgb');
    Route::post('/putsup/{id}',[App\Http\Controllers\AdminController::class,'putgg'])->name('putgg');
    Route::post('/selectpuesto/{id}',[App\Http\Controllers\AdminController::class,'selectpuesto'])->name('selectpuesto');
    Route::get('/adduser',[App\Http\Controllers\AdminController::class,'adduser'])->name('adduser');

    Route::post('/newuser',[App\Http\Controllers\AdminController::class,'newuser'])->name('newuser');
    Route::post('/mymail',[App\Http\Controllers\queueController::class,'mymail'])->name('mymail');
    Route::post('/temperature/{id}',[App\Http\Controllers\AdminController::class,'temperature'])->name('temperature');

    Route::get('/pcrcrud/{id}',[App\Http\Controllers\AdminController::class,'pcrcrud'])->name('pcrcrud');
    Route::post('/crearpcr/{id}',[App\Http\Controllers\AdminController::class,'crearpcr'])->name('crearpcr');
    Route::get('/editarpcr/{id}',[App\Http\Controllers\AdminController::class,'editarpcr'])->name('editarpcr');
    Route::post('/savepcr/{id}',[App\Http\Controllers\AdminController::class,'savepcr'])->name('savepcr');
    Route::post('/eliminarpcr/{id}',[App\Http\Controllers\AdminController::class,'eliminarpcr'])->name('eliminarpcr');


    Route::post('/creardepartamento',[App\Http\Controllers\AdminController::class,'creardepartamento'])->name('creardepartamento');
    Route::get('/departamentocrud',[App\Http\Controllers\AdminController::class,'departamentocrud'])->name('departamentocrud');
    Route::get('/activardepartamento/{id}',[App\Http\Controllers\AdminController::class,'activardepartamento'])->name('activardepartamento');
    Route::post('/desactivardepartamento/{id}',[App\Http\Controllers\AdminController::class,'desactivardepartamento'])->name('desactivardepartamento');
    Route::get('/editardepartamento/{id}/edit',[App\Http\Controllers\AdminController::class,'editardepartamento'])->name('editardepartamento');
    Route::post('/savedepartamento/{id}',[App\Http\Controllers\AdminController::class,'savedepartamento'])->name('savedepartamento');

    Route::get('/positionscrud',[App\Http\Controllers\AdminController::class,'positionscrud'])->name('positionscrud');
    Route::post('/crearpositions',[App\Http\Controllers\AdminController::class,'crearpositions'])->name('crearpositions');
    Route::get('/editpositions/{id}',[App\Http\Controllers\AdminController::class,'editpositions'])->name('editpositions');
    Route::post('/saveposition/{id}',[App\Http\Controllers\AdminController::class,'saveposition'])->name('saveposition');


    Route::post('/crearnotificacion',[App\Http\Controllers\AdminController::class,'crearnotificacion'])->name('crearnotificacion');
    Route::get('/notificacioncrud',[App\Http\Controllers\AdminController::class,'notificacioncrud'])->name('notificacioncrud');
    Route::get('/activarnotificacion/{id}',[App\Http\Controllers\AdminController::class,'activarnotificacion'])->name('activarnotificacion');
    Route::post('/desactivarnotificacion/{id}',[App\Http\Controllers\AdminController::class,'desactivarnotificacion'])->name('desactivarnotificacion');
    Route::get('/editarnotificacion/{id}',[App\Http\Controllers\AdminController::class,'editarnotificacion'])->name('editarnotificacion');
    Route::post('/savenotificacion/{id}',[App\Http\Controllers\AdminController::class,'savenotificacion'])->name('savenotificacion');
    
    Route::get('/useredit/{id}',[App\Http\Controllers\AdminController::class,'useredit'])->name('useredit');
    Route::get('/idcard/{id}',[App\Http\Controllers\AdminController::class,'idcard'])->name('idcard');
    Route::post('/acountupdate/{id}',[App\Http\Controllers\AdminController::class,'acountupdate'])->name('account.update');
    Route::get('/historial/{id}/',[App\Http\Controllers\AdminController::class,'historial'])->name('historial');
    Route::get('/historialWR/{id}/',[App\Http\Controllers\AdminController::class,'historialwr'])->name('historialwr');
    Route::get('/searchusers/{valor}/',[App\Http\Controllers\AdminController::class,'searchusers'])->name('searchusers');
    Route::get('/searchcom/{valor}/',[App\Http\Controllers\AdminController::class,'searchcom'])->name('searchcom');
    Route::get('/searchdoctor/{valor}/',[App\Http\Controllers\AdminController::class,'searchdoctor'])->name('searchdoctor');
    Route::get('/searchlab/{valor}/',[App\Http\Controllers\AdminController::class,'searchlab'])->name('searchlab');
    Route::get('/searcheckpoint/{valor}/',[App\Http\Controllers\AdminController::class,'searcheckpoint'])->name('searcheckpoint');
    Route::get('/searchidcard/{valor}/',[App\Http\Controllers\AdminController::class,'searchidcard'])->name('searchidcard');
    Route::get('/searchqueue/{valor}/',[App\Http\Controllers\queueController::class,'searchqueue'])->name('searchqueue');
    Route::post('/notsick/{id}',[App\Http\Controllers\AdminController::class,'notsick'])->name('notsick');

    Route::get('/sendreminder',[App\Http\Controllers\AdminController::class,'sendreminder'])->name('sendreminder');
    Route::post('/enviarcorreos',[App\Http\Controllers\queueController::class,'enviarCorreoTest'])->name('enviarCorreoTest');

    Route::get('/lab',[App\Http\Controllers\AdminController::class,'mainlab'])->name('mainlab');
    Route::get('/listcrew',[App\Http\Controllers\AdminController::class,'testpcrcrud'])->name('testpcrcrud');
    Route::post('/positivepcr/{id}/',[App\Http\Controllers\AdminController::class,'positivepcr'])->name('positivepcr');
    Route::get('/welcomeresend/{id}/',[App\Http\Controllers\AdminController::class,'welcomeresend'])->name('welcomeresend');
    Route::get('/negativepcr',[App\Http\Controllers\AdminController::class,'negativepcr'])->name('negativepcr');
    Route::get('/negativeantg',[App\Http\Controllers\AdminController::class,'negativeantg'])->name('negativeantg');

    Route::get('/importcrew',[App\Http\Controllers\importController::class,'importcrew'])->name('importcrew');
    Route::post('/crewstore',[App\Http\Controllers\importController::class,'store'])->name('crewstore');
    Route::get('/testexport',[App\Http\Controllers\exportController::class,'export'])->name('export');
    
    Route::get('/virtualqueue',[App\Http\Controllers\queueController::class,'scheduletest'])->name('scheduletest');
    Route::get('/antigentest',[App\Http\Controllers\queueController::class,'antigentest'])->name('antigentest');
    Route::get('/crudtest',[App\Http\Controllers\queueController::class,'curdtest'])->name('curdtest');
    Route::post('/selecta/{id}',[App\Http\Controllers\queueController::class,'selecta'])->name('selecta');
    Route::post('/selectb/{id}',[App\Http\Controllers\queueController::class,'selectb'])->name('selectb');
    Route::post('/selectglobal/{id}',[App\Http\Controllers\queueController::class,'selectglobal'])->name('selectglobal');
    Route::get('/userqueue/{id}',[App\Http\Controllers\queueController::class,'userqueue'])->name('userqueue');
    Route::get('/usertested/{id}',[App\Http\Controllers\queueController::class,'usertested'])->name('usertested');
    Route::get('/remindertest',[App\Http\Controllers\queueController::class,'remindertest'])->name('remindertest');
    Route::get('/locationcrud', [App\Http\Controllers\locationController::class, 'index'])->name('location.crud');
    Route::get('/location', [App\Http\Controllers\locationController::class, 'viewlocation'])->name('location.view');
    // Formulario para crear la nueva auditoría (Vista V2)
Route::get('/location2/create', [App\Http\Controllers\locationController2::class, 'create'])->name('locationreport2.create');

// Proceso para guardar los datos en la base de datos
Route::post('/location2/store', [App\Http\Controllers\locationController2::class, 'store'])->name('locationreport2.store');

// Vista del reporte final generado (Diseño de Auditoría)
Route::get('/location2/{id}', [App\Http\Controllers\locationController2::class, 'show'])->name('location2.show');
    Route::post('/locationreport', [App\Http\Controllers\locationController::class, 'store'])->name('locationreport.store');
    Route::get('/location/{id}', [App\Http\Controllers\locationController::class, 'show'])->name('locationreport.show');

    Route::get('/hazardnotification', [App\Http\Controllers\HazardNotificationController::class, 'create'])->name('hazard_notifications.create');
    Route::post('/hazard-notifications', [App\Http\Controllers\HazardNotificationController::class, 'store'])->name('hazard_notifications.store');
    Route::get('/unsafeacts', [App\Http\Controllers\HazardNotificationController::class, 'index'])->name('hazard_notifications.index');
    Route::get('/unsafeact/{id}', [App\Http\Controllers\HazardNotificationController::class, 'show'])->name('hazard_notifications.show');

    // ==========================================
    // MÓDULO: DAILY SAFETY REPORT (ENEG)
    // ==========================================

    // Listado
    Route::get('/dsr-reports', [App\Http\Controllers\DailyReportController::class, 'index'])->name('daily_reports.index');

    // Crear (Debe ir antes que el ID)
    Route::get('/dsr-reports/create', [App\Http\Controllers\DailyReportController::class, 'create'])->name('daily_reports.create');

    // Guardar (POST)
    Route::post('/dsr-reports', [App\Http\Controllers\DailyReportController::class, 'store'])->name('daily_reports.store');

    // Mostrar
    Route::get('/dsr-reports/{id}', [App\Http\Controllers\DailyReportController::class, 'show'])->name('daily_reports.show');

    // Logs y PDF
    Route::post('/dsr-reports/{id}/log', [App\Http\Controllers\DailyReportController::class, 'storeLog'])->name('daily_logs.store');
    Route::get('/dsr-reports/{id}/pdf', [App\Http\Controllers\DailyReportController::class, 'downloadPdf'])->name('daily_reports.pdf');
    // Actualizar el reporte (Cierre de día / Editar)
    Route::post('/dsr-reports/{id}/update', [App\Http\Controllers\DailyReportController::class, 'update'])->name('daily_reports.update');

     // ==========================================
    
    Route::get('/unsafenotifications/create', [App\Http\Controllers\unsafecondNotificationController::class, 'create'])->name('unsafenotifications.create');
    Route::post('/unsafenotifications/store', [App\Http\Controllers\unsafecondNotificationController::class, 'store'])->name('unsafenotifications.store');
    Route::get('/unsafeconds', [App\Http\Controllers\unsafecondNotificationController::class, 'index'])->name('unsafenotifications.index');
    Route::get('/unsafecond/{id}', [App\Http\Controllers\unsafecondNotificationController::class, 'show'])->name('unsafenotifications.show');

    Route::get('/accidents', [App\Http\Controllers\InjuryReportController::class, 'inicial'])->name('injury_reports.index');
    Route::get('/accident', [App\Http\Controllers\InjuryReportController::class, 'create'])->name('injury_reports.create');
    Route::post('/accidentCreate', [App\Http\Controllers\InjuryReportController::class, 'store'])->name('injury_reports.store');
    Route::get('/accident/{id}', [App\Http\Controllers\InjuryReportController::class, 'show'])->name('injury_reports.show');
    Route::get('/users/search', [App\Http\Controllers\InjuryReportController::class, 'searchUsers'])->name('users.search');

    Route::get('/profile',[App\Http\Controllers\PerfilController::class,'indexb'])->name('perfil');

    // Route::get('/consultas',[App\Http\Controllers\AdminController::class,'consultas'])->name('consultas');
    // Route::get('/cargarusuarios/{id}',[App\Http\Controllers\AdminController::class,'cargarusuarios'])->name('cargarusuarios');
    // Route::get('/historial/{id}/',[App\Http\Controllers\AdminController::class,'historial'])->name('historial');

});
Route::get('/nophoto',[App\Http\Controllers\resetController::class,'expCsv'])->name('expCsv');

Route::get('/changepassword',[App\Http\Controllers\PerfilController::class,'changepassword'])->name('changepassword');
Route::post('/updatepassword/{id}',[App\Http\Controllers\PerfilController::class,'updatepassword'])->name('updatepassword');
Route::post('/formularios/registro',[App\Http\Controllers\FormulariosController::class, 'newformulario']);
Route::post('/formularios/medicos',[App\Http\Controllers\FormulariosController::class, 'registrarformulario']);
Route::group(['middleware' => ['auth']], function() 
        {
            Route::get('/profile',[App\Http\Controllers\PerfilController::class,'indexb'])->name('perfil');
        });
Route::post('/update',[App\Http\Controllers\PerfilController::class,'update'] )->name('perfil.update');
Route::get('/pruebachedule',[App\Http\Controllers\PerfilController::class,'pruebachedule'] )->name('pruebachedule');
Route::get('/crop-image', [App\Http\Controllers\cropimageController::class, 'index']);
Route::post('/crop-image-upload', [App\Http\Controllers\cropimageController::class, 'uploadCropImage'])->name('uploadCropImage');;