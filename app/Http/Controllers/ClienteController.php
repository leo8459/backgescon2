<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
class ClienteController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return Cliente::all();

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
        $cliente = new Cliente();
        $cliente->nombre = $request->nombre;
        $cliente->carnet = $request->carnet;
        $cliente->direccion = $request->direccion;
        $cliente->telefono = $request->telefono;
        $cliente->email = $request->email;

        $cliente->password = Hash::make($request->input('password'));
    
        $cliente->save();
    
        return $cliente;
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Cliente  $cliente
     * @return \Illuminate\Http\Response
     */
    public function show(Cliente $cliente)
    {
        return $cliente;

    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Cliente  $cliente
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Cliente $cliente)
    {
        $cliente->nombre = $request->nombre;
        $cliente->estado= $request->estado;
        $cliente->carnet = $request->carnet;
        $cliente->direccion = $request->direccion;
        $cliente->telefono = $request->telefono;
        $cliente->email= $request->email;
        if(isset($request->password)){
            if(!empty($request->password)){
                $cliente->password = Hash::make($request->password);

            }
        }
        $cliente->save();
        return $cliente;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Cliente  $cliente
     * @return \Illuminate\Http\Response
     */
    public function destroy(Cliente $cliente)
    {
        $cliente->estado = 0;
        $cliente->save();
        return $cliente;
    }
    public function login2(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);
    
        // Intenta autenticar al maestro
        if (Auth::guard('cliente')->attempt(['email' => $request->email, 'password' => $request->password])) {
            // Autenticación exitosa, recupera la información del maestro
            $cliente = Auth::guard('cliente')->user();
            
            // Ahora puedes acceder a la información del maestro, por ejemplo, $maestro->nombre, $maestro->email, etc.
    
            // Devuelve un mensaje de éxito junto con los datos del maestro
            return response()->json(['message' => 'Inicio de sesión correcto', 'cliente' => $cliente]);
        }
    
        // Si la autenticación falla, devuelve un mensaje de error
        return response()->json(['error' => 'Credenciales incorrectas'],401);
}
    
}
