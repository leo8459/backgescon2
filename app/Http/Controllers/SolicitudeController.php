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
use App\Models\Evento; // AsegÃºrate de importar el modelo Evento
use Maatwebsite\Excel\Excel as ExcelFormat;
use Illuminate\Support\Facades\Response;
use App\Exports\PlantillaSolicitudesExport;

use App\Imports\SolicitudesImport;
use Maatwebsite\Excel\Facades\Excel;

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

        // Validar si el campo 'guia' tiene un valor, si no, generar la guÃ­a
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

        // Generar el cÃ³digo de barras para la guÃ­a
        $generator = new BarcodeGeneratorPNG();
        $barcode = $generator->getBarcode($solicitude->guia, $generator::TYPE_CODE_128);
        $solicitude->codigo_barras = base64_encode($barcode);
        $solicitude->fecha_envio_regional = $request->fecha_envio_regional;

        // Guardar la solicitud en la base de datos
        $solicitude->save();

        // Registrar el evento usando el modelo Evento
        Evento::create([
            'accion' => 'Solicitud',
            'sucursale_id' => $solicitude->sucursale_id,
            'descripcion' => 'Solicitud de Recojo de Paquetes',
            'codigo' => $solicitude->guia,
            'fecha_hora' => now(),
        ]);

        // Cargar la relaciÃ³n de sucursale antes de devolver la respuesta
        $solicitude->load('sucursale');
        $solicitude->load('direccion');
        $solicitude->load('tarifa');

        // Devolver la respuesta con la solicitud guardada, incluyendo la relaciÃ³n cargada
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
        $solicitude->entrega_observacion = $request->entrega_observacion;
        $solicitude->fecha_envio_regional = $request->fecha_envio_regional; // Asigna la fecha actual si no se proporciona
        $solicitude->peso_r = $request->peso_r; // Asigna la fecha actual si no se proporciona
        $solicitude->encargado_regional_id = $request->encargado_regional_id; // Asignar el cartero de entrega
        Evento::create([
            'accion' => 'Entregado',
            'encargado_id' => $solicitude->encargado_id,  // Asignar encargado
            'cartero_id' => $solicitude->cartero_entrega_id ?? $solicitude->cartero_recogida_id,  // Si no hay cartero_entrega_id, usa cartero_recogida_id
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
                'sucursale_id' => $solicitude->sucursale_id,
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
        ->leftJoin('solicitudes', function ($join) {
            $join->on('sucursales.id', '=', 'solicitudes.sucursale_id')
                 ->whereIn('solicitudes.estado', [3, 4]); // âœ… SOLO estados 3 y 4
        })
        ->where('sucursales.id', $sucursale_id)
        ->select(DB::raw("
            sucursales.limite::numeric
            - COALESCE(SUM(COALESCE(solicitudes.nombre_d,'0')::numeric), 0)
            AS saldo_restante
        "))
        ->groupBy('sucursales.limite')
        ->first();

    return response()->json([
        'sucursal' => $sucursal->nombre,
        'saldo_restante' => $saldoRestante ? $saldoRestante->saldo_restante : $sucursal->limite,
        'limite_total' => $sucursal->limite
    ]);
}

   public function obtenerSaldoRestanteSucursalActual()
{
    $sucursal = Auth::user();

    if (!$sucursal) {
        return response()->json(['error' => 'Sucursal no autenticada.'], 401);
    }

    $limite = (float) $sucursal->limite;

    // âœ… SOLO estados 3 y 4
    $totalSolicitudes = Solicitude::where('sucursale_id', $sucursal->id)
        ->whereIn('estado', [3, 4])
        ->sum(DB::raw("CAST(COALESCE(nombre_d,'0') AS NUMERIC)"));

    $saldoRestante = $limite - $totalSolicitudes;

    return response()->json([
        'sucursal' => $sucursal->nombre,
        'saldo_restante' => $saldoRestante,
        'limite_total' => $limite,
        'total_nombre_d' => $totalSolicitudes
    ]);
}




   public function obtenerSaldoRestanteTodasSucursales()
{
    $sucursales = Sucursale::all();
    $resultados = [];

    foreach ($sucursales as $sucursal) {
        $saldoRestante = DB::table('sucursales')
            ->leftJoin('solicitudes', function ($join) {
                $join->on('sucursales.id', '=', 'solicitudes.sucursale_id')
                     ->whereIn('solicitudes.estado', [3, 4]); // âœ… SOLO 3 y 4
            })
            ->where('sucursales.id', $sucursal->id)
            ->select(DB::raw("
                sucursales.limite::numeric
                - COALESCE(SUM(COALESCE(solicitudes.nombre_d,'0')::numeric), 0)
                AS saldo_restante
            "))
            ->groupBy('sucursales.limite')
            ->first();

        $diezPorCiento = $sucursal->limite * 0.1;

        if (($saldoRestante->saldo_restante ?? $sucursal->limite) < $diezPorCiento) {
            $resultados[] = [
                'sucursal' => $sucursal->nombre,
                'saldo_restante' => $saldoRestante ? $saldoRestante->saldo_restante : $sucursal->limite,
                'limite_total' => $sucursal->limite,
                'contacto_administrativo' => $sucursal->contacto_administrativo
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

        // Primero va n_contrato
        $sucursalCode = preg_replace('/\s+/', '', trim((string) $sucursal->n_contrato));
        if ($sucursalCode === '') {
            $sucursalCode = str_pad($sucursal->codigo_cliente, 2, '0', STR_PAD_LEFT);
        }
        $sucursalOrigin = str_pad($sucursal->origen, 2, '0', STR_PAD_LEFT);

        // Número de sucursal tomado del nombre (ej: "GESTORA CBB 1" => "1")
        $sucursalNumero = '0';
        if (preg_match('/(\d+)\s*$/', trim((string) $sucursal->nombre), $matches)) {
            $sucursalNumero = $matches[1];
        }

        $tarifaCode = str_pad($tarifa->departamento, 2, '0', STR_PAD_LEFT);

        // Obtener el último número secuencial para esa sucursal
        $lastGuia = Solicitude::where('sucursale_id', $sucursaleId)
            ->latest('id')
            ->first();

        // Extraer el número secuencial del último ID de guía, si existe
        $lastNumber = 0;
        if ($lastGuia) {
            $lastNumber = intval(substr($lastGuia->guia, -4));
        }

        // Incrementar el número para la nueva guía
        $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);

        // Generar la nueva guía concatenando todo sin espacios ni separadores
        $newGuia = "{$sucursalCode}{$sucursalOrigin}{$sucursalNumero}{$tarifaCode}{$newNumber}";

        return response()->json(['guia' => $newGuia]);
    }


    public function markAsEnCamino(Request $request, Solicitude $solicitude)
    {
        $solicitude->estado = 2; // Cambiar estado a "En camino"
        $solicitude->cartero_entrega_id = $request->cartero_entrega_id; // Asignar el cartero logueado
        $solicitude->recojo_observacion = $request->recojo_observacion; // Asignar el cartero logueado
        $solicitude->save();
        Evento::create([
            'accion' => 'En camino',
            'cartero_id' => $solicitude->cartero_entrega_id ?? $solicitude->cartero_recogida_id,  // Si no hay cartero_entrega_id, usa cartero_recogida_id
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
            'accion' => 'En camino',
            'cartero_id' => $solicitude->cartero_entrega_id ?? $solicitude->cartero_recogida_id,  // Si no hay cartero_entrega_id, usa cartero_recogida_id
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

            // Utilizar el encargado_id recibido en la solicitud
            $encargadoId = $request->input('encargado_id');

            if (is_null($encargadoId)) {
                return response()->json(['error' => 'No se recibiÃ³ un encargado para esta solicitud.'], 400);
            }

            Evento::create([
                'accion' => 'Verificados',
                'encargado_id' => $encargadoId,
                'descripcion' => 'Verificar Envios',
                'codigo' => $solicitude->guia,
                'fecha_hora' => now(),
            ]);

            return response()->json(['message' => 'Solicitud marcada como verificada exitosamente.', 'solicitude' => $solicitude], 200);
        } catch (\Exception $e) {
            [
                'error' => $e->getMessage(),
                'solicitude_id' => $solicitude->id
            ];
            return response()->json([
                'error' => 'Error al marcar la solicitud como verificada.',
                'exception' => $e->getMessage()
            ], 500);
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
            $solicitude->recojo_observacion = $request->recojo_observacion; // Actualizar el peso
            $solicitude->fecha_recojo_c = now();

            // Guarda los cambios
            $solicitude->save();
            // Registrar el evento usando el modelo Evento
            Evento::create([
                'accion' => 'Recojo',
                'cartero_id' => $solicitude->cartero_entrega_id ?? $solicitude->cartero_recogida_id,  // Si no hay cartero_entrega_id, usa cartero_recogida_id
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
                'encargado_id' => $solicitude->encargado_id ?? $solicitude->encargado_regional_id,  // Si no hay encargado_id, usa encargado_regional_id
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
                'cartero_id' => $solicitude->cartero_entrega_id ?? $solicitude->cartero_recogida_id,  // Si no hay cartero_entrega_id, usa cartero_recogida_id
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
            // Lo demÃ¡s que ya tenÃ­as...
            $solicitude->estado = 8;
            $solicitude->fecha_envio_regional = $request->fecha_envio_regional ?? now();
            $solicitude->encargado_id = $request->encargado_id;
            $solicitude->peso_v = $request->peso_v;
            $solicitude->nombre_d = $request->nombre_d;

            // <-- AquÃ­ guardas en la columna 'reencaminamiento' el departamento.
            if ($request->has('reencaminamiento')) {
                $solicitude->reencaminamiento = $request->reencaminamiento;
            }

            $solicitude->save();

            Evento::create([
                'accion' => 'Transito',
                'encargado_id' => $solicitude->encargado_id ?? $solicitude->encargado_regional_id,
                'descripcion' => 'Despacho de envio a regional',
                'codigo' => $solicitude->guia,
                'fecha_hora' => now(),
            ]);
            return response()->json($solicitude);
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
            Evento::create([
                'accion' => 'En camino',
                'cartero_id' => $solicitude->cartero_entrega_id ?? $solicitude->cartero_recogida_id,  // Si no hay cartero_entrega_id, usa cartero_recogida_id
                'descripcion' => 'Envio en camino',
                'codigo' => $solicitude->guia,
                'fecha_hora' => now(),
            ]);
            return response()->json($solicitude);

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
            'accion' => 'Recibido en oficina',
            'encargado_id' => $solicitude->encargado_id ?? $solicitude->encargado_regional_id,  // Si no hay encargado_id, usa encargado_regional_id
            'descripcion' => 'Recibir envÃ­o en oficina de entrega',
            'codigo' => $solicitude->guia,
            'fecha_hora' => now(),
        ]);
        return response()->json($solicitude);
    }

    public function Rechazado(Request $request, Solicitude $solicitude)
    {
        try {
            // Optimizar la imagen utilizando el mÃ©todo optimizeImage

            // Asignar los valores al modelo
            $solicitude->estado = 11;
            $solicitude->observacion = $request->observacion;
            $solicitude->fecha_d = $request->fecha_d ?? now(); // Asigna la fecha actual si no se proporciona
            $solicitude->imagen = $request->imagen;
            $solicitude->save();
            Evento::create([
                'accion' => 'Rechazado',
                'cartero_id' => $solicitude->cartero_entrega_id ?? $solicitude->cartero_recogida_id,  // Si no hay cartero_entrega_id, usa cartero_recogida_id
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
                'accion' => 'Reencaminado',
                'encargado_id' => $solicitude->encargado_id ?? $solicitude->encargado_regional_id,  // Si no hay encargado_id, usa encargado_regional_id
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
                'accion' => 'Recibido origen',
                'encargado_id' => $solicitude->encargado_id ?? $solicitude->encargado_regional_id,  // Si no hay encargado_id, usa encargado_regional_id
                'descripcion' => 'Recibir envÃ­o reencaminado en oficinas',
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
    public function getSolicitudesPorCategoriasHoy()
    {
        $today = date('Y-m-d');

        // Solicitudes generadas hoy
        $solicitadasHoy = Solicitude::with(['sucursale', 'tarifa'])
            ->whereDate('created_at', $today)
            ->get();

        // Solicitudes recogidas hoy
        $recogidasHoy = Solicitude::with(['sucursale', 'tarifa'])
            ->whereDate('fecha_recojo_c', $today)
            ->get();

        // Solicitudes entregadas hoy
        $entregadasHoy = Solicitude::with(['sucursale', 'tarifa'])
            ->whereNotNull('cartero_entrega_id')
            ->whereDate('updated_at', $today)
            ->get();

        // Retornar las solicitudes organizadas en categorÃ­as
        return response()->json([
            'solicitadas_hoy' => $solicitadasHoy,
            'recogidas_hoy' => $recogidasHoy,
            'entregadas_hoy' => $entregadasHoy,
        ]);
    }



    public function cargaMasiva(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
            'sucursale_id' => 'required|integer|exists:sucursales,id',
        ]);

        $sucursale_id = $request->input('sucursale_id');

        try {
            $import = new SolicitudesImport($sucursale_id);
            Excel::import($import, $request->file('file'));

            // Retornar el nÃºmero de registros creados y las guÃ­as generadas
            return response()->json([
                'message' => 'Solicitudes importadas exitosamente',
                'creados' => $import->getCreadoCount(),
                'guias' => $import->getGuias(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al importar el archivo',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function descargarPlantilla()
    {
        // Obtener la sucursal actualmente autenticada
        $sucursal = Auth::user();

        // Verificar si la sucursal estÃ¡ autenticada
        if (!$sucursal) {
            return response()->json(['error' => 'Sucursal no autenticada.'], 401);
        }

        // Pasar el ID de la sucursal a la clase de exportaciÃ³n
        return Excel::download(new PlantillaSolicitudesExport($sucursal->id), 'plantilla_solicitudes.xlsx');
    }


    public function cambiarEstado(Request $request, $id)
    {
        $request->validate([
            'estado' => 'required|integer|min:1|max:15',
        ]);

        try {
            $solicitude = Solicitude::findOrFail($id);
            $solicitude->estado = $request->estado;
            $solicitude->save();
            // Evento::create([
            //     'accion' => 'Actualizar Estado',
            //     'descripcion' => "Estado cambiado a {$request->estado}",
            //     'codigo' => $solicitude->guia,
            //     'fecha_hora' => now(),
            // ]);
            return response()->json([
                'success' => true,
                'message' => 'Estado actualizado correctamente.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar el estado.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

 public function storeManual(Request $request)
{
$request->validate([
    'sucursale_id'     => 'required|integer|exists:sucursales,id',
    'guia'             => 'required|string|max:255',
    'ciudad'           => 'nullable|string|max:255',
    'peso_v'           => 'nullable|string|max:255',
    'observacion'      => 'nullable|string|max:255',
    'reencaminamiento' => 'nullable|string|max:255', // ✅ NUEVO
]);


    $solicitude = new Solicitude();
    $solicitude->sucursale_id = $request->sucursale_id;
$solicitude->tarifa_id = null; // ✅ SIEMPRE NULL
$solicitude->reencaminamiento = $request->reencaminamiento; // ✅ NUEVO

    // âœ… cartero_recogida_id = usuario logueado
    $solicitude->cartero_recogida_id = Auth::id() ?? $request->cartero_recogida_id;

    // âœ… FECHA DE RECOJO (AHORA)
    $solicitude->fecha_recojo_c = now();

    // =========================
    // âœ… PESOS (MISMO VALOR)
    // =========================
    $solicitude->peso_v = $request->peso_v;
    $solicitude->peso_o = $request->peso_v;

    $solicitude->guia        = $request->guia;
    $solicitude->ciudad      = $request->provincia ?? $request->ciudad;
    $solicitude->observacion = $request->observacion;
    $solicitude->estado      = 5;

    // Defaults para campos NOT NULL
    $solicitude->remitente   = 'N/D';
    $solicitude->telefono    = '0';
    $solicitude->contenido   = 'N/D';
    $solicitude->destinatario = 'N/D';
    $solicitude->telefono_d  = '0';
    $solicitude->direccion_especifica_d = 'N/D';
    $solicitude->zona_d      = 'N/D';

    // CÃ³digo de barras
    $generator = new BarcodeGeneratorPNG();
    $barcode = $generator->getBarcode($solicitude->guia, $generator::TYPE_CODE_128);
    $solicitude->codigo_barras = base64_encode($barcode);

    $solicitude->save();

    Evento::create([
        'accion' => 'Solicitud Manual',
        'cartero_id' => $solicitude->cartero_entrega_id ?? $solicitude->cartero_recogida_id,
        'descripcion' => 'Registro manual de solicitud',
        'codigo' => $solicitude->guia,
        'fecha_hora' => now(),
    ]);

    return response()->json($solicitude, 201);
}

public function storeEMS(Request $request)
{
    $request->validate([
        'guia'        => 'required|string|max:255',
        'peso_v'      => 'nullable|string|max:255',
        'provincia'   => 'nullable|string|max:255',
        'observacion' => 'nullable|string|max:255',
    ]);

    $solicitude = new Solicitude();

    // âœ… NULL explÃ­cito
    $solicitude->sucursale_id = null;
    $solicitude->tarifa_id    = null;

    // Cartero logueado
    $solicitude->cartero_recogida_id = Auth::id() ?? $request->cartero_recogida_id;

    // âœ… FECHA DE RECOJO (AHORA)
    $solicitude->fecha_recojo_c = now();

    // Datos EMS
    $solicitude->tipo_correspondencia = 'EMS';
    $solicitude->guia = $request->guia;
    $solicitude->ciudad = $request->provincia ?? $request->ciudad;

    // âœ… PESOS IGUALES
    $solicitude->peso_v = $request->peso_v;
    $solicitude->peso_o = $request->peso_v;

    $solicitude->observacion = $request->observacion;
    $solicitude->estado = 5;

    // Defaults NOT NULL
    $solicitude->remitente   = 'N/D';
    $solicitude->telefono    = '0';
    $solicitude->contenido   = 'N/D';
    $solicitude->destinatario = 'N/D';
    $solicitude->telefono_d  = '0';
    $solicitude->direccion_especifica_d = 'N/D';
    $solicitude->zona_d      = 'N/D';

    // CÃ³digo de barras
    $generator = new \Picqer\Barcode\BarcodeGeneratorPNG();
    $barcode = $generator->getBarcode($solicitude->guia, $generator::TYPE_CODE_128);
    $solicitude->codigo_barras = base64_encode($barcode);

    $solicitude->save();

    Evento::create([
        'accion' => 'Envio EMS',
        'cartero_id' => $solicitude->cartero_recogida_id,
        'descripcion' => 'Registro EMS global',
        'codigo' => $solicitude->guia,
        'fecha_hora' => now(),
    ]);

    return response()->json($solicitude, 201);
}




}

