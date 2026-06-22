<?php

namespace App\Http\Controllers;

use App\Models\ParameterCategories;
use App\Models\Parameters;
use App\Models\Operation;
use Illuminate\Http\Request;

class ParameterCategoriesController extends Controller
{
    public function index(Request $request)
    {
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
        $parameterCategories = ParameterCategories::query()
            ->when($search, function ($query) use ($search) {
                $query->where('description', 'ilike', "%{$search}%");
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
        return view('parameters.categoriesParam', [
            'parameterCategories' => $parameterCategories,
            'search' => $search,
            'perPage' => $perPage,
            'allowedPerPage' => $allowedPerPage,
            'operaciones' => $operaciones,
        ]);
    }
    public function store(Request $request)
    {
        $request->validate(
            [
                'description' => 'required|string|max:255',
            ],
            [
                'description.required' => 'La descripcion es requerida',
                'description.string' => 'La descripcion debe ser una cadena de texto',
                'description.max' => 'La descripcion debe tener menos de 255 caracteres',
            ]
        );
        ParameterCategories::create($request->all());
        $viewId = $request->input('view_id');
        return redirect()
            ->route('admin.parameters.categories.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Categoria creada correctamente');
    }

    public function destroy(ParameterCategories $parameterCategory)
    {
        // Evitar eliminar una categoría si tiene parámetros relacionados (incluyendo soft-deleted)
        $hasRelatedParameters = Parameters::withTrashed()
            ->where('parameter_category_id', $parameterCategory->id)
            ->exists();

        if ($hasRelatedParameters) {
            return redirect()
                ->route('admin.parameters.categories.index', request('view_id') ? ['view_id' => request('view_id')] : [])
                ->with('error', 'No se puede eliminar esta categoría porque tiene parámetros relacionados.');
        }

        $parameterCategory->delete();
        $viewId = request('view_id');
        return redirect()
            ->route('admin.parameters.categories.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Categoria eliminada correctamente');
    }

    public function update(Request $request, ParameterCategories $parameterCategory)
    {
        $request->validate(
            [
                'description' => 'required|string|max:255',
            ],
            [
                'description.required' => 'La descripcion es requerida',
                'description.string' => 'La descripcion debe ser una cadena de texto',
                'description.max' => 'La descripcion debe tener menos de 255 caracteres',
            ]
        );
        $parameterCategory->update($request->all());
        $viewId = $request->input('view_id');
        return redirect()
            ->route('admin.parameters.categories.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Categoria actualizada correctamente');
    }
}

