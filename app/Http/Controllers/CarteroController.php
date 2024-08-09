<?php

namespace App\Http\Controllers;

use App\Models\cartero;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use Illuminate\Notifications\Notifiable;
use App\Http\Requests\LoginFormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class CarteroController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return Cartero::all();
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
        $cartero = new Cartero();
        $cartero->nombre = $request->nombre;
        $cartero->apellidos = $request->apellidos;
        $cartero->email = $request->email;
        $cartero->zona = $request->zona;

        $cartero->password = Hash::make($request->input('password'));

        $cartero->save();

        return $cartero;
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\cartero  $cartero
     * @return \Illuminate\Http\Response
     */
    public function show(cartero $cartero)
    {
        return $cartero;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\cartero  $cartero
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, cartero $cartero)
    {
        $cartero->nombre = $request->nombre;
        $cartero->apellidos = $request->apellidos;
        $cartero->email = $request->email;
        $cartero->zona = $request->zona;

        $cartero->password = Hash::make($request->input('password'));

        $cartero->save();

        return $cartero;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\cartero  $cartero
     * @return \Illuminate\Http\Response
     */
    public function destroy(cartero $cartero)
    {
        $cartero->estado = 0;
        $cartero->save();
        return $cartero;
    }
    public function login3(Request $request)
{
    $credentials = $request->only('email', 'password');

    try {
        if (!$token = Auth::guard('api_cartero')->attempt($credentials)) {
            return response()->json(['error' => 'Credenciales incorrectas'], 400);
        }
    } catch (JWTException $e) {
        return response()->json(['error' => 'No se pudo crear el token'], 500);
    }

    $cartero = Auth::guard('api_cartero')->user();
    return response()->json([
        'message' => 'Inicio de sesión correcto',
        'token' => $token,
        'cartero' => $cartero,
        'userType' => 'cartero'
    ]);
}

    
    
}
