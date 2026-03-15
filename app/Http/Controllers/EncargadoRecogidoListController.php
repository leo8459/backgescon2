<?php

namespace App\Http\Controllers;

use App\Models\Solicitude;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EncargadoRecogidoListController extends Controller
{
    protected function parseFecha(?string $value): ?Carbon
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $formats = [
            'd/m/Y H:i:s',
            'd/m/Y H:i',
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'Y-m-d\TH:i:s',
            'Y-m-d\TH:i',
        ];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $raw, 'America/La_Paz');
            } catch (\Throwable $e) {
            }
        }

        try {
            return Carbon::parse($raw, 'America/La_Paz');
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function enrichSolicitude(Solicitude $solicitude): Solicitude
    {
        $hasCiudad = trim((string) ($solicitude->ciudad ?? '')) !== '';
        $greenLimitHours = $hasCiudad ? 92 : 48;
        $recojoDate = $this->parseFecha($solicitude->fecha_recojo_c);

        $rowStatusClass = '';
        $horasRestantesAlerta = null;

        if ($recojoDate) {
            $hoursSinceRecojo = max(0, $recojoDate->diffInSeconds(Carbon::now('America/La_Paz')) / 3600);
            $horasRestantes = (int) ceil($greenLimitHours - $hoursSinceRecojo);

            if ($hasCiudad) {
                if ($hoursSinceRecojo <= 92) {
                    $rowStatusClass = 'row-green';
                } elseif ($hoursSinceRecojo <= 114) {
                    $rowStatusClass = 'row-orange';
                } else {
                    $rowStatusClass = 'row-red';
                }
            } else {
                if ($hoursSinceRecojo <= 48) {
                    $rowStatusClass = 'row-green';
                } elseif ($hoursSinceRecojo <= 72) {
                    $rowStatusClass = 'row-orange';
                } else {
                    $rowStatusClass = 'row-red';
                }
            }

            if ($horasRestantes <= 10 && $horasRestantes > 0) {
                $horasRestantesAlerta = $horasRestantes;
            }
        }

        $solicitude->setAttribute('row_status_class', $rowStatusClass);
        $solicitude->setAttribute('horas_restantes_alerta', $horasRestantesAlerta);

        return $solicitude;
    }

    protected function paginateResponse($query, Request $request, string $orderColumn = 'fecha')
    {
        $paginator = $query
            ->orderByDesc($orderColumn)
            ->orderByDesc('id')
            ->paginate($this->perPage($request))
            ->appends($request->query());

        $paginator->getCollection()->transform(function ($item) {
            return $this->enrichSolicitude($item);
        });

        return response()->json($paginator);
    }

    protected function perPage(Request $request, int $default = 100): int
    {
        $perPage = (int) $request->input('per_page', $default);
        if ($perPage <= 0) {
            $perPage = $default;
        }

        return min($perPage, 200);
    }

    protected function applySearch($query, ?string $search): void
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

    public function index(Request $request)
    {
        $encargado = Auth::guard('api_encargado')->user();
        $departamento = $encargado->departamento ?? null;

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
                    $subQuery->whereIn('estado', [5, 10, 11, 13])
                        ->whereRaw("UPPER(COALESCE(tipo_correspondencia, '')) = ?", ['EMS'])
                        ->whereNull('sucursale_id')
                        ->whereNull('tarifa_id');
                });

                return;
            }

            $query->whereRaw('1 = 0');
        });

        $this->applySearch($query, $request->input('search'));

        return $this->paginateResponse($query, $request, 'fecha');
    }

    public function indexEncamino(Request $request)
    {
        $query = Solicitude::with([
            'carteroRecogida',
            'carteroEntrega',
            'sucursale',
            'tarifa',
            'direccion',
            'encargado',
            'encargadoregional',
            'transporte',
        ])->whereIn('estado', [2, 9]);

        $this->applySearch($query, $request->input('search'));

        return $this->paginateResponse($query, $request, 'fecha');
    }

    public function indexEntregado(Request $request)
    {
        $encargado = Auth::guard('api_encargado')->user();
        $departamento = $encargado->departamento ?? null;

        $query = Solicitude::with([
            'carteroRecogida',
            'carteroEntrega',
            'sucursale',
            'tarifa',
            'direccion',
            'encargado',
            'encargadoregional',
            'transporte',
        ])->whereIn('estado', [3, 10]);

        if (!empty($departamento)) {
            $query->whereHas('carteroEntrega', function ($carteroQuery) use ($departamento) {
                $carteroQuery->where('departamento_cartero', $departamento);
            });
        } else {
            $query->whereRaw('1 = 0');
        }

        $this->applySearch($query, $request->input('search'));

        return $this->paginateResponse($query, $request, 'fecha_d');
    }

    public function indexVerificado(Request $request)
    {
        $encargado = Auth::guard('api_encargado')->user();
        $departamento = $encargado->departamento ?? null;

        $query = Solicitude::with([
            'carteroRecogida',
            'carteroEntrega',
            'sucursale',
            'tarifa',
            'direccion',
            'encargado',
            'encargadoregional',
            'transporte',
        ])->where('estado', 4);

        if (!empty($departamento)) {
            $query->whereHas('carteroEntrega', function ($carteroQuery) use ($departamento) {
                $carteroQuery->where('departamento_cartero', $departamento);
            });
        } else {
            $query->whereRaw('1 = 0');
        }

        $this->applySearch($query, $request->input('search'));

        return $this->paginateResponse($query, $request, 'fecha_d');
    }

    public function indexRegional(Request $request)
    {
        $encargado = Auth::guard('api_encargado')->user();
        $departamento = $encargado->departamento ?? null;

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

        $this->applySearch($query, $request->input('search'));

        return $this->paginateResponse($query, $request, 'fecha_envio_regional');
    }

    public function indexRecibidoRegional(Request $request)
    {
        $encargado = Auth::guard('api_encargado')->user();
        $departamento = $encargado->departamento ?? null;

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

        $this->applySearch($query, $request->input('search'));

        return $this->paginateResponse($query, $request, 'fecha_envio_regional');
    }

    public function indexVerificarDevuelto(Request $request)
    {
        $query = Solicitude::with([
            'carteroRecogida',
            'carteroEntrega',
            'sucursale',
            'tarifa',
            'direccion',
            'encargado',
            'encargadoregional',
            'transporte',
        ])->where('estado', 11)
            ->where(function ($subQuery) {
                $subQuery
                    ->whereNotNull('sucursale_id')
                    ->orWhere(function ($emsQuery) {
                        $emsQuery
                            ->whereRaw("UPPER(COALESCE(tipo_correspondencia, '')) = ?", ['EMS'])
                            ->whereNull('sucursale_id')
                            ->whereNull('tarifa_id');
                    });
            });

        $this->applySearch($query, $request->input('search'));

        return $this->paginateResponse($query, $request, 'fecha_d');
    }

    public function indexRechazado(Request $request)
    {
        $query = Solicitude::with([
            'carteroRecogida',
            'carteroEntrega',
            'sucursale',
            'tarifa',
            'direccion',
            'encargado',
            'encargadoregional',
            'transporte',
        ])->where('estado', 6);

        $this->applySearch($query, $request->input('search'));

        return $this->paginateResponse($query, $request, 'fecha');
    }

    public function indexDevuelto(Request $request)
    {
        $query = Solicitude::with([
            'carteroRecogida',
            'carteroEntrega',
            'sucursale',
            'tarifa',
            'direccion',
            'encargado',
            'encargadoregional',
            'transporte',
        ])->where('estado', 7);

        $this->applySearch($query, $request->input('search'));

        return $this->paginateResponse($query, $request, 'fecha_devolucion');
    }
}
