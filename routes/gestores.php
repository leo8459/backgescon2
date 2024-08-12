<?php


use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api_gestore'])->group(function () {
    Route::apiResource('/users3', 'UserController')->parameters(['users3' => 'user']);
    Route::apiResource('/empresas3', 'EmpresaController')->parameters(['empresas3' => 'empresa']);  //editar agragar eliminar listar apiresource
    Route::apiResource('/sucursales3', 'SucursaleController')->parameters(['sucursales3' => 'sucursale']);  //editar agragar eliminar listar apiresource
    Route::apiResource('/tarifas3', 'TarifaController')->parameters(['tarifas3' => 'tarifa']);  //editar agragar eliminar listar apiresource
    Route::apiResource('/solicitudes3', 'SolicitudeController')->parameters(['solicitudes3' => 'solicitude']);  //editar agragar eliminar listar apiresource
    Route::apiResource('/encargados3', 'EncargadoController')->parameters(['encargados3' => 'encargado']);  //editar agragar eliminar listar apiresource
    Route::apiResource('/carteros3', 'CarteroController')->parameters(['carteros3' => 'cartero']);  //editar agragar eliminar listar apiresource

    Route::put('/solicitudesrecojo3/{solicitude}/', 'SolicitudeController@markAsEnCamino');
    Route::put('/solicitudesentrega3/{solicitude}/', 'SolicitudeController@markAsEntregado');
    Route::put('/marcarrecogido3/{solicitude}/', 'SolicitudeController@marcarRecogido');



    Route::get('/GsucursalesConContrato', 'DashboardGestorController@sucursalesConContrato');
    Route::get('/GsucursalesSinContrato', 'DashboardGestorController@sucursalesSinContrato');
    Route::get('/GtotalNombreD', 'DashboardGestorController@totalNombreD');
});
Route::post('/login4', 'GestoreController@login');
