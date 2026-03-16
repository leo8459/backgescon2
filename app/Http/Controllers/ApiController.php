<?php

namespace App\Http\Controllers;

use App\Exports\PlantillaSolicitudesExport;
use App\Imports\SolicitudesImport;
use App\Models\Cartero;
use App\Models\DetalleSolicitude;
use App\Models\Direccione;
use App\Models\Encargado;
use App\Models\Evento;
use App\Models\Solicitude;
use App\Models\Sucursale;
use App\Models\Tarifa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Intervention\Image\Facades\Image;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Picqer\Barcode\BarcodeGeneratorPNG;

class ApiController extends Controller
{
    protected function logImagenRequest(Request $request, string $context, ?string $guia = null): void
    {
        $img = $request->input('imagen');
        Log::info("{$context} payload imagen", [
            'guia' => $guia ?? $request->input('guia'),
            'has_imagen_key' => $request->has('imagen'),
            'imagen_is_string' => is_string($img),
            'imagen_length' => is_string($img) ? strlen($img) : null,
            'imagen_prefix' => is_string($img) ? substr($img, 0, 60) : null,
            'content_type' => $request->header('Content-Type'),
            'ip' => $request->ip(),
        ]);
    }

    protected function optimizeImage($imageData)
    {
        if (empty($imageData) || !is_string($imageData)) {
            return null;
        }

        try {
            return (string) Image::make(trim($imageData))
                ->resize(300, null, function ($constraint) {
                    $constraint->aspectRatio();
                })
                ->encode('webp', 50)
                ->encode('data-url');
        } catch (\Throwable $e) {
            Log::error('Error optimizando imagen en ApiController', [
                'message' => $e->getMessage(),
            ]);

            return trim($imageData);
        }
    }

    protected function resolveCarteroIdByNombre(string $nombre): ?int
    {
        $nombreNormalizado = mb_strtoupper(trim($nombre));
        if ($nombreNormalizado === '') {
            Log::info('ApiController resolveCarteroIdByNombre: nombre vacio', [
                'nombre_original' => $nombre,
            ]);

            return null;
        }

        $carteroId = Cartero::query()
            ->whereRaw('trim(upper(nombre)) = ?', [$nombreNormalizado])
            ->value('id');

        if ($carteroId) {
            Log::info('ApiController resolveCarteroIdByNombre: match exacto', [
                'nombre_original' => $nombre,
                'nombre_normalizado' => $nombreNormalizado,
                'cartero_id' => (int) $carteroId,
            ]);

            return (int) $carteroId;
        }

        $candidatos = Cartero::query()
            ->select('id', 'nombre', 'apellidos', 'email')
            ->whereRaw('upper(nombre) like ?', ['%' . $nombreNormalizado . '%'])
            ->limit(5)
            ->get()
            ->toArray();

        Log::warning('ApiController resolveCarteroIdByNombre: sin coincidencia', [
            'nombre_original' => $nombre,
            'nombre_normalizado' => $nombreNormalizado,
            'candidatos' => $candidatos,
        ]);

        return null;
    }

    protected function resolveEventoUserCartero(?string $nombre, ?int $carteroId): ?string
    {
        $nombre = trim((string) $nombre);
        if ($nombre !== '') {
            return $nombre;
        }

        if ($carteroId) {
            $cartero = Cartero::query()
                ->select('nombre', 'apellidos')
                ->find($carteroId);

            if ($cartero) {
                return trim(implode(' ', array_filter([
                    $cartero->nombre,
                    $cartero->apellidos,
                ])));
            }
        }

        return null;
    }

    public function solicitudesPorEstado($estado)
    {
        $solicitudes = Solicitude::with([
            'carteroRecogida',
            'carteroEntrega',
            'sucursale',
            'tarifa',
            'direccion',
            'encargado',
            'encargadoregional',
        ])
            ->where('estado', $estado)
            ->get();

        return response()->json($solicitudes);
    }

