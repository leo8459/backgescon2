<?php

namespace App\Http\Controllers;

use App\Models\Codigo;
use App\Models\Sucursale;
use Illuminate\Http\Request;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;   // ⬅️  al inicio del archivo

use Illuminate\Support\Facades\Log;

class CodigoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Codigo::with('sucursale:id,nombre')->get();   // trae el nombre
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'sucursale_id' => 'required|exists:sucursales,id',
            'cantidad' => 'required|integer|min:1',
            'correlativo_inicio' => 'nullable|integer|min:1' // Opcional
        ]);

        $sucursal = Sucursale::findOrFail($request->sucursale_id);
        $codigo_cliente = $sucursal->codigo_cliente;
        $n_contrato = $sucursal->n_contrato ?? 'SIN_CONTRATO';

        if (!$codigo_cliente) {
            return response()->json([
                'message' => 'La sucursal no tiene un codigo_cliente asignado.'
            ], 400);
        }

        // Obtener el último código generado
        $ultimoCodigo = Codigo::where('codigo', 'LIKE', "C{$codigo_cliente}A%BO")
            ->orderBy('codigo', 'desc')
            ->first();

        $ultimoNumero = $ultimoCodigo ? intval(substr($ultimoCodigo->codigo, strlen("C{$codigo_cliente}A"), 5)) : 0;

        // Si el usuario proporciona un número inicial, se usa. Si no, continúa desde el último.
        $inicioNumero = $request->filled('correlativo_inicio') ? $request->correlativo_inicio : ($ultimoNumero + 1);

        // Validación: el número de inicio debe ser mayor que el último generado
        if ($request->filled('correlativo_inicio') && $inicioNumero <= $ultimoNumero) {
            return response()->json([
                'message' => 'El número de inicio debe ser mayor al último número generado.'
            ], 400);
        }

        $cantidad = $request->cantidad;
        $codigosCreados = [];

        for ($i = 0; $i < $cantidad; $i++) {
            $nuevoNumero = str_pad($inicioNumero, 5, '0', STR_PAD_LEFT);
            $codigoGenerado = "C{$codigo_cliente}A{$nuevoNumero}BO";
            $inicioNumero++;

            Log::info("Generando código de barras para: {$codigoGenerado}");

            // Generar código de barras en formato binario
            $generator = new BarcodeGeneratorPNG();
            try {
                $barcodeData = $generator->getBarcode($codigoGenerado, $generator::TYPE_CODE_128);
            } catch (\Exception $e) {
                Log::error("Error al generar código de barras para {$codigoGenerado}: " . $e->getMessage());
                continue;
            }

            if (!$barcodeData) {
                Log::error("El código de barras generado es vacío para {$codigoGenerado}");
                continue;
            }

            // Convertir el binario del código de barras en una cadena base64 para almacenar en la base de datos
            $barcodeBase64 = base64_encode($barcodeData);

            Log::info("Código de barras generado correctamente para {$codigoGenerado}");

            // Crear registro en la base de datos con el código de barras en base64
            $codigo = Codigo::create([
                'n_contrato' => $n_contrato,
                'codigo' => $codigoGenerado,
                'sucursale_id' => $request->sucursale_id,
                'barcode' => $barcodeBase64 // Guardamos el código en base64 en la BD
            ]);

            $codigosCreados[] = $codigo;
        }

        return response()->json([
            'message' => 'Códigos creados exitosamente',
            'codigos' => $codigosCreados,
            'sucursal_nombre' => $sucursal->nombre,
            'sucursal_id' => $sucursal->id,
            'cantidad' => $cantidad
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Codigo $codigo)
    {
        return response()->json($codigo);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Codigo $codigo)
    {
        $request->validate([
            'sucursale_id' => 'required|exists:sucursales,id',
        ]);

        $codigo->update($request->all());

        return response()->json([
            'message' => 'Código actualizado correctamente',
            'codigo' => $codigo
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Codigo $codigo)
    {
        $codigo->delete();

        return response()->json([
            'message' => 'Código eliminado correctamente'
        ]);
    }
    public function reimprimir(Request $request)
    {
        $request->validate([
            'codigos'   => 'required|array|min:1',
            'codigos.*' => 'string'
        ]);

        // Recuperamos los registros que existan en BD
        $codigosSolicitados = Codigo::whereIn('codigo', $request->codigos)->get();

        if ($codigosSolicitados->isEmpty()) {
            return response()->json([
                'message' => 'No se encontró ninguno de los códigos solicitados.'
            ], 404);
        }

        // Devolvemos el mismo formato que usa el frontend
        return response()->json([
            'message' => 'Códigos recuperados correctamente',
            'codigos' => $codigosSolicitados
                ->map(fn($c) => [
                    'codigo'  => $c->codigo,
                    'barcode' => $c->barcode      // ya está en base64
                ]),
            'sucursal_nombre' => 'REIMPRESION',
            'sucursal_id'     => 'VARIOS',
            'cantidad'        => $codigosSolicitados->count()
        ]);
    }
    public function totales()
    {
        // código_cliente viene de la tabla sucursales
        $totales = Codigo::select(
                'sucursale_id',
                DB::raw('count(*) as total')
            )
            ->groupBy('sucursale_id')
            ->with('sucursale:id,nombre,codigo_cliente')
            ->get()
            ->map(fn ($row) => [
                'sucursale_id'  => $row->sucursale_id,
                'codigo_cliente'=> $row->sucursale->codigo_cliente,
                'nombre'        => $row->sucursale->nombre,
                'total'         => $row->total
            ])
            ->sortBy('codigo_cliente')            // 🡅 0001 → 9999
            ->values();                           // re‑indexar
    
        // total global
        $granTotal = $totales->sum('total');
    
        return response()->json([
            'detalle'    => $totales,
            'gran_total' => $granTotal
        ]);
    }
}
