<?php

namespace App\Http\Controllers;

use App\Models\Encargado;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\LoginFormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
class EncargadoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return Encargado::all();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
         // Crea una nueva instancia de usuario
         $encargado = new Encargado();
         $encargado->nombre = $request->nombre;
         $encargado->apellidos = $request->apellidos;
         $encargado->email = $request->email;
         $encargado->estado = $request->estado ?? 1;
         $encargado->departamento = $request->departamento;

         $encargado->password = Hash::make($request->input('password'));
     
         $encargado->save();
     
         return $encargado;
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Encargado  $encargado
     * @return \Illuminate\Http\Response
     */
    public function show(Encargado $encargado)
    {
        return $encargado;

    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Encargado  $Encargado
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Encargado $encargado)
    {
        $encargado->nombre = $request->nombre;
        $encargado->apellidos = $request->apellidos;
        $encargado->email = $request->email;
        $encargado->estado = $request->estado ?? 1;
        $encargado->departamento = $request->departamento;

        $encargado->password = Hash::make($request->input('password'));
    
        $encargado->save();
    
        return $encargado;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Encargado  $encargado
     * @return \Illuminate\Http\Response
     */
    public function destroy(Encargado $encargado)
    {
        $encargado->estado = 0;
        $encargado->save();
        return $encargado;
    }

    public function login5(Request $request)
    {
        $credentials = $request->only('email', 'password');

        try {
            if (!$token = Auth::guard('api_encargado')->attempt($credentials)) {
                return response()->json(['error' => 'Credenciales incorrectas'], 400);
            }
        } catch (JWTException $e) {
            return response()->json(['error' => 'No se pudo crear el token'], 500);
        }

        $encargado = Auth::guard('api_encargado')->user();
        return response()->json(['message' => 'Inicio de sesión correcto', 'token' => $token, 'encargado' => $encargado]);
    }
}
