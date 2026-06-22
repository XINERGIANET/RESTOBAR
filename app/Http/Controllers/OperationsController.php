<?php

namespace App\Http\Controllers;

use App\Models\View;
use App\Models\Operation;
use App\Models\Branch;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OperationsController extends Controller
{
    public function index(Request $request, View $view) 
    {
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

        $operations = $view->operations()
            ->orderBy('id', 'asc')
            ->paginate(10)
            ->withQueryString();

        return view('views.operations.index', [
            'view' => $view,
            'operations' => $operations,
            'viewsList' => View::query()->orderBy('name')->get(['id', 'name']),
            'operaciones' => $operaciones,
        ]);
    }

    public function store(Request $request, View $view)
    {
        $data = $this->validateData($request);

        DB::transaction(function () use ($view, $data) {
            $operation = $view->operations()->create($data);
            $status = (int) ($operation->status ?? 1);
            $now = now();

            $branches = Branch::query()->pluck('id');
            $profiles = Profile::query()->pluck('id');

            if ($branches->isNotEmpty()) {
                $branchOperationRows = $branches->map(fn ($branchId) => [
                    'operation_id' => $operation->id,
                    'branch_id' => $branchId,
                    'status' => $status,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                DB::table('branch_operation')->insert($branchOperationRows);
            }

            if ($branches->isNotEmpty() && $profiles->isNotEmpty()) {
                $operationProfileBranchRows = [];
                foreach ($branches as $branchId) {
                    foreach ($profiles as $profileId) {
                        $operationProfileBranchRows[] = [
                            'operation_id' => $operation->id,
                            'profile_id' => $profileId,
                            'branch_id' => $branchId,
                            'status' => $status,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                DB::table('operation_profile_branch')->insert($operationProfileBranchRows);
            }
        });

        $viewId = $request->input('view_id');

        return redirect()
            ->route('admin.views.operations.index', $viewId ? [$view, 'view_id' => $viewId] : $view)
            ->with('status', 'Operación creada correctamente.');
    }


    public function edit(View $view, Operation $operation)
    {
        $operation = $this->resolveScope($view, $operation);

        return view('views.operations.edit', [
            'view' => $view,
            'operation' => $operation,
            'viewsList' => View::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, View $view, Operation $operation)
    {
        $operation = $this->resolveScope($view, $operation);
        $data = $this->validateData($request);

        $operation->update($data);

        $viewId = $request->input('view_id');
       
        return redirect()
            ->route('admin.views.operations.index', $viewId ? [$view, 'view_id' => $viewId] : $view)
            ->with('status', 'Operación actualizada correctamente.');
    }

    public function destroy(View $view, Operation $operation)
    {
        $operation = $this->resolveScope($view, $operation);

        DB::transaction(function () use ($operation) {
            DB::table('branch_operation')
                ->where('operation_id', $operation->id)
                ->delete();

            DB::table('operation_profile_branch')
                ->where('operation_id', $operation->id)
                ->delete();

            $operation->deleteOrFail();
        });

        $viewId = request('view_id');

        return redirect()
            ->route('admin.views.operations.index', $viewId ? [$view, 'view_id' => $viewId] : $view)
            ->with('status', 'Operación eliminada correctamente.');
    }



    private function validateData(Request $request): array
    {
        $request->merge([
            'view_id_action' => $request->input('view_id_action') ?: null,
        ]);

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'icon' => ['required', 'string', 'max:255'], // Asumo requerido para botones
            'action' => ['required', 'string', 'max:255'], // Ej: create, edit, delete, export
            'view_id_action' => ['nullable', 'integer', 'exists:views,id'],
            'color' => ['required', 'string', 'max:50'],   // Nuevo campo Color
            'status' => ['required', 'boolean'],
            'type' => ['required', 'in:R,T'],
        ]);
    }

    private function resolveScope(View $view, Operation $operation): Operation
    {
        if ($operation->view_id !== $view->id) {
            abort(404);
        }

        return $operation;
    }
}





