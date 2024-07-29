<?php

namespace App\Http\Controllers;
use App\Models\Alquilere;
use App\Models\Cliente;
use App\Models\Casilla;
use App\Models\Seccione;
use App\Models\User;
use App\Models\Precio;
use App\Models\Categoria;


use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function patito() {
        return [
            "alquileres" => Alquilere::where('estado', 1)->count(),
            "users" => User::where('estado', 1)->count(),
            "clientes" => Cliente::where('estado', 1)->count(),
            "casillas" => Casilla::where('estado', 1)->count(),
            "precios" => Precio::where('estado', 1)->count(),
            "categorias" => Categoria::where('nombre', 'Pequeña')->count(),
            "pequeñas" => Casilla::where('categoria_id', 1)->count(),
            "medianas" => Casilla::where('categoria_id', 2)->count(),
            "gabetas" => Casilla::where('categoria_id', 3)->count(),
            "cajones" => Casilla::where('categoria_id', 4)->count(),
            "pequeñaslibres1" => Casilla::where('categoria_id', 1)->where('estado', 1)->count(),
            "medianaslibres" => Casilla::where('categoria_id', 2)->where('estado', 1)->count(),
            "gabetaslibres" => Casilla::where('categoria_id', 3)->where('estado', 1)->count(),
            "cajoneslibres" => Casilla::where('categoria_id', 4)->where('estado', 1)->count(),
            "pequeñasocupadas" => Alquilere::whereHas('casilla', function ($query) {
                $query->where('categoria_id', 1);
            })->where('estado', 1)->count(),


            "medianasocupadas" => Alquilere::whereHas('casilla', function ($query) {
                $query->where('categoria_id', 2);
            })->where('estado', 1)->count(),


            "gabetaocupadas" => Alquilere::whereHas('casilla', function ($query) {
                 $query->where('categoria_id', 3);
                        })->where('estado', 1)->count(),


            "cajonocupadas" => Alquilere::whereHas('casilla', function ($query) {
                $query->where('categoria_id', 4);
                                    })->where('estado', 1)->count(),
                        
                        
            


           
        ];
    }
    
}