    public function updateReencaminamiento(Request $request)
    {
        $solicitud = Solicitude::where('guia', $request->guia)->first();

        if (!$solicitud) {
            return response()->json(['message' => 'Solicitud no encontrada'], 404);
        }

        $solicitud->reencaminamiento = $request->reencaminamiento;
        $solicitud->estado = 8;
        $solicitud->manifiesto = $request->manifiesto;
        $solicitud->save();

        return response()->json([
            'message' => 'Solicitud actualizada correctamente',
            'solicitud' => $solicitud,
        ], 200);
    }

    public function updateEstadoSolicitud(Request $request)
    {
        $solicitud = Solicitude::where('guia', $request->guia)->first();

        if (!$solicitud) {
            return response()->json(['message' => 'Solicitud no encontrada'], 404);
        }

        $solicitud->estado = $request->estado;
        $solicitud->observacion = $request->observacion;
        $solicitud->peso_r = $request->peso_r;
        $solicitud->save();

        return response()->json([
            'message' => 'Solicitud actualizada correctamente',
            'solicitud' => $solicitud,
        ], 200);
    }

    public function solicitudesPorManifiesto($manifiesto)
    {
        $solicitudes = Solicitude::with([
            'carteroRecogida',
            'carteroEntrega',
            'sucursale',
            'tarifa',
            'direccion',
            'encargado',
            'encargadoregional',
        ])
            ->where('manifiesto', $manifiesto)
            ->get();

        if ($solicitudes->isEmpty()) {
            return response()->json(['message' => 'No se encontraron solicitudes con este manifiesto'], 404);
        }

        return response()->json($solicitudes, 200);
    }

    public function solicitudPorCodigo($codigo)
    {
        $solicitud = Solicitude::with('direccion')->where('guia', $codigo)->first();

        if (!$solicitud) {
            return response()->json(['message' => 'Solicitud no encontrada'], 404);
        }

        $peso = $solicitud->peso_r ?: ($solicitud->peso_v ?: $solicitud->peso_o);

        $ciudadesMap = [
            'LPB' => 'LA PAZ (LPB)',
            'SRZ' => 'SANTA CRUZ (SRZ)',
            'CBB' => 'COCHABAMBA (CBB)',
            'ORU' => 'ORURO (ORU)',
            'PTI' => 'POTOSI (PTI)',
            'TJA' => 'TARIJA (TJA)',
            'SRE' => 'SUCRE (SRE)',
            'BEN' => 'TRINIDAD (TDD)',
            'CIJ' => 'COBIJA (CIJ)',
        ];

        if (!empty($solicitud->reencaminamiento)) {
            $codigoCiudad = strtoupper($solicitud->reencaminamiento);
        } else {
            $codigoCiudad = strtoupper(substr($codigo, 7, 3));
        }

        $nombreCiudad = isset($ciudadesMap[$codigoCiudad]) ? explode(' (', $ciudadesMap[$codigoCiudad])[0] : 'DESCONOCIDA';

        return response()->json([
            'CODIGO' => $solicitud->guia,
            'destinatario' => $solicitud->destinatario,
            'estado' => $solicitud->estado,
            'telefono_d' => $solicitud->telefono_d,
            'peso' => $peso,
            'ciudad' => $nombreCiudad,
        ], 200);
    }

    public function cambiarEstadoPorGuia(Request $request)
    {
        $solicitud = Solicitude::where('guia', $request->guia)->first();

        if (!$solicitud) {
            return response()->json(['message' => 'Solicitud no encontrada'], 404);
        }

        $carteroId = null;

        if (!empty($request->usercartero)) {
            $carteroId = $this->resolveCarteroIdByNombre((string) $request->usercartero);
        }

        $solicitud->estado = $request->estado;
        $solicitud->cartero_entrega_id = $carteroId;
        $solicitud->entrega_observacion = $request->entrega_observacion;
        $solicitud->save();

        $descripcionEstado = match ($request->estado) {
            2 => 'En camino',
            3 => 'Entregado',
            5 => 'Inventario',
            default => 'Actualizacion de estado a ' . $request->estado,
        };

        $accionEvento = match ($request->estado) {
            2 => 'En camino',
            3 => 'Entregado',
            5 => 'Inventario',
            default => 'Estado ' . $request->estado,
        };

        $eventoUserCartero = $this->resolveEventoUserCartero($request->usercartero, $carteroId);

        Evento::create([
            'accion' => $accionEvento,
            'descripcion' => $descripcionEstado,
            'codigo' => $solicitud->guia,
            'cartero_id' => $carteroId,
            'fecha_hora' => now(),
            'usercartero' => $eventoUserCartero,
        ]);

        return response()->json([
            'message' => 'Estado actualizado y evento registrado correctamente',
            'solicitud' => $solicitud,
        ], 200);
    }

