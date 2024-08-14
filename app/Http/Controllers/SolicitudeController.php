<?php

namespace App\Http\Controllers;

use App\Models\DetalleSolicitude;
use App\Models\Solicitude;
use App\Models\Sucursale;
use App\Models\Tarifa;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Intervention\Image\Facades\Image;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SolicitudeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function index()
    {
        $solicitudes = Solicitude::with(['carteroRecogida', 'carteroEntrega', 'sucursale', 'tarifa'])->get();
        return response()->json($solicitudes);
    }



    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
    // Extraer la imagen en base64 del request
    $imageData = $request->input('imagen'); // Base64 image data

    // Si existe una imagen, optimizarla
    if ($imageData) {
        // Optimizar la imagen usando Intervention Image
        $img = Image::make($imageData)->resize(300, null, function ($constraint) {
            $constraint->aspectRatio();
        })->encode('webp', 50); // Redimensionar y comprimir en formato WebP con calidad 80%

        // Convertir la imagen optimizada de vuelta a base64
        $optimizedImage = (string) $img->encode('data-url'); // Imagen optimizada en base64 en formato WebP
    } else {
        $optimizedImage = null; // O manejarlo como desees si no hay imagen
    }

    // Crear una nueva instancia de Solicitude
    $solicitude = new Solicitude();
    $solicitude->cartero_recogida_id = $request->cartero_recogida_id ?? null;
    $solicitude->cartero_entrega_id = $request->cartero_entrega_id ?? null;
    $solicitude->sucursale_id = $request->sucursale_id;
    $solicitude->tarifa_id = $request->tarifa_id ?? null;
    $solicitude->guia = $this->generateGuia($request->sucursale_id, $request->tarifa_id)->getData()->guia;
    $solicitude->peso_o = $request->peso_o;
    $solicitude->peso_v = $request->peso_v;
    $solicitude->remitente = $request->remitente;
    $solicitude->direccion = $request->direccion;
    $solicitude->direccion_especifica = $request->direccion_especifica;
    $solicitude->telefono = $request->telefono;
    $solicitude->contenido = $request->contenido;
    $solicitude->fecha = $request->fecha;
    $solicitude->firma_o = $request->firma_o;
    $solicitude->destinatario = $request->destinatario;
    $solicitude->telefono_d = $request->telefono_d;
    $solicitude->direccion_d = $request->direccion_d;
    $solicitude->direccion_especifica_d = $request->direccion_especifica_d;
    $solicitude->ciudad = $request->ciudad;
    $solicitude->firma_d = $request->firma_d;
    $solicitude->nombre_d = $request->nombre_d;
    $solicitude->ci_d = $request->ci_d;
    $solicitude->fecha_d = $request->fecha_d;
    $solicitude->estado = $request->estado ?? 1;
    $solicitude->observacion = $request->observacion;
    $solicitude->zona_r = $request->zona_r;
    $solicitude->zona_d = $request->zona_d;

    // Asignar la imagen optimizada en formato WebP al modelo
    $solicitude->imagen = $optimizedImage;

    // Generar el código de barras para la guía
    $generator = new BarcodeGeneratorPNG();
    $barcode = $generator->getBarcode($solicitude->guia, $generator::TYPE_CODE_128);
    $solicitude->codigo_barras = base64_encode($barcode);

    // Guardar la solicitud en la base de datos
    $solicitude->save();

    // Devolver la respuesta con la solicitud guardada
    return $solicitude;




    }









    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Solicitude  $solicitude
     * @return \Illuminate\Http\Response
     */
    public function show(Solicitude $solicitude)
    {
        $solicitude->sucursale = $solicitude->sucursale;

        return $solicitude;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Solicitude  $solicitude
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Solicitude $solicitude)
    {
        $solicitude->tarifa_id = $request->tarifa_id ?? null;
        $solicitude->sucursale_id = $request->sucursale_id;
        $solicitude->cartero_recogida_id = $request->cartero_recogida_id ?? null;
        $solicitude->cartero_entrega_id = $request->cartero_entrega_id ?? null;

        $solicitude->guia = $request->guia;
        $solicitude->peso_o = $request->peso_o;
        $solicitude->peso_v = $request->peso_v;
        $solicitude->remitente = $request->remitente;
        $solicitude->direccion = $request->direccion;
        $solicitude->direccion_especifica = $request->direccion_especifica;
        $solicitude->telefono = $request->telefono;
        $solicitude->contenido = $request->contenido;
        $solicitude->fecha = $request->fecha;
        $solicitude->firma_o = $request->firma_o;
        $solicitude->destinatario = $request->destinatario;
        $solicitude->telefono_d = $request->telefono_d;
        $solicitude->direccion_d = $request->direccion_d;
        $solicitude->direccion_especifica_d = $request->direccion_especifica_d;
        $solicitude->ciudad = $request->ciudad;
        $solicitude->firma_d = $request->firma_d;
        $solicitude->nombre_d = $request->nombre_d;
        $solicitude->ci_d = $request->ci_d;
        $solicitude->fecha_d = $request->fecha_d;
        $solicitude->estado = $request->estado;
        $solicitude->observacion = $request->observacion;
        $solicitude->zona_r = $request->zona_r;
        $solicitude->zona_d = $request->zona_d;

        $solicitude->imagen = $request->imagen;




        $solicitude->save();

        return $solicitude;
    }



    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Solicitude  $solicitude
     * @return \Illuminate\Http\Response
     */
    public function destroy(Solicitude $solicitude)
    {
        try {
            $solicitude->estado = 0;
            $solicitude->save();
            return response()->json(['message' => 'Solicitud actualizada correctamente.'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar la solicitud.', 'error' => $e->getMessage()], 500);
        }
    }

    public function markAsEnCamino(Request $request, Solicitude $solicitude)
    {
        $solicitude->estado = 2; // Cambiar estado a "En camino"
        $solicitude->cartero_entrega_id = $request->cartero_entrega_id; // Asignar el cartero logueado
        $solicitude->save();


        return $solicitude;
    }
    public function markAsEntregado(Request $request, Solicitude $solicitude)
    {
        $solicitude->estado = 2; // Cambiar estado a "En camino"
        $solicitude->cartero_entrega_id = $request->cartero_entrega_id; // Asignar el cartero de entrega
        $solicitude->peso_v = $request->peso_v; // Actualizar el peso
        $solicitude->nombre_d = $request->nombre_d; // Actualizar el peso
        $solicitude->save();

        return response()->json($solicitude);
    }
    public function marcarRecogido(Request $request, $id)
    {
        try {
            // Encuentra la solicitud por ID
            $solicitude = Solicitude::findOrFail($id);

            // Cambia el estado a 5 y guarda el cartero_entrega_id del request
            $solicitude->estado = 5;
            $solicitude->cartero_recogida_id = $request->input('cartero_recogida_id');

            // Guarda los cambios
            $solicitude->save();

            return response()->json(['message' => 'Solicitud marcada como recogida exitosamente.'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al marcar la solicitud como recogida.'], 500);
        }
    }
    // En tu controlador
    public function getTarifas(Request $request)
    {
        $sucursaleId = $request->query('sucursale_id');
        if ($sucursaleId) {
            $tarifas = Tarifa::where('sucursale_id', $sucursaleId)->get();
        } else {
            $tarifas = Tarifa::all(); // O manejar el caso donde no se proporcione sucursale_id
        }
        return response()->json($tarifas);
    }

    public function generateGuia($sucursaleId, $tarifaId)
{
    // Recuperar la sucursale y tarifa
    $sucursal = Sucursale::find($sucursaleId);
    $tarifa = Tarifa::find($tarifaId);

    // Validar si ambos datos existen
    if (!$sucursal || !$tarifa) {
        return response()->json(['error' => 'Sucursal o tarifa no encontrados.'], 404);
    }

    // Obtener el código de sucursal y tarifa
    $sucursalCode = str_pad($sucursal->codigo_cliente, 2, '0', STR_PAD_LEFT);
    $sucursalOrigin = str_pad($sucursal->origen, 2, '0', STR_PAD_LEFT); // Suponiendo que 'origen' es un número
    $tarifaCode = str_pad($tarifa->departamento, 2, '0', STR_PAD_LEFT);

    // Obtener el último número secuencial para esa sucursal
    $lastGuia = Solicitude::where('sucursale_id', $sucursaleId)
        ->latest('id')
        ->first();

    // Extraer el número secuencial del último ID de guía, si existe
    $lastNumber = 0;
    if ($lastGuia) {
        // Extraer los últimos 4 dígitos de la guía (asumiendo que estos representan el número secuencial)
        $lastNumber = intval(substr($lastGuia->guia, -4));
    }

    // Incrementar el número para la nueva guía
    $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);

    // Generar la nueva guía concatenando todo sin espacios ni separadores
    $newGuia = "{$sucursalCode}{$sucursalOrigin}{$tarifaCode}{$newNumber}";

    return response()->json(['guia' => $newGuia]);
}

    
    public function markAsVerified(Request $request, Solicitude $solicitude)
    {
        try {
            $solicitude->estado = 4;
            $solicitude->save();

            return response()->json(['message' => 'Solicitud marcada como verificada exitosamente.'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al marcar la solicitud como verificada.', 'exception' => $e->getMessage()], 500);
        }
    }

    public function Rechazado(Request $request, Solicitude $solicitude)
{
    try {
        $solicitude->estado = 6;
        $solicitude->observacion = $request->observacion;
        $solicitude->fecha_d = $request->fecha_d ?? now(); // Asigna la fecha actual si no se proporciona
        $solicitude->save();

        return response()->json(['message' => 'Solicitud marcada como rechazada exitosamente.'], 200);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Error al marcar la solicitud como rechazada.', 'exception' => $e->getMessage()], 500);
    }
}

}
