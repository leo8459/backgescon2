<?php

namespace App\Http\Controllers;

use App\Models\DetalleSolicitude;
use App\Models\Solicitude;
use App\Models\Sucursale;
use App\Models\Encargado;
use App\Models\Tarifa;
use App\Models\Direccione;
use App\Models\Transporte;
use App\Models\Cartero;
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
use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Facades\Http;

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

    protected function resolveEncargadoRegionalId(Request $request, ?Solicitude $solicitude = null): ?int
    {
        $hasEncargadoRegional = $request->exists('encargado_regional_id');

        if (!$hasEncargadoRegional && $solicitude) {
            return $solicitude->encargado_regional_id;
        }

        $candidates = [
            $request->input('encargado_regional_id'),
            $request->input('cartero_recogida_id'),
            $solicitude?->cartero_recogida_id,
            $solicitude?->encargado_regional_id,
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === null || $candidate === '') {
                continue;
            }

            $id = (int) $candidate;
            if ($id > 0 && Encargado::whereKey($id)->exists()) {
                return $id;
            }
        }

        return null;
    }

    protected function resolveCarteroId(Request $request, ?Solicitude $solicitude = null): ?int
    {
        $carteroAuthId = Auth::guard('api_cartero')->id();

        $candidates = [
            $request->input('cartero_entrega_id'),
            $request->input('cartero_recogida_id'),
            $request->input('cartero_id'),
            $carteroAuthId,
            $solicitude?->cartero_entrega_id,
            $solicitude?->cartero_recogida_id,
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === null || $candidate === '') {
                continue;
            }

            $id = (int) $candidate;
            if ($id > 0 && Cartero::whereKey($id)->exists()) {
                return $id;
            }
        }

        return null;
    }

    protected function normalizeGuia(?string $guia): ?string
    {
        if ($guia === null) {
            return null;
        }

        $normalized = strtoupper(trim((string) $guia));

        return $normalized === '' ? null : $normalized;
    }

    protected function guiaAlreadyExists(string $guia, ?int $exceptSolicitudeId = null): bool
    {
        $normalizedGuia = $this->normalizeGuia($guia);
        if ($normalizedGuia === null) {
            return false;
        }

        $query = Solicitude::query()
            ->whereNotNull('guia')
            ->whereRaw('UPPER(TRIM(guia)) = ?', [$normalizedGuia]);

        if ($exceptSolicitudeId !== null) {
            $query->where('id', '!=', $exceptSolicitudeId);
        }

        return $query->exists();
    }

    protected function guiaDuplicadaResponse(string $guia)
    {
        return response()->json([
            'error' => 'La guia ya existe. No se permiten guias duplicadas.',
            'guia' => $guia,
        ], 422);
    }

    protected function applyCarteroSearch($query, ?string $search): void
    {
        $search = trim((string) $search);
        if ($search === '') {
            return;
        }

        $like = '%' . $search . '%';

        $query->where(function ($searchQuery) use ($like) {
            $searchQuery
                ->where('guia', 'like', $like)
                ->orWhere('remitente', 'like', $like)
                ->orWhere('telefono', 'like', $like)
                ->orWhere('contenido', 'like', $like)
                ->orWhere('destinatario', 'like', $like)
                ->orWhere('telefono_d', 'like', $like)
                ->orWhere('direccion_d', 'like', $like)
                ->orWhere('ciudad', 'like', $like)
                ->orWhere('zona_d', 'like', $like)
                ->orWhere('reencaminamiento', 'like', $like)
                ->orWhereHas('sucursale', function ($sucursaleQuery) use ($like) {
                    $sucursaleQuery
                        ->where('nombre', 'like', $like)
                        ->orWhere('origen', 'like', $like)
                        ->orWhere('sigla', 'like', $like);
                })
                ->orWhereHas('direccion', function ($direccionQuery) use ($like) {
                    $direccionQuery
                        ->where('direccion', 'like', $like)
                        ->orWhere('direccion_especifica', 'like', $like)
                        ->orWhere('direccion_especifica_d', 'like', $like)
                        ->orWhere('zona', 'like', $like);
                })
                ->orWhereHas('tarifa', function ($tarifaQuery) use ($like) {
                    $tarifaQuery
                        ->where('departamento', 'like', $like)
                        ->orWhere('servicio', 'like', $like);
                });
        });
    }

    protected function paginateForCartero($query, Request $request, int $defaultPerPage = 10)
    {
        $perPage = (int) $request->input('per_page', $defaultPerPage);
        if ($perPage <= 0) {
            $perPage = $defaultPerPage;
        }

        $perPage = min($perPage, 100);

        return response()->json(
            $query->paginate($perPage)->appends($request->query())
        );
    }

    public function index()
    {
        $solicitudes = Solicitude::with(['carteroRecogida', 'carteroEntrega', 'sucursale', 'tarifa', 'direccion', 'encargado', 'encargadoregional', 'transporte'])->get();
        return response()->json($solicitudes);
    }

    public function searchAnyForCartero(Request $request)
    {
        $search = preg_replace('/\s+/', '', trim((string) $request->query('search', '')));

        if ($search === '') {
            return response()->json([
                'message' => 'El parametro search es obligatorio.',
            ], 422);
        }

        $query = Solicitude::with([
            'carteroRecogida',
            'carteroEntrega',
            'sucursale',
            'tarifa',
            'direccion',
            'encargado',
            'encargadoregional',
            'transporte',
        ])->orderByDesc('id');

        $searchLower = mb_strtolower($search, 'UTF-8');

        if (ctype_digit($search)) {
            $query->where(function ($builder) use ($search, $searchLower) {
                $builder->where('id', (int) $search)
                    ->orWhereRaw('LOWER(guia) = ?', [$searchLower]);
            });
        } else {
            $query->whereRaw('LOWER(guia) = ?', [$searchLower]);
        }

        $solicitude = $query->first();

        if (!$solicitude) {
            return response()->json(null, 404);
        }

        return response()->json($solicitude);
    }

    public function assignAnyStateFromSearch(Request $request, Solicitude $solicitude)
    {
        $carteroId = $this->resolveCarteroId($request, $solicitude);

        if ($carteroId === null) {
            return response()->json([
                'message' => 'No se pudo resolver el cartero para la asignacion.',
            ], 422);
        }

        $solicitude->estado = 2;
        $solicitude->cartero_entrega_id = $carteroId;

        if (($solicitude->tipo_correspondencia ?? null) === 'EMS' && empty($solicitude->cartero_recogida_id)) {
            $solicitude->cartero_recogida_id = $carteroId;
        }

        if ($request->filled('peso_v')) {
            $solicitude->peso_v = $request->input('peso_v');
        }

        if ($request->filled('nombre_d')) {
            $solicitude->nombre_d = $request->input('nombre_d');
        }

        $solicitude->save();

        Evento::create([
            'accion' => 'En camino',
            'cartero_id' => $solicitude->cartero_entrega_id ?? $solicitude->cartero_recogida_id,
            'descripcion' => 'Asignacion de cartero y peso desde busqueda',
            'codigo' => $solicitude->guia,
            'fecha_hora' => now(),
        ]);

        return response()->json($solicitude);
    }

    public function indexSolicitudesCartero(Request $request)
    {
        $cartero = Auth::guard('api_cartero')->user();
        $departamento = $cartero->departamento_cartero ?? null;

        $query = Solicitude::with([
            'carteroRecogida',
            'carteroEntrega',
            'sucursale',
            'tarifa',
            'direccion',
            'encargado',
            'encargadoregional',
            'transporte',
        ])->where('estado', 1);

        if (!empty($departamento)) {
            $query->whereHas('sucursale', function ($sucursaleQuery) use ($departamento) {
                $sucursaleQuery->where('origen', $departamento);
            });
        } else {
            $query->whereRaw('1 = 0');
        }

        if ($request->filled('sucursale_id')) {
            $query->where('sucursale_id', $request->input('sucursale_id'));
        }

        $this->applyCarteroSearch($query, $request->input('search'));

        $query->orderByDesc('fecha');

        return $this->paginateForCartero($query, $request);
    }

    public function indexEncaminoCartero(Request $request)
    {
        $carteroId = Auth::guard('api_cartero')->id();
        $scope = trim((string) $request->input('scope', 'encamino'));

        $query = Solicitude::with([
            'carteroRecogida',
            'carteroEntrega',
            'sucursale',
            'tarifa',
            'direccion',
            'encargado',
            'encargadoregional',
            'transporte',
        ]);

        if ($scope === 'provincia') {
            $query->where('estado', 14)
                ->where('cartero_entrega_id', $carteroId);
        } else {
            if ($carteroId) {
                $query->where('estado', 2)
                    ->where('cartero_entrega_id', $carteroId);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $this->applyCarteroSearch($query, $request->input('search'));

        $query
            ->orderByDesc('fecha_recojo_c')
            ->orderByDesc('id');

        return $this->paginateForCartero($query, $request);
    }

    public function indexEntregadosCartero(Request $request)
    {
        $carteroId = Auth::guard('api_cartero')->id();

        $query = Solicitude::with([
            'carteroRecogida',
            'carteroEntrega',
            'sucursale',
            'tarifa',
            'direccion',
            'encargado',
            'encargadoregional',
            'transporte',
        ]);

        if ($carteroId) {
            $query->where('cartero_entrega_id', $carteroId);
        } else {
            $query->whereRaw('1 = 0');
        }

        $query->whereIn('estado', [3, 4, 7, 10]);

        $this->applyCarteroSearch($query, $request->input('search'));

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $startDate = trim((string) $request->input('start_date'));
            $endDate = trim((string) $request->input('end_date'));

            $query->where(function ($dateQuery) use ($startDate, $endDate) {
                $dateQuery
                    ->whereDate('fecha_d', '>=', $startDate)
                    ->whereDate('fecha_d', '<=', $endDate)
                    ->orWhereDate('fecha_devolucion', '>=', $startDate)
                    ->whereDate('fecha_devolucion', '<=', $endDate);
            });
        }

        $query
            ->orderByDesc('fecha_devolucion')
            ->orderByDesc('fecha_d')
            ->orderByDesc('id');

        return $this->paginateForCartero($query, $request);
    }

    public function indexBitacoraCartero(Request $request)
    {
        $carteroId = Auth::guard('api_cartero')->id();

        $query = Solicitude::with([
            'carteroRecogida',
            'carteroEntrega',
            'sucursale',
            'tarifa',
            'direccion',
            'encargado',
            'encargadoregional',
            'transporte',
        ]);

        if ($carteroId) {
            $query->where('cartero_entrega_id', $carteroId);
        } else {
            $query->whereRaw('1 = 0');
        }

        $this->applyCarteroSearch($query, $request->input('search'));

        $query->orderByDesc('id');

        return $this->paginateForCartero($query, $request);
    }

    public function indexDarBajaCartero(Request $request)
    {
        $carteroId = Auth::guard('api_cartero')->id();

        $query = Solicitude::with([
            'carteroRecogida',
            'carteroEntrega',
            'sucursale',
            'tarifa',
            'direccion',
            'encargado',
            'encargadoregional',
            'transporte',
        ]);

        if ($carteroId) {
            $query->where('cartero_entrega_id', $carteroId);
        } else {
            $query->whereRaw('1 = 0');
        }

        $query->whereNotIn('estado', [3, 4]);

        $this->applyCarteroSearch($query, $request->input('search'));

        $query->orderByDesc('id');

        return $this->paginateForCartero($query, $request);
    }

    public function indexGenerarDespachoCartero(Request $request)
    {
        $cartero = Auth::guard('api_cartero')->user();
        $departamento = $cartero->departamento_cartero ?? null;

        $query = Solicitude::with([
            'carteroRecogida',
            'carteroEntrega',
            'sucursale',
            'tarifa',
            'direccion',
            'encargado',
            'encargadoregional',
            'transporte',
        ]);

        $query->where(function ($query) use ($departamento) {
            if (!empty($departamento)) {
                $query->where(function ($subQuery) use ($departamento) {
                    $subQuery->where('estado', 5)
                        ->whereHas('sucursale', function ($sucursaleQuery) use ($departamento) {
                            $sucursaleQuery->where('origen', $departamento);
                        });
                })->orWhere(function ($subQuery) use ($departamento) {
                    $subQuery->whereIn('estado', [10, 11, 13])
                        ->where('reencaminamiento', $departamento);
                })->orWhere(function ($subQuery) {
                    $subQuery->where('estado', 5)
                        ->whereRaw("UPPER(COALESCE(tipo_correspondencia, '')) = ?", ['EMS'])
                        ->whereNull('sucursale_id')
                        ->whereNull('tarifa_id');
                });

                return;
            }

            $query->where(function ($subQuery) {
                $subQuery->where('estado', 5)
                    ->whereRaw("UPPER(COALESCE(tipo_correspondencia, '')) = ?", ['EMS'])
                    ->whereNull('sucursale_id')
                    ->whereNull('tarifa_id');
            });
        });

        $this->applyCarteroSearch($query, $request->input('search'));

        $query
            ->orderByDesc('fecha_recojo_c')
            ->orderByDesc('id');

        return $this->paginateForCartero($query, $request);
    }

    public function indexRecibirRegionalCartero(Request $request)
    {
        $cartero = Auth::guard('api_cartero')->user();
        $departamento = $cartero->departamento_cartero ?? $cartero->departamento ?? null;

        $query = Solicitude::with([
            'carteroRecogida',
            'carteroEntrega',
            'sucursale',
            'tarifa',
            'direccion',
            'encargado',
            'encargadoregional',
            'transporte',
        ])->whereIn('estado', [8, 12]);

        if (!empty($departamento)) {
            $query->where(function ($subQuery) use ($departamento) {
                $subQuery
                    ->where('reencaminamiento', $departamento)
                    ->orWhereHas('tarifa', function ($tarifaQuery) use ($departamento) {
                        $tarifaQuery->where('departamento', $departamento);
                    });
            });
        } else {
            $query->whereRaw('1 = 0');
        }

        $this->applyCarteroSearch($query, $request->input('search'));

        $query
            ->orderByDesc('fecha_envio_regional')
            ->orderByDesc('id');

        return $this->paginateForCartero($query, $request);
    }

    public function indexRecogidoRegionalCartero(Request $request)
    {
        $cartero = Auth::guard('api_cartero')->user();
        $departamento = $cartero->departamento_cartero ?? null;

        $query = Solicitude::with([
            'carteroRecogida',
            'carteroEntrega',
            'sucursale',
            'tarifa',
            'direccion',
            'encargado',
            'encargadoregional',
            'transporte',
        ])->where('estado', 10);

        if (!empty($departamento)) {
            $query->where(function ($subQuery) use ($departamento) {
                $subQuery
                    ->where('reencaminamiento', $departamento)
                    ->orWhereHas('tarifa', function ($tarifaQuery) use ($departamento) {
                        $tarifaQuery->where('departamento', $departamento);
                    });
            });
        } else {
            $query->whereRaw('1 = 0');
        }

        $this->applyCarteroSearch($query, $request->input('search'));

        $query
            ->orderByDesc('fecha_envio_regional')
            ->orderByDesc('id');

        return $this->paginateForCartero($query, $request);
    }

    public function indexDevolucionCartero(Request $request)
    {
        $carteroId = Auth::guard('api_cartero')->id();

        $query = Solicitude::with([
            'carteroRecogida',
            'carteroEntrega',
            'sucursale',
            'tarifa',
            'direccion',
            'encargado',
            'encargadoregional',
            'transporte',
        ])->whereIn('estado', [6, 13]);

        if ($carteroId) {
            $query->where('cartero_entrega_id', $carteroId);
        } else {
            $query->whereRaw('1 = 0');
        }

        if ($request->filled('sucursale_id')) {
            $query->where('sucursale_id', $request->input('sucursale_id'));
        }

        $this->applyCarteroSearch($query, $request->input('search'));

        $query
            ->orderByDesc('fecha_recojo_c')
            ->orderByDesc('id');

        return $this->paginateForCartero($query, $request);
    }

    public function indexEncaminoRegionalCartero(Request $request)
    {
        $carteroId = Auth::guard('api_cartero')->id();
        $scope = trim((string) $request->input('scope', 'encamino'));

        $query = Solicitude::with([
            'carteroRecogida',
            'carteroEntrega',
            'sucursale',
            'tarifa',
            'direccion',
            'encargado',
            'encargadoregional',
            'transporte',
        ]);

        if ($scope === 'regional') {
            $query->where('estado', 14)
                ->where(function ($regionalQuery) use ($carteroId) {
                    $regionalQuery
                        ->where('cartero_entrega_id', $carteroId)
                        ->orWhereNull('cartero_entrega_id');
                });
        } else {
            if ($carteroId) {
                $query->where('estado', 9)
                    ->where('cartero_entrega_id', $carteroId);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $this->applyCarteroSearch($query, $request->input('search'));

        $query
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        return $this->paginateForCartero($query, $request);
    }

    public function indexRecogidosCartero(Request $request)
    {
        $cartero = Auth::guard('api_cartero')->user();
        $departamento = $cartero->departamento_cartero ?? null;

        $query = Solicitude::with([
            'carteroRecogida',
            'carteroEntrega',
            'sucursale',
            'tarifa',
            'direccion',
            'encargado',
            'encargadoregional',
            'transporte',
        ]);

        $query->where(function ($query) use ($departamento) {
            if (!empty($departamento)) {
                $query->where(function ($subQuery) use ($departamento) {
                    $subQuery->where('estado', 5)
                        ->whereHas('sucursale', function ($sucursaleQuery) use ($departamento) {
                            $sucursaleQuery->where('origen', $departamento);
                        });
                })->orWhere(function ($subQuery) use ($departamento) {
                    $subQuery->whereIn('estado', [10, 11, 13])
                        ->where('reencaminamiento', $departamento);
                })->orWhere(function ($subQuery) {
                    $subQuery->where('estado', 5)
                        ->whereRaw("UPPER(COALESCE(tipo_correspondencia, '')) = ?", ['EMS'])
                        ->whereNull('sucursale_id')
                        ->whereNull('tarifa_id');
                });

                return;
            }

            $query->where(function ($subQuery) {
                $subQuery->where('estado', 5)
                    ->whereRaw("UPPER(COALESCE(tipo_correspondencia, '')) = ?", ['EMS'])
                    ->whereNull('sucursale_id')
                    ->whereNull('tarifa_id');
            });
        });

        $this->applyCarteroSearch($query, $request->input('search'));

        $query->orderByDesc('fecha_recojo_c');

        return $this->paginateForCartero($query, $request);
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

    // Validar si el campo 'guia' tiene un valor, si no, generar la guía
    $guia = $this->normalizeGuia($request->guia);
    if (empty($guia)) {
        $guiaData = $this->buildGuiaData(
            $request->input('sucursale_id'),
            $request->input('tarifa_id'),
            $request->input('reencaminamiento')
        );
        $guia = $guiaData['guia'] ?? null;

        if (empty($guia)) {
            return response()->json([
                'error' => $guiaData['error'] ?? 'No se pudo generar la guia. Verifica sucursale_id.',
            ], 422);
        }
    }

    if ($this->guiaAlreadyExists($guia)) {
        return $this->guiaDuplicadaResponse($guia);
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
    $solicitude->encargado_regional_id = $this->resolveEncargadoRegionalId($request);

    // Asignar la imagen optimizada en formato WebP al modelo
    $solicitude->imagen = $optimizedImage;
    $solicitude->imagen_devolucion = $request->imagen_devolucion;
    $solicitude->peso_r = $request->peso_r;

    // Generar el código de barras para la guía
    $generator = new BarcodeGeneratorPNG();
    $barcode = $generator->getBarcode($solicitude->guia, $generator::TYPE_CODE_128);
    $solicitude->codigo_barras = base64_encode($barcode);
    $solicitude->fecha_envio_regional = $request->fecha_envio_regional;
    $solicitude->reencaminamiento = $request->reencaminamiento;

    // Guardar la solicitud en la base de datos local
    $solicitude->save();

    // Registrar evento
    Evento::create([
        'accion' => 'Solicitud',
        'sucursale_id' => $solicitude->sucursale_id,
        'descripcion' => 'Solicitud de Recojo de Paquetes',
        'codigo' => $solicitude->guia,
        'fecha_hora' => now(),
    ]);

    // Enviar al otro backend
    $this->enviarAOtroBackend($solicitude);

    // Cargar relaciones
    $solicitude->load('sucursale');
    $solicitude->load('direccion');
    $solicitude->load('tarifa');

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
        $estadoAnterior = (int) $solicitude->estado;
        $solicitude->tarifa_id = $request->input('tarifa_id', $solicitude->tarifa_id);
        $solicitude->sucursale_id = $request->input('sucursale_id', $solicitude->sucursale_id);
        $solicitude->cartero_recogida_id = $request->input('cartero_recogida_id', $solicitude->cartero_recogida_id);
        $solicitude->cartero_entrega_id = $request->input('cartero_entrega_id', $solicitude->cartero_entrega_id);
        $solicitude->direccion_id = $request->input('direccion_id', $solicitude->direccion_id);
        $solicitude->encargado_id = $request->input('encargado_id', $solicitude->encargado_id);
        $guia = $this->normalizeGuia($request->input('guia', $solicitude->guia));
        if (empty($guia)) {
            return response()->json([
                'error' => 'La guia es obligatoria.',
            ], 422);
        }

        if ($this->guiaAlreadyExists($guia, (int) $solicitude->id)) {
            return $this->guiaDuplicadaResponse($guia);
        }

        $solicitude->guia = $guia;
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
        $solicitude->encargado_regional_id = $this->resolveEncargadoRegionalId($request, $solicitude);

        $solicitude->save();

        if ($request->has('estado') && (int) $solicitude->estado === 3 && $estadoAnterior !== 3) {
            Evento::create([
                'accion' => 'Entregado',
                'encargado_id' => $solicitude->encargado_id,
                'cartero_id' => $solicitude->cartero_entrega_id ?? $solicitude->cartero_recogida_id,
                'descripcion' => 'Envio entregado con exito',
                'codigo' => $solicitude->guia,
                'fecha_hora' => now(),
            ]);
        }

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
    protected function getLastCorrelativeByContractCode(string $contractCode): int
    {
        $maxCorrelative = 0;

        $contractCode = trim((string) $contractCode);
        if ($contractCode === '') {
            return 0;
        }

        $rows = Solicitude::query()
            ->whereNotNull('guia')
            ->whereRaw('UPPER(TRIM(guia)) LIKE ?', ["C{$contractCode}A%BO"])
            ->select('guia')
            ->cursor();

        foreach ($rows as $row) {
            $guia = $this->normalizeGuia((string) $row->guia);
            if ($guia === null) {
                continue;
            }

            $current = $this->extractCorrelativeFromGuia($guia, $contractCode);
            if ($current <= 0) {
                continue;
            }

            if ($current > $maxCorrelative) {
                $maxCorrelative = $current;
            }
        }

        return $maxCorrelative;
    }

    protected function extractCorrelativeFromGuia(?string $guia, ?string $expectedContractCode = null): int
    {
        $guia = $this->normalizeGuia($guia);
        if ($guia === null) {
            return 0;
        }

        if (preg_match('/^C(\d{4})A(\d{1,5})BO$/', $guia, $matches)) {
            $contractCode = $matches[1];
            if ($expectedContractCode !== null && $expectedContractCode !== '' && $contractCode !== $expectedContractCode) {
                return 0;
            }
            return (int) $matches[2];
        }

        if ($expectedContractCode !== null && $expectedContractCode !== '') {
            return 0;
        }

        if (preg_match('/A(\d{1,5})BO$/', $guia, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    protected function getLastCorrelativeFromExternal(string $contractCode, int $localFallback = 0): int
    {
        if (!filter_var(env('API_OTRO_BACKEND_LAST_CODE_ENABLED', true), FILTER_VALIDATE_BOOLEAN)) {
            return 0;
        }

        $url = trim((string) env('API_OTRO_BACKEND_LAST_CODE_URL', ''));
        if ($url === '') {
            return 0;
        }

        try {
            $timeout = (int) env('API_OTRO_BACKEND_TIMEOUT', 20);
            $client = Http::timeout($timeout)->acceptJson();
            $verifySsl = filter_var(env('API_OTRO_BACKEND_VERIFY_SSL', true), FILTER_VALIDATE_BOOLEAN);
            if (!$verifySsl) {
                $client = $client->withoutVerifying();
            }

            $token = trim((string) env('API_OTRO_BACKEND_TOKEN', ''));
            if ($token !== '') {
                $client = $client->withToken($token);
            }

            $apiKeyHeader = trim((string) env('API_OTRO_BACKEND_API_KEY_HEADER', ''));
            $apiKeyValue = trim((string) env('API_OTRO_BACKEND_API_KEY', ''));
            if ($apiKeyHeader !== '' && $apiKeyValue !== '') {
                $client = $client->withHeaders([$apiKeyHeader => $apiKeyValue]);
            }

            $response = $client->get($url, [
                'n_contrato' => $contractCode,
                'contract_code' => $contractCode,
            ]);

            if (
                !$response->successful()
                && $response->status() === 400
                && stripos((string) $response->body(), 'plain HTTP request was sent to HTTPS port') !== false
                && str_starts_with($url, 'http://')
            ) {
                $httpsUrl = 'https://' . substr($url, 7);
                $response = $client->get($httpsUrl, [
                    'n_contrato' => $contractCode,
                    'contract_code' => $contractCode,
                ]);
            }

            if (!$response->successful()) {
                Log::warning('No se pudo obtener ultimo codigo desde API externa', [
                    'url' => $url,
                    'contract_code' => $contractCode,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return 0;
            }

            $body = $response->json();
            if (!is_array($body)) {
                return 0;
            }

            $candidates = [
                data_get($body, 'codigo'),
                data_get($body, 'guia'),
                data_get($body, 'code'),
                data_get($body, 'last_code'),
                data_get($body, 'ultimo_codigo'),
                data_get($body, 'data.codigo'),
                data_get($body, 'data.guia'),
                data_get($body, 'data.code'),
                data_get($body, 'data.last_code'),
                data_get($body, 'data.ultimo_codigo'),
            ];

            $rows = data_get($body, 'data');
            if (is_array($rows)) {
                $first = reset($rows);
                if (is_array($first)) {
                    $candidates[] = data_get($first, 'codigo');
                    $candidates[] = data_get($first, 'guia');
                    $candidates[] = data_get($first, 'code');
                    $candidates[] = data_get($first, 'last_code');
                    $candidates[] = data_get($first, 'ultimo_codigo');
                }
            }

            foreach ($candidates as $candidate) {
                if (!is_string($candidate) || trim($candidate) === '') {
                    continue;
                }

                $correlative = $this->extractCorrelativeFromGuia($candidate, $contractCode);
                if ($correlative > 0) {
                    return $correlative;
                }
            }

            return 0;
        } catch (\Throwable $e) {
            Log::warning('Error consultando ultimo codigo externo para generar guia', [
                'url' => $url,
                'contract_code' => $contractCode,
                'local_fallback' => $localFallback,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    protected function resolveExternalGeneratedCode($responseBody): ?string
    {
        if (!is_array($responseBody)) {
            return null;
        }

        $candidates = [
            data_get($responseBody, 'codigo'),
            data_get($responseBody, 'guia'),
            data_get($responseBody, 'code'),
            data_get($responseBody, 'data.codigo'),
            data_get($responseBody, 'data.guia'),
            data_get($responseBody, 'data.code'),
        ];

        foreach ($candidates as $candidate) {
            if (!is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            $normalized = $this->normalizeGuia($candidate);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    protected function syncLocalGuideWithExternalCode(Solicitude $solicitude, string $externalCode): void
    {
        $externalCode = $this->normalizeGuia($externalCode);
        if ($externalCode === null || $externalCode === $solicitude->guia) {
            return;
        }

        if ($this->guiaAlreadyExists($externalCode, (int) $solicitude->id)) {
            Log::warning('No se sincronizo guia local con codigo externo por duplicidad local', [
                'solicitude_id' => $solicitude->id,
                'guia_local' => $solicitude->guia,
                'codigo_externo' => $externalCode,
            ]);
            return;
        }

        $oldGuia = $solicitude->guia;
        $solicitude->guia = $externalCode;

        try {
            $generator = new BarcodeGeneratorPNG();
            $barcode = $generator->getBarcode($solicitude->guia, $generator::TYPE_CODE_128);
            $solicitude->codigo_barras = base64_encode($barcode);
        } catch (\Throwable $e) {
            Log::warning('No se pudo regenerar codigo de barras al sincronizar guia externa', [
                'solicitude_id' => $solicitude->id,
                'guia' => $externalCode,
                'error' => $e->getMessage(),
            ]);
        }

        $solicitude->save();

        Evento::query()
            ->where('codigo', $oldGuia)
            ->where('sucursale_id', $solicitude->sucursale_id)
            ->where('accion', 'Solicitud')
            ->orderByDesc('id')
            ->limit(1)
            ->update(['codigo' => $externalCode]);

        Log::info('Guia local sincronizada con codigo generado por API externa', [
            'solicitude_id' => $solicitude->id,
            'guia_anterior' => $oldGuia,
            'guia_nueva' => $externalCode,
        ]);
    }

    protected function buildGuiaData($sucursaleId, $tarifaId = null, $reencaminamiento = null, $departamento = null): array
    {
        if (is_array($sucursaleId)) {
            $sucursaleId = $sucursaleId['id'] ?? null;
        }

        $sucursaleId = is_numeric($sucursaleId) ? (int) $sucursaleId : null;
        if (!$sucursaleId) {
            return ['error' => 'Sucursal no proporcionada.'];
        }

        // Recuperar la sucursal
        $sucursal = Sucursale::find($sucursaleId);

        if (!$sucursal) {
            return ['error' => 'Sucursal no encontrada.'];
        }

        // n_contrato (4 digitos) para formato: C{n_contrato}A{correlativo}BO
        $contractDigits = preg_replace('/\D+/', '', (string) $sucursal->n_contrato);
        if ($contractDigits === '') {
            $contractDigits = preg_replace('/\D+/', '', (string) $sucursal->codigo_cliente);
        }
        if ($contractDigits === '') {
            return ['error' => 'La sucursal no tiene n_contrato/codigo_cliente valido para generar guia.'];
        }
        $contractCode = str_pad(substr($contractDigits, -4), 4, '0', STR_PAD_LEFT);

        // Secuencial global por n_contrato (misma empresa/contrato comparte correlativo).
        // Toma el mayor entre local y API externa para no retroceder correlativo.
        $lastLocalNumber = $this->getLastCorrelativeByContractCode($contractCode);
        $lastExternalNumber = $this->getLastCorrelativeFromExternal($contractCode, $lastLocalNumber);
        $lastNumber = max($lastLocalNumber, $lastExternalNumber);
        if ($lastNumber >= 99999) {
            return ['error' => 'Se alcanzo el limite maximo del correlativo para la sucursal.'];
        }

        $maxAttempts = 99999 - $lastNumber;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $newNumber = str_pad((string) ($lastNumber + $attempt), 5, '0', STR_PAD_LEFT);
            $newGuia = "C{$contractCode}A{$newNumber}BO";
            if (!$this->guiaAlreadyExists($newGuia)) {
                return ['guia' => $newGuia];
            }
        }

        return ['error' => 'No se pudo generar una guia unica.'];
    }

    public function generateGuia(Request $request)
    {
        $sucursaleId = $request->input('sucursale_id')
            ?? $request->input('sucursal_id')
            ?? $request->input('sucursal')
            ?? $request->input('sucursalId')
            ?? $request->input('sucursaleId');

        $tarifaId = $request->input('tarifa_id')
            ?? $request->input('tarifaId');

        $departamento = $request->input('departamento')
            ?? $request->input('destino');

        $guiaData = $this->buildGuiaData(
            $sucursaleId,
            $tarifaId,
            $request->input('reencaminamiento'),
            $departamento
        );

        if (empty($guiaData['guia'])) {
            return response()->json([
                'error' => $guiaData['error'] ?? 'No se pudo generar la guia.',
            ], 422);
        }

        return response()->json(['guia' => $guiaData['guia']]);
    }
    public function markAsEnCamino(Request $request, Solicitude $solicitude)
    {
        $solicitude->estado = 2; // Cambiar estado a "En camino"
        $carteroId = $this->resolveCarteroId($request, $solicitude);
        if ($carteroId !== null) {
            $solicitude->cartero_entrega_id = $carteroId;
            // Para EMS permitimos usar el mismo cartero como referencia de recogida cuando falta.
            if (($solicitude->tipo_correspondencia ?? null) === 'EMS' && empty($solicitude->cartero_recogida_id)) {
                $solicitude->cartero_recogida_id = $carteroId;
            }
        }
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
        $carteroId = $this->resolveCarteroId($request, $solicitude);
        if ($carteroId !== null) {
            $solicitude->cartero_entrega_id = $carteroId;
            if (($solicitude->tipo_correspondencia ?? null) === 'EMS' && empty($solicitude->cartero_recogida_id)) {
                $solicitude->cartero_recogida_id = $carteroId;
            }
        }
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
            $carteroId = $this->resolveCarteroId($request, $solicitude);
            if ($carteroId !== null) {
                $solicitude->cartero_recogida_id = $carteroId;
            }
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

    public function markAsDevueltoAlmacen(Request $request, Solicitude $solicitude)
    {
        try {
            $solicitude->estado = 5;

            if ($request->filled('observacion')) {
                $solicitude->observacion = $request->input('observacion');
            }

            $carteroId = $this->resolveCarteroId($request, $solicitude);
            if ($carteroId !== null) {
                $solicitude->cartero_recogida_id = $carteroId;
            }

            $solicitude->save();

            $carteroId = $solicitude->cartero_entrega_id ?? $solicitude->cartero_recogida_id;
            $encargadoId = $carteroId ? null : ($solicitude->encargado_id ?? $solicitude->encargado_regional_id);

            Evento::create([
                'accion' => 'Devuelto a almacen',
                'cartero_id' => $carteroId,
                'encargado_id' => $encargadoId,
                'descripcion' => 'Devuelto a almacen',
                'codigo' => $solicitude->guia,
                'fecha_hora' => now(),
            ]);

            return response()->json([
                'message' => 'Solicitud devuelta a almacen correctamente.',
                'solicitude' => $solicitude,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al devolver la solicitud a almacen.',
                'exception' => $e->getMessage(),
            ], 500);
        }
    }







    public function returnverificar(Request $request, Solicitude $solicitude)
    {
        try {
            $data = $request->validate([
                'imagen_devolucion' => 'nullable',
            ]);

            $solicitude->estado = 6;
            if (array_key_exists('imagen_devolucion', $data)) {
                $solicitude->imagen_devolucion = empty($data['imagen_devolucion']) ? null : $data['imagen_devolucion'];
            }
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
            $data = $request->validate([
                'fecha_devolucion' => 'nullable',
                'imagen_devolucion' => 'nullable',
                'firma_o' => 'nullable',
                'cartero_entrega_id' => 'nullable|integer|exists:carteros,id',
            ]);

            $solicitude->estado = 7;
            $solicitude->fecha_devolucion = $data['fecha_devolucion'] ?? now(); // Asigna la fecha actual si no se proporciona
            $solicitude->imagen_devolucion = empty($data['imagen_devolucion']) ? null : $data['imagen_devolucion'];
            $solicitude->firma_o = $data['firma_o'] ?? null;
            $solicitude->cartero_entrega_id = $data['cartero_entrega_id'] ?? null; // Asignar el cartero de entrega

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
            $carteroAuthId = Auth::guard('api_cartero')->id();
            $encargadoAuthId = Auth::guard('api_encargado')->id();

            $solicitude->estado = 8;
            $solicitude->fecha_envio_regional = $request->fecha_envio_regional ?? now();

            // Prioridad: cartero logueado -> cartero enviado por payload -> cartero existente en solicitud.
            $incomingCarteroId = $request->input('cartero_recogida_id')
                ?? $request->input('cartero_entrega_id')
                ?? $request->input('cartero_id');
            $actorCarteroId = $carteroAuthId
                ?: (($incomingCarteroId !== null && $incomingCarteroId !== '') ? (int) $incomingCarteroId : null)
                ?: ($solicitude->cartero_recogida_id ?: null);

            if ($actorCarteroId) {
                $solicitude->cartero_recogida_id = $actorCarteroId;
                $solicitude->cartero_entrega_id = null;
                $solicitude->encargado_id = null;
            } else {
                // Compatibilidad para flujo de encargado.
                if ($encargadoAuthId) {
                    $solicitude->encargado_id = $encargadoAuthId;
                } elseif ($request->filled('encargado_id')) {
                    $encargadoCandidate = (int) $request->input('encargado_id');
                    if ($encargadoCandidate > 0 && Encargado::whereKey($encargadoCandidate)->exists()) {
                        $solicitude->encargado_id = $encargadoCandidate;
                    }
                }
            }

            $solicitude->peso_v = $request->peso_v;
            $solicitude->nombre_d = $request->nombre_d;

            if ($request->has('reencaminamiento')) {
                $solicitude->reencaminamiento = $request->reencaminamiento;
            }

            $solicitude->save();

            Evento::create([
                'accion' => 'Transito a regional',
                'cartero_id' => $actorCarteroId ?: null,
                'encargado_id' => $actorCarteroId ? null : ($solicitude->encargado_id ?? $solicitude->encargado_regional_id),
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
            $carteroId = $this->resolveCarteroId($request, $solicitude);
            if ($carteroId !== null) {
                $solicitude->cartero_entrega_id = $carteroId; // Asignar el cartero de entrega
                if (($solicitude->tipo_correspondencia ?? null) === 'EMS' && empty($solicitude->cartero_recogida_id)) {
                    $solicitude->cartero_recogida_id = $carteroId;
                }
            }
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
        $carteroId = $this->resolveCarteroId($request, $solicitude);
        if ($carteroId !== null) {
            $solicitude->cartero_recogida_id = $carteroId;
        }
        $solicitude->cartero_entrega_id = null;
        $solicitude->encargado_regional_id = $this->resolveEncargadoRegionalId($request, $solicitude);
        $solicitude->peso_r = $request->peso_r; // Actualizar el peso
        $solicitude->nombre_d = $request->nombre_d; // Actualizar el nombre destinatario
        $solicitude->save();

        Evento::create([
            'accion' => 'Recibido en oficina',
            'cartero_id' => $solicitude->cartero_recogida_id,
            'descripcion' => 'Recibir envio en oficina de entrega',
            'codigo' => $solicitude->guia,
            'fecha_hora' => now(),
        ]);

        return response()->json($solicitude);
    }

    public function Rechazado(Request $request, Solicitude $solicitude)
    {
        try {
            $data = $request->validate([
                'observacion' => 'nullable|string|max:255',
                'fecha_d' => 'nullable',
                'imagen' => 'nullable',
            ]);

            // Optimizar la imagen utilizando el mÃ©todo optimizeImage

            // Asignar los valores al modelo
            $solicitude->estado = 11;
            $solicitude->observacion = $data['observacion'] ?? null;
            $solicitude->fecha_d = $data['fecha_d'] ?? now(); // Asigna la fecha actual si no se proporciona
            if (array_key_exists('imagen', $data)) {
                $incomingImage = $data['imagen'];
                $solicitude->imagen = !empty($incomingImage)
                    ? ($this->optimizeImage($incomingImage) ?? $solicitude->imagen)
                    : null;
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
            $solicitude->encargado_regional_id = $this->resolveEncargadoRegionalId($request, $solicitude);
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

    $guia = $this->normalizeGuia($request->guia);
    if (empty($guia)) {
        return response()->json([
            'error' => 'La guia es obligatoria.',
        ], 422);
    }
    if ($this->guiaAlreadyExists($guia)) {
        return $this->guiaDuplicadaResponse($guia);
    }

    $solicitude->guia        = $guia;
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
    $validator = Validator::make($request->all(), [
        'guia'              => 'required|max:255',
        'peso_v'            => 'nullable|numeric|min:0|max:99999',
        'provincia'         => 'nullable|string|max:255',
        'ciudad'            => 'nullable|string|max:255',
        'observacion'       => 'nullable|string|max:1000',
        'reencaminamiento'  => 'nullable|string|max:255',
        'cartero_recogida_id' => 'nullable|integer|exists:carteros,id',
    ]);

    if ($validator->fails()) {
        Log::warning('Validacion storeEMS fallida', [
            'errors' => $validator->errors()->toArray(),
            'payload' => $request->all(),
        ]);

        return response()->json([
            'message' => 'Los datos enviados no son validos.',
            'errors' => $validator->errors(),
        ], 422);
    }

    $solicitude = new Solicitude();

    // âœ… NULL explÃ­cito
    $solicitude->sucursale_id = null;
    $solicitude->tarifa_id    = null;

    // Cartero logueado (guard de cartero) o enviado por payload.
    $solicitude->cartero_recogida_id = $this->resolveCarteroId($request);

    // âœ… FECHA DE RECOJO (AHORA)
    $solicitude->fecha_recojo_c = now();

    // Datos EMS
    $solicitude->tipo_correspondencia = 'EMS';
    $guia = $this->normalizeGuia($request->guia);
    if (empty($guia)) {
        return response()->json([
            'error' => 'La guia es obligatoria.',
        ], 422);
    }
    if ($this->guiaAlreadyExists($guia)) {
        return $this->guiaDuplicadaResponse($guia);
    }

    $solicitude->guia = $guia;
    $solicitude->ciudad = $request->provincia ?? $request->ciudad;

    // âœ… PESOS IGUALES
    $peso = $request->input('peso_v');
    $pesoFormatted = ($peso !== null && $peso !== '') ? number_format((float) $peso, 3, '.', '') : null;
    $solicitude->peso_v = $pesoFormatted;
    $solicitude->peso_o = $pesoFormatted;

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

    $guia = $this->normalizeGuia($data['guia'] ?? null);
    if (empty($guia)) {
        return response()->json([
            'error' => 'La guia es obligatoria.',
        ], 422);
    }
    if ($this->guiaAlreadyExists($guia)) {
        return $this->guiaDuplicadaResponse($guia);
    }

    $solicitude->guia             = $guia;
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
private function normalizeCityKey(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    if (function_exists('mb_strtoupper')) {
        $value = mb_strtoupper($value, 'UTF-8');
    } else {
        $value = strtoupper($value);
    }

    $value = strtr($value, [
        'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A',
        'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
        'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
        'Ñ' => 'N',
    ]);

    $value = preg_replace('/[^A-Z0-9]+/', ' ', $value) ?? '';
    return preg_replace('/\s+/', ' ', trim($value)) ?? '';
}

private function getCityAliases(?string $city): array
{
    $cityKey = $this->normalizeCityKey($city);
    if ($cityKey === '') {
        return [];
    }

    $groups = [
        ['LA PAZ', 'LPB', 'LPZ'],
        ['COCHABAMBA', 'CBB'],
        ['SANTA CRUZ', 'SRZ', 'SCZ'],
        ['ORURO', 'ORU'],
        ['POTOSI', 'PTI'],
        ['TARIJA', 'TJA'],
        ['CHUQUISACA', 'SUCRE', 'SRE'],
        ['BENI', 'BEN', 'TRINIDAD', 'TDD'],
        ['PANDO', 'COBIJA', 'CIJ'],
    ];

    foreach ($groups as $group) {
        $normalizedGroup = array_map(fn($item) => $this->normalizeCityKey($item), $group);
        if (in_array($cityKey, $normalizedGroup, true)) {
            return array_values(array_unique($normalizedGroup));
        }

        foreach ($normalizedGroup as $alias) {
            if ($alias !== '' && str_contains($cityKey, $alias)) {
                return array_values(array_unique($normalizedGroup));
            }
        }
    }

    return [$cityKey];
}

private function getExternalUserIdDefault(): int
{
    $defaultUserId = (int) env('API_OTRO_BACKEND_USER_ID', 7);
    return $defaultUserId > 0 ? $defaultUserId : 7;
}

private function getExternalUserIdByCity(?string $city): int
{
    $defaultUserId = $this->getExternalUserIdDefault();
    $mapRaw = trim((string) env('API_OTRO_BACKEND_USER_ID_BY_CITY', ''));
    if ($mapRaw === '') {
        return $defaultUserId;
    }

    $cityAliases = $this->getCityAliases($city);
    if (empty($cityAliases)) {
        return $defaultUserId;
    }

    $pairs = preg_split('/\s*,\s*/', $mapRaw, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($pairs as $pair) {
        [$mapCity, $mapUserId] = array_pad(explode(':', $pair, 2), 2, null);
        $normalizedMapCity = $this->normalizeCityKey($mapCity);
        $userId = is_numeric($mapUserId) ? (int) $mapUserId : 0;

        if ($normalizedMapCity !== '' && $userId > 0 && in_array($normalizedMapCity, $cityAliases, true)) {
            return $userId;
        }
    }

    return $defaultUserId;
}

private function resolveExternalUserReference(Solicitude $solicitude): ?string
{
    if (!empty($solicitude->sucursale_id)) {
        $sucursal = Sucursale::query()
            ->select('id', 'origen', 'nombre')
            ->find($solicitude->sucursale_id);

        if ($sucursal) {
            $candidates = [
                $sucursal->origen ?? null,
                $sucursal->nombre ?? null,
            ];

            foreach ($candidates as $candidate) {
                $value = trim((string) $candidate);
                if ($value !== '') {
                    return $value;
                }
            }
        }
    }

    return null;
}

private function enviarAOtroBackend(Solicitude $solicitude): void
{
    try {
        if (! filter_var(env('API_OTRO_BACKEND_ENABLED', true), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $url = trim((string) env('API_OTRO_BACKEND_URL', ''));
        if ($url === '') {
            Log::warning('No se envio solicitud al otro backend: API_OTRO_BACKEND_URL vacio.', [
                'guia' => $solicitude->guia,
            ]);
            return;
        }

        $direccionRemitente = null;
        if (!empty($solicitude->direccion_id)) {
            $direccionModel = Direccione::find($solicitude->direccion_id);
            if ($direccionModel) {
                $direccionRemitente = trim((string) ($direccionModel->direccion_especifica ?? ''));
                if ($direccionRemitente === '') {
                    $direccionRemitente = trim((string) ($direccionModel->direccion ?? ''));
                }
                if ($direccionRemitente === '') {
                    $direccionRemitente = trim((string) ($direccionModel->nombre ?? ''));
                }
            } else {
                // Mantener mapeo solicitado: direccion_id -> direccion_r.
                $direccionRemitente = (string) $solicitude->direccion_id;
            }
        }
        if (empty($direccionRemitente)) {
            $direccionRemitente = 'N/D';
        }

        $destinoRaw = trim((string) ($solicitude->reencaminamiento ?? ''));
        $ciudadRaw = trim((string) ($solicitude->ciudad ?? ''));
        $destinosMap = [
            'LPB' => 'LA PAZ',
            'SRZ' => 'SANTA CRUZ',
            'CBB' => 'COCHABAMBA',
            'ORU' => 'ORURO',
            'PTI' => 'POTOSI',
            'TJA' => 'TARIJA',
            'SRE' => 'CHUQUISACA',
            'BEN' => 'BENI',
            'CIJ' => 'PANDO',
        ];
        $destino = strtoupper($destinoRaw);
        if (isset($destinosMap[$destino])) {
            $destino = $destinosMap[$destino];
        } elseif ($destino === '') {
            $destino = strtoupper($ciudadRaw);
            if (isset($destinosMap[$destino])) {
                $destino = $destinosMap[$destino];
            }
        }
        if ($destino === '') {
            $destino = 'LA PAZ';
        }

        $userReference = $this->resolveExternalUserReference($solicitude);
        $userReferenceSource = 'sucursal';

        if ($userReference === null || trim($userReference) === '') {
            $userReference = trim((string) ($solicitude->ciudad ?? ''));
            $userReferenceSource = 'ciudad';
        }
        if ($userReference === null || trim($userReference) === '') {
            $userReference = $destino;
            $userReferenceSource = 'reencaminamiento';
        }

        $externalUserId = $this->getExternalUserIdByCity($userReference);

        // Mapeo solicitado: direccion_especifica_d -> direccion_d.
        $direccionDestino = trim((string) ($solicitude->direccion_especifica_d ?? ''));
        if ($direccionDestino === '') {
            $direccionDestino = $solicitude->direccion_d ?: 'N/D';
        }

        // Formato externo exacto solicitado (con compatibilidad de campos requeridos en destino).
        // sucursale_id -> user_id
        // direccion_id -> direccion_r
        // guia -> codigo
        // remitente -> nombre_r
        // telefono -> telefono_r
        // contenido -> contenido
        // destinatario -> nombre_d
        // reencaminamiento -> destino
        // telefono_d -> telefono_d
        // direccion_d -> direccion_d
        // ciudad -> provincia
        $payload = [
            'user_id' => $externalUserId,
            'direccion_r' => $direccionRemitente,
            'codigo' => $solicitude->guia,
            'nombre_r' => $solicitude->remitente,
            'telefono_r' => $solicitude->telefono,
            'contenido' => $solicitude->contenido,
            'nombre_d' => $solicitude->destinatario,
            'destino' => $destino,
            'telefono_d' => $solicitude->telefono_d,
            'direccion_d' => $direccionDestino,
            'direccion' => $direccionDestino,
            'provincia' => $solicitude->ciudad,
            'estados_id' => 28,
        ];

        $timeout = (int) env('API_OTRO_BACKEND_TIMEOUT', 20);
        $client = Http::timeout($timeout)->acceptJson();
        $verifySsl = filter_var(env('API_OTRO_BACKEND_VERIFY_SSL', true), FILTER_VALIDATE_BOOLEAN);
        if (!$verifySsl) {
            $client = $client->withoutVerifying();
        }

        $token = trim((string) env('API_OTRO_BACKEND_TOKEN', ''));
        if ($token !== '') {
            $client = $client->withToken($token);
        }

        $apiKeyHeader = trim((string) env('API_OTRO_BACKEND_API_KEY_HEADER', ''));
        $apiKeyValue = trim((string) env('API_OTRO_BACKEND_API_KEY', ''));
        if ($apiKeyHeader !== '' && $apiKeyValue !== '') {
            $client = $client->withHeaders([$apiKeyHeader => $apiKeyValue]);
        }

        $effectiveUrl = $url;
        $response = $client->post($effectiveUrl, $payload);

        if (
            !$response->successful()
            && $response->status() === 400
            && stripos((string) $response->body(), 'plain HTTP request was sent to HTTPS port') !== false
            && str_starts_with($effectiveUrl, 'http://')
        ) {
            $effectiveUrl = 'https://' . substr($effectiveUrl, 7);
            $response = $client->post($effectiveUrl, $payload);
        }

        if (! $response->successful()) {
            Log::error('Error al enviar solicitud al otro backend', [
                'guia' => $solicitude->guia,
                'url' => $effectiveUrl,
                'status' => $response->status(),
                'response' => $response->body(),
                'payload' => $payload,
                'user_reference' => $userReference,
                'user_reference_source' => $userReferenceSource,
            ]);
        } else {
            $responseJson = $response->json();
            $externalCode = $this->resolveExternalGeneratedCode($responseJson);
            if ($externalCode !== null) {
                $this->syncLocalGuideWithExternalCode($solicitude, $externalCode);
            }

            Log::info('Solicitud enviada correctamente al otro backend', [
                'guia' => $solicitude->guia,
                'url' => $effectiveUrl,
                'status' => $response->status(),
                'response' => $responseJson,
                'codigo_externo_detectado' => $externalCode,
                'user_reference' => $userReference,
                'user_reference_source' => $userReferenceSource,
            ]);
        }
    } catch (\Throwable $e) {
        Log::error('Excepcion al enviar solicitud al otro backend', [
            'guia' => $solicitude->guia,
            'url' => env('API_OTRO_BACKEND_URL'),
            'error' => $e->getMessage(),
        ]);
    }
}
}
