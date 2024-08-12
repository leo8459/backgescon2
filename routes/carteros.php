<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api_cartero'])->group(function () {
    Route::apiResource('/carteros', 'CarteroController');
    Route::apiResource('/users', 'UserController');
    Route::apiResource('/empresas', 'EmpresaController');
    Route::apiResource('/tarifas', 'TarifaController');
    Route::apiResource('/solicitudes', 'SolicitudeController');
    Route::apiResource('/encargados', 'EncargadoController');

    Route::put('/solicitudesrecojo/{solicitude}', 'SolicitudeController@markAsEnCamino');
    Route::put('/solicitudesentrega/{solicitude}', 'SolicitudeController@markAsEntregado');
    Route::put('/marcarrecogido/{solicitude}', 'SolicitudeController@marcarRecogido');


    Route::get('/getTarifas', 'SolicitudeController@getTarifas');
    Route::get('/CtotalNombreD', 'DashboardController@totalNombreD');
    Route::get('/Ctodas-solicitudes', 'DashboardController@todasSolicitudes');
    Route::get('/Csolicitudes-hoy', 'DashboardController@solicitudesHoy');
    Route::get('/Csolicitudes-estado/{estado}', 'DashboardController@solicitudesPorEstado');
    Route::get('/Csolicitudes-estado-1', 'DashboardCarteroController@solicitudesEstado1');
    Route::get('/Csolicitudes-estado-2', 'DashboardCarteroController@solicitudesEstado2');
    Route::get('/Csolicitudes-estado-3', 'DashboardCarteroController@solicitudesEstado3');
    Route::get('/Csolicitudes-por-cartero-recogida', 'DashboardCarteroController@solicitudesPorCarteroRecogida');
    Route::get('/Csolicitudes-estado-0', 'DashboardCarteroController@solicitudesEstado0');
    Route::get('/Csolicitudes-estado-1-hoy', 'DashboardCarteroController@solicitudesEstado1Hoy');
    Route::get('/Csolicitudes-estado-2-hoy', 'DashboardCarteroController@solicitudesEstado2Hoy');
    Route::get('/Csolicitudes-estado-3-hoy', 'DashboardCarteroController@solicitudesEstado3Hoy');
    Route::get('/Csolicitudes-estado-5-hoy', 'DashboardCarteroController@solicitudesEstado5Hoy');
});

Route::post('/login3', 'CarteroController@login3'); // Login de Cartero


