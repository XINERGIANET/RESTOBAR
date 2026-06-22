<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\Operation;
use App\Support\InsensitiveSearch;
use Illuminate\Http\Request;

class UnitController extends Controller
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
        $units = Unit::query()
            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    InsensitiveSearch::whereInsensitiveLike($inner, 'description', $search);
                    InsensitiveSearch::whereInsensitiveLike($inner, 'abbreviation', $search, 'or');
                    InsensitiveSearch::whereInsensitiveLike($inner, 'type', $search, 'or');
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
        return view('units.index', [
            'units' => $units,
            'search' => $search,
            'perPage' => $perPage,
            'operaciones' => $operaciones,
        ]);
    }

    public function store(Request $request){
        $data = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'abbreviation' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:100'],
            'is_sunat' => ['nullable', 'boolean'],
        ]);

        $data['is_sunat'] = $request->has('is_sunat') ? (bool) $request->input('is_sunat') : false;

        Unit::create($data);
        $viewId = $request->input('view_id');
        return redirect()->route('admin.units.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Unidad creada correctamente.');
    }

    public function edit(Unit $unit){
        return view('units.edit', [
            'unit' => $unit,
        ]);
    }

    public function update(Request $request, Unit $unit){
        $data = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'abbreviation' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:100'],
            'is_sunat' => ['nullable', 'boolean'],
        ]);

        $data['is_sunat'] = $request->has('is_sunat') ? (bool) $request->input('is_sunat') : false;
        $unit->update($data);
        $viewId = $request->input('view_id');
        return redirect()->route('admin.units.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Unidad actualizada correctamente.');
    }

    public function destroy(Unit $unit){
        $unit->delete();
        $viewId = request('view_id');
        return redirect()->route('admin.units.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Unidad eliminada correctamente.');
    }
}
