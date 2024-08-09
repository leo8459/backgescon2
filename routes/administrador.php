<?php


use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api_admin'])->group(function () {
    Route::apiResource('/users1', 'UserController');
    Route::apiResource('/empresas1', 'EmpresaController');  //editar agragar eliminar listar apiresource
    Route::apiResource('/sucursales1', 'SucursaleController');  //editar agragar eliminar listar apiresource
    Route::apiResource('/tarifas1', 'TarifaController');  //editar agragar eliminar listar apiresource
    Route::apiResource('/solicitudes1', 'SolicitudeController');  //editar agragar eliminar listar apiresource
    Route::apiResource('/encargados1', 'EncargadoController');  //editar agragar eliminar listar apiresource
    Route::apiResource('/carteros1', 'CarteroController');  //editar agragar eliminar listar apiresource
    Route::apiResource('/asignar1', 'DetallecarteroController');  //editar agragar eliminar listar apiresource
    Route::apiResource('/gestore1', 'GestoreController');  //editar agragar eliminar listar apiresource

    Route::put('/solicitudesrecojo/{solicitude}/', 'SolicitudeController@markAsEnCamino');
    Route::put('/solicitudesentrega/{solicitude}/', 'SolicitudeController@markAsEntregado');
    Route::put('/marcarrecogido/{solicitude}/', 'SolicitudeController@marcarRecogido');




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
});
Route::post('/login', 'UserController@login');
