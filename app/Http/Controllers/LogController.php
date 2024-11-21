<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LogController extends Controller
{
    public function getLogs()
    {
        $logFile = storage_path('logs/laravel.log');

        if (!file_exists($logFile)) {
            return response()->json(['message' => 'No se encontró el archivo de logs.'], 404);
        }

        $logs = file_get_contents($logFile);

        // Dividir los logs por los timestamps para separar cada log
        $logEntries = preg_split('/(?=\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\])/', $logs, -1, PREG_SPLIT_NO_EMPTY);

        $structuredLogs = [];
        foreach ($logEntries as $index => $entry) {
            // Dividir la entrada en mensaje principal y detalles
            $lines = explode("\n", $entry);
            $mainMessage = array_shift($lines); // Obtener la primera línea (mensaje principal)
            $details = implode("\n", $lines); // Concatenar el resto como detalles

            $structuredLogs[] = [
                'logNumber' => $index + 1, // Número del log
                'mainMessage' => trim($mainMessage),
                'details' => trim($details),
            ];
        }

        return response()->json($structuredLogs, 200);
    }
}
