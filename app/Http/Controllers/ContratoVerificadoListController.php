<?php

namespace App\Http\Controllers;

use App\Models\Solicitude;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ContratoVerificadoListController extends Controller
{
    protected function perPage(Request $request, int $default = 50): int
    {
        $perPage = (int) $request->input('per_page', $default);
        if ($perPage <= 0) {
            $perPage = $default;
        }

        return min($perPage, 200);
    }

    protected function normalizeDepartamento($value): string
    {
        if (!$value) {
            return '';
        }

        $cleaned = strtoupper(trim((string) $value));
        $text = preg_replace('/\s+/', ' ', $cleaned);

        if (str_contains($text, 'LA PAZ') || str_contains($text, 'LPB') || str_contains($text, 'LPZ')) return 'LPB';
        if (str_contains($text, 'SANTA CRUZ') || str_contains($text, 'SRZ') || str_contains($text, 'SCZ')) return 'SRZ';
        if (str_contains($text, 'COCHABAMBA') || str_contains($text, 'CBB')) return 'CBB';
        if (str_contains($text, 'ORURO') || str_contains($text, 'ORU')) return 'ORU';
        if (str_contains($text, 'POTOSI') || str_contains($text, 'POTOSÍ') || str_contains($text, 'PTI')) return 'PTI';
        if (str_contains($text, 'TARIJA') || str_contains($text, 'TJA')) return 'TJA';
        if (str_contains($text, 'SUCRE') || str_contains($text, 'SRE')) return 'SRE';
        if (str_contains($text, 'BENI') || str_contains($text, 'TRINIDAD') || str_contains($text, 'TDD') || str_contains($text, 'BEN')) return 'BEN';
        if (str_contains($text, 'COBIJA') || str_contains($text, 'CIJ')) return 'CIJ';

        return $cleaned;
    }

    protected function getOrigenesSeleccionados(?string $origen): array
    {
        $rawValue = trim((string) $origen);
        if ($rawValue === '') {
            return [];
        }

        if ($rawValue === 'LPB_SRZ') {
            return ['LPB', 'SRZ'];
        }

        if ($rawValue === 'TODOS') {
            return ['LPB', 'SRZ', 'CBB', 'ORU', 'PTI', 'TJA', 'SRE', 'BEN', 'CIJ'];
        }

        return [$this->normalizeDepartamento($rawValue)];
    }

    protected function getDepartamentoLabel(?string $codigo): string
    {
        $code = $this->normalizeDepartamento($codigo);
        $labels = [
            'LPB' => 'LA PAZ',
            'SRZ' => 'SANTA CRUZ',
            'CBB' => 'COCHABAMBA',
            'ORU' => 'ORURO',
            'PTI' => 'POTOSI',
            'TJA' => 'TARIJA',
            'SRE' => 'SUCRE',
            'BEN' => 'TRINIDAD',
            'CIJ' => 'COBIJA',
        ];

        return $labels[$code] ?? ($code ?: 'SIN DEPARTAMENTO');
    }

    protected function getDepartamentoFromItem($item): string
    {
        return $this->normalizeDepartamento(data_get($item, 'tarifa.departamento') ?: data_get($item, 'ciudad'));
    }

    protected function getOrigenFromItem($item): string
    {
        return $this->normalizeDepartamento(data_get($item, 'sucursale.origen') ?: data_get($item, 'sucursale.nombre'));
    }

    protected function getEmpresaFromItem($item): string
    {
        return trim((string) (
            data_get($item, 'sucursale.empresa.nombre')
            ?: data_get($item, 'sucursale.pagador')
            ?: data_get($item, 'sucursale.nombre')
            ?: 'SIN EMPRESA'
        ));
    }

    protected function getNumeroContratoFromItem($item): string
    {
        return trim((string) (
            data_get($item, 'sucursale.n_contrato')
            ?: data_get($item, 'n_contrato')
            ?: data_get($item, 'numero_contrato')
            ?: ''
        ));
    }

    protected function parseDate($value): ?\DateTimeImmutable
    {
        if (!$value) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return new \DateTimeImmutable($value->format('c'));
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $raw)) {
            [$day, $month, $year] = explode('/', $raw);
            $parsed = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', "{$year}-{$month}-{$day} 00:00:00");
            return $parsed ?: null;
        }

        try {
            return new \DateTimeImmutable($raw);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function matchesDateRange($item, ?\DateTimeImmutable $startDate, ?\DateTimeImmutable $endDate): bool
    {
        if (!$startDate && !$endDate) {
            return true;
        }

        $fechas = collect([
            data_get($item, 'fecha'),
            data_get($item, 'fecha_recojo_c'),
            data_get($item, 'fecha_d'),
        ])->map(fn ($value) => $this->parseDate($value))->filter();

        return $fechas->contains(function (\DateTimeImmutable $fecha) use ($startDate, $endDate) {
            if ($startDate && $endDate) return $fecha >= $startDate && $fecha <= $endDate;
            if ($startDate) return $fecha >= $startDate;
            if ($endDate) return $fecha <= $endDate;
            return true;
        });
    }

    public function index(Request $request)
    {
        $searchTerm = mb_strtolower(trim((string) $request->input('search', '')), 'UTF-8');
        $selectedSucursales = collect($request->input('sucursal_ids', []))
            ->map(fn ($id) => (string) $id)
            ->filter()
            ->values()
            ->all();
        $selectedOrigenes = $this->getOrigenesSeleccionados($request->input('origen'));
        $selectedEmpresa = trim((string) $request->input('empresa', ''));
        $selectedNumeroContrato = trim((string) $request->input('numero_contrato', ''));
        $startDate = $this->parseDate($request->input('start_date'));
        $endDate = $this->parseDate($request->input('end_date'));

        if ($endDate) {
            $endDate = $endDate->modify('+1 day');
        }

        $items = Solicitude::with([
            'carteroRecogida',
            'carteroEntrega',
            'sucursale',
            'sucursale.empresa',
            'tarifa',
            'direccion',
            'encargado',
            'encargadoregional',
            'transporte',
        ])
            ->whereNotNull('sucursale_id')
            ->whereIn('estado', [3, 4, 7])
            ->orderByDesc('fecha_d')
            ->orderByDesc('id')
            ->get()
            ->filter(function ($item) use (
                $selectedSucursales,
                $selectedOrigenes,
                $selectedEmpresa,
                $selectedNumeroContrato,
                $startDate,
                $endDate,
                $searchTerm
            ) {
                if (!$item->sucursale || !$item->sucursale->id) {
                    return false;
                }

                if ($selectedSucursales && !in_array((string) $item->sucursale->id, $selectedSucursales, true)) {
                    return false;
                }

                $origenItem = $this->getOrigenFromItem($item);
                if ($selectedOrigenes && !in_array($origenItem, $selectedOrigenes, true)) {
                    return false;
                }

                $empresaItem = $this->getEmpresaFromItem($item);
                if ($selectedEmpresa !== '' && $empresaItem !== $selectedEmpresa) {
                    return false;
                }

                $numeroContratoItem = $this->getNumeroContratoFromItem($item);
                if ($selectedNumeroContrato !== '' && $numeroContratoItem !== $selectedNumeroContrato) {
                    return false;
                }

                if (!$this->matchesDateRange($item, $startDate, $endDate)) {
                    return false;
                }

                if ($searchTerm === '') {
                    return true;
                }

                $searchableFields = [
                    $item->guia,
                    $item->remitente,
                    $item->destinatario,
                    $item->telefono,
                    $item->telefono_d,
                    $item->reencaminamiento,
                    $item->ciudad,
                    $item->zona_d,
                    data_get($item, 'sucursale.nombre'),
                    $numeroContratoItem,
                    $empresaItem,
                    $this->getDepartamentoLabel($this->getDepartamentoFromItem($item)),
                ];

                return collect($searchableFields)
                    ->filter(fn ($value) => $value !== null && $value !== '')
                    ->contains(fn ($value) => mb_stripos((string) $value, $searchTerm, 0, 'UTF-8') !== false);
            })
            ->values();

        $empresaOptions = $items
            ->map(fn ($item) => $this->getEmpresaFromItem($item))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->map(fn ($value) => ['value' => $value, 'label' => $value])
            ->all();

        $numeroContratoOptions = $items
            ->map(fn ($item) => $this->getNumeroContratoFromItem($item))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->map(fn ($value) => ['value' => $value, 'label' => $value])
            ->all();

        $perPage = $this->perPage($request);
        $page = max(1, (int) $request->input('page', 1));
        $total = $items->count();
        $paginatedItems = $items->forPage($page, $perPage)->values();

        $paginator = new LengthAwarePaginator(
            $paginatedItems,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return response()->json([
            'data' => $paginator->items(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'empresas' => $empresaOptions,
            'contratos' => $numeroContratoOptions,
        ]);
    }
}
