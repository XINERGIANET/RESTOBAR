<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Operation;
use App\Models\Table;
use App\Support\InsensitiveSearch;
use Illuminate\Http\Request;

class TableController extends Controller
{
    public function indexAll(Request $request)
    {
        $search = $request->input('search');
        $perPage = (int) $request->input('per_page', 10);
        $allowedPerPage = [10, 20, 50, 100];
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

        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        $tables = Table::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($search, function ($query) use ($search) {
                InsensitiveSearch::whereInsensitiveLike($query, 'name', $search);
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $areas = Area::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->orderBy('name')
            ->get();

        return view('tables.index', [
            'tables' => $tables,
            'areas' => $areas,
            'search' => $search,
            'perPage' => $perPage,
            'operaciones' => $operaciones,
        ]);
    }

    public function index(Area $area, Request $request)
    {
        $search = $request->input('search');
        $perPage = (int) $request->input('per_page', 10);
        $allowedPerPage = [10, 20, 50, 100];
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        $tables = Table::query()
            ->where('area_id', $area->id)
            ->when($search, function ($query) use ($search) {
                InsensitiveSearch::whereInsensitiveLike($query, 'name', $search);
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
                
        return view('areas.tables.index', [
            'tables' => $tables,
            'area' => $area,
            'search' => $search,
            'perPage' => $perPage,
        ]);
    }

    public function store(Area $area, Request $request)
    {   
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'status' => ['required', 'integer', 'in:0,1'],
            'situation' => ['required', 'in:libre,ocupada'],
            'opened_at' => ['nullable', 'date_format:H:i'],
        ]);

        Table::create([
            'name' => $data['name'],
            'capacity' => $data['capacity'],
            'status' => $data['status'],
            'situation' => $data['situation'],
            'opened_at' => $data['opened_at'],
            'area_id' => $area->id,
            'branch_id' => $area->branch_id,
        ]);

        return redirect()->route('areas.tables.index', $area)
            ->with('status', 'Mesa creada correctamente');
    }

    public function storeGeneral(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'status' => ['required', 'integer', 'in:0,1'],
            'situation' => ['required', 'in:libre,ocupada'],
            'opened_at' => ['nullable', 'date_format:H:i'],
            'area_id' => ['required', 'integer', 'exists:areas,id'],
        ]);

        $area = Area::findOrFail($data['area_id']);

        Table::create([
            'name' => $data['name'],
            'capacity' => $data['capacity'],
            'status' => $data['status'],
            'situation' => $data['situation'] ?? 'libre',
            'opened_at' => $data['opened_at'],
            'area_id' => $area->id,
            'branch_id' => session('branch_id') ?? $area->branch_id,
        ]);

        $viewId = $request->input('view_id');

        return redirect()->route('tables.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Mesa creada correctamente');
    }

    public function edit(Area $area, Table $table)
    {
        return view('areas.tables.edit', [
            'area' => $area,
            'table' => $table,
        ]);
    }

    public function editGeneral(Table $table)
    {
        $branchId = session('branch_id');
        $areas = Area::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->orderBy('name')
            ->get();

        return view('tables.edit', [
            'table' => $table,
            'areas' => $areas,
            'viewId' => request()->input('view_id'),
        ]);
    }

    public function update(Area $area, Table $table, Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'status' => ['required', 'integer', 'in:0,1'],
            'situation' => ['required', 'in:libre,ocupada'],
            'opened_at' => ['nullable', 'date_format:H:i'],
        ]);

        $table->update($data);

        return redirect()->route('areas.tables.index', $area)
            ->with('status', 'Mesa actualizada correctamente');
    }

    public function updateGeneral(Table $table, Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'status' => ['required', 'integer', 'in:0,1'],
            'situation' => ['required', 'in:libre,ocupada'],
            'opened_at' => ['nullable', 'date_format:H:i'],
            'area_id' => ['required', 'integer', 'exists:areas,id'],
        ]);

        $area = Area::findOrFail($data['area_id']);

        $table->update([
            'name' => $data['name'],
            'capacity' => $data['capacity'],
            'status' => $data['status'],
            'situation' => $data['situation'],
            'opened_at' => $data['opened_at'],
            'area_id' => $area->id,
            'branch_id' => session('branch_id') ?? $area->branch_id,
        ]);

        $viewId = $request->input('view_id');

        return redirect()->route('tables.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Mesa actualizada correctamente');
    }

    public function destroy(Area $area, Table $table)
    {
        $table->delete();

        return redirect()->route('areas.tables.index', $area)
            ->with('status', 'Mesa eliminada correctamente');
    }

    public function destroyGeneral(Table $table)
    {
        $table->delete();

        $viewId = request()->input('view_id');

        return redirect()->route('tables.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Mesa eliminada correctamente');
    }
}
