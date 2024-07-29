<?php
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api_encargado'])->group(function () {
    Route::apiResource('/carteros4','CarteroController');
    Route::apiResource('/users4','UserController');
    Route::apiResource('/empresas4','EmpresaController');
    Route::apiResource('/tarifas4','TarifaController');
    Route::apiResource('/solicitudes4','SolicitudeController');
    Route::apiResource('/encargados4','EncargadoController');
    Route::apiResource('/asignar4','DetallecarteroController');

    Route::put('/solicitudesrecojo4/{solicitude}', 'SolicitudeController@markAsEnCamino');
    Route::put('/solicitudesentrega4/{solicitude}', 'SolicitudeController@markAsEntregado');
    Route::put('/marcarrecogido4/{solicitude}', 'SolicitudeController@marcarRecogido');
});

Route::post('/login4', 'EncargadoController@login4'); // Login de Cartero


