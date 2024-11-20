<?php

namespace App\Exports;

use App\Models\Tarifa;
use App\Models\Direccione;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\NamedRange;
use PhpOffice\PhpSpreadsheet\Style\Table;

class PlantillaSolicitudesExport implements FromCollection, WithHeadings, WithEvents
{
    protected $sucursale_id;

    public function __construct($sucursale_id)
    {
        $this->sucursale_id = $sucursale_id;
    }

    public function collection()
    {
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
            'Dirección Específica Destinatario', // Columna J
            'Zona Destinatario',       // Columna K
            'Ciudad Destinatario',     // Columna L
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

                $listSheet = new Worksheet($workbook, 'Listas');
                $workbook->addSheet($listSheet);

                // Escribir los datos verticalmente en la hoja 'Listas'
                $listSheet->fromArray(array_map(function($item) { return [$item]; }, $servicios), null, 'A1');
                $listSheet->fromArray(array_map(function($item) { return [$item]; }, $departamentos), null, 'B1');
                $listSheet->fromArray(array_map(function($item) { return [$item]; }, $direcciones), null, 'C1');

                // Ocultar la hoja 'Listas'
                $listSheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

                // Crear rangos nombrados para las listas
                $serviciosCount = count($servicios);
                $departamentosCount = count($departamentos);
                $direccionesCount = count($direcciones);

                if ($serviciosCount > 0) {
                    $workbook->addNamedRange(
                        new NamedRange('Servicios', $listSheet, 'A1:A' . $serviciosCount)
                    );
                }

                if ($departamentosCount > 0) {
                    $workbook->addNamedRange(
                        new NamedRange('Departamentos', $listSheet, 'B1:B' . $departamentosCount)
                    );
                }

                if ($direccionesCount > 0) {
                    $workbook->addNamedRange(
                        new NamedRange('Direcciones', $listSheet, 'C1:C' . $direccionesCount)
                    );
                }

                // Definir la cantidad de filas para las que se aplicarán las validaciones
                $highestRow = 100;

                for ($row = 2; $row <= $highestRow; $row++) {

                    // Validación para 'Servicio' en la columna A
                    $this->setDataValidation($sheet, 'A' . $row, 'Listas!$A$1:$A$' . $serviciosCount, 'Servicio');

                    // Validación para 'Departamento' en la columna B
                    $this->setDataValidation($sheet, 'B' . $row, 'Listas!$B$1:$B$' . $departamentosCount, 'Departamento');

                    // Validación para 'Nombre de Dirección' en la columna C
                    $this->setDataValidation($sheet, 'C' . $row, 'Listas!$C$1:$C$' . $direccionesCount, 'Nombre de Dirección');
                }

                // **Nuevo código para aplicar el diseño de tabla y ajustar los anchos de las columnas**

                // Determinar el rango de la tabla (desde A1 hasta la última columna y fila)
                $columnCount = count($this->headings());
                $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnCount);
               // Crear el rango de la tabla
$tableRange = 'A1:' . $lastColumn . $highestRow; // Desde A1 hasta la última columna y fila

// Crear la tabla
$table = new \PhpOffice\PhpSpreadsheet\Worksheet\Table($tableRange);
$table->setName('DataTable'); // Nombre de la tabla

// Aplicar estilo de tabla
$tableStyle = $table->getStyle();
$tableStyle->setTheme('TableStyleMedium2'); // Cambia por cualquier estilo predefinido si deseas otro

// Agregar la tabla a la hoja
$sheet->addTable($table);


                // Ajustar el ancho de las columnas
                $columnWidths = [
                    'A' => 20,
                    'B' => 25,
                    'C' => 30,
                    'D' => 15,
                    'E' => 25,
                    'F' => 20,
                    'G' => 25,
                    'H' => 25,
                    'I' => 20,
                    'J' => 35,
                    'K' => 20,
                    'L' => 20,
                ];

                foreach ($columnWidths as $column => $width) {
                    $sheet->getColumnDimension($column)->setWidth($width);
                }

                // Aumentar la altura de la fila de encabezados si es necesario
                $sheet->getRowDimension(1)->setRowHeight(25);

                // Aplicar estilo de fuente y alineación a los encabezados
                $headerStyle = [
                    'font' => [
                        'bold' => true,
                        'size' => 12,
                        'color' => ['argb' => 'FFFFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FF4F81BD'],
                    ],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    ],
                ];

                $sheet->getStyle('A1:' . $lastColumn . '1')->applyFromArray($headerStyle);
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
