<?php

namespace App\Http\Controllers;

use App\Models\DetalleSolicitude;
use App\Models\Solicitude;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SolicitudeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
  
        public function index()
    {
        $solicitudes = Solicitude::with(['carteroRecogida', 'carteroEntrega', 'sucursale'])->get();
        return response()->json($solicitudes);
    }

    

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
{
    $solicitude = new Solicitude();
    $solicitude->cartero_recogida_id = $request->cartero_recogida_id?? null;
    $solicitude->cartero_entrega_id = $request->cartero_entrega_id?? null;
    $solicitude->sucursale_id = $request->sucursale_id;
    $solicitude->guia = $request->guia;
    $solicitude->peso_o = $request->peso_o;
    $solicitude->peso_v = $request->peso_v;
    $solicitude->remitente = $request->remitente;
    $solicitude->direccion = $request->direccion;
    $solicitude->telefono = $request->telefono;
    $solicitude->contenido = $request->contenido;
    $solicitude->fecha = $request->fecha;
    $solicitude->firma_o = $request->firma_o;
    $solicitude->destinatario = $request->destinatario;
    $solicitude->telefono_d = $request->telefono_d;
    $solicitude->direccion_d = $request->direccion_d;
    $solicitude->ciudad = $request->ciudad;
    $solicitude->firma_d = $request->firma_d;
    $solicitude->nombre_d = $request->nombre_d;
    $solicitude->ci_d = $request->ci_d;
    $solicitude->fecha_d = $request->fecha_d;
    $solicitude->estado = $request->estado ?? 1;
    $solicitude->zona_r = $request->zona_r;
    $solicitude->zona_d = $request->zona_d;

    $solicitude->save();

    return $solicitude;
}



    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Solicitude  $solicitude
     * @return \Illuminate\Http\Response
     */
    public function show(Solicitude $solicitude)
    {
        $solicitude->sucursale = $solicitude->sucursale;

        return $solicitude;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Solicitude  $solicitude
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Solicitude $solicitude)
    {
        $solicitude->sucursale_id = $request->sucursale_id;
        $solicitude->cartero_recogida_id = $request->cartero_recogida_id?? null;
        $solicitude->cartero_entrega_id = $request->cartero_entrega_id?? null;
        $solicitude->guia = $request->guia;
        $solicitude->peso_o = $request->peso_o;
        $solicitude->peso_v = $request->peso_v;
        $solicitude->remitente = $request->remitente;
        $solicitude->direccion = $request->direccion;
        $solicitude->telefono = $request->telefono;
        $solicitude->contenido = $request->contenido;
        $solicitude->fecha = $request->fecha;
        $solicitude->firma_o = $request->firma_o;
        $solicitude->destinatario = $request->destinatario;
        $solicitude->telefono_d = $request->telefono_d;
        $solicitude->direccion_d = $request->direccion_d;
        $solicitude->ciudad = $request->ciudad;
        $solicitude->firma_d = $request->firma_d;
        $solicitude->nombre_d = $request->nombre_d;
        $solicitude->ci_d = $request->ci_d;
        $solicitude->fecha_d = $request->fecha_d;
        $solicitude->estado = $request->estado;
        $solicitude->zona_r = $request->zona_r;
        $solicitude->zona_d = $request->zona_d;
        $solicitude->save();
    
        return $solicitude;
    }
    


    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Solicitude  $solicitude
     * @return \Illuminate\Http\Response
     */
    public function destroy(Solicitude $solicitude)
    {
        $solicitude->estado = 0;
        $solicitude->save();
        return $solicitude;
    }
    public function markAsEnCamino(Request $request, Solicitude $solicitude)
    {    $solicitude->estado = 2; // Cambiar estado a "En camino"
        $solicitude->cartero_entrega_id = $request->cartero_entrega_id; // Asignar el cartero logueado
        $solicitude->save();


            return $solicitude;
    }
    public function markAsEntregado(Request $request, Solicitude $solicitude)
    {
        $solicitude->estado = 2; // Cambiar estado a "En camino"
        $solicitude->cartero_entrega_id = $request->cartero_entrega_id; // Asignar el cartero de entrega
        $solicitude->peso_v = $request->peso_v; // Actualizar el peso
        $solicitude->save();
    
        return response()->json($solicitude);
    }
    public function marcarRecogido(Request $request, $id)
    {
        try {
            // Encuentra la solicitud por ID
            $solicitude = Solicitude::findOrFail($id);

            // Cambia el estado a 5 y guarda el cartero_entrega_id del request
            $solicitude->estado = 5;
            $solicitude->cartero_recogida_id = $request->input('cartero_recogida_id');

            // Guarda los cambios
            $solicitude->save();

            return response()->json(['message' => 'Solicitud marcada como recogida exitosamente.'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al marcar la solicitud como recogida.'], 500);
        }
    }

}
