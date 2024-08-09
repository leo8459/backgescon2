<?php

namespace App\Http\Controllers;

use App\Models\Solicitude;
use App\Models\Sucursale;
use App\Models\Tarifa;
use App\Models\Seccione;
use App\Models\User;
use App\Models\Precio;
use App\Models\Categoria;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;


use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function todasSolicitudes(Request $request)
    {
        $sucursalId = $request->query('sucursal_id');

        $query = Solicitude::query();

        if ($sucursalId) {
            $query->where('sucursale_id', $sucursalId);
        }

        $totalSolicitudes = $query->count();

        return response()->json(['total' => $totalSolicitudes]);
    }

    public function totalNombreD(Request $request)
    {
        $sucursalId = $request->query('sucursal_id');

        $query = Solicitude::whereNotNull('nombre_d');

        if ($sucursalId) {
            $query->where('sucursale_id', $sucursalId);
        }

        $totalGastado = $query->sum(DB::raw('CAST(nombre_d AS NUMERIC)'));

        return response()->json(['total_gastado' => $totalGastado]);
    }
    public function solicitudesHoy(Request $request)
    {
        $sucursalId = $request->query('sucursal_id');
        $hoy = Carbon::today()->toDateString();

        $query = Solicitude::whereDate('fecha', $hoy);

        if ($sucursalId) {
            $query->where('sucursale_id', $sucursalId);
        }

        $totalSolicitudesHoy = $query->count();

        return response()->json(['total' => $totalSolicitudesHoy]);
    }
    public function solicitudesEstado1(Request $request)
    {
        return $this->solicitudesPorEstado($request, 1);
    }

    public function solicitudesEstado3(Request $request)
    {
        return $this->solicitudesPorEstado($request, 3);
    }

    public function solicitudesEstado0(Request $request)
    {
        return $this->solicitudesPorEstado($request, 0);
    }
    
    public function solicitudesEstado2(Request $request)
    {
        return $this->solicitudesPorMultiplesEstados($request, [2, 5]);
    }
    private function solicitudesPorEstado(Request $request, $estado)
    {
        $sucursalId = $request->query('sucursal_id');
    
        $query = Solicitude::where('estado', $estado);
    
        if ($sucursalId) {
            $query->where('sucursale_id', $sucursalId);
        }
    
        $totalSolicitudes = $query->count();
    
        return response()->json(['total' => $totalSolicitudes]);
    }
    
    private function solicitudesPorMultiplesEstados(Request $request, $estados)
    {
        $sucursalId = $request->query('sucursal_id');
    
        $query = Solicitude::whereIn('estado', $estados);
    
        if ($sucursalId) {
            $query->where('sucursale_id', $sucursalId);
        }
    
        $totalSolicitudes = $query->count();
    
        return response()->json(['total' => $totalSolicitudes]);
    }
}
