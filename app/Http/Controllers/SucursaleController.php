<?php

namespace App\Http\Controllers;

use App\Models\Sucursale;
use Illuminate\Http\Request;
use App\Models\empresa;
use Illuminate\Support\Facades\Storage;
use Illuminate\Notifications\Notifiable;
use App\Http\Requests\LoginFormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;


class SucursaleController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return sucursale::with(['empresa'])->get();

        // return sucursale::all();    
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
         $sucursale = new sucursale();
         $sucursale->nombre = $request->nombre;
         $sucursale->origen = $request->origen;
         $sucursale->fin_vigencia = $request->fin_vigencia;
         $sucursale->limite = $request->limite;
         $sucursale->cobertura = $request->cobertura;
         $sucursale->ini_vigencia = $request->ini_vigencia;
         $sucursale->direccion = $request->direccion;
         $sucursale->contacto_administrativo = $request->contacto_administrativo;
         $sucursale->acuerdos = $request->acuerdos;
         $sucursale->codigo_cliente = $request->codigo_cliente;
         $sucursale->empresa_id = $request->empresa_id;
         $sucursale->password = Hash::make($request->input('password'));
         $sucursale->email = $request->email;


         $sucursale->save();
     
         return $sucursale;
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Sucursale  $sucursale
     * @return \Illuminate\Http\Response
     */
    public function show(Sucursale $sucursale)
    {
        $sucursale->empresa = $sucursale->empresa;
        return $sucursale;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Sucursale  $sucursale
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Sucursale $sucursale)
    {
        $sucursale->nombre = $request->nombre;
        $sucursale->origen = $request->origen;
        $sucursale->fin_vigencia = $request->fin_vigencia;
        $sucursale->limite = $request->limite;
        $sucursale->cobertura = $request->cobertura;
        $sucursale->empresa_id = $request->empresa_id;
        $sucursale->ini_vigencia = $request->ini_vigencia;
        $sucursale->direccion = $request->direccion;
        $sucursale->acuerdos = $request->acuerdos;
        $sucursale->codigo_cliente = $request->codigo_cliente;
        $sucursale->contacto_administrativo = $request->contacto_administrativo;
        $sucursale->save();
        $sucursale->email = $request->email;

        if(isset($request->password)){
            if(!empty($request->password)){
                $sucursale->password = Hash::make($request->password);

            }
        }
         return $sucursale;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Sucursale  $sucursale
     * @return \Illuminate\Http\Response
     */
    public function destroy(Sucursale $sucursale)
    {
        $sucursale->delete();
        return $sucursale;
    }
    public function login2(Request $request)
    {
        $credentials = $request->only('email', 'password');

        try {
            if (!$token = Auth::guard('api_sucursal')->attempt($credentials)) {
                return response()->json(['error' => 'Credenciales incorrectas'], 400);
            }
        } catch (JWTException $e) {
            return response()->json(['error' => 'No se pudo crear el token'], 500);
        }

        $sucursal = Auth::guard('api_sucursal')->user();
        return response()->json(['message' => 'Inicio de sesión correcto', 'token' => $token, 'sucursal' => $sucursal]);
    }
    
}
