<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiController;
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

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
    Route::post('/sucursales/change-password', 'SucursaleController@changePassword');

});
// Route::get('/ems/estado/{estado}', 'SolicitudeController@solicitudesPorEstado');
// Route::get('/ems/estado/{estado}', 'ApiController@solicitudesPorEstado');
Route::get('/ems/estado/{estado}', [ApiController::class, 'solicitudesPorEstado']);
Route::put('/solicitudes/reencaminar', [ApiController::class, 'updateReencaminamiento']);
Route::put('/solicitudes/estado', [ApiController::class, 'updateEstadoSolicitud']);
Route::get('/solicitudes/manifiesto/{manifiesto}', [ApiController::class, 'solicitudesPorManifiesto']);
Route::get('/solicitudes/buscar-por-codigo/{codigo}', [ApiController::class, 'solicitudPorCodigo']);
Route::put('/solicitudes/cambiar-estado', [ApiController::class, 'cambiarEstadoPorGuia']);
Route::put('/solicitud/actualizar-estado', [ApiController::class, 'actualizarEstadoConFirma']);
