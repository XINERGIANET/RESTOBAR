<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Operation;
use App\Models\Shift;
use App\Support\InsensitiveSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShiftController extends Controller
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

        $shifts = Shift::query()
            ->with('branch')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    InsensitiveSearch::whereInsensitiveLike($inner, 'name', $search);
                    InsensitiveSearch::whereInsensitiveLike($inner, 'abbreviation', $search, 'or');
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $branches = Branch::all();

        return view('shifts.index', [
            'title' => 'Gestion de Turnos',
            'shifts' => $shifts,
            'branches' => $branches,
            'search' => $search,
            'perPage' => $perPage,
            'operaciones' => $operaciones,
        ]);
    }

    public function store(Request $request)
    {
        $branchId = session('branch_id');

        $request->validate([
            'name' => 'required|string|max:255',
            'start_time' => 'required',
            'end_time' => 'required',
        ]);

        if (!$branchId) {
            return back()->withErrors(['error' => 'No se pudo identificar tu sucursal.']);
        }

        try {
            Shift::create([
                'name' => $request->name,
                'abbreviation' => $request->abbreviation,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'branch_id' => $branchId,
            ]);

            $viewId = $request->input('view_id');

            return redirect()->route('shifts.index', $viewId ? ['view_id' => $viewId] : [])
                ->with('status', 'Turno creado correctamente');
        } catch (\Exception $e) {
            Log::error('Error al crear el turno: ' . $e->getMessage());
            return back()->withInput()->withErrors(['error' => 'Error al guardar: ' . $e->getMessage()]);
        }
    }

    public function edit(Request $request, $id)
    {
        $shift = Shift::findOrFail($id);
        $branches = Branch::all();

        return view('shifts.edit', [
            'shift' => $shift,
            'branches' => $branches,
            'viewId' => $request->input('view_id'),
        ]);
    }

    public function update(Request $request, $id)
    {
        $shift = Shift::findOrFail($id);

        $branchId = session('branch_id');

        if (!$branchId) {
            return back()->withErrors(['error' => 'No se pudo identificar tu sucursal.']);
        }

        try {
            $shift->update([
                'name' => $request->input('name'),
                'abbreviation' => $request->input('abbreviation'),
                'start_time' => $request->input('start_time'),
                'end_time' => $request->input('end_time'),
                'branch_id' => $branchId,
            ]);

            $viewId = $request->input('view_id');

            return redirect()->route('shifts.index', $viewId ? ['view_id' => $viewId] : [])
                ->with('status', 'Turno actualizado correctamente');
        } catch (\Exception $e) {
            Log::error('Error al actualizar el turno: ' . $e->getMessage());
            return back()->withInput()->withErrors(['error' => 'Error al actualizar: ' . $e->getMessage()]);
        }
    }

    public function destroy(Request $request, $id)
    {
        $shift = Shift::findOrFail($id);

        try {
            $shift->delete();

            $viewId = $request->input('view_id');

            return redirect()->route('shifts.index', $viewId ? ['view_id' => $viewId] : [])
                ->with('status', 'Turno eliminado correctamente');
        } catch (\Exception $e) {
            Log::error('Error al eliminar el turno', ['error' => $e->getMessage()]);
            return back()->withErrors(['error' => 'Error al eliminar: ' . $e->getMessage()]);
        }
    }
}
