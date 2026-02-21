<?php


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CarteroController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\TarifaController;
use App\Http\Controllers\EncargadoController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\SucursaleController;
use App\Http\Controllers\DireccioneController;
use App\Http\Controllers\SolicitudeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardCarteroController;
Route::post('/login', 'UserController@login');
Route::middleware(['auth:api_admin'])->group(function () { 
    Route::get('codigos/totales', 'CodigoController@totales');

    Route::apiResource('/users1', 'UserController')->parameters(['users1' => 'user']);
    Route::apiResource('/empresas1', 'EmpresaController')->parameters(['empresas1' => 'empresa']);  //editar agragar eliminar listar apiresource
    Route::apiResource('/sucursales1', 'SucursaleController')->parameters(['sucursales1' => 'sucursale']);  //editar agragar eliminar listar apiresource
    Route::apiResource('/tarifas1', 'TarifaController')->parameters(['tarifas1' => 'tarifa']);  //editar agragar eliminar listar apiresource
    Route::apiResource('/solicitudes1', 'SolicitudeController')->parameters(['solicitudes1' => 'solicitude']);  //editar agragar eliminar listar apiresource
    Route::apiResource('/encargados1', 'EncargadoController')->parameters(['encargados1' => 'encargado']);  //editar agragar eliminar listar apiresource
    Route::apiResource('/carteros1', 'CarteroController')->parameters(['carteros1' => 'cartero']);  //editar agragar eliminar listar apiresource
    Route::apiResource('/gestore1', 'GestoreController')->parameters(['gestore1' => 'gestore']);  //editar agragar eliminar listar apiresource
    Route::apiResource('/contrato1', 'ContratosController')->parameters(['contrato1' => 'contrato']);  //editar agragar eliminar listar apiresource
    Route::apiResource('/direcciones1', 'DireccioneController')->parameters(['direcciones1' => 'direccione']);  //editar agragar eliminar listar apiresource
    Route::apiResource('/codigos', 'CodigoController');  //editar agragar eliminar listar apiresource
    Route::apiResource('/eventos1', EventoController::class);

    Route::put('/solicitudesrecojo/{solicitude}/', 'SolicitudeController@markAsEnCamino');
    Route::put('/solicitudesentrega/{solicitude}/', 'SolicitudeController@markAsEntregado');
    Route::put('/marcarrecogido/{solicitude}/', 'SolicitudeController@marcarRecogido');
    Route::put('solicitudes/{id}/cambiar-estado', 'SolicitudeController@cambiarEstado');




    Route::get('/Asolicitudes-hoy', 'DashboardAdminController@solicitudesHoy');
    Route::get('/Asolicitudes-estado-0', 'DashboardAdminController@solicitudesEstado0');
    Route::get('/Asolicitudes-estado-1', 'DashboardAdminController@solicitudesEstado1');
    Route::get('/Asolicitudes-estado-2', 'DashboardAdminController@solicitudesEstado2');
    Route::get('/Asolicitudes-estado-3', 'DashboardAdminController@solicitudesEstado3');
    Route::get('/Asolicitudes-estado-5', 'DashboardAdminController@solicitudesEstado5');
    Route::get('/Asolicitudes-estado-1-hoy', 'DashboardAdminController@solicitudesEstado1Hoy');
    Route::get('/Asolicitudes-estado-2-hoy', 'DashboardAdminController@solicitudesEstado2Hoy');
    Route::get('/Asolicitudes-estado-3-hoy', 'DashboardAdminController@solicitudesEstado3Hoy');
    Route::get('/Asolicitudes-estado-5-hoy', 'DashboardAdminController@solicitudesEstado5Hoy');
    Route::get('/Atotal-nombre-d', 'DashboardAdminController@totalNombreD');
    Route::get('/Asucursales-con-contrato', 'DashboardAdminController@sucursalesConContrato');
    Route::get('/Asucursales-sin-contrato', 'DashboardAdminController@sucursalesSinContrato');
    Route::post('codigos/reimprimir', 'CodigoController@reimprimir');


    Route::get('/logs', 'LogController@getLogs')->name('logs.get');
   // ✅ Para tu modal: sucursal simple (id, sigla, nombre)
    Route::get('/sucursales-simple', function () {
        return \App\Models\Sucursale::select('id', 'sigla', 'nombre')
            ->where('estado', 1)
            ->get();
    });

    // ✅ Registro manual
    Route::post('/solicitudes/manual', [SolicitudeController::class, 'storeManual']);
Route::post('solicitudes/ems', 'SolicitudeController@storeEMS');
    // Transporte
    Route::post('/transportes1', [SolicitudeController::class, 'registrarTransporte']);
    Route::get('/transportes1', [SolicitudeController::class, 'listarTransportes']);
    Route::get('/transportes1/{id}', [SolicitudeController::class, 'mostrarTransporte']);
});

