<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SolicitudeController;

Route::post('/login', 'UserController@login'); // Login de Cartero

Route::middleware(['auth:api_cartero'])->group(function () {
    Route::apiResource('/carteros', 'CarteroController');
    Route::apiResource('/users', 'UserController');
    Route::apiResource('/empresas', 'EmpresaController');
    Route::apiResource('/tarifas', 'TarifaController');
    Route::apiResource('/encargados', 'EncargadoController');
    Route::apiResource('/eventos', 'EventoController');
    Route::apiResource('/sucursales', 'SucursaleController');
    Route::apiResource('/direcciones', 'DireccioneController')->parameters(['direcciones' => 'direccione']);  //editar agragar eliminar listar apiresource

    Route::put('/solicitudesrecojo/{solicitude}', 'SolicitudeController@markAsEnCamino');
    Route::put('/solicitudesentrega/{solicitude}', 'SolicitudeController@markAsEntregado');
    Route::put('/marcarrecogido/{solicitude}', 'SolicitudeController@marcarRecogido');
    Route::put('/rechazado/{solicitude}', 'SolicitudeController@Rechazado');
    Route::put('/devolucion/{solicitude}', 'SolicitudeController@Devolucion');
    Route::put('/encaminoregional/{solicitude}', 'SolicitudeController@EnCaminoRegional');

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
    Route::post('/solicitudes/generar-codigo-barras', 'SolicitudeController@generateBarcode');
    Route::post('/generar-guia', 'SolicitudeController@generateGuia');
});


// Route::get('/ems/estado/{estado}', [SolicitudeController::class, 'solicitudesPorEstado']);

// Route::get('/ems/estado/{estado}', 'SolicitudeController@solicitudesPorEstado');
Route::apiResource('/solicitudes', 'SolicitudeController');
