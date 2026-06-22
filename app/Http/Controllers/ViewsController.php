<?php

namespace App\Http\Controllers;

use App\Models\View;
use App\Models\Operation;
use App\Models\Branch;
use App\Support\InsensitiveSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ViewsController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
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

        $views = View::query()
            ->when($search, function ($query) use ($search) {
                InsensitiveSearch::whereInsensitiveLike($query, 'name', $search);
            })
            ->paginate(10)
            ->withQueryString(); 

        return view('views.index', [
            'title' => 'Vistas',
            'views' => $views,
            'operaciones' => $operaciones,
        ]);
    }
    
    public function store(Request $request)
    {
        $viewId = $request->input('view_id');
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'abbreviation' => 'nullable|string|max:255',
            'status' => 'required|in:0,1',
        ], [
            'name.required' => 'El nombre de la vista es obligatorio.',
            'name.string' => 'El nombre debe ser una cadena de texto.',
            'name.max' => 'El nombre no debe exceder los 255 caracteres.',
            'abbreviation.string' => 'La abreviatura debe ser una cadena de texto.',
            'abbreviation.max' => 'La abreviatura no debe exceder los 255 caracteres.',
            'status.required' => 'El estado es obligatorio.',
            'status.in' => 'El estado debe ser Activo o Inactivo.',
        ]);

        try {
            DB::transaction(function () use ($validated) {
                $view = View::create([
                    'name'      => $validated['name'],
                    'abbreviation' => $validated['abbreviation'] ?? null,
                    'status'    => (bool) $validated['status'],  
                ]);

                $branchIds = Branch::query()->pluck('id');
                if ($branchIds->isNotEmpty()) {
                    $now = now();
                    $viewBranchRows = $branchIds->map(fn ($branchId) => [
                        'view_id' => $view->id,
                        'branch_id' => $branchId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all();

                    DB::table('view_branch')->insert($viewBranchRows);
                }

                $base = $validated['abbreviation'] ?: $validated['name'];
                $actionBase = Str::slug($base, '.');

                $operations = [
                    [
                        'name' => 'Nuevo ' . $view->name,
                        'icon' => 'ri-add-line',
                        'action' => $actionBase . '.create',
                        'color' => '#12f00e',
                        'type' => 'T',
                    ],
                    [
                        'name' => 'Editar ' . $view->name,
                        'icon' => 'ri-pencil-line',
                        'action' => $actionBase . '.edit',
                        'color' => '#FBBF24',
                        'type' => 'R',
                    ],
                    [
                        'name' => 'Eliminar ' . $view->name,
                        'icon' => 'ri-delete-bin-line',
                        'action' => $actionBase . '.destroy',
                        'color' => '#EF4444',
                        'type' => 'R',
                    ],
                ];

                    foreach ($operations as $operation) {
                        Operation::create([
                            'name' => $operation['name'],
                            'icon' => $operation['icon'],
                            'action' => $operation['action'],
                            'view_id' => $view->id,
                            'color' => $operation['color'],
                            'status' => 1,
                            'type' => $operation['type'],
                        ]);
                    }
            });

            $redirectParams = $viewId ? ['view_id' => $viewId] : [];
            return redirect()->route('admin.views.index', $redirectParams)
                ->with('status', 'Vista creada correctamente');

        } catch (\Exception $e) {
            Log::error('Error al crear la vista: ' . $e->getMessage());

            return back()->withInput()->withErrors(['error' => 'Error al guardar: ' . $e->getMessage()]);
        }
    }

    public function edit($id)
    {
        $view = View::findOrFail($id);

        return view('views.edit', [
            'view' => $view
        ]);
    }

    public function update(Request $request, $id)
    {
        $view = View::findOrFail($id);

        try {
            $view->update([
                'name'      => $request->input('name'),
                'abbreviation' => $request->input('abbreviation'),
                'status'    => $request->input('status'),
            ]);

            $redirectParams = $request->filled('view_id') ? ['view_id' => $request->input('view_id')] : [];

            return redirect()->route('admin.views.index', $redirectParams)
                ->with('status', 'Vista actualizada correctamente');

        } catch (\Exception $e) {
            Log::error('Error al actualizar la vista: ' . $e->getMessage());
            return back()->withInput()->withErrors(['error' => 'Error al actualizar: ' . $e->getMessage()]);
        }
    }

    public function destroy($id)
    {
        $view = View::findOrFail($id);

        try {
            $view->update([
                'status' => 0
            ]);

            $view->delete();

            $redirectParams = request()->filled('view_id') ? ['view_id' => request()->input('view_id')] : [];
            return redirect()->route('admin.views.index', $redirectParams)
                ->with('status', 'Vista eliminada correctamente');

        } catch (\Exception $e) {
            Log::error('Error al eliminar la vista', ['error' => $e->getMessage()]);
            return back()->withErrors(['error' => 'Error al eliminar la vista: ' . $e->getMessage()]);
        }
    }
}
