<?php


use Illuminate\Support\Facades\Route;
Route::post('/login', 'UserController@login'); // Login de Cartero

Route::middleware(['auth:api_sucursal'])->group(function () {
    Route::apiResource('/users2', 'UserController')->parameters(['users2' => 'user']);
    Route::apiResource('/empresas2', 'EmpresaController')->parameters(['empresas2' => 'empresa']);  //editar agragar eliminar listar apiresource
    Route::apiResource('/sucursales2', 'SucursaleController')->parameters(['sucursales2' => 'sucursale']);  //editar agragar eliminar listar apiresource
    Route::apiResource('/tarifas2', 'TarifaController')->parameters(['tarifas2' => 'tarifa']);  //editar agragar eliminar listar apiresource
    Route::apiResource('/solicitudes2', 'SolicitudeController')->parameters(['solicitudes2' => 'solicitude']);  //editar agragar eliminar listar apiresource
    Route::apiResource('/encargados2', 'EncargadoController')->parameters(['encargados2' => 'encargado']);  //editar agragar eliminar listar apiresource
    Route::apiResource('/carteros2', 'CarteroController')->parameters(['carteros2' => 'cartero']);  //editar agragar eliminar listar apiresource
    Route::apiResource('/direcciones2', 'DireccioneController')->parameters(['direcciones2' => 'direccione']);  //editar agragar eliminar listar apiresource
    Route::apiResource('/eventos2', 'EventoController');
    Route::get('/sucursales/eventos20/sucursal', 'EventoController@eventosPorSucursal');
    // Route::post('/sucursales/change-password', 'SucursaleController@changePassword');




    // TODOS LOS PUTS

    Route::put('/solicitudesrecojo2/{solicitude}/', 'SolicitudeController@markAsEnCamino');
    Route::put('/solicitudesentrega2/{solicitude}/', 'SolicitudeController@markAsEntregado');
    Route::put('/marcarrecogido2/{solicitude}/', 'SolicitudeController@marcarRecogido');
    Route::put('/solicitudes2/{solicitude}/destroy', 'SolicitudeController@destroy');



    // TODOS LOS GETS
    Route::get('/restantesaldo2', 'SolicitudeController@obtenerSaldoRestanteSucursalActual');

    Route::get('/restante2/{sucursale_id}', 'SolicitudeController@obtenerSaldoRestante');

    Route::get('/totalNombreD', 'DashboardController@totalNombreD');
    Route::get('/todas-solicitudes', 'DashboardController@todasSolicitudes');
    Route::get('/solicitudes-hoy', 'DashboardController@solicitudesHoy');
    Route::get('/solicitudes-estado/{estado}', 'DashboardController@solicitudesPorEstado');
    Route::get('/getTarifas2', 'SolicitudeController@getTarifas');
    Route::get('/solicitudes-estado-1', 'DashboardController@solicitudesEstado1');
    Route::get('/solicitudes-estado-2', 'DashboardController@solicitudesEstado2');
    Route::get('/solicitudes-estado-3', 'DashboardController@solicitudesEstado3');
    Route::get('/solicitudes-estado-0', 'DashboardController@solicitudesEstado0');

    Route::get('/solicitudes/buscar-por-codigo-barras', 'SolicitudeController@buscarPorCodigoBarras');
    Route::get('/direcciones', 'SolicitudeController@getDirecciones');




    // TODOS LOS POST
    Route::post('/solicitudes/generar-codigo-barras', 'SolicitudeController@generateBarcode');
    Route::post('/generar-guia', 'SolicitudeController@generateGuia');
    Route::post('/solicitudes2/carga-masiva', 'SolicitudeController@cargaMasiva');

});
Route::post('/login2', 'SucursaleController@login2');
Route::post('/sucursales/change-password', 'SucursaleController@changePassword');
