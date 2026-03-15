<?php

namespace App\Http\Controllers;

use App\Models\Solicitude;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EncargadoRecogidoListController extends Controller
{
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

        $perPage = (int) $request->input('per_page', 100);
        if ($perPage <= 0) {
            $perPage = 100;
        }
        $perPage = min($perPage, 200);

        return response()->json(
            $query
                ->orderByDesc('fecha')
                ->orderByDesc('id')
                ->paginate($this->perPage($request))
                ->appends($request->query())
        );
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

        return response()->json(
            $query
                ->orderByDesc('fecha')
                ->orderByDesc('id')
                ->paginate($this->perPage($request))
                ->appends($request->query())
        );
    }
}
