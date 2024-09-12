<?php

namespace App\Http\Controllers;

use App\Models\Tarifa;
use Illuminate\Http\Request;
use App\Models\Empresa;
use App\Models\Sucursale;

class TarifaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return Tarifa::with(['sucursale'])->get();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $tarifa = new Tarifa();
        $tarifa->departamento = $request->departamento;
        $tarifa->servicio = $request->servicio;//nacional
        $tarifa->precio = $request->precio;//nacional
        $tarifa->precio_extra = $request->precio_extra;//nacional
        $tarifa->provincia = $request->provincia;//nacional
        $tarifa->retencion = $request->retencion;//nacional
        $tarifa->dias_entrega = $request->dias_entrega;//nacional
        $tarifa->descuento = $request->descuento;//nacional
        // $tarifa->hora_pedido = $request->hora_pedido;//nacional
        $tarifa->estado = $request->estado ?? 1;

        $tarifa->sucursale_id = $request->sucursale_id;//nacional
        $tarifa->save();
        return $tarifa;

    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Tarifa  $Tarifa
     * @return \Illuminate\Http\Response
     */
    public function show(Tarifa $tarifa)
    {
        return $tarifa;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Tarifa  $Tarifa
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Tarifa $tarifa)
    {
        $tarifa->departamento = $request->departamento;
        $tarifa->servicio = $request->servicio;//nacional
        $tarifa->precio = $request->precio;//nacional
        $tarifa->precio_extra = $request->precio_extra;//nacional
        $tarifa->provincia = $request->provincia;//nacional
        $tarifa->retencion = $request->retencion;//nacional
        $tarifa->dias_entrega = $request->dias_entrega;//nacional
        $tarifa->descuento = $request->descuento;//nacional
        // $tarifa->hora_pedido = $request->hora_pedido;//nacional

        $tarifa->sucursale_id = $request->sucursale_id;
        $tarifa->save();
        return $tarifa;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Tarifa  $Tarifa
     * @return \Illuminate\Http\Response
     */
    public function destroy(Tarifa $tarifa)
    {
        $tarifa->delete();
        return $tarifa;
    }
    public function markAsInactive(Tarifa $tarifa)
    {
        $tarifa->estado = 2; // Cambiamos el estado a 2 (inactivo)
        $tarifa->save(); // Guardamos los cambios en la base de datos
    
        return response()->json([
            'message' => 'Estado cambiado a inactivo',
            'tarifa' => $tarifa
        ]);
    }
    
}
