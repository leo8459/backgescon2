<?php
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api_encargado'])->group(function () {
    Route::apiResource('/carteros5','CarteroController');
    Route::apiResource('/users5','UserController');
    Route::apiResource('/empresas5','EmpresaController');
    Route::apiResource('/tarifas5','TarifaController');
    Route::apiResource('/solicitudes5','SolicitudeController');
    Route::apiResource('/encargados5','EncargadoController');
    Route::apiResource('/asignar5','DetallecarteroController');
    Route::apiResource('/sucursales5','SucursaleController');  //editar agragar eliminar listar apiresource

    Route::put('/solicitudesrecojo5/{solicitude}', 'SolicitudeController@markAsEnCamino');
    Route::put('/solicitudesentrega5/{solicitude}', 'SolicitudeController@markAsEntregado');
    Route::put('/marcarrecogido5/{solicitude}', 'SolicitudeController@marcarRecogido');
});

Route::post('/login5', 'EncargadoController@login5'); // Login de Cartero


