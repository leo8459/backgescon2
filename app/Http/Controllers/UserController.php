<?php

namespace App\Http\Controllers;
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


class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return User::all();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function show(User $user)
    {
      
        return $user;
        
        
    }

    
    public function store(Request $request)
    {
      
        // Crea una nueva instancia de usuario
        $user = new User();
        $user->nombre = $request->nombre;
        $user->apellidos = $request->apellidos;
        $user->email = $request->email;

        $user->password = Hash::make($request->input('password'));
    
        $user->save();
    
        return $user;
    }
    

 

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, User $user)
    {
        $user->nombre = $request->nombre;
        $user->apellidos = $request->apellidos;
        $user->estado= $request->estado;
        $user->email= $request->email;
        if(isset($request->password)){
            if(!empty($request->password)){
                $user->password = Hash::make($request->password);

            }
        }
        $user->save();
        return $user;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */
    public function destroy(User $user)
    {
        $user->estado = 0;
        $user->save();
        return $user;
    }

    
    
    public function login(Request $request)
{
    $credentials = $request->only('email', 'password');

    try {
        // Array de guards para intentar autenticación
        $guards = [
            'api_admin' => 'administrador',
            'api_cartero' => 'cartero',
            'api_sucursal' => 'sucursal',
            'api_gestore' => 'Gestore',
            'api_encargado' => 'encargado',
            'api_contratos' => 'contratos'
        ];

        foreach ($guards as $guard => $userType) {
            if ($token = Auth::guard($guard)->attempt($credentials)) {
                $user = Auth::guard($guard)->user();
                return response()->json([
                    'message' => 'Inicio de sesión correcto',
                    'token' => $token,
                    'user' => $user,
                    'userType' => $userType
                ]);
            }
        }

        // Si no se autenticó con ningún guard, devolver error
        return response()->json(['error' => 'Credenciales incorrectas'], 400);

    } catch (\Exception $e) {
        return response()->json(['error' => 'Ocurrió un problema al intentar iniciar sesión.'], 500);
    }
}
}
