<?php

namespace App\Http\Controllers;

use App\Models\Direccione;
use App\Models\Evento;
use App\Models\Solicitude;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Picqer\Barcode\BarcodeGeneratorPNG;

class RecojoController extends Controller
{
    protected function normalizeGuia(?string $guia): ?string
    {
        if ($guia === null) {
            return null;
        }

        $value = strtoupper(trim((string) $guia));
        return $value === '' ? null : $value;
    }

    protected function guiaAlreadyExists(string $guia): bool
    {
        $normalized = $this->normalizeGuia($guia);
        if ($normalized === null) {
            return false;
        }

        return Solicitude::query()
            ->whereNotNull('guia')
            ->whereRaw('UPPER(TRIM(guia)) = ?', [$normalized])
            ->exists();
    }

    protected function resolveDireccionId(int $sucursaleId, ?string $direccionR): ?int
    {
        $direccionR = trim((string) $direccionR);
        if ($direccionR === '') {
            return null;
        }

        $direccion = Direccione::firstOrCreate(
            [
                'sucursale_id' => $sucursaleId,
                'direccion' => $direccionR,
            ],
            [
                'nombre' => 'Direccion API Publica',
                'direccion_especifica' => null,
                'zona' => null,
            ]
        );

        return $direccion->id;
    }

    protected function resolveGuia(array $data): ?string
    {
        $incoming = $this->normalizeGuia($data['codigo'] ?? null);
        if ($incoming !== null) {
            return $incoming;
        }

        $generatorResponse = app(SolicitudeController::class)->generateGuia(new Request([
            'sucursale_id' => (int) $data['user_id'],
            'reencaminamiento' => $data['destino'] ?? null,
        ]));

        if (!($generatorResponse instanceof JsonResponse)) {
            return null;
        }

        $payload = $generatorResponse->getData(true);
        return $this->normalizeGuia($payload['guia'] ?? null);
    }

    protected function mirrorToExternal(array $payload): array
    {
        $url = trim((string) env('PAQUETES_CONTRATO_MIRROR_URL', ''));
        if ($url === '') {
            return [
                'enabled' => false,
                'success' => false,
                'message' => 'Mirror deshabilitado (PAQUETES_CONTRATO_MIRROR_URL vacio).',
            ];
        }

        try {
            $client = Http::timeout(20)->acceptJson();
            $token = trim((string) env('PAQUETES_CONTRATO_MIRROR_TOKEN', ''));
            if ($token !== '') {
                $client = $client->withToken($token);
            }

            $response = $client->post($url, $payload);

            return [
                'enabled' => true,
                'success' => $response->successful(),
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ];
        } catch (\Throwable $e) {
            Log::error('Error replicando paquetes-contrato al sistema externo', [
                'message' => $e->getMessage(),
                'url' => $url,
            ]);

            return [
                'enabled' => true,
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function storePublic(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => 'required|integer|exists:sucursales,id',
            'codigo' => 'nullable|string|max:255',
            'nombre_r' => 'required|string|max:255',
            'telefono_r' => 'required|string|max:255',
            'contenido' => 'required|string|max:255',
            'direccion_r' => 'nullable|string|max:255',
            'nombre_d' => 'required|string|max:255',
            'telefono_d' => 'required|string|max:255',
            'destino' => 'nullable|string|max:255',
            'direccion_d' => 'nullable|string|max:255',
            'direccion' => 'nullable|string|max:255',
            'provincia' => 'nullable|string|max:255',
            'mapa' => 'nullable|string|max:1000',
        ]);

        $sucursaleId = (int) $data['user_id'];
        $direccionId = $this->resolveDireccionId($sucursaleId, $data['direccion_r'] ?? null);

        $guia = $this->resolveGuia($data);
        if ($guia === null) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo resolver/generar la guia.',
            ], 422);
        }

        if ($this->guiaAlreadyExists($guia)) {
            return response()->json([
                'success' => false,
                'message' => 'La guia ya existe. No se permiten guias duplicadas.',
                'guia' => $guia,
            ], 422);
        }

        $direccionDestino = $data['direccion_d'] ?? ($data['direccion'] ?? null);
        $direccionEspecificaDestino = trim((string) ($data['direccion'] ?? $direccionDestino ?? ''));
        if ($direccionEspecificaDestino === '') {
            $direccionEspecificaDestino = 'N/D';
        }

        $solicitude = DB::transaction(function () use ($data, $sucursaleId, $direccionId, $guia, $direccionDestino, $direccionEspecificaDestino) {
            $solicitude = new Solicitude();
            $solicitude->sucursale_id = $sucursaleId;
            $solicitude->tarifa_id = null;
            $solicitude->direccion_id = $direccionId;
            $solicitude->guia = $guia;
            $solicitude->estado = 1;
            $solicitude->remitente = $data['nombre_r'];
            $solicitude->telefono = $data['telefono_r'];
            $solicitude->contenido = $data['contenido'];
            $solicitude->destinatario = $data['nombre_d'];
            $solicitude->telefono_d = $data['telefono_d'];
            $solicitude->direccion_d = $direccionDestino;
            $solicitude->direccion_especifica_d = $direccionEspecificaDestino;
            $solicitude->zona_d = 'N/D';
            $solicitude->ciudad = $data['provincia'] ?? null;
            $solicitude->reencaminamiento = $data['destino'] ?? null;
            $solicitude->save();

            try {
                $generator = new BarcodeGeneratorPNG();
                $barcode = $generator->getBarcode($solicitude->guia, $generator::TYPE_CODE_128);
                $solicitude->codigo_barras = base64_encode($barcode);
                $solicitude->save();
            } catch (\Throwable $e) {
                Log::warning('No se pudo generar codigo de barras en storePublic', [
                    'guia' => $solicitude->guia,
                    'message' => $e->getMessage(),
                ]);
            }

            Evento::create([
                'accion' => 'Solicitud Publica',
                'sucursale_id' => $solicitude->sucursale_id,
                'descripcion' => 'Solicitud creada desde API publica de contrato',
                'codigo' => $solicitude->guia,
                'fecha_hora' => now(),
            ]);

            return $solicitude;
        });

        $mirrorPayload = [
            'user_id' => $sucursaleId,
            'codigo' => $guia,
            'nombre_r' => $data['nombre_r'],
            'telefono_r' => $data['telefono_r'],
            'contenido' => $data['contenido'],
            'direccion_r' => $data['direccion_r'] ?? null,
            'nombre_d' => $data['nombre_d'],
            'telefono_d' => $data['telefono_d'],
            'destino' => $data['destino'] ?? null,
            'direccion_d' => $data['direccion_d'] ?? null,
            'direccion' => $data['direccion'] ?? null,
            'mapa' => $data['mapa'] ?? null,
            'provincia' => $data['provincia'] ?? null,
        ];
        $mirrorResult = $this->mirrorToExternal($mirrorPayload);

        return response()->json([
            'success' => true,
            'message' => 'GUARDADO EN SISTEMA INTERNO',
            'data' => [
                'id' => $solicitude->id,
                'codigo' => $solicitude->guia,
                'reporte_url' => url("/paquetes-contrato/{$solicitude->id}/reporte"),
            ],
            'mirror' => $mirrorResult,
        ], 201);
    }
}
