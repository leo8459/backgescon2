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
public function updateEstadoSolicitud(Request $request)
{
    // Validar que se envíe la guía, observación y peso_r
    // $request->validate([
    //     'guia' => 'required|string|max:255',
    //     'estado' => 'required|integer',
    //     'observacion' => 'nullable|string',
    //     'peso_r' => 'nullable|string'
    // ]);

    // Buscar la solicitud por el número de guía
    $solicitud = Solicitude::where('guia', $request->guia)->first();

    if (!$solicitud) {
        return response()->json(['message' => 'Solicitud no encontrada'], 404);
    }

    // Actualizar los campos estado, observacion y peso_r
    $solicitud->estado = $request->estado;
    $solicitud->observacion = $request->observacion;
    $solicitud->peso_r = $request->peso_r;
    $solicitud->save();

    return response()->json([
        'message' => 'Solicitud actualizada correctamente',
        'solicitud' => $solicitud
    ], 200);
}

public function solicitudesPorManifiesto($manifiesto)
{
    // Buscar solicitudes que coincidan con el manifiesto
    $solicitudes = Solicitude::with([
        'carteroRecogida',
        'carteroEntrega',
        'sucursale',
        'tarifa',
        'direccion',
        'encargado',
        'encargadoregional'
    ])
    ->where('manifiesto', $manifiesto)
    ->get();

    // Verificar si hay resultados
    if ($solicitudes->isEmpty()) {
        return response()->json(['message' => 'No se encontraron solicitudes con este manifiesto'], 404);
    }

    return response()->json($solicitudes, 200);
}
public function solicitudPorCodigo($codigo)
{
    // 1. Buscar la solicitud que tenga la "guia" igual al $codigo
    $solicitud = Solicitude::with('direccion')->where('guia', $codigo)->first();

    // 2. Verificar si no existe
    if (! $solicitud) {
        return response()->json(['message' => 'Solicitud no encontrada'], 404);
    }

    // 3. Seleccionar el primer peso disponible: peso_r, luego peso_v, luego peso_o
    $peso = $solicitud->peso_r ?: ($solicitud->peso_v ?: $solicitud->peso_o);

    // 4. Definir un mapeo de códigos a nombre de ciudad
    $ciudadesMap = [
        'LPB' => 'La Paz (LPB)',
        'SRZ' => 'Santa Cruz (SRZ)',
        'CBB' => 'Cochabamba (CBB)',
        'ORU' => 'Oruro (ORU)',
        'PTI' => 'Potosí (PTI)',
        'TJA' => 'Tarija (TJA)',
        'SRE' => 'Sucre (SRE)',
        'BEN' => 'Trinidad (TDD)', // Aquí hay variaciones, ajusta según necesites
        'CIJ' => 'Cobija (CIJ)',
    ];

    // 5. Determinar la ciudad final, usando reencaminamiento o extraer el 2do código del "CODIGO"
    if (! empty($solicitud->reencaminamiento)) {
        // 5A. Si hay reencaminamiento, mapeamos ese código
        $codigoCiudad = strtoupper($solicitud->reencaminamiento);
    } else {
        // 5B. Analizar el "CODIGO" original (guía) para extraer el segundo bloque de 3 caracteres
        //     Ej: 0005LPBCBB0029 -> 
        //         - primer bloque (4 primeros dígitos): 0005 
        //         - segundo bloque (3 siguientes): LPB 
        //         - tercer bloque (3 siguientes): CBB 
        //         El "segundo" podría ser este que inicia en posición 7.
        //     Ajusta según tu formato real.

        // Aquí suponemos que el segundo código es el que va de la posición 7 a la 9 (tres caracteres)
        $codigoCiudad = substr($codigo, 7, 3);
        $codigoCiudad = strtoupper($codigoCiudad);
    }

    // 6. Tomar el nombre de la ciudad desde $ciudadesMap si existe
    $nombreCiudad = $ciudadesMap[$codigoCiudad] ?? 'Desconocida';

    // 7. Devolver los datos solicitados
    return response()->json([
        'CODIGO'       => $solicitud->guia,            // Renombrado a CODIGO en la respuesta
        'destinatario' => $solicitud->destinatario,
        'estado'       => $solicitud->estado,
        'telefono_d'   => $solicitud->telefono_d,
        'peso'         => $peso,
        'ciudad'       => $nombreCiudad,
    ], 200);
}



}
