<?php

namespace App\Http\Controllers;

use App\Models\TaxRate;
use App\Models\Operation;
use App\Support\InsensitiveSearch;
use Illuminate\Http\Request;

class TaxRateController extends Controller
{
    public function index(Request $request){
        $search = $request->input('search');
        $perPage = (int) $request->input('per_page', 10);
        $allowedPerPage = [10, 20, 50, 100];
        $viewId = $request->input('view_id');
        $branchId = $request->session()->get('branch_id');
        $profileId = $request->session()->get('profile_id') ?? $request->user()?->profile_id;
        $operaciones = collect();
        if ($viewId && $branchId && $profileId) {
            $operaciones = Operation::query()
                ->select('operations.*')
                ->join('branch_operation', function ($join) use ($branchId) {
                    $join->on('branch_operation.operation_id', '=', 'operations.id')
                        ->where('branch_operation.branch_id', $branchId)
                        ->where('branch_operation.status', 1)
                        ->whereNull('branch_operation.deleted_at');
                })
                ->join('operation_profile_branch', function ($join) use ($branchId, $profileId) {
                    $join->on('operation_profile_branch.operation_id', '=', 'operations.id')
                        ->where('operation_profile_branch.branch_id', $branchId)
                        ->where('operation_profile_branch.profile_id', $profileId)
                        ->where('operation_profile_branch.status', 1)
                        ->whereNull('operation_profile_branch.deleted_at');
                })
                ->where('operations.status', 1)
                ->where('operations.view_id', $viewId)
                ->whereNull('operations.deleted_at')
                ->orderBy('operations.id')
                ->distinct()
                ->get();
        }
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }
        $taxRates = TaxRate::query()
            ->when($search, function ($query) use ($search) {
                InsensitiveSearch::whereInsensitiveLike($query, 'description', $search);
            })
            ->orderBy('order_num')
            ->paginate($perPage)
            ->withQueryString();
        return view('tax_rates.index', compact('taxRates', 'search', 'perPage', 'allowedPerPage', 'operaciones'));
    }
    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:255',
            'description' => 'required|string|max:255',
            'tax_rate' => 'required|numeric',
            'order_num' => 'required|integer',
            'status' => ['nullable', 'boolean'],
        ]);

        $data['status'] = $request->has('status') ? (bool) $request->input('status') : false;

        $taxRate = TaxRate::create($data);
        $viewId = $request->input('view_id');

        return redirect()
            ->route('admin.tax_rates.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Tasa de impuesto creada correctamente.');
    }

    public function edit(TaxRate $taxRate)
    {
        return view('tax_rates.edit', compact('taxRate'));
    }


    public function update(Request $request, TaxRate $taxRate)
    {
        $data = $request->validate([
            'code' => 'required|string|max:255',
            'description' => 'required|string|max:255',
            'tax_rate' => 'required|numeric',
            'order_num' => 'required|integer',
            'status' => ['nullable', 'boolean'],
        ]);

        $data['status'] = $request->has('status') ? (bool) $request->input('status') : false;

        $taxRate->update($data);
        $viewId = $request->input('view_id');

        return redirect()
            ->route('admin.tax_rates.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Tasa de impuesto actualizada correctamente.');
    }

    public function destroy(Request $request, TaxRate $taxRate)
    {
        $taxRate->delete();
        $viewId = $request->input('view_id');

        return redirect()
            ->route('admin.tax_rates.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Tasa de impuesto eliminada correctamente.');
    }
}
