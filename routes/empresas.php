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

Route::middleware(['auth:api_empresas'])->group(function () {
    Route::apiResource('/users7', 'UserController')->parameters(['users7' => 'user']);
    Route::apiResource('/empresas7', 'EmpresaController')->parameters(['empresas7' => 'empresa']);  //editar agragar eliminar listar apiresource
    Route::apiResource('/sucursales7', 'SucursaleController')->parameters(['sucursales7' => 'sucursale']);  //editar agragar eliminar listar apiresource
    Route::apiResource('/tarifas7', 'TarifaController')->parameters(['tarifas7' => 'tarifa']);  //editar agragar eliminar listar apiresource
    Route::apiResource('/solicitudes7', 'SolicitudeController')->parameters(['solicitudes7' => 'solicitude']);  //editar agragar eliminar listar apiresource
    Route::apiResource('/encargados7', 'EncargadoController')->parameters(['encargados7' => 'encargado']);  //editar agragar eliminar listar apiresource
    Route::apiResource('/carteros7', 'CarteroController')->parameters(['carteros7' => 'cartero']);  //editar agragar eliminar listar apiresource
    Route::apiResource('/direcciones7', 'DireccioneController')->parameters(['direcciones7' => 'direccione']);  //editar agragar eliminar listar apiresource




    // TODOS LOS PUTS

    Route::put('/solicitudesrecojo6/{solicitude}/', 'SolicitudeController@markAsEnCamino');
    Route::put('/solicitudesentrega6/{solicitude}/', 'SolicitudeController@markAsEntregado');
    Route::put('/marcarrecogido6/{solicitude}/', 'SolicitudeController@marcarRecogido');
    Route::put('/solicitudes6/{solicitude}/destroy', 'SolicitudeController@destroy');



    // TODOS LOS GETS
    Route::get('/cantidadsucursales', 'DashboardEmpresaController@getSucursalesByEmpresa');


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


   // ✅ Para tu modal: sucursal simple (id, sigla, nombre)
    Route::get('/sucursales-simple', function () {
        return \App\Models\Sucursale::select('id', 'sigla', 'nombre')
            ->where('estado', 1)
            ->get();
    });

    // ✅ Registro manual
    Route::post('/solicitudes/manual', [SolicitudeController::class, 'storeManual']);
Route::post('solicitudes/ems', 'SolicitudeController@storeEMS');


    // TODOS LOS POST
    Route::post('/solicitudes/generar-codigo-barras', 'SolicitudeController@generateBarcode');
    Route::post('/generar-guia', 'SolicitudeController@generateGuia');
});
// Route::post('/login2', 'SucursaleController@login2');
