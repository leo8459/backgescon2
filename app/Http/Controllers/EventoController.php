<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Http\Request;

class EventoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Evento::all();

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
}
