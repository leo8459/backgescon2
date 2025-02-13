<?php

namespace App\Http\Controllers;

use App\Models\DetalleSolicitude;
use App\Models\Solicitude;
use App\Models\Sucursale;
use App\Models\Encargado;
use App\Models\Tarifa;
use App\Models\Direccione;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\DB;
use App\Models\Evento; // Asegúrate de importar el modelo Evento
use Maatwebsite\Excel\Excel as ExcelFormat;
use Illuminate\Support\Facades\Response;
use App\Exports\PlantillaSolicitudesExport;

use App\Imports\SolicitudesImport;
use Maatwebsite\Excel\Facades\Excel;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;


class ApiController extends Controller
{
// ruta: GET /api/solicitudes/estado/{estado}
public function solicitudesPorEstado($estado)
{
    $solicitudes = Solicitude::with([
        'carteroRecogida',
        'carteroEntrega',
        'sucursale',
        'tarifa',
        'direccion',
        'encargado',
        'encargadoregional'
    ])
    ->where('estado', $estado)
    ->get();

    return response()->json($solicitudes);
}
public function updateReencaminamiento(Request $request)
{
    // Validar que se envíe la guía y el reencaminamiento
    // $request->validate([
    //     'guia' => 'required|string|max:255',
    //     'reencaminamiento' => 'required|string|max:255',
    // ]);

    // Buscar la solicitud por el número de guía
    $solicitud = Solicitude::where('guia', $request->guia)->first();

    if (!$solicitud) {
        return response()->json(['message' => 'Solicitud no encontrada'], 404);
    }

   

    // Actualizar reencaminamiento, estado y manifiesto
    $solicitud->reencaminamiento = $request->reencaminamiento;
    $solicitud->estado = 8;
    $solicitud->manifiesto = $request->manifiesto;
    $solicitud->save();

    return response()->json([
        'message' => 'Solicitud actualizada correctamente',
        'solicitud' => $solicitud
    ], 200);
}

}
