<?php

namespace App\Http\Controllers;

use App\Models\DetalleSolicitude;
use App\Models\Solicitude;
use App\Models\Sucursale;
use App\Models\Encargado;
use App\Models\Tarifa;
use App\Models\Direccione;
use App\Models\Transporte;
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
    protected function logImagenRequest(Request $request, string $context, ?string $guia = null): void
    {
        $img = $request->input('imagen');
        Log::info("{$context} payload imagen", [
            'guia' => $guia ?? $request->input('guia'),
            'has_imagen_key' => $request->has('imagen'),
            'imagen_is_string' => is_string($img),
            'imagen_length' => is_string($img) ? strlen($img) : null,
            'imagen_prefix' => is_string($img) ? substr($img, 0, 60) : null,
            'content_type' => $request->header('Content-Type'),
            'ip' => $request->ip(),
        ]);
    }

    protected function optimizeImage($imageData)
    {
        if (empty($imageData) || !is_string($imageData)) {
            return null;
        }

        try {
            return (string) Image::make(trim($imageData))
                ->resize(300, null, function ($constraint) {
                    $constraint->aspectRatio();
                })
                ->encode('webp', 50)
                ->encode('data-url');
        } catch (\Throwable $e) {
            Log::error('Error optimizando imagen en SolicitudeController', [
                'message' => $e->getMessage(),
            ]);
            // Fallback: guardar original para no perder la imagen.
            return trim($imageData);
        }
    }
    public function index()
    {
        $solicitudes = Solicitude::with(['carteroRecogida', 'carteroEntrega', 'sucursale', 'tarifa', 'direccion', 'encargado', 'encargadoregional', 'transporte'])->get();
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
            'encargadoregional',
            'transporte',
        ])
            ->where('estado', $estado)
            ->get();

        return response()->json($solicitudes);
    }



    public function store(Request $request)
    {
        $this->logImagenRequest($request, 'SolicitudeController@store');

        // Extraer y optimizar imagen en base64 (si existe)
        $optimizedImage = $this->optimizeImage($request->input('imagen'));

        // Crear una nueva instancia de Solicitude
        $solicitude = new Solicitude();
        $solicitude->cartero_recogida_id = $request->cartero_recogida_id ?? null;
        $solicitude->cartero_entrega_id = $request->cartero_entrega_id ?? null;
        $solicitude->encargado_id = $request->encargado_id ?? null;
        $solicitude->sucursale_id = $request->sucursale_id;
        $solicitude->tarifa_id = $request->tarifa_id ?? null;
        $solicitude->direccion_id = $request->direccion_id ?? null;

        // Validar si el campo 'guia' tiene un valor, si no, generar la guÃ­a
        $guia = $request->guia;
        if (empty($guia)) {
            $guiaResponse = $this->generateGuia(
                $request->sucursale_id,
                $request->tarifa_id,
                $request->reencaminamiento
            );
            $guiaData = method_exists($guiaResponse, 'getData') ? $guiaResponse->getData(true) : [];
            $guia = $guiaData['guia'] ?? null;

            if (empty($guia)) {
                return response()->json([
                    'error' => $guiaData['error'] ?? 'No se pudo generar la guia. Verifica sucursale_id.',
                ], 422);
            }
        }
        $solicitude->guia = $guia;

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
        $solicitude->reencaminamiento = $request->reencaminamiento;

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
        $this->logImagenRequest($request, 'SolicitudeController@update', $solicitude->guia);
        $solicitude->tarifa_id = $request->input('tarifa_id', $solicitude->tarifa_id);
        $solicitude->sucursale_id = $request->input('sucursale_id', $solicitude->sucursale_id);
        $solicitude->cartero_recogida_id = $request->input('cartero_recogida_id', $solicitude->cartero_recogida_id);
        $solicitude->cartero_entrega_id = $request->input('cartero_entrega_id', $solicitude->cartero_entrega_id);
        $solicitude->direccion_id = $request->input('direccion_id', $solicitude->direccion_id);
        $solicitude->encargado_id = $request->input('encargado_id', $solicitude->encargado_id);
        $solicitude->guia = $request->input('guia', $solicitude->guia);
        $solicitude->peso_o = $request->input('peso_o', $solicitude->peso_o);
        $solicitude->peso_v = $request->input('peso_v', $solicitude->peso_v);
        $solicitude->remitente = $request->input('remitente', $solicitude->remitente);
        $solicitude->telefono = $request->input('telefono', $solicitude->telefono);
        $solicitude->contenido = $request->input('contenido', $solicitude->contenido);
        $solicitude->fecha = $request->input('fecha', $solicitude->fecha);
        $solicitude->firma_o = $request->input('firma_o', $solicitude->firma_o);
        $solicitude->destinatario = $request->input('destinatario', $solicitude->destinatario);
        $solicitude->telefono_d = $request->input('telefono_d', $solicitude->telefono_d);
        $solicitude->direccion_d = $request->input('direccion_d', $solicitude->direccion_d);
        $solicitude->direccion_especifica_d = $request->input('direccion_especifica_d', $solicitude->direccion_especifica_d);
        $solicitude->ciudad = $request->input('ciudad', $solicitude->ciudad);
        $solicitude->firma_d = $request->input('firma_d', $solicitude->firma_d);
        $solicitude->nombre_d = $request->input('nombre_d', $solicitude->nombre_d);
        $solicitude->fecha_d = $request->input('fecha_d', $solicitude->fecha_d);
        $solicitude->estado = $request->input('estado', $solicitude->estado);
        $solicitude->observacion = $request->input('observacion', $solicitude->observacion);
        $solicitude->zona_d = $request->input('zona_d', $solicitude->zona_d);
        $solicitude->justificacion = $request->input('justificacion', $solicitude->justificacion);
        $solicitude->imagen_justificacion = $request->input('imagen_justificacion', $solicitude->imagen_justificacion);
        // No sobrescribir imagen existente cuando el request no incluye imagen.
        if ($request->has('imagen')) {
            $incomingImage = $request->input('imagen');
            $solicitude->imagen = !empty($incomingImage)
                ? ($this->optimizeImage($incomingImage) ?? $solicitude->imagen)
                : $solicitude->imagen;
        }
        $solicitude->fecha_recojo_c = $request->input('fecha_recojo_c', $solicitude->fecha_recojo_c);
        $solicitude->fecha_devolucion = $request->input('fecha_devolucion', $solicitude->fecha_devolucion);
        $solicitude->imagen_devolucion = $request->input('imagen_devolucion', $solicitude->imagen_devolucion);
        $solicitude->entrega_observacion = $request->input('entrega_observacion', $solicitude->entrega_observacion);
        $solicitude->fecha_envio_regional = $request->input('fecha_envio_regional', $solicitude->fecha_envio_regional);
        $solicitude->peso_r = $request->input('peso_r', $solicitude->peso_r);
        $solicitude->encargado_regional_id = $request->input('encargado_regional_id', $solicitude->encargado_regional_id);
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
    public function generateGuia($sucursaleId, $tarifaId = null, $reencaminamiento = null)
    {
        // Recuperar la sucursal y tarifa (tarifa opcional)
        $sucursal = Sucursale::find($sucursaleId);
        $tarifa = $tarifaId ? Tarifa::find($tarifaId) : null;

        if (!$sucursal) {
            return response()->json(['error' => 'Sucursal no encontrada.'], 404);
        }

        $sucursalCode = preg_replace('/\s+/', '', trim((string) $sucursal->n_contrato));
        if ($sucursalCode === '') {
            $sucursalCode = str_pad((string) $sucursal->codigo_cliente, 4, '0', STR_PAD_LEFT);
        }
        $sucursalOrigin = strtoupper(trim((string) $sucursal->origen));

        // Prioridad: tarifa->departamento, luego reencaminamiento, luego "000"
        if ($tarifa) {
            $destino = strtoupper(preg_replace('/\s+/', '', trim((string) $tarifa->departamento)));
        } else {
            $destino = strtoupper(preg_replace('/\s+/', '', trim((string) $reencaminamiento)));
        }

        if ($destino === '') {
            $tarifaCode = '000';
        } elseif (ctype_digit($destino)) {
            $tarifaCode = str_pad($destino, 3, '0', STR_PAD_LEFT);
        } else {
            $tarifaCode = str_pad(substr($destino, 0, 3), 3, '0', STR_PAD_RIGHT);
        }

        // Secuencial por sucursal con 5 digitos al final
        $lastGuia = Solicitude::where('sucursale_id', $sucursaleId)
            ->latest('id')
            ->first();

        $lastNumber = 0;
        if ($lastGuia && preg_match('/(\d+)$/', (string) $lastGuia->guia, $matches)) {
            $lastNumber = (int) $matches[1];
        }

        $newNumber = str_pad((string) ($lastNumber + 1), 5, '0', STR_PAD_LEFT);
        $newGuia = "{$sucursalCode}{$sucursalOrigin}{$tarifaCode}{$newNumber}";

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
            if ($request->has('imagen')) {
                $incomingImage = $request->input('imagen');
                $solicitude->imagen = !empty($incomingImage)
                    ? ($this->optimizeImage($incomingImage) ?? $solicitude->imagen)
                    : $solicitude->imagen;
            }
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
        'reencaminamiento' => 'nullable|string|max:255',
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
    $solicitude->reencaminamiento = $request->reencaminamiento;
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

public function registrarTransporte(Request $request)
{
    $data = $request->validate([
        'transportadora' => 'nullable|string|max:255',
        'provincia' => 'nullable|string|max:255',
        'cartero_id' => 'required|integer|exists:carteros,id',
        'n_recibo' => 'nullable|string|max:255',
        'n_factura' => 'nullable|string|max:255',
        'precio_total' => 'nullable|numeric|min:0',
        'peso_total' => 'nullable|numeric|min:0',
        'solicitude_ids' => 'required_without:solicitudes|array|min:1',
        'solicitude_ids.*' => 'integer|exists:solicitudes,id',
        'solicitudes' => 'nullable|array|min:1',
        'solicitudes.*.id' => 'required_with:solicitudes|integer|exists:solicitudes,id',
        'solicitudes.*.guia' => 'nullable|string|max:255',
    ]);

    try {
        $idsDesdeArray = collect($data['solicitude_ids'] ?? []);
        $idsDesdeObjetos = collect($data['solicitudes'] ?? [])->pluck('id');
        $solicitudeIds = $idsDesdeArray->merge($idsDesdeObjetos)->unique()->values()->all();

        $solicitudes = Solicitude::whereIn('id', $solicitudeIds)->get()->keyBy('id');
        if ($solicitudes->count() !== count($solicitudeIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Una o mas solicitudes no existen.'
            ], 422);
        }

        if (!empty($data['solicitudes'])) {
            foreach ($data['solicitudes'] as $item) {
                if (!empty($item['guia'])) {
                    $solicitude = $solicitudes->get($item['id']);
                    if (!$solicitude || $solicitude->guia !== $item['guia']) {
                        return response()->json([
                            'success' => false,
                            'message' => 'La guia no coincide con la solicitud seleccionada.',
                            'solicitude_id' => $item['id'],
                            'guia_enviada' => $item['guia'],
                            'guia_actual' => $solicitude->guia ?? null,
                        ], 422);
                    }
                }
            }
        }

        $transporte = DB::transaction(function () use ($data, $solicitudes, $solicitudeIds) {
            $pesoTotalCalculado = $solicitudes->sum(function ($solicitude) {
                $peso = $solicitude->peso_r ?? $solicitude->peso_v ?? $solicitude->peso_o ?? 0;
                return (float) str_replace(',', '.', (string) $peso);
            });

            $transporte = Transporte::create([
                'transportadora' => trim((string) ($data['transportadora'] ?? '')),
                'provincia' => trim((string) ($data['provincia'] ?? '')),
                'cartero_id' => $data['cartero_id'],
                'n_recibo' => isset($data['n_recibo']) && trim((string) $data['n_recibo']) !== '' ? trim((string) $data['n_recibo']) : null,
                'n_factura' => isset($data['n_factura']) && trim((string) $data['n_factura']) !== '' ? trim((string) $data['n_factura']) : null,
                'precio_total' => $data['precio_total'] ?? 0,
                'peso_total' => $data['peso_total'] ?? $pesoTotalCalculado,
                'guias' => $solicitudes->pluck('guia')->values()->all(),
            ]);

            Solicitude::whereIn('id', $solicitudeIds)->update([
                'transporte_id' => $transporte->id,
                'estado' => 14,
            ]);

            foreach ($solicitudes as $solicitude) {
                $carteroId = $solicitude->cartero_recogida_id;
                $encargadoId = $carteroId ? null : $solicitude->encargado_id;

                Evento::create([
                    'accion' => 'TRANSPORTE',
                    'cartero_id' => $carteroId,
                    'encargado_id' => $encargadoId,
                    'descripcion' => 'Envio en transporte externo',
                    'codigo' => $solicitude->guia,
                    'fecha_hora' => now(),
                ]);
            }

            return $transporte;
        });

        $transporte->load(['cartero', 'solicitudes']);
        $solicitudesSeleccionadas = Solicitude::whereIn('id', $solicitudeIds)
            ->select('id', 'guia', 'estado', 'transporte_id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Transporte registrado y solicitudes actualizadas a estado 14.',
            'transporte' => $transporte,
            'solicitudes' => $solicitudesSeleccionadas,
        ], 201);
    } catch (\Throwable $e) {
        return response()->json([
            'success' => false,
            'message' => 'No se pudo registrar el transporte.',
            'error' => $e->getMessage(),
        ], 500);
    }
}

