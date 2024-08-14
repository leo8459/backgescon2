<?php

namespace App\Http\Controllers;

use App\Models\Solicitude;
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
        $totalSolicitudes = Solicitude::where('estado', 5)->count();

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
        $hoy = Carbon::today()->toDateString();

        $totalSolicitudes = Solicitude::where('estado', 5)
                            ->whereDate('fecha', $hoy)
                            ->count();

        return response()->json(['total' => $totalSolicitudes]);
    }
}
