<?php

namespace App\Http\Controllers;

use App\Models\Solicitude;
use App\Models\Evento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardEncargadoController extends Controller
{
    public function solicitudesHoy()
    {
        $hoy = Carbon::today()->toDateString();

        $totalSolicitudesHoy = Solicitude::whereDate('fecha', $hoy)->count();

        return response()->json(['total' => $totalSolicitudesHoy]);
    }

    public function solicitudesEstado0()
    {
        return $this->solicitudesPorEstado(0);
    }

    public function solicitudesEstado1()
    {
        $totalSolicitudes = Solicitude::where('estado', 1)->count();
        return response()->json(['total' => $totalSolicitudes]);
    }

    public function solicitudesEstado2()
    {
        return $this->solicitudesPorEstado(2);
    }

    public function solicitudesEstado3()
    {
        $totalSolicitudes = Solicitude::whereIn('estado', [3, 4])->count(); // Cambiado para reconocer estado 3 y 4
    
        return response()->json(['total' => $totalSolicitudes]);
    }
    

    public function solicitudesEstado5()
{
    // Obtener todas las solicitudes que tienen un evento de "Recojo"
    $totalSolicitudes = Evento::where('accion', 'Recojo')
                              ->distinct('codigo') // Evita contar la misma solicitud varias veces
                              ->count('codigo'); // Contar las solicitudes (asumiendo que 'codigo' es único para cada solicitud)

    // Retornar el total en formato JSON
    return response()->json(['total' => $totalSolicitudes]);
}

    

    private function solicitudesPorEstado($estado)
    {
        $totalSolicitudes = Solicitude::where('estado', $estado)->count();

        return response()->json(['total' => $totalSolicitudes]);
    }

    public function solicitudesEstado1Hoy()
    {
        $hoy = Carbon::today()->toDateString();

        $totalSolicitudes = Solicitude::where('estado', 1)
                            ->whereDate('fecha', $hoy)
                            ->count();

        return response()->json(['total' => $totalSolicitudes]);
    }

    public function solicitudesEstado2Hoy()
    {
        $hoy = Carbon::today()->toDateString();

        $totalSolicitudes = Solicitude::where('estado', 2)
                            ->whereDate('fecha', $hoy)
                            ->count();

        return response()->json(['total' => $totalSolicitudes]);
    }

    public function solicitudesEstado3Hoy()
    {
        $hoy = Carbon::today()->toDateString();
    
        $totalSolicitudes = Solicitude::whereIn('estado', [3, 4]) // Cambiado para reconocer estado 3 y 4
                            ->whereDate('fecha', $hoy)
                            ->count();
    
        return response()->json(['total' => $totalSolicitudes]);
    }
    

    public function solicitudesEstado5Hoy()
    {
        // Obtener la fecha de hoy
        $hoy = Carbon::today()->toDateString();
    
        // Contar los eventos con la acción "Recojo" que ocurrieron hoy
        $totalSolicitudes = Evento::where('accion', 'Recojo') // Aquí "Recojo" debe ir entre comillas
                                ->whereDate('fecha_hora', $hoy) // Asegúrate de que estás usando la columna correcta para la fecha
                                ->count();
    
        // Retornar el total en formato JSON
        return response()->json(['total' => $totalSolicitudes]);
    }
    
}
