<?php


use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api_sucursal'])->group(function () {
    Route::apiResource('/users2','UserController');
    Route::apiResource('/empresas2','EmpresaController');  //editar agragar eliminar listar apiresource
    Route::apiResource('/sucursales2','SucursaleController');  //editar agragar eliminar listar apiresource
    Route::apiResource('/tarifas2','TarifaController');  //editar agragar eliminar listar apiresource
    Route::apiResource('/solicitudes2','SolicitudeController');  //editar agragar eliminar listar apiresource
    Route::apiResource('/encargados2','EncargadoController');  //editar agragar eliminar listar apiresource
    Route::apiResource('/carteros2','CarteroController');  //editar agragar eliminar listar apiresource
    Route::apiResource('/asignar2','DetallecarteroController');  //editar agragar eliminar listar apiresource

    Route::put('/solicitudesrecojo2/{solicitude}/', 'SolicitudeController@markAsEnCamino');
    Route::put('/solicitudesentrega2/{solicitude}/', 'SolicitudeController@markAsEntregado');
    Route::put('/marcarrecogido2/{solicitude}/', 'SolicitudeController@marcarRecogido');
});
Route::post('/login2', 'SucursaleController@login2');
