<?php

namespace App\Http\Controllers;

use App\Models\DigitalWallet;
use App\Models\Operation;
use App\Support\InsensitiveSearch;
use Illuminate\Http\Request;

class DigitalWalletController extends Controller
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

        $digitalWallets = DigitalWallet::query()
            ->when($search, function ($query) use ($search) {
                InsensitiveSearch::whereInsensitiveLike($query, 'description', $search);
            })
            ->orderBy('order_num')
            ->paginate($perPage)
            ->withQueryString();

        return view('digital_wallets.index', compact('digitalWallets', 'search', 'perPage', 'allowedPerPage', 'operaciones'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'description' => 'required|string|max:255',
            'order_num' => 'required|integer',
            'status' => 'nullable|boolean',
        ]);

        try {
            DigitalWallet::create([
                'description' => $request->description,
                'order_num' => $request->order_num,
                'status' => $request->status ?? true,
            ]);

            $viewId = $request->input('view_id');
            return redirect()->route('admin.digital_wallets.index', $viewId ? ['view_id' => $viewId] : [])
                ->with('status', 'Billetera digital creada correctamente');
        } catch (\Exception $e) {
            $viewId = $request->input('view_id');
            return redirect()->route('admin.digital_wallets.index', $viewId ? ['view_id' => $viewId] : [])
                ->withErrors(['error' => 'Error al crear la billetera digital: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function update(Request $request, DigitalWallet $digitalWallet)
    {
        $request->validate([
            'description' => 'required|string|max:255',
            'order_num' => 'required|integer',
            'status' => 'nullable|boolean',
        ]);

        try {
            $digitalWallet->update([
                'description' => $request->description,
                'order_num' => $request->order_num,
                'status' => $request->status ?? true,
            ]);

            $viewId = $request->input('view_id');
            return redirect()->route('admin.digital_wallets.index', $viewId ? ['view_id' => $viewId] : [])
                ->with('status', 'Billetera digital actualizada correctamente');
        } catch (\Exception $e) {
            $viewId = $request->input('view_id');
            return redirect()->route('admin.digital_wallets.index', $viewId ? ['view_id' => $viewId] : [])
                ->withErrors(['error' => 'Error al actualizar la billetera digital: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function destroy(DigitalWallet $digitalWallet)
    {
        try {
            $digitalWallet->delete();
            $viewId = request('view_id');
            return redirect()->route('admin.digital_wallets.index', $viewId ? ['view_id' => $viewId] : [])
                ->with('status', 'Billetera digital eliminada correctamente');
        } catch (\Exception $e) {
            $viewId = request('view_id');
            return redirect()->route('admin.digital_wallets.index', $viewId ? ['view_id' => $viewId] : [])
                ->withErrors(['error' => 'Error al eliminar la billetera digital: ' . $e->getMessage()]);
        }
    }
}   
