<?php

namespace App\Http\Controllers;

use App\Models\Direccione;
use Illuminate\Http\Request;

class DireccioneController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Direccione::with(['sucursale'])->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $direccione = new Direccione();
        $direccione->direccion = $request->direccion;
        $direccione->direccion_especifica = $request->direccion_especifica;
        $direccione->zona = $request->zona;
        $direccione->sucursale_id = $request->sucursale_id;
        // Guardar la solicitud en la base de datos
        $direccione->save();
        // Devolver la respuesta con la solicitud guardada
        return $direccione;
    }

    /**
     * Display the specified resource.
     */
    public function show(Direccione $direccione)
    {
        return $direccione;

    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Direccione $direccione)
    {
        
        $direccione->direccion = $request->direccion;
        $direccione->direccion_especifica = $request->direccion_especifica;
        $direccione->zona = $request->zona;
        $direccione->sucursale_id = $request->sucursale_id;
        $direccione->save();

        return $direccione;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Direccione $direccione)
    {
        $direccione->delete();
        return $direccione;
    }
}
