<?php
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api_encargado'])->group(function () {
    Route::apiResource('/carteros5','CarteroController');
    Route::apiResource('/users5','UserController');
    Route::apiResource('/empresas5','EmpresaController');
    Route::apiResource('/tarifas5','TarifaController');
    Route::apiResource('/solicitudes5','SolicitudeController');
    Route::apiResource('/encargados5','EncargadoController');
    Route::apiResource('/sucursales5','SucursaleController');  //editar agragar eliminar listar apiresource

    Route::put('/solicitudesrecojo5/{solicitude}', 'SolicitudeController@markAsEnCamino');
    Route::put('/solicitudesentrega5/{solicitude}', 'SolicitudeController@markAsEntregado');
    Route::put('/marcarrecogido5/{solicitude}', 'SolicitudeController@marcarRecogido');
    Route::put('/verificarsolicitudes5/{solicitude}', 'SolicitudeController@markAsVerified');
    Route::put('/rechazado5/{solicitude}', 'SolicitudeController@Rechazado');




    Route::get('/Esolicitudes-hoy', 'DashboardEncargadoController@solicitudesHoy');
Route::get('/Esolicitudes-estado-0', 'DashboardEncargadoController@solicitudesEstado0');
Route::get('/Esolicitudes-estado-1', 'DashboardEncargadoController@solicitudesEstado1');
Route::get('/Esolicitudes-estado-2', 'DashboardEncargadoController@solicitudesEstado2');
Route::get('/Esolicitudes-estado-3', 'DashboardEncargadoController@solicitudesEstado3');
Route::get('/Esolicitudes-estado-5', 'DashboardEncargadoController@solicitudesEstado5');
Route::get('/Esolicitudes-estado-1-hoy', 'DashboardEncargadoController@solicitudesEstado1Hoy');
Route::get('/Esolicitudes-estado-2-hoy', 'DashboardEncargadoController@solicitudesEstado2Hoy');
Route::get('/Esolicitudes-estado-3-hoy', 'DashboardEncargadoController@solicitudesEstado3Hoy');
Route::get('/Esolicitudes-estado-5-hoy', 'DashboardEncargadoController@solicitudesEstado5Hoy');



});

Route::post('/login5', 'EncargadoController@login5'); // Login de Cartero