    public function actualizarEstadoConFirma(Request $request)
    {
        try {
            $this->logImagenRequest($request, 'ApiController@actualizarEstadoConFirma');
            Log::info('ApiController@actualizarEstadoConFirma request', [
                'guia' => $request->input('guia'),
                'estado' => $request->input('estado'),
                'firma_d' => $request->input('firma_d'),
                'entrega_observacion' => $request->input('entrega_observacion'),
                'usercartero' => $request->input('usercartero'),
                'content_type' => $request->header('Content-Type'),
            ]);

            $request->validate([
                'guia' => 'required|string|max:255',
                'estado' => 'required|integer',
                'firma_d' => 'nullable|string',
                'entrega_observacion' => 'nullable|string|max:255',
                'imagen' => 'nullable|string',
                'usercartero' => 'nullable|string|max:255',
            ]);

            $solicitud = Solicitude::where('guia', $request->guia)->first();

            if (!$solicitud) {
                return response()->json(['message' => 'Solicitud no encontrada'], 404);
            }

            $carteroId = $solicitud->cartero_entrega_id;

            if (!empty($request->usercartero)) {
                $carteroMatchId = $this->resolveCarteroIdByNombre((string) $request->usercartero);
                if ($carteroMatchId !== null) {
                    $carteroId = $carteroMatchId;
                    $solicitud->cartero_entrega_id = $carteroId;
                }
            }

            Log::info('ApiController@actualizarEstadoConFirma cartero resuelto', [
                'guia' => $request->input('guia'),
                'usercartero' => $request->input('usercartero'),
                'cartero_id_final' => $carteroId,
                'cartero_entrega_id_actual' => $solicitud->cartero_entrega_id,
            ]);

            $solicitud->estado = $request->estado;
            $solicitud->firma_d = $request->firma_d;
            $solicitud->entrega_observacion = $request->entrega_observacion;

            if ($request->has('imagen')) {
                $incomingImage = $request->input('imagen');
                $solicitud->imagen = !empty($incomingImage)
                    ? ($this->optimizeImage($incomingImage) ?? $solicitud->imagen)
                    : $solicitud->imagen;
            }

            $solicitud->save();

            $descripcionEstado = match ($request->estado) {
                3 => 'Entregado',
                5 => 'Inventario',
                default => 'Actualizacion de estado a ' . $request->estado,
            };

            $accionEvento = match ($request->estado) {
                3 => 'Entregado',
                5 => 'Envio inventario',
                default => 'Estado ' . $request->estado,
            };

            $eventoUserCartero = $this->resolveEventoUserCartero($request->input('usercartero'), $carteroId);

            Evento::create([
                'accion' => $accionEvento,
                'descripcion' => $descripcionEstado,
                'codigo' => $solicitud->guia,
                'cartero_id' => $carteroId,
                'fecha_hora' => now(),
                'usercartero' => $eventoUserCartero,
            ]);

            Log::info('ApiController@actualizarEstadoConFirma response', [
                'guia' => $solicitud->guia,
                'estado' => $solicitud->estado,
                'cartero_id' => $carteroId,
                'usercartero' => $eventoUserCartero,
                'solicitud_id' => $solicitud->id,
            ]);

            return response()->json([
                'message' => 'Estado actualizado y evento registrado correctamente',
                'solicitud' => $solicitud,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error en actualizarEstadoConFirma', [
                'guia' => $request->input('guia'),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error al actualizar la solicitud',
                'detalle' => $e->getMessage(),
            ], 500);
        }
    }
}
