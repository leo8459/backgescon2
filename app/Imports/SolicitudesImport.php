<?php

namespace App\Imports;

use App\Models\Solicitude;
use App\Models\Sucursale;
use App\Models\Tarifa;
use App\Models\Direccione;
use App\Models\Evento;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class SolicitudesImport implements ToCollection
{
    protected $sucursale_id;
    protected $guias = [];
    protected $addedSolicitudes = [];

    public function __construct($sucursale_id)
    {
        $this->sucursale_id = $sucursale_id;
    }

    public function collection(Collection $rows)
    {
        // Saltar la fila de encabezados
        $rows->shift();
    
        foreach ($rows as $row) {
            try {
                // Buscar tarifa usando servicio y departamento, filtrado por sucursal
                $tarifa = Tarifa::where('servicio', $row[0]) // Servicio en la columna 0
                                ->where('departamento', $row[1]) // Departamento en la columna 1
                                ->where('sucursale_id', $this->sucursale_id)
                                ->first();
    
                if (!$tarifa) {
                    // Continuar si no se encuentra una tarifa válida
                    continue;
                }
    
                // Buscar dirección usando nombre de dirección, filtrado por sucursal
                $direccion = Direccione::where('nombre', $row[2]) // Nombre de dirección en la columna 2
                                        ->where('sucursale_id', $this->sucursale_id)
                                        ->first();
    
                if (!$direccion) {
                    // Continuar si no se encuentra una dirección válida
                    continue;
                }
    
                // Crear una nueva instancia de Solicitude con los IDs de tarifa y dirección
                $solicitude = new Solicitude();
                $solicitude->sucursale_id = $this->sucursale_id;
                $solicitude->tarifa_id = $tarifa->id; // Usar ID de tarifa encontrado
                $solicitude->direccion_id = $direccion->id; // Usar ID de dirección encontrado
                $solicitude->peso_o = $row[3]; // Peso en la columna 3
                $solicitude->remitente = $row[4]; // Remitente en la columna 4
                $solicitude->telefono = $row[5]; // Teléfono en la columna 5
                $solicitude->contenido = $row[6]; // Contenido en la columna 6
                $solicitude->destinatario = $row[7]; // Destinatario en la columna 7
                $solicitude->telefono_d = $row[8]; // Teléfono destinatario en la columna 8
                $solicitude->direccion_d = $row[9]; // Dirección destinatario en la columna 9
                $solicitude->direccion_especifica_d = $row[10]; // Dirección específica en la columna 10
                $solicitude->zona_d = $row[11]; // Zona en la columna 11
                $solicitude->ciudad = $row[12]; // Ciudad en la columna 12
                $solicitude->fecha = now();
    
                // Generar la guía
                $solicitude->guia = $this->generateGuia($solicitude->sucursale_id, $solicitude->tarifa_id);
    
                // Generar el código de barras
                $generator = new \Picqer\Barcode\BarcodeGeneratorPNG();
                $barcode = $generator->getBarcode($solicitude->guia, $generator::TYPE_CODE_128);
                $solicitude->codigo_barras = base64_encode($barcode);
    
                // Guardar la solicitud
                $solicitude->save();
    
                // Registrar el evento
                Evento::create([
                    'accion' => 'Solicitud',
                    'sucursale_id' => $solicitude->sucursale_id,
                    'descripcion' => 'Solicitud de Recojo de Paquetes',
                    'codigo' => $solicitude->guia,
                    'fecha_hora' => now(),
                ]);
    
                $this->guias[] = $solicitude->guia;
            } catch (\Exception $e) {
                continue;
            }
        }
    }

    public function getGuias()
    {
        return $this->guias;
    }

    protected function generateGuia($sucursaleId, $tarifaId)
    {
        $sucursal = Sucursale::find($sucursaleId);
        $tarifa = Tarifa::find($tarifaId);

        if (!$sucursal || !$tarifa) {
            throw new \Exception('Sucursal o tarifa no encontrada.');
        }

        $sucursalCode = str_pad($sucursal->codigo_cliente, 2, '0', STR_PAD_LEFT);
        $sucursalOrigin = str_pad($sucursal->origen, 2, '0', STR_PAD_LEFT);
        $tarifaCode = str_pad($tarifa->departamento, 2, '0', STR_PAD_LEFT);

        $lastGuia = Solicitude::where('sucursale_id', $sucursaleId)
            ->latest('id')
            ->first();

        $lastNumber = 0;
        if ($lastGuia) {
            $lastNumber = intval(substr($lastGuia->guia, -4));
        }

        $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);

        return "{$sucursalCode}{$sucursalOrigin}{$tarifaCode}{$newNumber}";
    }
}