public function listarTransportes()
{
    $transportes = Transporte::with(['cartero', 'solicitudes'])->latest('id')->get();
    return response()->json($transportes);
}

public function mostrarTransporte($id)
{
    $transporte = Transporte::with(['cartero', 'solicitudes'])->findOrFail($id);
    return response()->json($transporte);
}

public function storeEntregadoManual(Request $request)
{
    $data = $request->validate([
        'sucursale_id'     => 'nullable|integer|exists:sucursales,id',
        'guia'             => 'required|string|max:255',
        'peso_r'           => 'nullable|string|max:255',
        'remitente'        => 'required|string|max:255',
        'telefono'         => 'required|string|max:255',
        'contenido'        => 'required|string|max:255',
        'destinatario'     => 'required|string|max:255',
        'telefono_d'       => 'required|string|max:255',
        'direccion_d'      => 'nullable|string|max:255',
        'reencaminamiento' => 'nullable|string|max:255',
        'imagen'           => 'nullable|string', // base64 data-url
    ]);

    // ✅ optimizar imagen como ya haces
    $optimizedImage = null;
    if (!empty($data['imagen'])) {
        try {
            $optimizedImage = (string) Image::make($data['imagen'])
                ->resize(300, null, function ($constraint) {
                    $constraint->aspectRatio();
                })
                ->encode('webp', 50)
                ->encode('data-url');
        } catch (\Throwable $e) {
            // si falla la imagen, no rompas la creación
            $optimizedImage = null;
        }
    }

    // ✅ fecha formato dd/mm/yyyy HH:MM (igual que en Vue)
    $fecha_d = now()->format('d/m/Y H:i');

    $solicitude = new Solicitude();
    $solicitude->sucursale_id     = $data['sucursale_id'] ?? null;
    $solicitude->tarifa_id        = null;

    // si tienes login de carteros, aquí puedes usar Auth::id()
    $solicitude->cartero_entrega_id = Auth::id(); // si tu auth coincide con carteros
    $solicitude->estado           = 3;
    $solicitude->fecha_d          = $fecha_d;

    $solicitude->guia             = $data['guia'];
    $solicitude->peso_r           = $data['peso_r'] ?? null;
    $solicitude->remitente        = $data['remitente'];
    $solicitude->telefono         = $data['telefono'];
    $solicitude->contenido        = $data['contenido'];
    $solicitude->destinatario     = $data['destinatario'];
    $solicitude->telefono_d       = $data['telefono_d'];
    $solicitude->direccion_d      = $data['direccion_d'] ?? null;
    $solicitude->reencaminamiento = $data['reencaminamiento'] ?? null;

    // defaults para NOT NULL (según tu migración)
    $solicitude->direccion_especifica_d = $solicitude->direccion_especifica_d ?? 'N/D';
    $solicitude->zona_d = $solicitude->zona_d ?? 'N/D';

    $solicitude->imagen = $optimizedImage;

    // código barras opcional (si quieres)
    try {
        $generator = new \Picqer\Barcode\BarcodeGeneratorPNG();
        $barcode = $generator->getBarcode($solicitude->guia, $generator::TYPE_CODE_128);
        $solicitude->codigo_barras = base64_encode($barcode);
    } catch (\Throwable $e) {}

    $solicitude->save();

    Evento::create([
        'accion' => 'Entregado Manual',
        'sucursale_id' => $solicitude->sucursale_id,
        'descripcion' => 'Registro manual de envío entregado',
        'codigo' => $solicitude->guia,
        'fecha_hora' => now(),
    ]);

    return response()->json($solicitude, 201);
}

}

