<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Storage;
use App\Models\empresa;
use Illuminate\Notifications\Notifiable;
use App\Http\Requests\LoginFormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;


class EmpresaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return empresa::all();
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
        $empresa = new empresa();
        $empresa->nombre = $request->nombre;
        $empresa->email = $request->email;
        $empresa->estado = $request->estado ?? 1;
        $empresa->nit = $request->nit;

        $empresa->password = Hash::make($request->input('password'));

        $empresa->save();

        return $empresa;
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\empresa  $empresa
     * @return \Illuminate\Http\Response
     */
    public function show(empresa $empresa)
    {
        return $empresa;

    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\empresa  $empresa
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, empresa $empresa)
    {
        $empresa->nombre = $request->nombre;
        $empresa->estado= $request->estado;
        $empresa->nit = $request->nit;
        $empresa->email= $request->email;
        if(isset($request->password)){
            if(!empty($request->password)){
                $empresa->password = Hash::make($request->password);

            }
        }
        $empresa->save();
        return $empresa;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\empresa  $empresa
     * @return \Illuminate\Http\Response
     */
    public function destroy(empresa $empresa)
    {
        $empresa->estado = 0;
        $empresa->save();
        return $empresa;
    }
    public function login4(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Intenta autenticar al maestro
        if (Auth::guard('empresa')->attempt(['email' => $request->email, 'password' => $request->password])) {
            // Autenticación exitosa, recupera la información del maestro
            $empresa = Auth::guard('empresa')->user();

            // Ahora puedes acceder a la información del maestro, por ejemplo, $maestro->nombre, $maestro->email, etc.

            // Devuelve un mensaje de éxito junto con los datos del maestro
            return response()->json(['message' => 'Inicio de sesión correcto', 'empresa' => $empresa]);
        }

        // Si la autenticación falla, devuelve un mensaje de error
        return response()->json(['error' => 'Credenciales incorrectas'], 401);
    }
}
