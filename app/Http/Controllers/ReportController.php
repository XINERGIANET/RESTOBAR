<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\SalesMovement;
use App\Models\PurchaseMovement;
use App\Models\SalesMovementDetail;
use Illuminate\Http\Request;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ConsolidatedProductsExport;
use App\Exports\SalesAndFinancesExport;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function consolidatedProducts(Request $request)
    {
        $data = $this->getConsolidatedProductsData($request);
        return view('reports.consolidated_products', $data);
    }

    public function consolidateProductsExcel(Request $request)
    {
        $data = $this->getConsolidatedProductsData($request);
        return Excel::download(
            new ConsolidatedProductsExport($data['rows'], $data['grandTotal']),
            'Consolidado_Productos_' . now()->format('Ymd') . '.xlsx'
        );
    }

    public function consolidatedProductsPdf(Request $request)
    {
        $data = $this->getConsolidatedProductsData($request);
        $pdf = PDF::loadView('reports.pdf.consolidated_products', $data);
        return $pdf->download('Consolidado_Productos_' . now()->format('Ymd') . '.pdf');
    }

    private function getConsolidatedProductsData(Request $request)
    {
        $branchId  = session('branch_id');
        $viewId    = $request->input('view_id');
        $dateFrom  = $request->input('date_from', now()->format('Y-m-d'));
        $dateTo    = $request->input('date_to',   now()->format('Y-m-d'));
        $categoryId = $request->input('category_id', 'all');

        // Categorías para el filtro
        $categories = Category::orderBy('description')->get();
        $categories->prepend((object) ['id' => 'all', 'description' => 'Todas las categorías']);

        // Consulta consolidada agrupada por producto
        $query = SalesMovementDetail::query()
            ->select(
                'sales_movement_details.product_id',
                DB::raw('MAX(sales_movement_details.description) as product_name'),
                DB::raw('MAX(sales_movement_details.code) as product_code'),
                DB::raw('MAX(products.category_id) as category_id'),
                DB::raw('MAX(categories.description) as category_name'),
                DB::raw('SUM(sales_movement_details.quantity) as total_quantity'),
                DB::raw('SUM(sales_movement_details.courtesy_quantity) as total_courtesy'),
                DB::raw('SUM(sales_movement_details.original_amount) as total_original'),
                DB::raw('SUM(sales_movement_details.original_amount - sales_movement_details.amount) as total_discount'),
                DB::raw('SUM(sales_movement_details.amount) as total_amount'),
                DB::raw('AVG(sales_movement_details.amount / NULLIF(sales_movement_details.quantity, 0)) as avg_price')
            )
            ->join('products', 'products.id', '=', 'sales_movement_details.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->join('sales_movements', 'sales_movements.id', '=', 'sales_movement_details.sales_movement_id')
            ->whereNull('sales_movement_details.deleted_at')
            ->whereNull('sales_movements.deleted_at')
            ->whereBetween(DB::raw('DATE(sales_movements.created_at)'), [$dateFrom, $dateTo])
            ->whereNotNull('sales_movement_details.product_id')
            ->whereNull('sales_movement_details.parent_detail_id'); // excluir complementos

        if ($branchId) {
            $query->where('sales_movements.branch_id', $branchId);
        }

        if ($categoryId !== 'all') {
            $query->where('products.category_id', $categoryId);
        }

        $rows = $query
            ->groupBy('sales_movement_details.product_id')
            ->orderByDesc('total_amount')
            ->get();

        // Totales generales
        $grandTotal         = $rows->sum('total_amount');
        $grandTotalNet      = $rows->sum('total_original');
        $grandTax           = $grandTotal - $grandTotalNet;
        $grandDiscount      = 0;
        $grandQuantity      = $rows->sum('total_quantity');

        // Top 10 para el gráfico
        $chartLabels  = $rows->take(10)->pluck('product_name')->toArray();
        $chartAmounts = $rows->take(10)->pluck('total_amount')->map(fn($v) => round((float)$v, 2))->toArray();
        $chartQty     = $rows->take(10)->pluck('total_quantity')->map(fn($v) => round((float)$v, 2))->toArray();

        return compact(
            'viewId',
            'dateFrom',
            'dateTo',
            'categoryId',
            'categories',
            'rows',
            'grandTotal',
            'grandTotalNet',
            'grandTax',
            'grandDiscount',
            'grandQuantity',
            'chartLabels',
            'chartAmounts',
            'chartQty'
        );
    }
    //Excel

    public function salesByCustomer(Request $request)
    {
        $branchId  = session('branch_id');
        $dateFrom  = $request->input('date_from', now()->format('Y-m-d'));
        $dateTo    = $request->input('date_to',   now()->format('Y-m-d'));

        $query = DB::table('sales_movements')
            ->join('movements', 'movements.id', '=', 'sales_movements.movement_id')
            ->select(
                'movements.person_id',
                'movements.person_name',
                DB::raw('COUNT(sales_movements.id) as sales_count'),
                DB::raw('SUM(sales_movements.total) as total_amount')
            )
            ->whereNull('sales_movements.deleted_at')
            ->whereNull('movements.deleted_at')
            ->whereBetween(DB::raw('DATE(sales_movements.created_at)'), [$dateFrom, $dateTo])
            ->orderBy('movements.person_name', 'asc');

        if ($branchId) {
            $query->where('sales_movements.branch_id', $branchId);
        }

        $rows = $query->groupBy('movements.person_id', 'movements.person_name')
            ->orderByDesc('total_amount')
            ->get();

        $grandTotal = $rows->sum('total_amount');
        $grandCount = $rows->sum('sales_count');

        // Top 10 para el gráfico
        $chartLabels = $rows->take(5)->pluck('person_name')->map(fn($n) => $n ?? 'Público General')->toArray();
        $chartData   = $rows->take(5)->pluck('total_amount')->map(fn($v) => round((float)$v, 2))->toArray();

        return view('reports.sales_by_customer', compact(
            'dateFrom',
            'dateTo',
            'rows',
            'grandTotal',
            'grandCount',
            'chartLabels',
            'chartData'
        ));
    }

    public function paymentMethod(Request $request)
    {
        $branchId  = session('branch_id');
        $dateFrom  = $request->input('date_from', now()->format('Y-m-d'));
        $dateTo    = $request->input('date_to',   now()->format('Y-m-d'));

        $query = DB::table('cash_movement_details')
            ->join('cash_movements', 'cash_movements.id', '=', 'cash_movement_details.cash_movement_id')
            ->join('payment_concepts', 'payment_concepts.id', '=', 'cash_movements.payment_concept_id')
            ->select(
                'cash_movement_details.payment_method',
                DB::raw('COUNT(cash_movement_details.id) as transactions_count'),
                DB::raw('SUM(cash_movement_details.amount) as total_amount')
            )
            ->where('payment_concepts.type', 'I') // Solo ingresos
            ->where('payment_concepts.description', 'NOT LIKE', '%Apertura%') // Excluir apertura
            ->whereNull('cash_movement_details.deleted_at')
            ->whereNull('cash_movements.deleted_at')
            ->whereBetween(DB::raw('DATE(cash_movement_details.created_at)'), [$dateFrom, $dateTo]);

        if ($branchId) {
            $query->where('cash_movement_details.branch_id', $branchId);
        }

        $rows = $query->groupBy('cash_movement_details.payment_method')
            ->orderByDesc('total_amount')
            ->get();

        $grandTotal = $rows->sum('total_amount');
        $grandCount = $rows->sum('transactions_count');

        // Para el gráfico
        $chartLabels = $rows->pluck('payment_method')->toArray();
        $chartData   = $rows->pluck('total_amount')->map(fn($v) => round((float)$v, 2))->toArray();

        return view('reports.payment_method', compact(
            'dateFrom',
            'dateTo',
            'rows',
            'grandTotal',
            'grandCount',
            'chartLabels',
            'chartData'
        ));
    }
    public function salesAndFinances(Request $request)
    {
        $data = $this->getSalesAndFinancesData($request);
        return view('reports.sales_and_finances', $data);
    }

    public function salesAndFinancesExcel(Request $request)
    {
        $data = $this->getSalesAndFinancesData($request);
        return Excel::download(
            new SalesAndFinancesExport($data['dates'], $data['chartSales'], $data['chartPurchases']),
            'Ventas_Finanzas_' . now()->format('Ymd') . '.xlsx'
        );
    }

    public function salesAndFinancesPdf(Request $request)
    {
        $data = $this->getSalesAndFinancesData($request);
        $pdf = PDF::loadView('reports.pdf.sales_and_finances', $data);
        return $pdf->download('Ventas_Finanzas_' . now()->format('Ymd') . '.pdf');
    }

    private function getSalesAndFinancesData(Request $request)
    {
        $branchId  = session('branch_id');
        $groupBy   = $request->input('group_by', 'day'); // day, week, month
        $period    = $request->input('period');
        
        // Formatos por defecto
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');

        if ($period) {
            switch ($period) {
                case 'today':
                    $dateFrom = $dateTo = now()->toDateString();
                    break;
                case 'yesterday':
                    $dateFrom = $dateTo = now()->subDay()->toDateString();
                    break;
                case 'this_week':
                    $dateFrom = now()->startOfWeek()->toDateString();
                    $dateTo   = now()->toDateString();
                    break;
                case 'last_7_days':
                    $dateFrom = now()->subDays(6)->toDateString();
                    $dateTo   = now()->toDateString();
                    break;
                case 'this_month':
                    $dateFrom = now()->startOfMonth()->toDateString();
                    $dateTo   = now()->toDateString();
                    break;
                case 'last_30_days':
                    $dateFrom = now()->subDays(29)->toDateString();
                    $dateTo   = now()->toDateString();
                    break;
                case 'this_year':
                    $dateFrom = now()->startOfYear()->toDateString();
                    $dateTo   = now()->toDateString();
                    $groupBy  = 'month';
                    break;
            }
        }

        $dateFrom = $dateFrom ?? now()->startOfMonth()->format('Y-m-d');
        $dateTo   = $dateTo ?? now()->format('Y-m-d');

        // Si las fechas vienen en formato Y-m (del filtro anterior), ajustarlas
        if (strlen($dateFrom) == 7) $dateFrom .= '-01';
        if (strlen($dateTo) == 7) $dateTo = Carbon::parse($dateTo)->endOfMonth()->toDateString();

        $start = Carbon::parse($dateFrom)->startOfDay();
        $end   = Carbon::parse($dateTo)->endOfDay();

        // Query Ventas
        $salesQuery = SalesMovement::query()
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$start, $end]);

        // Query Compras/Gastos
        $purchasesQuery = PurchaseMovement::query()
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$start, $end]);

        if ($branchId) {
            $salesQuery->where('branch_id', $branchId);
            $purchasesQuery->where('branch_id', $branchId);
        }

        $totalSales     = (float) $salesQuery->sum('total');
        $totalPurchases = (float) $purchasesQuery->sum('total');
        $netProfit      = $totalSales - $totalPurchases;

        $driver = DB::getDriverName();

        // Definir SQL de agrupación según el parámetro y el driver de base de datos
        $groupSql = match($driver) {
            'pgsql' => match($groupBy) {
                'week'  => "TO_CHAR(created_at, 'IYYYIW')",
                'month' => "TO_CHAR(created_at, 'YYYY-MM')",
                default => "created_at::date"
            },
            default => match($groupBy) {
                'week'  => 'YEARWEEK(created_at, 1)',
                'month' => 'DATE_FORMAT(created_at, "%Y-%m")',
                default => 'DATE(created_at)'
            }
        };

        // Datos para gráfico
        $salesByPeriod = $salesQuery->select(DB::raw("$groupSql as period"), DB::raw('SUM(total) as total'))
            ->groupBy(DB::raw($groupSql))
            ->orderBy('period')
            ->pluck('total', 'period')->toArray();

        $purchasesByPeriod = $purchasesQuery->select(DB::raw("$groupSql as period"), DB::raw('SUM(total) as total'))
            ->groupBy(DB::raw($groupSql))
            ->orderBy('period')
            ->pluck('total', 'period')->toArray();

        // Combinar periodos para el eje X
        $periods = array_unique(array_merge(array_keys($salesByPeriod), array_keys($purchasesByPeriod)));
        sort($periods);

        $dates = [];
        $chartSales = [];
        $chartPurchases = [];
        foreach ($periods as $period) {
            $dates[]          = $this->formatPeriodLabel($period, $groupBy);
            $chartSales[]     = round((float)($salesByPeriod[$period] ?? 0), 2);
            $chartPurchases[] = round((float)($purchasesByPeriod[$period] ?? 0), 2);
        }

        return [
            'dateFrom'       => $dateFrom,
            'dateTo'         => $dateTo,
            'groupBy'        => $groupBy,
            'period'         => $period,
            'totalSales'     => $totalSales,
            'totalPurchases' => $totalPurchases,
            'netProfit'      => $netProfit,
            'dates'          => $dates,
            'chartSales'     => $chartSales,
            'chartPurchases' => $chartPurchases
        ];
    }

    private function formatPeriodLabel($period, $groupBy)
    {
        try {
            if ($groupBy === 'week') {
                $year = substr($period, 0, 4);
                $week = substr($period, 4);
                return "Sem $week ($year)";
            }
            if ($groupBy === 'month') {
                return Carbon::parse($period . '-01')->translatedFormat('M Y');
            }
            return Carbon::parse($period)->format('d/m/Y');
        } catch (\Exception $e) {
            return $period;
        }
    }
}
