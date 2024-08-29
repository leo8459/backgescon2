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
use Illuminate\Support\Facades\Auth;


use Illuminate\Http\Request;

class DashboardEmpresaController extends Controller
{
    public function getSucursalesByEmpresa()
    {
        // Get the logged-in company's ID
        $empresaId = Auth::user()->empresa_id;

        // Retrieve all sucursales that belong to the logged-in company's ID
        $sucursales = Sucursale::where('empresa_id', $empresaId)->get();

        return response()->json($sucursales);
    }
}
