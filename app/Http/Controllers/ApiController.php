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
        'LPB' => 'LA PAZ (LPB)',
        'SRZ' => 'SANTA CRUZ (SRZ)',
        'CBB' => 'COCHABAMBA (CBB)',
        'ORU' => 'ORURO (ORU)',
        'PTI' => 'POTOSÍ (PTI)',
        'TJA' => 'TARIJA (TJA)',
        'SRE' => 'SUCRE (SRE)',
        'BEN' => 'TRINIDAD (TDD)', // Aquí hay variaciones, ajusta según necesites
        'CIJ' => 'COBIJA (CIJ)',
    ];

    // 5. Determinar la ciudad final, usando reencaminamiento o extraer el 2do código del "CODIGO"
    if (! empty($solicitud->reencaminamiento)) {
        $codigoCiudad = strtoupper($solicitud->reencaminamiento);
    } else {
        $codigoCiudad = strtoupper(substr($codigo, 7, 3));
    }

    // 6. Tomar solo el nombre de la ciudad en mayúsculas
    $nombreCiudad = isset($ciudadesMap[$codigoCiudad]) ? explode(' (', $ciudadesMap[$codigoCiudad])[0] : 'DESCONOCIDA';

    // 7. Devolver los datos solicitados
    return response()->json([
        'CODIGO'       => $solicitud->guia,
        'destinatario' => $solicitud->destinatario,
        'estado'       => $solicitud->estado,
        'telefono_d'   => $solicitud->telefono_d,
        'peso'         => $peso,
        'ciudad'       => $nombreCiudad, // Ahora sin paréntesis
    ], 200);
}


public function cambiarEstadoPorGuia(Request $request)
{
    // 1. Buscar la solicitud por la guía
    $solicitud = Solicitude::where('guia', $request->guia)->first();

    if (!$solicitud) {
        return response()->json(['message' => 'Solicitud no encontrada'], 404);
    }

    // 2. Inicializar carteroId en null. Luego buscamos si usercartero coincide con la columna 'nombre'
    $carteroId = null;

    if (!empty($request->usercartero)) {
        // Buscar en la tabla 'carteros' donde 'nombre' coincida exactamente con lo que llega en usercartero
        $carteroMatch = \App\Models\Cartero::where('nombre', $request->usercartero)->first();

        if ($carteroMatch) {
            $carteroId = $carteroMatch->id;
        }
    }

    // 3. Actualizar la solicitud
    $solicitud->estado              = $request->estado;
    $solicitud->cartero_entrega_id  = $carteroId; 
    $solicitud->entrega_observacion = $request->entrega_observacion;
    $solicitud->usercartero         = $request->usercartero;
    $solicitud->save();

    // 4. Registrar el evento
    $descripcionEstado = match ($request->estado) {
        2 => 'En camino',
        5 => 'Inventario',
        default => 'Actualización de estado a ' . $request->estado,
    };
    
    // Registrar el evento
    Evento::create([
        'accion'        => $request->action,
        'descripcion'   => $descripcionEstado,
        'codigo'        => $solicitud->guia, 
        'cartero_id'    => $carteroId,       // ID del cartero (si lo encontró)
        'fecha_hora'    => now(),
        'observaciones' => $request->entrega_observacion ?? '',
        'usercartero'   => $request->usercartero
    ]);

    return response()->json([
        'message'   => 'Estado actualizado y evento registrado correctamente',
        'solicitud' => $solicitud
    ], 200);
}

public function actualizarEstadoConFirma(Request $request)
{
    try {
        // Agrega 'action' y 'user_id' a la validación si piensas recibirlos
        $request->validate([
            'guia'                => 'required|string|max:255',
            'estado'              => 'required|integer',
            'firma_d'             => 'nullable|string',
            'entrega_observacion' => 'nullable|string|max:255',
            'imagen'              => 'nullable|string',  // Se espera en formato Base64
            'usercartero'         => 'nullable|string|max:255',
            'action'              => 'nullable|string|max:255', 
            'user_id'             => 'nullable|integer',
        ]);

        // 1. Buscar la solicitud por la guía
        $solicitud = Solicitude::where('guia', $request->guia)->first();

        if (!$solicitud) {
            return response()->json(['message' => 'Solicitud no encontrada'], 404);
        }

        // 2. Inicializar carteroId en null y buscar si usercartero coincide con la columna 'nombre'
        $carteroId = null;
        if (!empty($request->usercartero)) {
            $carteroMatch = \App\Models\Cartero::where('nombre', $request->usercartero)->first();
            if ($carteroMatch) {
                $carteroId = $carteroMatch->id;
            }
        }

        // 3. Actualizar la solicitud con los nuevos datos
        $solicitud->estado              = $request->estado;
        $solicitud->firma_d             = $request->firma_d;
        $solicitud->entrega_observacion = $request->entrega_observacion;
        $solicitud->imagen              = $request->imagen;
        $solicitud->cartero_entrega_id  = $carteroId;
        $solicitud->usercartero         = $request->usercartero;
        // Si deseas guardar 'action' y 'user_id' en la tabla solicitudes,
        // debes asegurarte de tener esos campos. De lo contrario, omite estas líneas:
        // $solicitud->action         = $request->action;
        // $solicitud->user_id        = $request->user_id;

        $solicitud->save();

        // 4. Definir la descripción basada en el estado
        $descripcionEstado = match ($request->estado) {
            3 => 'Entregado',
            5 => 'Inventario',
            default => 'Actualización de estado a ' . $request->estado,
        };

        // 5. Registrar el evento
        Evento::create([
            'accion'       => $request->action,  // la acción que se está recibiendo en la request
            'descripcion'  => $descripcionEstado,
            'codigo'       => $solicitud->guia,
            'cartero_id'   => $carteroId,  // ID del cartero (si lo encontró)
            'user_id'      => $request->user_id, // Asegúrate de que en la tabla 'eventos' exista esta columna
            'fecha_hora'   => now(),
            'observaciones'=> $request->entrega_observacion ?? '',
            'usercartero'  => $request->usercartero,
        ]);

        return response()->json([
            'message'   => 'Estado actualizado correctamente',
            'solicitud' => $solicitud
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'error'   => 'Error al actualizar la solicitud',
            'detalle' => $e->getMessage()
        ], 500);
    }
}










}
