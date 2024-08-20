<?php

namespace App\Http\Controllers;

use App\Models\tarifa;
use Illuminate\Http\Request;
use App\Models\empresa;
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
        return tarifa::with(['sucursale'])->get();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $tarifa = new tarifa();
        $tarifa->departamento = $request->departamento;
        $tarifa->servicio = $request->servicio;//nacional
        $tarifa->precio = $request->precio;//nacional
        $tarifa->precio_extra = $request->precio_extra;//nacional
        $tarifa->provincia = $request->provincia;//nacional
        $tarifa->retencion = $request->retencion;//nacional
        $tarifa->dias_entrega = $request->dias_entrega;//nacional

        $tarifa->sucursale_id = $request->sucursale_id;//nacional
        $tarifa->save();
        return $tarifa;

    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\tarifa  $tarifa
     * @return \Illuminate\Http\Response
     */
    public function show(tarifa $tarifa)
    {
        return $tarifa;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\tarifa  $tarifa
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, tarifa $tarifa)
    {
        $tarifa->departamento = $request->departamento;
        $tarifa->servicio = $request->servicio;//nacional
        $tarifa->precio = $request->precio;//nacional
        $tarifa->precio_extra = $request->precio_extra;//nacional
        $tarifa->provincia = $request->provincia;//nacional
        $tarifa->retencion = $request->retencion;//nacional
        $tarifa->dias_entrega = $request->dias_entrega;//nacional

        $tarifa->sucursale_id = $request->sucursale_id;
        $tarifa->save();
        return $tarifa;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\tarifa  $tarifa
     * @return \Illuminate\Http\Response
     */
    public function destroy(tarifa $tarifa)
    {
        $tarifa->delete();
        return $tarifa;
    }
}
