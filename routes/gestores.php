<?php


use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api_gestore'])->group(function () {
    Route::apiResource('/users3', 'UserController');
    Route::apiResource('/empresas3', 'EmpresaController');  //editar agragar eliminar listar apiresource
    Route::apiResource('/sucursales3', 'SucursaleController');  //editar agragar eliminar listar apiresource
    Route::apiResource('/tarifas3', 'TarifaController');  //editar agragar eliminar listar apiresource
    Route::apiResource('/solicitudes3', 'SolicitudeController');  //editar agragar eliminar listar apiresource
    Route::apiResource('/encargados3', 'EncargadoController');  //editar agragar eliminar listar apiresource
    Route::apiResource('/carteros3', 'CarteroController');  //editar agragar eliminar listar apiresource
    Route::apiResource('/asignar3', 'DetallecarteroController');  //editar agragar eliminar listar apiresource

    Route::put('/solicitudesrecojo3/{solicitude}/', 'SolicitudeController@markAsEnCamino');
    Route::put('/solicitudesentrega3/{solicitude}/', 'SolicitudeController@markAsEntregado');
    Route::put('/marcarrecogido3/{solicitude}/', 'SolicitudeController@marcarRecogido');



    Route::get('/GsucursalesConContrato', 'DashboardGestorController@sucursalesConContrato');
    Route::get('/GsucursalesSinContrato', 'DashboardGestorController@sucursalesSinContrato');
    Route::get('/GtotalNombreD', 'DashboardGestorController@totalNombreD');
});
Route::post('/login4', 'GestoreController@login');
