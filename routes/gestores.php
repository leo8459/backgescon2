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

Route::middleware(['auth:api_gestore'])->group(function () {
    Route::apiResource('/users3', 'UserController')->parameters(['users3' => 'user']);
    Route::apiResource('/empresas3', 'EmpresaController')->parameters(['empresas3' => 'empresa']);  //editar agragar eliminar listar apiresource
    Route::apiResource('/sucursales3', 'SucursaleController')->parameters(['sucursales3' => 'sucursale']);  //editar agragar eliminar listar apiresource
    Route::apiResource('/tarifas3', 'TarifaController')->parameters(['tarifas3' => 'tarifa']);  //editar agragar eliminar listar apiresource
    Route::apiResource('/solicitudes3', 'SolicitudeController')->parameters(['solicitudes3' => 'solicitude']);  //editar agragar eliminar listar apiresource
    Route::apiResource('/encargados3', 'EncargadoController')->parameters(['encargados3' => 'encargado']);  //editar agragar eliminar listar apiresource
    Route::apiResource('/carteros3', 'CarteroController')->parameters(['carteros3' => 'cartero']);  //editar agragar eliminar listar apiresource
    Route::apiResource('/direcciones3', 'DireccioneController')->parameters(['direcciones3' => 'direccione']);  //editar agragar eliminar listar apiresource

    Route::put('/solicitudesrecojo3/{solicitude}/', 'SolicitudeController@markAsEnCamino');
    Route::put('/solicitudesentrega3/{solicitude}/', 'SolicitudeController@markAsEntregado');
    Route::put('/marcarrecogido3/{solicitude}/', 'SolicitudeController@marcarRecogido');
    Route::put('/validar3/{tarifa}/inactivar', 'TarifaController@markAsInactive');


    Route::get('/restantessaldo3', 'SolicitudeController@obtenerSaldoRestanteTodasSucursales');
    Route::get('/GsucursalesConContrato', 'DashboardGestorController@sucursalesConContrato');
    Route::get('/GsucursalesSinContrato', 'DashboardGestorController@sucursalesSinContrato');
    Route::get('/GtotalNombreD', 'DashboardGestorController@totalNombreD');

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
