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




                                          // TODOS LOS PUTS

    Route::put('/solicitudesrecojo2/{solicitude}/', 'SolicitudeController@markAsEnCamino');
    Route::put('/solicitudesentrega2/{solicitude}/', 'SolicitudeController@markAsEntregado');
    Route::put('/marcarrecogido2/{solicitude}/', 'SolicitudeController@marcarRecogido');
    Route::put('/solicitudes2/{solicitude}/destroy', 'SolicitudeController@destroy');



                                          // TODOS LOS GETS


    Route::get('/totalNombreD', 'DashboardController@totalNombreD');
    Route::get('/todas-solicitudes', 'DashboardController@todasSolicitudes');
    Route::get('/solicitudes-hoy', 'DashboardController@solicitudesHoy');
    Route::get('/solicitudes-estado/{estado}', 'DashboardController@solicitudesPorEstado');
    Route::get('/getTarifas2', 'SolicitudeController@getTarifas');
    Route::get('/solicitudes-estado-1', 'DashboardController@solicitudesEstado1');
    Route::get('/solicitudes-estado-2', 'DashboardController@solicitudesEstado2');
    Route::get('/solicitudes-estado-3', 'DashboardController@solicitudesEstado3');
    Route::get('/solicitudes-estado-0', 'DashboardController@solicitudesEstado0');
    

                                          // TODOS LOS POST

    Route::post('solicitudes/generar-guia', 'SolicitudeController@generateGuia');

});
Route::post('/login2', 'SucursaleController@login2');

