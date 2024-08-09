<?php

namespace App\Http\Controllers;

use App\Models\Sucursale;
use App\Models\Solicitude;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class DashboardAdminController extends Controller
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
        return $this->solicitudesPorEstado(3);
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

        $totalSolicitudes = Solicitude::where('estado', 3)
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
    
    public function totalNombreD()
    {
        $totalGastado = Solicitude::whereNotNull('nombre_d')
            ->sum(DB::raw('CAST(nombre_d AS NUMERIC)'));

        return response()->json(['total_gastado' => $totalGastado]);
    }

    public function sucursalesConContrato()
    {
        $sucursales = Sucursale::where('estado', 1)->get();
        return response()->json($sucursales);
    }

    public function sucursalesSinContrato()
    {
        $sucursales = Sucursale::where('estado', 0)->get();
        return response()->json($sucursales);
    }
}
