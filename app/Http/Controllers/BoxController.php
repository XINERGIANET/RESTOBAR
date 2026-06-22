<?php

namespace App\Http\Controllers;

use App\Support\InsensitiveSearch;
use Illuminate\Http\Request;
use App\Models\CashRegister;
use App\Models\Operation;

class BoxController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $perPage = (int) $request->input('per_page', 10);
        $allowedPerPage = [10, 20, 50, 100];
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }
        $viewId = $request->input('view_id');
        $branchId = \effective_branch_id();
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

        $cash = CashRegister::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    InsensitiveSearch::whereInsensitiveLike($inner, 'number', $search);
                    InsensitiveSearch::whereInsensitiveLike($inner, 'series', $search, 'or');
                });
            })
            ->orderBy('number', 'asc')
            ->paginate($perPage)
            ->withQueryString();

        return view('boxes.index', [
            'title' => 'Cajas Registradoras',
            'cash'  => $cash,
            'search' => $search,
            'perPage' => $perPage,
            'operaciones' => $operaciones,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'number' => 'required|string|max:50',
            'series' => 'required|string|max:10',
            'status' => 'required|boolean',
        ], [
            'number.required' => 'El nombre de caja es obligatorio.',
            'series.required' => 'La serie es obligatoria.',
        ]);

        try {
            CashRegister::create([
                'number'    => $validated['number'],
                'series'    => $validated['series'],
                'status'    => $validated['status'],
                'branch_id' => $request->session()->get('branch_id'),
            ]);
            $viewId = $request->input('view_id');
            
            return redirect()->route('boxes.index', $viewId ? ['view_id' => $viewId] : [])
                ->with('success', 'Caja creada correctamente');

        } catch (\Exception $e) {
            $viewId = $request->input('view_id');
            return redirect()->route('boxes.index', $viewId ? ['view_id' => $viewId] : [])
                ->withErrors(['error' => 'Error al crear la caja: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function edit(Request $request, CashRegister $box)
    {
        $branchId = \effective_branch_id();
        $cash = CashRegister::query()
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->paginate(10);

        return view('boxes.edit', [
            'title' => 'Cajas',
            'cash'  => $cash,
            'box'   => $box,
            'viewId' => $request->input('view_id'),
        ]);
    }

    public function update(Request $request, CashRegister $box)
    {
        $validated = $request->validate([
            'number' => 'required|string|max:50',
            'series' => 'required|string|max:10',
            'status' => 'required|boolean',
        ]);
        
        try {
            $box->update([
                'number' => $validated['number'],
                'series' => $validated['series'],
                'status' => $validated['status'],
            ]);
            $viewId = $request->input('view_id');
            
            return redirect()->route('boxes.index', $viewId ? ['view_id' => $viewId] : [])
                ->with('success', 'Caja actualizada correctamente');

        } catch (\Exception $e) {
            $viewId = $request->input('view_id');
            return redirect()->route('boxes.index', $viewId ? ['view_id' => $viewId] : [])
                ->withErrors(['error' => 'Error al actualizar: ' . $e->getMessage()])
            ->withInput();
        }
    }

    public function destroy(Request $request, CashRegister $box)
    {
        try {
            $box->delete();
            $viewId = $request->input('view_id');
            return redirect()->route('boxes.index', $viewId ? ['view_id' => $viewId] : [])
                ->with('success', 'Caja eliminada correctamente');
        } catch (\Exception $e) {
            $viewId = $request->input('view_id');
            return redirect()->route('boxes.index', $viewId ? ['view_id' => $viewId] : [])
                ->withErrors(['error' => 'Error al eliminar: ' . $e->getMessage()]);
        }
    }
}
