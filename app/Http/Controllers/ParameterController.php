<?php

namespace App\Http\Controllers;

use App\Models\ParameterCategories;
use App\Models\Parameters;
use App\Models\Branch;
use App\Models\Operation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ParameterController extends Controller
{
    public function index(Request $request){
        $search = $request->input('search');
        $allowedPerPage = [10, 20, 50, 100];
        $perPage = (int) $request->input('per_page', 10);
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

        $parameters = Parameters::with('parameterCategory')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('description', 'ilike', "%{$search}%")
                        ->orWhere('value', 'ilike', "%{$search}%")
                        ->orWhereHas('parameterCategory', function ($query) use ($search) {
                            $query->where('description', 'ilike', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $parameterCategories = ParameterCategories::all();
        
        return view('parameters.index', [
            'parameters' => $parameters,
            'search' => $search,
            'parameterCategories' => $parameterCategories,
            'allowedPerPage' => $allowedPerPage,
            'perPage' => $perPage,
            'operaciones' => $operaciones,
        ]);
    }

    public function store(Request $request){
        $request->validate([
            'description' => 'required|string|max:255',
            'value' => 'required|string|max:255',
            'parameter_category_id' => 'required|exists:parameter_categories,id',
            'status' => 'Integer:0,1',
        ], [
            'status.integer' => 'El estado debe ser 0 o 1',
            'description.required' => 'La descripcion es requerida',
            'description.string' => 'La descripcion debe ser una cadena de texto',
            'description.max' => 'La descripcion debe tener menos de 255 caracteres',
            'value.required' => 'El valor es requerido',
            'value.string' => 'El valor debe ser una cadena de texto',
            'value.max' => 'El valor debe tener menos de 255 caracteres',
            'parameter_category_id.required' => 'La categoria es requerida',
            'parameter_category_id.exists' => 'La categoria seleccionada no existe',
        ]);
        
        try {
            DB::transaction(function () use ($request) {
                $parameter = Parameters::create([
                    'description' => $request->description,
                    'value' => $request->value,
                    'parameter_category_id' => $request->parameter_category_id,
                    'status' => $request->status ? $request->status : 1,
                ]);

                $branchIds = Branch::query()->pluck('id');
                if ($branchIds->isNotEmpty()) {
                    $now = now();
                    $rows = $branchIds->map(fn ($branchId) => [
                        'value' => $parameter->value,
                        'parameter_id' => $parameter->id,
                        'branch_id' => $branchId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all();

                    DB::table('branch_parameters')->insert($rows);
                }
            });
            $viewId = $request->input('view_id');
            return redirect()
                ->route('admin.parameters.index', $viewId ? ['view_id' => $viewId] : [])
                ->with('status', 'Parametro creado correctamente');
        } catch (\Exception $e) {
            $viewId = $request->input('view_id');
            return redirect()
                ->route('admin.parameters.index', $viewId ? ['view_id' => $viewId] : [])
                ->withErrors(['error' => 'Error al crear el parametro: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function update(Request $request, Parameters $parameter){
        $request->validate([
            'description' => 'required|string|max:255',
            'value' => 'required|string|max:255',
            'parameter_category_id' => 'required|exists:parameter_categories,id',
        ], [
            'description.required' => 'La descripcion es requerida',
            'description.string' => 'La descripcion debe ser una cadena de texto',
            'description.max' => 'La descripcion debe tener menos de 255 caracteres',
            'value.required' => 'El valor es requerido',
            'value.string' => 'El valor debe ser una cadena de texto',
            'value.max' => 'El valor debe tener menos de 255 caracteres',
            'parameter_category_id.required' => 'La categoria es requerida',
            'parameter_category_id.exists' => 'La categoria seleccionada no existe',
        ]);
        
        try {
            $parameter->update($request->all());
            $viewId = $request->input('view_id');
            return redirect()
                ->route('admin.parameters.index', $viewId ? ['view_id' => $viewId] : [])
                ->with('status', 'Parametro actualizado correctamente');
        } catch (\Exception $e) {
            $viewId = $request->input('view_id');
            return redirect()
                ->route('admin.parameters.index', $viewId ? ['view_id' => $viewId] : [])
                ->withErrors(['error' => 'Error al actualizar el parametro: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function destroy(Parameters $parameter){
        try {
            $parameter->delete();
            $viewId = request('view_id');
            return redirect()
                ->route('admin.parameters.index', $viewId ? ['view_id' => $viewId] : [])
                ->with('status', 'Parametro eliminado correctamente');
        } catch (\Exception $e) {
            $viewId = request('view_id');
            return redirect()
                ->route('admin.parameters.index', $viewId ? ['view_id' => $viewId] : [])
                ->withErrors(['error' => 'Error al eliminar el parametro: ' . $e->getMessage()]);
        }
    }
}
