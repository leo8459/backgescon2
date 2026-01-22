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

Route::post('/login', [UserController::class, 'login']); // Login de Cartero

Route::middleware(['auth:api_cartero'])->group(function () {

    Route::apiResource('/carteros', CarteroController::class);
    Route::apiResource('/users', UserController::class);
    Route::apiResource('/empresas', EmpresaController::class);
    Route::apiResource('/tarifas', TarifaController::class);
    Route::apiResource('/encargados', EncargadoController::class);
    Route::apiResource('/eventos', EventoController::class);
    Route::apiResource('/sucursales', SucursaleController::class);

    Route::apiResource('/direcciones', DireccioneController::class)
        ->parameters(['direcciones' => 'direccione']);

    // ✅ Solicitudes (PROTEGIDO)
    Route::apiResource('/solicitudes', SolicitudeController::class);

    // Acciones solicitudes
    Route::put('/solicitudesrecojo/{solicitude}', [SolicitudeController::class, 'markAsEnCamino']);
    Route::put('/solicitudesentrega/{solicitude}', [SolicitudeController::class, 'markAsEntregado']);
    Route::put('/marcarrecogido/{solicitude}', [SolicitudeController::class, 'marcarRecogido']);
    Route::put('/rechazado/{solicitude}', [SolicitudeController::class, 'Rechazado']);
    Route::put('/devolucion/{solicitude}', [SolicitudeController::class, 'Devolucion']);
    Route::put('/encaminoregional/{solicitude}', [SolicitudeController::class, 'EnCaminoRegional']);
    Route::put('solicitudes/registrar-contrato/{id}', [SolicitudeController::class, 'registrarContrato']);

    // Tarifas
    Route::get('/getTarifas', [SolicitudeController::class, 'getTarifas']);

    // Dashboards
    Route::get('/CtotalNombreD', [DashboardController::class, 'totalNombreD']);
    Route::get('/Ctodas-solicitudes', [DashboardController::class, 'todasSolicitudes']);
    Route::get('/Csolicitudes-hoy', [DashboardController::class, 'solicitudesHoy']);
    Route::get('/Csolicitudes-estado/{estado}', [DashboardController::class, 'solicitudesPorEstado']);
    

    Route::get('/Csolicitudes-estado-1', [DashboardCarteroController::class, 'solicitudesEstado1']);
    Route::get('/Csolicitudes-estado-2', [DashboardCarteroController::class, 'solicitudesEstado2']);
    Route::get('/Csolicitudes-estado-3', [DashboardCarteroController::class, 'solicitudesEstado3']);
    Route::get('/Csolicitudes-por-cartero-recogida', [DashboardCarteroController::class, 'solicitudesPorCarteroRecogida']);
    Route::get('/Csolicitudes-estado-0', [DashboardCarteroController::class, 'solicitudesEstado0']);
    Route::get('/Csolicitudes-estado-1-hoy', [DashboardCarteroController::class, 'solicitudesEstado1Hoy']);
    Route::get('/Csolicitudes-estado-2-hoy', [DashboardCarteroController::class, 'solicitudesEstado2Hoy']);
    Route::get('/Csolicitudes-estado-3-hoy', [DashboardCarteroController::class, 'solicitudesEstado3Hoy']);
    Route::get('/Csolicitudes-estado-5-hoy', [DashboardCarteroController::class, 'solicitudesEstado5Hoy']);

    Route::post('/solicitudes/generar-codigo-barras', [SolicitudeController::class, 'generateBarcode']);
    Route::post('/generar-guia', [SolicitudeController::class, 'generateGuia']);

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
