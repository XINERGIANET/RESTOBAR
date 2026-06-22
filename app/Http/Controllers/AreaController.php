<?php

namespace App\Http\Controllers;

use App\Exports\PlantillaAreasTablesExport;
use App\Imports\AreasTablesImport;
use App\Models\PrinterBranch;
use App\Support\InsensitiveSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Area;
use App\Models\Operation;
use Maatwebsite\Excel\Facades\Excel;

class AreaController extends Controller
{
    public function index(Request $request)
    {
        $branchId = \effective_branch_id();
        $search = $request->input('search');
        $perPage = (int) $request->input('per_page', 10);
        $allowedPerPage = [10, 20, 50, 100];
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }
        $viewId = $request->input('view_id');
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

        $areas = Area::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->with('printers:id,name')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    InsensitiveSearch::whereInsensitiveLike($inner, 'name', $search);
                    $inner->orWhereHas('branch', function ($branchQuery) use ($search) {
                        InsensitiveSearch::whereInsensitiveLike($branchQuery, 'legal_name', $search);
                    });
                });
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        $printers = PrinterBranch::query()
            ->where('branch_id', $branchId)
            ->where('status', 'E')
            ->orderBy('name')
            ->get(['id', 'name', 'ip']);

        return view('areas.index', compact('areas', 'operaciones', 'search', 'perPage', 'printers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'printer_ids' => ['nullable', 'array'],
            'printer_ids.*' => ['integer'],
        ], [
            'name.required' => 'El nombre del area es obligatorio.',
            'name.string' => 'El nombre debe ser texto.',
            'name.max' => 'El nombre no puede exceder 255 caracteres.',
        ]);

        try {
            $area = Area::create([
                'name' => $validated['name'],
                'branch_id' => session('branch_id'),
            ]);
            $this->syncAreaPrinters($area, $request->input('printer_ids', []));

            // Si viene un redirect_to, volver a esa URL (ej. vista de Mesas)
            if ($request->filled('redirect_to')) {
                return redirect($request->input('redirect_to'))
                    ->with('success', 'Area creada correctamente');
            }

            $params = [];
            if ($request->filled('view_id')) {
                $params['view_id'] = $request->input('view_id');
            }

            return redirect()->route('areas.index', $params)
                ->with('success', 'Area creada correctamente');
        } catch (\Exception $e) {
            // En error, si hay redirect_to, volver ahí con errores
            if ($request->filled('redirect_to')) {
                return redirect($request->input('redirect_to'))
                    ->withErrors(['error' => 'Error al crear el area: ' . $e->getMessage()])
                    ->withInput();
            }

            $params = [];
            if ($request->filled('view_id')) {
                $params['view_id'] = $request->input('view_id');
            }

            return redirect()->route('areas.index', $params)
                ->withErrors(['error' => 'Error al crear el area: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function edit(Request $request, Area $area)
    {
        $viewId = $request->input('view_id');
        $branchId = (int) session('branch_id');
        $printers = PrinterBranch::query()
            ->where('branch_id', $branchId)
            ->where('status', 'E')
            ->orderBy('name')
            ->get(['id', 'name', 'ip']);
        $area->load('printers:id,name');

        return view('areas.edit', compact('area', 'viewId', 'printers'));
    }

    public function update(Request $request, Area $area)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'printer_ids' => ['nullable', 'array'],
            'printer_ids.*' => ['integer'],
        ], [
            'name.required' => 'El nombre del area es obligatorio.',
            'name.string' => 'El nombre debe ser texto.',
            'name.max' => 'El nombre no puede exceder 255 caracteres.',
        ]);

        try {
            $area->update([
                'name' => $validated['name'],
                'branch_id' => session('branch_id'),
            ]);
            $this->syncAreaPrinters($area, $request->input('printer_ids', []));

            $params = [];
            if ($request->filled('view_id')) {
                $params['view_id'] = $request->input('view_id');
            }

            return redirect()->route('areas.index', $params)
                ->with('success', 'Area actualizada correctamente');
        } catch (\Exception $e) {
            Log::error('Error al actualizar el area: ' . $e->getMessage());

            $params = [];
            if ($request->filled('view_id')) {
                $params['view_id'] = $request->input('view_id');
            }

            return redirect()->route('areas.index', $params)
                ->withErrors(['error' => 'Error al actualizar el area: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function destroy(Request $request, Area $area)
    {
        try {
            $area->delete();

            $params = [];
            if ($request->filled('view_id')) {
                $params['view_id'] = $request->input('view_id');
            }

            return redirect()->route('areas.index', $params)
                ->with('success', 'Area eliminada correctamente');
        } catch (\Exception $e) {
            $params = [];
            if ($request->filled('view_id')) {
                $params['view_id'] = $request->input('view_id');
            }

            return redirect()->route('areas.index', $params)
                ->withErrors(['error' => 'Error al eliminar el area: ' . $e->getMessage()]);
        }
    }

    public function importForm(Request $request)
    {
        $viewId = $request->input('view_id');
        return view('areas.import', compact('viewId'));
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ], [
            'file.required' => 'Debes seleccionar un archivo.',
            'file.mimes'    => 'El archivo debe ser .xlsx o .xls.',
            'file.max'      => 'El archivo no debe superar 5 MB.',
        ]);

        $branchId = (int) session('branch_id');
        if (!$branchId) {
            return back()->withErrors(['file' => 'No hay sucursal activa en la sesión.']);
        }

        $import = new AreasTablesImport($branchId);
        Excel::import($import, $request->file('file'));

        $viewId = $request->input('view_id');

        return redirect()
            ->route('areas.import.form', $viewId ? ['view_id' => $viewId] : [])
            ->with([
                'import_errors'   => $import->errors(),
                'import_imported' => $import->imported(),
                'import_updated'  => $import->updated(),
                'import_areas_imported'  => $import->areasSheet->imported,
                'import_areas_updated'   => $import->areasSheet->updated,
                'import_tables_imported' => $import->tablesSheet->imported,
                'import_tables_updated'  => $import->tablesSheet->updated,
            ]);
    }

    public function downloadTemplate()
    {
        return Excel::download(new PlantillaAreasTablesExport(), 'plantilla_areas_mesas.xlsx');
    }

    private function syncAreaPrinters(Area $area, $printerIdsInput): void
    {
        $branchId = (int) session('branch_id');
        $ids = collect(is_array($printerIdsInput) ? $printerIdsInput : [])
            ->map(fn($id) => is_numeric($id) ? (int) $id : null)
            ->filter(fn($id) => $id && $id > 0)
            ->unique()
            ->values();
        if ($ids->isEmpty()) {
            $area->printers()->sync([]);
            return;
        }
        $validIds = PrinterBranch::query()
            ->where('branch_id', $branchId)
            ->whereIn('id', $ids->all())
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();

        $area->printers()->sync($validIds);
    }
}
