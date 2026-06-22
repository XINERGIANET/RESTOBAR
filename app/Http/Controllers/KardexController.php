<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Company;
use App\Models\DocumentType;
use App\Models\Kardex;
use App\Models\Product;
use App\Models\Operation;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;

class KardexController extends Controller
{

    public function index(Request $request)
    {
        $viewId = $request->input('view_id');
        $productId = $request->input('product_id') ?? 'all';
        $branchId = $request->session()->get('branch_id');
        $dateFrom = $request->input('date_from') ?? now()->startOfMonth()->format('Y-m-d');
        $dateTo = $request->input('date_to') ?? now()->format('Y-m-d');

        $sourceFilter = $request->input('source') ?? 'all';
        $typeFilter = $request->input('movement_type') ?? 'all';

        $perPage = (int) $request->input('per_page', 10);
        $allowedPerPage = [10, 20, 50, 100];
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        $products = Product::where('kardex', 'S')->with('baseUnit')->orderBy('description')->get();
        $branch = $branchId ? Branch::with('company')->find($branchId) : null;
        $companyId = $branch?->company_id;
        $companyName = $companyId ? Company::find($companyId)?->legal_name : null;
        $movementsCollection = collect();
        $showAllProducts = ($productId === 'all');

        // Correlativo 001, 002, 003... por tipo de documento para pedidos (como en ventas)
        $orderSeriesMap = $this->buildOrderSeriesMap($branchId ? (int) $branchId : null, $dateFrom, $dateTo);

        if ($showAllProducts) {
            $productIds = $this->getProductIdsWithMovements($branchId ? (int) $branchId : null, $dateFrom, $dateTo);
            $productIds = array_values(array_intersect($productIds, $products->pluck('id')->all()));

            if (!empty($productIds)) {
                $productMap = Product::whereIn('id', $productIds)->get()->keyBy('id');

                foreach ($productIds as $pid) {
                    $rows = $this->buildKardexMovements($pid, $branchId ? (int) $branchId : null, $dateFrom, $dateTo, $orderSeriesMap);

                    $p = $productMap->get($pid);
                    foreach ($rows as $r) {
                        $row = (array) $r;
                        $row['product_code'] = $p?->code ?? '-';
                        $row['product_description'] = $p?->description ?? '-';
                        $movementsCollection->push($row);
                    }
                }
                $movementsCollection = $movementsCollection->sortBy([
                    ['date', 'desc'],
                    ['product_code', 'asc']
                ])->values();
            }
        } elseif ($productId && is_numeric($productId)) {
            $data = $this->buildKardexMovements((int) $productId, $branchId ? (int) $branchId : null, $dateFrom, $dateTo, $orderSeriesMap);
            $movementsCollection = collect($data);
        }

        if ($sourceFilter !== 'all') {
            $movementsCollection = $movementsCollection->filter(function ($m) use ($sourceFilter) {
                $origin = $m['origin'] ?? '';
                $isSale = str_starts_with($origin, 'V - ') || str_starts_with($origin, 'O - ');
                $typeLower = strtolower($m['type'] ?? '');
                if (!$isSale) {
                    $isSale = str_contains($typeLower, 'boleta') || str_contains($typeLower, 'factura')
                        || str_contains($typeLower, 'nota') || str_contains($typeLower, 'ticket');
                }

                if ($sourceFilter === 'sales') return $isSale;
                if ($sourceFilter === 'warehouse') return !$isSale && ($m['type'] ?? '') !== 'Saldo inicial';
                return true;
            });
        }

        $availableTypes = $movementsCollection->pluck('type')
            ->unique()
            ->filter(fn($t) => $t !== 'Saldo inicial')
            ->sort()
            ->values();

        if ($typeFilter !== 'all') {
            $movementsCollection = $movementsCollection->filter(function ($m) use ($typeFilter) {
                return ($m['type'] ?? '') === $typeFilter;
            });
        }

        $movementsCollection = $movementsCollection->sortByDesc('date')->values();

        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $currentResults = $movementsCollection->slice(($currentPage - 1) * $perPage, $perPage)->all();

        $movements = new LengthAwarePaginator(
            $currentResults,
            $movementsCollection->count(),
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        $topOperations = Operation::where('view_id', $viewId)->where('type', 'T')->orderBy('id')->get();
        $resolveActionUrl = function ($action, $model = null, $operation = null) use ($viewId) {
            if (!$action) {
                return '#';
            }

            return route($action, $model ?? []);
        };
        $resolveTextColor = function ($operation) {
            // Texto siempre en blanco para asegurar contraste sobre el color de fondo
            return '#FFFFFF';
        };
        return view('kardex.index', compact(
            'viewId',
            'productId',
            'branchId',
            'dateFrom',
            'dateTo',
            'products',
            'branch',
            'movements',
            'showAllProducts',
            'sourceFilter',
            'typeFilter',
            'availableTypes',
            'perPage',
            'topOperations',
            'resolveActionUrl',
            'resolveTextColor'
        ));
    }

    /**
     * Correlativo 001, 002, 003... por tipo de documento para pedidos cobrados en el rango de fechas.
     * @return array<int, string> movement_id => series (ej. "001")
     */
    private function buildOrderSeriesMap(?int $branchId, string $dateFrom, string $dateTo): array
    {
        return [];
    }

    private function buildKardexMovements(int $productId, ?int $branchId, string $dateFrom, string $dateTo, array $orderSeriesMap = []): \Illuminate\Support\Collection
    {
        $dateFromStart = $dateFrom . ' 00:00:00';
        $dateToEnd = $dateTo . ' 23:59:59';
        $entries = Kardex::query()
            ->where('producto_id', $productId)
            ->when($branchId, fn($q) => $q->where('sucursal_id', $branchId))
            ->whereBetween('fecha', [$dateFromStart, $dateToEnd])
            ->with([
                'unit',
                'movement.documentType',
                'movement.movementType',
                'movement.salesMovement',
                'movement.orderMovement',
                'movement.warehouseMovement',
            ])
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        $pedidoTipoNombre = DocumentType::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', ['pedido'])
            ->value('name');

        return $entries->map(function ($k) use ($pedidoTipoNombre) {
            $movement = $k->movement;
            $qtySigned = (float) ($k->cantidad ?? 0);
            $quantity = abs($qtySigned);
            $docTypeName = trim((string) ($movement?->documentType?->name ?? ''));
            if ($movement?->orderMovement) {
                $type = $pedidoTipoNombre ? trim((string) $pedidoTipoNombre) : 'Pedido';
            } else {
                $type = $docTypeName !== '' ? $docTypeName : ($qtySigned >= 0 ? 'Entrada' : 'Salida');
            }

            $originPrefix = 'M';
            if ($movement?->salesMovement) {
                $originPrefix = 'V';
            } elseif ($movement?->orderMovement) {
                $originPrefix = 'O';
            } elseif ($movement?->warehouseMovement) {
                $originPrefix = 'A';
            }

            $originType = trim((string) ($movement?->movementType?->description ?? 'Movimiento'));
            if ($movement?->orderMovement) {
                $originType = 'Pedido';
            }
            $originNumber = trim((string) ($movement?->number ?? $k->movimiento_id));

            return [
                'date' => $k->fecha ? $k->fecha->format('Y-m-d H:i:s') : null,
                'number' => $originNumber,
                'type' => $type,
                'entry' => $qtySigned > 0 ? $quantity : 0,
                'exit' => $qtySigned < 0 ? $quantity : 0,
                'unit' => $k->unit?->description ?? $k->unit?->abbreviation ?? '-',
                'unit_price' => $k->preciounitario !== null ? (float) $k->preciounitario : null,
                'origin' => $originPrefix . ' - ' . ($originType !== '' ? ($originType . ' - ') : '') . $originNumber,
                'previous_stock' => (float) ($k->stockanterior ?? 0),
                'quantity' => $quantity,
                'balance' => (float) ($k->stockactual ?? 0),
            ];
        })->values();
    }

    private function getOpeningBalance(int $productId, ?int $branchId, string $beforeDate): float
    {
        return 0;
    }

    private function getProductIdsWithMovements(?int $branchId, string $dateFrom, string $dateTo): array
    {
        $dateFromStart = $dateFrom . ' 00:00:00';
        $dateToEnd = $dateTo . ' 23:59:59';
        return Kardex::query()
            ->when($branchId, fn($q) => $q->where('sucursal_id', $branchId))
            ->whereBetween('fecha', [$dateFromStart, $dateToEnd])
            ->pluck('producto_id')
            ->unique()
            ->filter()
            ->values()
            ->all();
    }

    public function pdf(Request $request)
    {
        // Alias para mantener compatibilidad si ya tienes rutas antiguas apuntando a pdf()
        $viewId = $request->input('view_id');
        $productId = $request->input('product_id') ?? 'all';
        $branchId = $request->session()->get('branch_id');
        $dateFrom = $request->input('date_from') ?? now()->startOfMonth()->format('Y-m-d');
        $dateTo = $request->input('date_to') ?? now()->format('Y-m-d');

        $sourceFilter = $request->input('source') ?? 'all';
        $typeFilter = $request->input('movement_type') ?? 'all';

        $products = Product::where('kardex', 'S')->with('baseUnit')->orderBy('description')->get();
        $branch = $branchId ? Branch::find($branchId) : null;
        $companyId = $branch?->company_id;
        $companyName = $companyId ? Company::find($companyId)?->legal_name : null;
        $movementsCollection = collect();
        $showAllProducts = ($productId === 'all');

        $orderSeriesMap = $this->buildOrderSeriesMap($branchId ? (int) $branchId : null, $dateFrom, $dateTo);

        if ($showAllProducts) {
            $productIds = $this->getProductIdsWithMovements($branchId ? (int) $branchId : null, $dateFrom, $dateTo);
            $productIds = array_values(array_intersect($productIds, $products->pluck('id')->all()));

            if (!empty($productIds)) {
                $productMap = Product::whereIn('id', $productIds)->get()->keyBy('id');

                foreach ($productIds as $pid) {
                    $rows = $this->buildKardexMovements($pid, $branchId ? (int) $branchId : null, $dateFrom, $dateTo, $orderSeriesMap);

                    $p = $productMap->get($pid);
                    foreach ($rows as $r) {
                        $row = (array) $r;
                        $row['product_code'] = $p?->code ?? '-';
                        $row['product_description'] = $p?->description ?? '-';
                        $movementsCollection->push($row);
                    }
                }
                $movementsCollection = $movementsCollection->sortBy([
                    ['date', 'desc'],
                    ['product_code', 'asc']
                ])->values();
            }
        } elseif ($productId && is_numeric($productId)) {
            $data = $this->buildKardexMovements((int) $productId, $branchId ? (int) $branchId : null, $dateFrom, $dateTo, $orderSeriesMap);
            $movementsCollection = collect($data);
        }

        if ($sourceFilter !== 'all') {
            $movementsCollection = $movementsCollection->filter(function ($m) use ($sourceFilter) {
                $origin = $m['origin'] ?? '';
                $isSale = str_starts_with($origin, 'V - ') || str_starts_with($origin, 'O - ');
                $typeLower = strtolower($m['type'] ?? '');
                if (!$isSale) {
                    $isSale = str_contains($typeLower, 'boleta') || str_contains($typeLower, 'factura')
                        || str_contains($typeLower, 'nota') || str_contains($typeLower, 'ticket');
                }

                if ($sourceFilter === 'sales') return $isSale;
                if ($sourceFilter === 'warehouse') return !$isSale && ($m['type'] ?? '') !== 'Saldo inicial';
                return true;
            });
        }

        if ($typeFilter !== 'all') {
            $movementsCollection = $movementsCollection->filter(function ($m) use ($typeFilter) {
                return ($m['type'] ?? '') === $typeFilter;
            });
        }

        $movementsCollection = $movementsCollection->sortByDesc('date')->values();

        $movements = $movementsCollection;

        $pdf = PDF::loadView('kardex.pdf.pdf_report', [
            'movements'       => $movements,
            'branch'          => $branch,
            'products'        => $products,
            'productId'       => $productId,
            'dateFrom'        => $dateFrom,
            'dateTo'          => $dateTo,
            'showAllProducts' => $showAllProducts,
            'sourceFilter'    => $sourceFilter,
            'typeFilter'      => $typeFilter,
            'viewId'          => $viewId,
            'companyName'     => $companyName,
        ]);

        $pdf->setPaper('a4')
            ->setOption('margin-bottom', 10)
            ->setOption('orientation', 'landscape')
            ->setOption('encoding', 'utf-8')
            ->setOption('enable-local-file-access', true);

        $output = $pdf->output();

        return response($output, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Kardex.pdf"',
            'Content-Length' => strlen($output),
        ]);
    }
}
