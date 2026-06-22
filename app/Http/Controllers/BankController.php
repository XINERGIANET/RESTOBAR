<?php

namespace App\Http\Controllers;

use App\Support\InsensitiveSearch;
use Illuminate\Http\Request;
use App\Models\Bank;
use App\Models\Operation;

class BankController extends Controller
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
        $banks = Bank::query()
            ->when($search, function ($query) use ($search) {
                InsensitiveSearch::whereInsensitiveLike($query, 'description', $search);
            })
            ->orderBy('order_num')
            ->paginate($perPage)
            ->withQueryString();
        return view('banks.index', compact('banks', 'search', 'perPage', 'allowedPerPage', 'operaciones'));
    }
    public function store(Request $request){
        $request->validate([
            'description' => 'required|string|max:255',
            'order_num' => 'required|integer',
            'status' => 'required|boolean',
        ]);
        Bank::create($request->all());
        $viewId = $request->input('view_id');
        return redirect()->route('admin.banks.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Banco creado correctamente');
    }
    public function edit(Bank $bank){
        return view('banks.edit', compact('bank'));
    }
    public function update(Request $request, Bank $bank){
        $request->validate([
            'description' => 'required|string|max:255',
            'order_num' => 'required|integer',
            'status' => 'required|boolean',
        ]);
        $bank->update($request->all());
        $viewId = $request->input('view_id');
        return redirect()->route('admin.banks.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Banco actualizado correctamente');
    }
    public function destroy(Bank $bank){
        $bank->delete();
        $viewId = request('view_id');
        return redirect()->route('admin.banks.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Banco eliminado correctamente');
    }
}
