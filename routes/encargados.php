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
Route::post('/login', 'UserController@login'); // Login de Cartero

Route::middleware(['auth:api_encargado'])->group(function () {
    Route::apiResource('/carteros5', 'CarteroController')->parameters(['carteros5' => 'cartero']);
    Route::apiResource('/users5', 'UserController')->parameters(['users5' => 'user']);
    Route::apiResource('/empresas5', 'EmpresaController')->parameters(['empresas5' => 'empresa']);
    Route::apiResource('/tarifas5', 'TarifaController')->parameters(['tarifas5' => 'tarifa']);
    Route::apiResource('/solicitudes5', 'SolicitudeController')->parameters(['solicitudes5' => 'solicitude']);
    Route::apiResource('/encargados5', 'EncargadoController')->parameters(['encargados5' => 'encargado']);
    Route::apiResource('/sucursales5', 'SucursaleController')->parameters(['sucursales5' => 'sucursale']);
    Route::apiResource('/eventos5', 'EventoController');

    Route::put('/solicitudesrecojo5/{solicitude}', 'SolicitudeController@markAsEnCamino');
    Route::put('/solicitudesentrega5/{solicitude}', 'SolicitudeController@markAsEntregado');
    Route::put('/solicitudesregional5/{solicitude}', 'SolicitudeController@MandarRegional');
    Route::put('/marcarrecogido5/{solicitude}', 'SolicitudeController@marcarRecogido');
    Route::put('/verificarsolicitudes5/{solicitude}', 'SolicitudeController@markAsVerified');
    Route::put('/rechazado5/{solicitude}', 'SolicitudeController@Rechazado');
    Route::put('/encaminoregional5/{solicitude}', 'SolicitudeController@EnCaminoRegional');
    Route::put('/recibirpaquetes5/{solicitude}', 'SolicitudeController@RecibirPaquete');
    Route::put('/verificarreturn5/{solicitude}', 'SolicitudeController@returnverificar');
    Route::put('/reencaminar5/{solicitude}', 'SolicitudeController@reencaminar');
    Route::put('/reencaminadorecibido5/{solicitude}', 'SolicitudeController@marcarComoReencaminadoRecibido');


    
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


       // ✅ Para tu modal: sucursal simple (id, sigla, nombre)
    Route::get('/sucursales-simple', function () {
        return \App\Models\Sucursale::select('id', 'sigla', 'nombre')
            ->where('estado', 1)
            ->get();
    });

    // ✅ Registro manual
    Route::post('/solicitudes/manual', [SolicitudeController::class, 'storeManual']);
Route::post('solicitudes/ems', 'SolicitudeController@storeEMS');

});

