<?php

namespace App\Http\Controllers;

use App\Models\Gestore;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\LoginFormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class GestoreController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Gestore::all();

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
          // Crea una nueva instancia de usuario
          $gestore = new Gestore();
          $gestore->nombre = $request->nombre;
          $gestore->apellidos = $request->apellidos;
          $gestore->email = $request->email;
  
          $gestore->password = Hash::make($request->input('password'));
      
          $gestore->save();
      
          return $gestore;
    }

    /**
     * Display the specified resource.
     */
    public function show(Gestore $gestore)
    {
        return $gestore;

    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Gestore $gestore)
    {
        
        $gestore->nombre = $request->nombre;
        $gestore->apellidos = $request->apellidos;
        $gestore->estado= $request->estado;
        $gestore->email= $request->email;
        if(isset($request->password)){
            if(!empty($request->password)){
                $gestore->password = Hash::make($request->password);

            }
        }
        $gestore->save();
        return $gestore;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Gestore $gestore)
    {
        $gestore->estado = 0;
        $gestore->save();
        return $gestore;
    }

    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        try {
            if (!$token = Auth::guard('api_gestore')->attempt($credentials)) {
                return response()->json(['error' => 'Credenciales incorrectas'], 400);
            }
        } catch (JWTException $e) {
            return response()->json(['error' => 'No se pudo crear el token'], 500);
        }

        $gestore = Auth::guard('api_gestore')->user();
        return response()->json(['message' => 'Inicio de sesión correcto', 'token' => $token, 'gestore' => $gestore]);
    }
}
