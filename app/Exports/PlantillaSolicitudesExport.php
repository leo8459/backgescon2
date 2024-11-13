<?php

namespace App\Exports;

use App\Models\Tarifa;
use App\Models\Direccione;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\FromCollection; // Cambiado de FromArray a FromCollection
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
class PlantillaSolicitudesExport implements FromCollection, WithHeadings, WithEvents
{
    protected $sucursale_id;

    public function __construct($sucursale_id)
    {
        $this->sucursale_id = $sucursale_id;
    }

    public function collection()
    {
        // Retorna una colección vacía; no necesitamos datos aquí
        return collect([]);
    }

    public function headings(): array
    {
        return [
            'Servicio',                // Columna A
            'Departamento',            // Columna B
            'Nombre de Dirección',     // Columna C
            'Peso',                    // Columna D
            'Remitente',               // Columna E
            'Teléfono Remitente',      // Columna F
            'Contenido',               // Columna G
            'Destinatario',            // Columna H
            'Teléfono Destinatario',   // Columna I
            'Dirección Destinatario',  // Columna J
            'Dirección Específica Destinatario', // Columna K
            'Zona Destinatario',       // Columna L
            'Ciudad Destinatario',     // Columna M
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {

                // Obtener los datos de la base de datos filtrados por sucursale_id
                $servicios = Tarifa::where('sucursale_id', $this->sucursale_id)
                    ->distinct()->pluck('servicio')->filter()->values()->toArray();

                $departamentos = Tarifa::where('sucursale_id', $this->sucursale_id)
                    ->distinct()->pluck('departamento')->filter()->values()->toArray();

                $direcciones = Direccione::where('sucursale_id', $this->sucursale_id)
                    ->distinct()->pluck('nombre')->filter()->values()->toArray();

                // Crear una nueva hoja oculta para almacenar las listas
                $sheet = $event->sheet->getDelegate();
                $workbook = $sheet->getParent();

                $listSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($workbook, 'Listas');
                $workbook->addSheet($listSheet);

                // Escribir los datos verticalmente en la hoja 'Listas'
                $listSheet->fromArray(array_map(function($item) { return [$item]; }, $servicios), null, 'A1');
                $listSheet->fromArray(array_map(function($item) { return [$item]; }, $departamentos), null, 'B1');
                $listSheet->fromArray(array_map(function($item) { return [$item]; }, $direcciones), null, 'C1');

                // Ocultar la hoja 'Listas'
                $listSheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);

                // Crear rangos nombrados para las listas
                $serviciosCount = count($servicios);
                $departamentosCount = count($departamentos);
                $direccionesCount = count($direcciones);

                if ($serviciosCount > 0) {
                    $workbook->addNamedRange(
                        new \PhpOffice\PhpSpreadsheet\NamedRange('Servicios', $listSheet, 'A1:A' . $serviciosCount)
                    );
                }

                if ($departamentosCount > 0) {
                    $workbook->addNamedRange(
                        new \PhpOffice\PhpSpreadsheet\NamedRange('Departamentos', $listSheet, 'B1:B' . $departamentosCount)
                    );
                }

                if ($direccionesCount > 0) {
                    $workbook->addNamedRange(
                        new \PhpOffice\PhpSpreadsheet\NamedRange('Direcciones', $listSheet, 'C1:C' . $direccionesCount)
                    );
                }

                // Definir la cantidad de filas para las que se aplicarán las validaciones
                $highestRow = 100;

                for ($row = 2; $row <= $highestRow; $row++) {

                    // Validación para 'Servicio' en la columna A
                    $this->setDataValidation($sheet, 'A' . $row, 'Listas!$A$1:$A$' . count($servicios), 'Servicio');

                    // Validación para 'Departamento' en la columna B
                    $this->setDataValidation($sheet, 'B' . $row, 'Listas!$B$1:$B$' . count($departamentos), 'Departamento');

                    // Validación para 'Nombre de Dirección' en la columna C
                    $this->setDataValidation($sheet, 'C' . $row, 'Listas!$C$1:$C$' . count($direcciones), 'Nombre de Dirección');
                }
            },
        ];
    }

    private function setDataValidation($sheet, $cell, $formula, $title)
    {
        $validation = $sheet->getCell($cell)->getDataValidation();
        $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
        $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setShowDropDown(true);
        $validation->setErrorTitle('Valor Inválido');
        $validation->setError('El valor no está en la lista.');
        $validation->setPromptTitle($title);
        $validation->setPrompt('Por favor, seleccione un valor de la lista.');
        $validation->setFormula1($formula);
    }

    
}
