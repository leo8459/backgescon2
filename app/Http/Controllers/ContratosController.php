<?php

namespace App\Http\Controllers;

use App\Models\Contratos;
use Illuminate\Http\Request;
use App\Models\Gestore;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\LoginFormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;


class ContratosController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Contratos::all();

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
          // Crea una nueva instancia de usuario
          $contratos = new Contratos();
          $contratos->nombre = $request->nombre;
          $contratos->apellidos = $request->apellidos;
          $contratos->email = $request->email;
  
          $contratos->password = Hash::make($request->input('password'));
      
          $contratos->save();
      
          return $contratos;
    }

    /**
     * Display the specified resource.
     */
    public function show(Contratos $contratos)
    {
        return $contratos;

    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Contratos $contratos)
    {
        
        $contratos->nombre = $request->nombre;
        $contratos->apellidos = $request->apellidos;
        $contratos->estado= $request->estado;
        $contratos->email= $request->email;
        if(isset($request->password)){
            if(!empty($request->password)){
                $contratos->password = Hash::make($request->password);

            }
        }
        $contratos->save();
        return $contratos;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Contratos $contratos)
    {
        $contratos->estado = 0;
        $contratos->save();
        return $contratos;
    }

    public function login6(Request $request)
    {
        $credentials = $request->only('email', 'password');

        try {
            if (!$token = Auth::guard('api_contratos')->attempt($credentials)) {
                return response()->json(['error' => 'Credenciales incorrectas'], 400);
            }
        } catch (JWTException $e) {
            return response()->json(['error' => 'No se pudo crear el token'], 500);
        }

        $contratos = Auth::guard('api_contratos')->user();
        return response()->json(['message' => 'Inicio de sesión correcto', 'token' => $token, 'contratos' => $contratos]);
    }
}
