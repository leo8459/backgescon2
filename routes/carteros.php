<?php
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api_cartero'])->group(function () {
    Route::apiResource('/carteros','CarteroController');
    Route::apiResource('/users','UserController');
    Route::apiResource('/empresas','EmpresaController');
    Route::apiResource('/tarifas','TarifaController');
    Route::apiResource('/solicitudes','SolicitudeController');
    Route::apiResource('/encargados','EncargadoController');
    Route::apiResource('/asignar','DetallecarteroController');

    Route::put('/solicitudesrecojo/{solicitude}', 'SolicitudeController@markAsEnCamino');
    Route::put('/solicitudesentrega/{solicitude}', 'SolicitudeController@markAsEntregado');
    Route::put('/marcarrecogido/{solicitude}', 'SolicitudeController@marcarRecogido');


    Route::get('/getTarifas', 'SolicitudeController@getTarifas');

});

Route::post('/login3', 'CarteroController@login3'); // Login de Cartero


