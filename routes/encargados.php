<?php

use Illuminate\Support\Facades\Route;
Route::post('/login', 'AdministradorController@login'); // Login de Cartero

Route::middleware(['auth:api_encargado'])->group(function () {
    Route::apiResource('/carteros5', 'CarteroController')->parameters(['carteros5' => 'cartero']);
    Route::apiResource('/users5', 'UserController')->parameters(['users5' => 'user']);
    Route::apiResource('/empresas5', 'EmpresaController')->parameters(['empresas5' => 'empresa']);
    Route::apiResource('/tarifas5', 'TarifaController')->parameters(['tarifas5' => 'tarifa']);
    Route::apiResource('/solicitudes5', 'SolicitudeController')->parameters(['solicitudes5' => 'solicitude']);
    Route::apiResource('/encargados5', 'EncargadoController')->parameters(['encargados5' => 'encargado']);
    Route::apiResource('/sucursales5', 'SucursaleController')->parameters(['sucursales5' => 'sucursale']);

    Route::put('/solicitudesrecojo5/{solicitude}', 'SolicitudeController@markAsEnCamino');
    Route::put('/solicitudesentrega5/{solicitude}', 'SolicitudeController@markAsEntregado');
    Route::put('/solicitudesregional5/{solicitude}', 'SolicitudeController@MandarRegional');
    Route::put('/marcarrecogido5/{solicitude}', 'SolicitudeController@marcarRecogido');
    Route::put('/verificarsolicitudes5/{solicitude}', 'SolicitudeController@markAsVerified');
    Route::put('/rechazado5/{solicitude}', 'SolicitudeController@Rechazado');
    Route::put('/encaminoregional5/{solicitude}', 'SolicitudeController@EnCaminoRegional');
    Route::put('/recibirpaquetes5/{solicitude}', 'SolicitudeController@RecibirPaquete');
    Route::put('/verificarreturn5/{solicitude}', 'SolicitudeController@returnverificar');


    
    Route::get('/getTarifas', 'SolicitudeController@getTarifas');
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

