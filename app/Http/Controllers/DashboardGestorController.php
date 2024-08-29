<?php

namespace App\Http\Controllers;

use App\Models\Sucursale;
use App\Models\Solicitude;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class DashboardGestorController extends Controller
{
    
    
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
