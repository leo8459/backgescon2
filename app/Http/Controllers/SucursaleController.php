<?php

namespace App\Http\Controllers;

use App\Models\Sucursale;
use Illuminate\Http\Request;
use App\Models\Empresa;
use Illuminate\Support\Facades\Storage;
use Illuminate\Notifications\Notifiable;
use App\Http\Requests\LoginFormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\Models\Contratos;
use App\Models\Cartero;
use App\Models\Gestor;
use App\Models\Gestore;
use App\Mail\PasswordResetMail; // Este es el Mailable para el correo de restablecimiento

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
        // Obtener el último código de cliente y generar el siguiente
        $ultimoCodigo = Sucursale::max(DB::raw('CAST(codigo_cliente AS INTEGER)'));
        $nuevoCodigo = str_pad($ultimoCodigo + 1, 4, '0', STR_PAD_LEFT);
    
        // Crear una nueva sucursal con los datos recibidos
        $sucursale = new Sucursale();
        $sucursale->nombre = $request->nombre;
        $sucursale->origen = $request->origen;
        $sucursale->fin_vigencia = $request->fin_vigencia;
        $sucursale->limite = $request->limite;
        $sucursale->cobertura = $request->cobertura;
        $sucursale->ini_vigencia = $request->ini_vigencia;
        $sucursale->direccion = $request->direccion;
        $sucursale->contacto_administrativo = $request->contacto_administrativo;
        $sucursale->acuerdos = $request->acuerdos;
        $sucursale->codigo_cliente = $nuevoCodigo;
        $sucursale->n_contrato = $request->n_contrato;
        $sucursale->empresa_id = $request->empresa_id;
        $sucursale->password = Hash::make($request->input('password'));
        $sucursale->email = $request->email;
        $sucursale->acuerdo_contrato = $request->acuerdo_contrato;
        $sucursale->tipo_contrato = $request->tipo_contrato;
        $sucursale->sigla = $request->sigla;
        $sucursale->pagador = $request->pagador;

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
        $sucursale->n_contrato = $request->n_contrato;
        $sucursale->contacto_administrativo = $request->contacto_administrativo;
        $sucursale->save();
        $sucursale->email = $request->email;
        $sucursale->acuerdo_contrato = $request->acuerdo_contrato;
        $sucursale->tipo_contrato = $request->tipo_contrato;
        $sucursale->sigla = $request->sigla;
        $sucursale->pagador = $request->pagador;

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
        $sucursale->estado = 0;
        $sucursale->save();
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
    public function changePassword(Request $request)
    {
        // Validar los datos de entrada
        $request->validate([
            'email' => 'required|email',
            'newPassword' => 'required|string|min:8|confirmed'
        ]);
    
        // Buscar el usuario en cada tabla
        $user = Sucursale::where('email', $request->email)->first();
        $userType = 'sucursal';
    
        if (!$user) {
            $user = Contratos::where('email', $request->email)->first();
            $userType = 'contrato';
        }
    
        if (!$user) {
            $user = Empresa::where('email', $request->email)->first();
            $userType = 'empresa';
        }
    
        if (!$user) {
            $user = Cartero::where('email', $request->email)->first();
            $userType = 'cartero';
        }
    
        if (!$user) {
            $user = Gestore::where('email', $request->email)->first();
            $userType = 'gestor';
        }
    
        // Verificar si se ha encontrado el usuario en alguna tabla
        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }
    
        // Encriptar la nueva contraseña y guardarla
        $user->password = Hash::make($request->newPassword);
        $user->save();
    
        return response()->json(['message' => "Contraseña actualizada correctamente en la tabla $userType"]);
    }

    
}
