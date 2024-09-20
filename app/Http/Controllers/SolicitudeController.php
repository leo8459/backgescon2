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

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SolicitudeController extends Controller
{
    protected function optimizeImage($imageData)
    {
        if ($imageData) {
            return (string) Image::make($imageData)
                ->resize(300, null, function ($constraint) {
                    $constraint->aspectRatio();
                })
                ->encode('webp', 50)
                ->encode('data-url');
        }
        return null;
    }
    public function index()
    {
        $solicitudes = Solicitude::with(['carteroRecogida', 'carteroEntrega', 'sucursale', 'tarifa', 'direccion', 'encargado', 'encargadoregional'])->get();
        return response()->json($solicitudes);
    }


    public function store(Request $request)
    {
        // Extraer la imagen en base64 del request
        $imageData = $request->input('imagen'); // Base64 image data

        // Si existe una imagen, optimizarla
        if ($imageData) {
            // Optimizar la imagen usando Intervention Image
            $img = Image::make($imageData)->resize(300, null, function ($constraint) {
                $constraint->aspectRatio();
            })->encode('webp', 50); // Redimensionar y comprimir en formato WebP con calidad 50%

            // Convertir la imagen optimizada de vuelta a base64
            $optimizedImage = (string) $img->encode('data-url'); // Imagen optimizada en base64 en formato WebP
        } else {
            $optimizedImage = null; // O manejarlo como desees si no hay imagen
        }

        // Crear una nueva instancia de Solicitude
        $solicitude = new Solicitude();
        $solicitude->cartero_recogida_id = $request->cartero_recogida_id ?? null;
        $solicitude->cartero_entrega_id = $request->cartero_entrega_id ?? null;
        $solicitude->encargado_id = $request->encargado_id ?? null;
        $solicitude->sucursale_id = $request->sucursale_id;
        $solicitude->tarifa_id = $request->tarifa_id ?? null;
        $solicitude->direccion_id = $request->direccion_id ?? null;

        // Validar si el campo 'guia' tiene un valor, si no, generar la guía
        $solicitude->guia = $request->guia ?: $this->generateGuia($request->sucursale_id, $request->tarifa_id)->getData()->guia;

        $solicitude->peso_o = $request->peso_o;
        $solicitude->peso_v = $request->peso_v;
        $solicitude->remitente = $request->remitente;
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
        $solicitude->fecha_d = $request->fecha_d;
        $solicitude->fecha_recojo_c = $request->fecha_recojo_c;
        $solicitude->fecha_devolucion = $request->fecha_devolucion;
        $solicitude->estado = $request->estado ?? 1;
        $solicitude->observacion = $request->observacion;
        $solicitude->zona_d = $request->zona_d;
        $solicitude->justificacion = $request->justificacion;
        $solicitude->imagen_justificacion = $request->imagen_justificacion;
        $solicitude->encargado_regional_id = $request->encargado_regional_id; // Asignar el cartero de entrega

        // Asignar la imagen optimizada en formato WebP al modelo
        $solicitude->imagen = $optimizedImage;
        $solicitude->imagen_devolucion = $request->imagen_devolucion;
        $solicitude->peso_r = $request->peso_r;

        // Generar el código de barras para la guía
        $generator = new BarcodeGeneratorPNG();
        $barcode = $generator->getBarcode($solicitude->guia, $generator::TYPE_CODE_128);
        $solicitude->codigo_barras = base64_encode($barcode);
        $solicitude->fecha_envio_regional = $request->fecha_envio_regional;

        // Guardar la solicitud en la base de datos
        $solicitude->save();

        // Registrar el evento usando el modelo Evento
        Evento::create([
            'accion' => 'Solicitud',
            'descripcion' => 'Solicitud de Recojo de Paquetes',
            'codigo' => $solicitude->guia,
            'fecha_hora' => now(),
        ]);

        // Cargar la relación de sucursale antes de devolver la respuesta
        $solicitude->load('sucursale');
        $solicitude->load('direccion');
        $solicitude->load('tarifa');

        // Devolver la respuesta con la solicitud guardada, incluyendo la relación cargada
        return $solicitude;
    }


    public function show(Solicitude $solicitude)
    {
        $solicitude->sucursale = $solicitude->sucursale;

        return $solicitude;
    }

    public function update(Request $request, Solicitude $solicitude)
    {
        $solicitude->tarifa_id = $request->tarifa_id ?? null;
        $solicitude->sucursale_id = $request->sucursale_id;
        $solicitude->cartero_recogida_id = $request->cartero_recogida_id ?? null;
        $solicitude->cartero_entrega_id = $request->cartero_entrega_id ?? null;
        $solicitude->direccion_id = $request->direccion_id ?? null;
        $solicitude->encargado_id = $request->encargado_id ?? null;
        $solicitude->guia = $request->guia;
        $solicitude->peso_o = $request->peso_o;
        $solicitude->peso_v = $request->peso_v;
        $solicitude->remitente = $request->remitente;
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
        $solicitude->fecha_d = $request->fecha_d;
        $solicitude->estado = $request->estado;
        $solicitude->observacion = $request->observacion;
        $solicitude->zona_d = $request->zona_d;
        $solicitude->justificacion = $request->justificacion;
        $solicitude->imagen_justificacion = $request->imagen_justificacion;
        $solicitude->imagen = $request->imagen;
        $solicitude->fecha_recojo_c = $request->fecha_recojo_c;
        $solicitude->fecha_devolucion = $request->fecha_devolucion;
        $solicitude->imagen_devolucion = $request->imagen_devolucion;
        $solicitude->fecha_envio_regional = $request->fecha_envio_regional; // Asigna la fecha actual si no se proporciona
        $solicitude->peso_r = $request->peso_r; // Asigna la fecha actual si no se proporciona
        $solicitude->encargado_regional_id = $request->encargado_regional_id; // Asignar el cartero de entrega
        Evento::create([
            'accion' => 'Entregado',
            'descripcion' => 'Envio entregado con exito',
            'codigo' => $solicitude->guia,
            'fecha_hora' => now(),
        ]);




        $solicitude->save();

        return $solicitude;
    }

    public function destroy(Solicitude $solicitude)
    {
        try {
            $solicitude->estado = 0;
            $solicitude->save();
            Evento::create([
                'accion' => 'Cancelar',
                'descripcion' => 'Cancelacion del envio',
                'codigo' => $solicitude->guia,
                'fecha_hora' => now(),
            ]);
            return response()->json(['message' => 'Solicitud actualizada correctamente.'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar la solicitud.', 'error' => $e->getMessage()], 500);
        }
    }
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
    public function obtenerSaldoRestante($sucursale_id)
    {
        $sucursal = Sucursale::find($sucursale_id);

        if (!$sucursal) {
            return response()->json(['error' => 'Sucursal no encontrada.'], 404);
        }

        $saldoRestante = DB::table('sucursales')
            ->leftJoin('solicitudes', 'sucursales.id', '=', 'solicitudes.sucursale_id')
            ->where('sucursales.id', $sucursale_id)
            ->select(DB::raw('sucursales.limite::numeric - COALESCE(SUM(solicitudes.nombre_d::numeric), 0) AS saldo_restante'))
            ->groupBy('sucursales.limite')
            ->first();

        return response()->json([
            'sucursal' => $sucursal->nombre,
            'saldo_restante' => $saldoRestante ? $saldoRestante->saldo_restante : $sucursal->limite,
            'limite_total' => $sucursal->limite // Asegúrate de devolver el límite total
        ]);
    }
    public function obtenerSaldoRestanteTodasSucursales()
    {
        // Obtener todas las sucursales
        $sucursales = Sucursale::all();

        // Crear una colección para almacenar los resultados
        $resultados = [];

        foreach ($sucursales as $sucursal) {
            $saldoRestante = DB::table('sucursales')
                ->leftJoin('solicitudes', 'sucursales.id', '=', 'solicitudes.sucursale_id')
                ->where('sucursales.id', $sucursal->id)
                ->select(DB::raw('sucursales.limite::numeric - COALESCE(SUM(solicitudes.nombre_d::numeric), 0) AS saldo_restante'))
                ->groupBy('sucursales.limite')
                ->first();

            // Calcular el 10% del límite total
            $diezPorCiento = $sucursal->limite * 0.1;

            // Añadir la sucursal a los resultados solo si su saldo restante es menor al 10% del límite
            if ($saldoRestante->saldo_restante < $diezPorCiento) {
                $resultados[] = [
                    'sucursal' => $sucursal->nombre,
                    'saldo_restante' => $saldoRestante ? $saldoRestante->saldo_restante : $sucursal->limite,
                    'limite_total' => $sucursal->limite,
                    'contacto_administrativo' => $sucursal->contacto_administrativo // Añadir el contacto administrativo
                ];
            }
        }

        return response()->json($resultados);
    }
    public function getDirecciones(Request $request)
    {
        $sucursaleId = $request->query('sucursale_id');
        if ($sucursaleId) {
            // Recupera las direcciones asociadas a la sucursal proporcionada
            $direcciones = Direccione::where('sucursale_id', $sucursaleId)->get();
        } else {
            // Maneja el caso donde no se proporcione sucursale_id
            $direcciones = Direccione::all();
        }
        return response()->json($direcciones);
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


    public function markAsEnCamino(Request $request, Solicitude $solicitude)
    {
        $solicitude->estado = 2; // Cambiar estado a "En camino"
        $solicitude->cartero_entrega_id = $request->cartero_entrega_id; // Asignar el cartero logueado
        $solicitude->save();
        Evento::create([
            'accion' => 'Despachado',
            'descripcion' => 'Envio en camino',
            'codigo' => $solicitude->guia,
            'fecha_hora' => now(),
        ]);

        return $solicitude;
    }


    public function markAsEntregado(Request $request, Solicitude $solicitude)
    {
        $solicitude->estado = 2; // Cambiar estado a "En camino"
        $solicitude->cartero_entrega_id = $request->cartero_entrega_id; // Asignar el cartero de entrega
        $solicitude->peso_v = $request->peso_v; // Actualizar el peso
        $solicitude->nombre_d = $request->nombre_d; // Actualizar el peso
        $solicitude->save();
        Evento::create([
            'accion' => 'Despachado',
            'descripcion' => 'Envio en camino',
            'codigo' => $solicitude->guia,
            'fecha_hora' => now(),
        ]);
        return response()->json($solicitude);
    }
    public function markAsVerified(Request $request, Solicitude $solicitude)
    {
        try {
            $solicitude->estado = 4;
            $solicitude->save();
            Evento::create([
                'accion' => 'Verificados',
                'descripcion' => 'Verificar Envios',
                'codigo' => $solicitude->guia,
                'fecha_hora' => now(),
            ]);
            return response()->json($solicitude);

            return response()->json(['message' => 'Solicitud marcada como verificada exitosamente.'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al marcar la solicitud como verificada.', 'exception' => $e->getMessage()], 500);
        }
    }
    public function marcarRecogido(Request $request, $id)
    {
        try {
            // Encuentra la solicitud por ID
            $solicitude = Solicitude::findOrFail($id);

            // Cambia el estado a 5 y guarda el cartero_entrega_id del request
            $solicitude->estado = 5;
            $solicitude->cartero_recogida_id = $request->input('cartero_recogida_id');
            $solicitude->fecha_recojo_c = now();

            // Guarda los cambios
            $solicitude->save();
            // Registrar el evento usando el modelo Evento
            Evento::create([
                'accion' => 'Recojo',
                'descripcion' => 'Recojo de envios',
                'codigo' => $solicitude->guia,
                'fecha_hora' => now(),
            ]);
            return response()->json($solicitude);

            return response()->json(['message' => 'Solicitud marcada como recogida exitosamente.'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al marcar la solicitud como recogida.'], 500);
        }
    }

    


    


    public function returnverificar(Request $request, Solicitude $solicitude)
    {
        try {
            $solicitude->estado = 6;
            $solicitude->save();
            Evento::create([
                'accion' => 'Rechazado',
                'descripcion' => 'Devolver a remitente',
                'codigo' => $solicitude->guia,
                'fecha_hora' => now(),
            ]);
            return response()->json($solicitude);

            return response()->json(['message' => 'Solicitud marcada como verificada exitosamente.'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al marcar la solicitud como verificada.', 'exception' => $e->getMessage()], 500);
        }
    }

    public function Devolucion(Request $request, Solicitude $solicitude)
    {
        try {
            $solicitude->estado = 7;
            $solicitude->fecha_devolucion = $request->fecha_devolucion ?? now(); // Asigna la fecha actual si no se proporciona
            $solicitude->imagen_devolucion = $request->imagen_devolucion;
            $solicitude->firma_o = $request->firma_o;
            $solicitude->cartero_entrega_id = $request->cartero_entrega_id; // Asignar el cartero de entrega

            $solicitude->save();
            Evento::create([
                'accion' => 'Devolucion',
                'descripcion' => 'Entregado a remitente',
                'codigo' => $solicitude->guia,
                'fecha_hora' => now(),
            ]);
            return response()->json($solicitude);

            return response()->json(['message' => 'Solicitud marcada como rechazada exitosamente.'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al marcar la solicitud como rechazada.', 'exception' => $e->getMessage()], 500);
        }
    }

    public function MandarRegional(Request $request, Solicitude $solicitude)
    {
        try {
            $solicitude->estado = 8;
            $solicitude->fecha_envio_regional = $request->fecha_envio_regional ?? now(); // Asigna la fecha actual si no se proporciona
            $solicitude->encargado_id = $request->encargado_id; // Asignar el cartero de entrega
            $solicitude->peso_v = $request->peso_v; // Actualizar el peso
            $solicitude->nombre_d = $request->nombre_d; // Actualizar el peso
            $solicitude->save();
            Evento::create([
                'accion' => 'Despachado',
                'descripcion' => 'Despacho de envio a regional',
                'codigo' => $solicitude->guia,
                'fecha_hora' => now(),
            ]);
            return response()->json($solicitude);

            return response()->json(['message' => 'Solicitud marcada como rechazada exitosamente.'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al marcar la solicitud como rechazada.', 'exception' => $e->getMessage()], 500);
        }
    }
    public function EnCaminoRegional(Request $request, Solicitude $solicitude)
    {
        try {
            $solicitude->estado = 9;
            $solicitude->cartero_entrega_id = $request->cartero_entrega_id; // Asignar el cartero de entrega
            $solicitude->save();
            return response()->json($solicitude);
            Evento::create([
                'accion' => 'EN ENTREGA',
                'descripcion' => 'Envio en camino',
                'codigo' => $solicitude->guia,
                'fecha_hora' => now(),
            ]);
            return response()->json(['message' => 'Solicitud marcada como rechazada exitosamente.'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al marcar la solicitud como rechazada.', 'exception' => $e->getMessage()], 500);
        }
    }


    public function RecibirPaquete(Request $request, Solicitude $solicitude)
    {
        $solicitude->estado = 10;
        $solicitude->encargado_regional_id = $request->encargado_regional_id; // Asignar el cartero de entrega
        $solicitude->peso_r = $request->peso_r; // Actualizar el peso
        $solicitude->nombre_d = $request->nombre_d; // Actualizar el nombre destinatario
        $solicitude->save();
        Evento::create([
            'accion' => 'Recibir',
            'descripcion' => 'Recibir envío en oficina de entrega',
            'codigo' => $solicitude->guia,
            'fecha_hora' => now(),
        ]);
        return response()->json($solicitude);
    }


    public function Rechazado(Request $request, Solicitude $solicitude)
    {
        try {
            // Optimizar la imagen utilizando el método optimizeImage

            // Asignar los valores al modelo
            $solicitude->estado = 11;
            $solicitude->observacion = $request->observacion;
            $solicitude->fecha_d = $request->fecha_d ?? now(); // Asigna la fecha actual si no se proporciona
            $solicitude->imagen = $request->imagen;
            $solicitude->save();
            Evento::create([
                'accion' => 'Rechazado',
                'descripcion' => 'Devolver a remitente',
                'codigo' => $solicitude->guia,
                'fecha_hora' => now(),
            ]);
            return response()->json($solicitude);

            return response()->json(['message' => 'Solicitud marcada como rechazada exitosamente.'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al marcar la solicitud como rechazada.', 'exception' => $e->getMessage()], 500);
        }
    }

    
    


   

   


    public function reencaminar(Request $request, Solicitude $solicitude)
    {
        try {
            // Cambiar el estado a 12
            $solicitude->estado = 12;
            $solicitude->peso_reencaminar = $request->peso_reencaminar; // Actualizar el peso

            // Asignar el valor del campo reencaminamiento desde el request
            $solicitude->reencaminamiento = $request->input('reencaminamiento');

            // Guardar los cambios en la base de datos
            $solicitude->save();
            Evento::create([
                'accion' => 'Reencaminar',
                'descripcion' => 'Despacho de envio a regional',
                'codigo' => $solicitude->guia,
                'fecha_hora' => now(),
            ]);

            // Devolver la respuesta con la solicitud actualizada
            return response()->json(['message' => 'Solicitud reencaminada exitosamente.', 'solicitud' => $solicitude], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al reencaminar la solicitud.', 'exception' => $e->getMessage()], 500);
        }
    }


    public function marcarComoReencaminadoRecibido(Request $request, Solicitude $solicitude)
    {
        try {
            // Cambiar el estado a 13
            $solicitude->estado = 13;
            $solicitude->encargado_regional_id = $request->encargado_regional_id; // Asignar el cartero de entrega
            $solicitude->nombre_d = $request->nombre_d; // Actualizar el nombre destinatario
            $solicitude->save();
            $solicitude->peso_r = $request->peso_r; // Actualizar el peso

            // Guardar cambios en la solicitud
            $solicitude->save();
            Evento::create([
                'accion' => 'Recibir',
                'descripcion' => 'Recibir envío reencaminado en oficinas',
                'codigo' => $solicitude->guia,
                'fecha_hora' => now(),
            ]);
            return response()->json([
                'message' => 'Solicitud actualizada correctamente.',
                'solicitude' => $solicitude
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar la solicitud.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    
}
