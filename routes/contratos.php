<?php

use Illuminate\Support\Facades\Route;
Route::post('/login', 'UserController@login'); // Login de Cartero

Route::middleware(['auth:api_contratos'])->group(function () {
    Route::apiResource('/carteros4', 'CarteroController')->parameters(['carteros4' => 'cartero']);
    Route::apiResource('/users4', 'UserController')->parameters(['users4' => 'user']);
    Route::apiResource('/empresas4', 'EmpresaController')->parameters(['empresas4' => 'empresa']);
    Route::apiResource('/tarifas4', 'TarifaController')->parameters(['tarifas4' => 'tarifa']);
    Route::apiResource('/solicitudes4', 'SolicitudeController')->parameters(['solicitudes4' => 'solicitude']);
    Route::apiResource('/encargados4', 'EncargadoController')->parameters(['encargados4' => 'encargado']);
    Route::apiResource('/sucursales4', 'SucursaleController')->parameters(['sucursales4' => 'sucursale']);
    Route::apiResource('/eventos4', 'EventoController');

    Route::put('/validar4/{tarifa}/inactivar', 'TarifaController@markAsInactive');



    Route::get('/restantessaldo4', 'SolicitudeController@obtenerSaldoRestanteTodasSucursales');

    Route::get('/Esolicitudes-hoy4', 'DashboardEncargadoController@solicitudesHoy');
    Route::get('/Esolicitudes-estado-04', 'DashboardEncargadoController@solicitudesEstado0');
    Route::get('/Esolicitudes-estado-14', 'DashboardEncargadoController@solicitudesEstado1');
    Route::get('/Esolicitudes-estado-24', 'DashboardEncargadoController@solicitudesEstado2');
    Route::get('/Esolicitudes-estado-34', 'DashboardEncargadoController@solicitudesEstado3');
    Route::get('/Esolicitudes-estado-54', 'DashboardEncargadoController@solicitudesEstado5');
    Route::get('/Esolicitudes-estado-1-hoy4', 'DashboardEncargadoController@solicitudesEstado1Hoy');
    Route::get('/Esolicitudes-estado-2-hoy4', 'DashboardEncargadoController@solicitudesEstado2Hoy');
    Route::get('/Esolicitudes-estado-3-hoy4', 'DashboardEncargadoController@solicitudesEstado3Hoy');
    Route::get('/Esolicitudes-estado-5-hoy4', 'DashboardEncargadoController@solicitudesEstado5Hoy');




    // Route::get('/solicitudes/alquileres', 'SolicitudeController@obtenerAlquileres');

});

