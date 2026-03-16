<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Http\Request;

class EventoController extends Controller
{
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

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $eventos = Evento::with(['cartero', 'sucursale', 'encargado'])->get();
        return response()->json($eventos);

    }

    public function indexCartero(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        $query = Evento::with(['cartero', 'sucursale', 'encargado']);

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($searchQuery) use ($like) {
                $searchQuery
                    ->where('codigo', 'like', $like)
                    ->orWhere('accion', 'like', $like)
                    ->orWhere('descripcion', 'like', $like)
                    ->orWhere('fecha_hora', 'like', $like)
                    ->orWhereHas('sucursale', function ($sucursaleQuery) use ($like) {
                        $sucursaleQuery->where('nombre', 'like', $like);
                    })
                    ->orWhereHas('cartero', function ($carteroQuery) use ($like) {
                        $carteroQuery->where('nombre', 'like', $like);
                    })
                    ->orWhereHas('encargado', function ($encargadoQuery) use ($like) {
                        $encargadoQuery->where('nombre', 'like', $like);
                    });
            });
        }

        $query
            ->orderByDesc('fecha_hora')
            ->orderByDesc('id');

        return $this->paginateForCartero($query, $request);
    }

    public function indexEncargado(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        $query = Evento::with(['cartero', 'sucursale', 'encargado']);

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($searchQuery) use ($like) {
                $searchQuery
                    ->where('codigo', 'like', $like)
                    ->orWhere('accion', 'like', $like)
                    ->orWhere('descripcion', 'like', $like)
                    ->orWhere('fecha_hora', 'like', $like)
                    ->orWhereHas('sucursale', function ($sucursaleQuery) use ($like) {
                        $sucursaleQuery->where('nombre', 'like', $like);
                    })
                    ->orWhereHas('cartero', function ($carteroQuery) use ($like) {
                        $carteroQuery->where('nombre', 'like', $like);
                    })
                    ->orWhereHas('encargado', function ($encargadoQuery) use ($like) {
                        $encargadoQuery->where('nombre', 'like', $like);
                    });
            });
        }

        $query
            ->orderByDesc('fecha_hora')
            ->orderByDesc('id');

        return $this->paginateForCartero($query, $request);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        dd($request->all()); // Verifica que los datos lleguen correctamente
        $evento = new Evento();
          $evento->accion = $request->accion;
          $evento->descripcion = $request->descripcion;
          $evento->codigo = $request->codigo;
          $evento->fecha_hora = $request->fecha_hora;
          $evento->usercartero = $request->usercartero;

      
          $evento->save();
      
          return $evento;
    }

    /**
     * Display the specified resource.
     */
    public function show(Evento $evento)
    {
        return $evento;

    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Evento $evento)
    {
          // Crea una nueva instancia de usuario
          $evento->accion = $request->accion;
          $evento->descripcion = $request->descripcion;
          $evento->codigo = $request->codigo;
          $evento->fecha_hora = $request->fecha_hora;
          $evento->usercartero = $request->usercartero;

      
          $evento->save();
      
          return $evento;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Evento $evento)
    {
        $evento->estado = 0;
        $evento->save();
        return $evento;
    }
    public function eventosPorSucursal($sucursale_id)
    {
        $eventos = Evento::with(['cartero', 'encargado'])
            ->where('sucursale_id', $sucursale_id)
            ->get();
    
        return response()->json($eventos);
    }
    

    

}
