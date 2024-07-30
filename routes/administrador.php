<?php


use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api_admin'])->group(function () {
    Route::apiResource('/users1','UserController');
    Route::apiResource('/empresas1','EmpresaController');  //editar agragar eliminar listar apiresource
    Route::apiResource('/sucursales1','SucursaleController');  //editar agragar eliminar listar apiresource
    Route::apiResource('/tarifas1','TarifaController');  //editar agragar eliminar listar apiresource
    Route::apiResource('/solicitudes1','SolicitudeController');  //editar agragar eliminar listar apiresource
    Route::apiResource('/encargados1','EncargadoController');  //editar agragar eliminar listar apiresource
    Route::apiResource('/carteros1','CarteroController');  //editar agragar eliminar listar apiresource
    Route::apiResource('/asignar1','DetallecarteroController');  //editar agragar eliminar listar apiresource
    Route::apiResource('/gestore1','GestoreController');  //editar agragar eliminar listar apiresource

    Route::put('/solicitudesrecojo/{solicitude}/', 'SolicitudeController@markAsEnCamino');
    Route::put('/solicitudesentrega/{solicitude}/', 'SolicitudeController@markAsEntregado');
    Route::put('/marcarrecogido/{solicitude}/', 'SolicitudeController@marcarRecogido');
});
Route::post('/login', 'UserController@login');
