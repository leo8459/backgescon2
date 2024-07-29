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
        return tarifa::with(['Sucursale'])->get();
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
        $tarifa->servicio = $request->servicio;
        // $tarifa->tiempo = $request->tiempo;
        $tarifa->servicioprov = $request->servicioprov;
        // $tarifa->tiempoprov = $request->tiempoprov;
        $tarifa->servicioexpress = $request->servicioexpress;
        // $tarifa->tiempoexpress = $request->tiempoexpress;
        $tarifa->sucursale_id = $request->sucursale_id;
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
        $tarifa->servicio = $request->servicio;
        // $tarifa->tiempo = $request->tiempo;
        $tarifa->servicioprov = $request->servicioprov;
        // $tarifa->tiempoprov = $request->tiempoprov;
        $tarifa->servicioexpress = $request->servicioexpress;
        // $tarifa->tiempoexpress = $request->tiempoexpress;

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
