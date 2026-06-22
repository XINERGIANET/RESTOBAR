<?php

namespace App\Http\Controllers;

use App\Models\Modules;
use App\Models\Operation;
use App\Support\InsensitiveSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ModulesController extends Controller
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

        $modules = Modules::query()
            ->when($search, function ($query) use ($search) {
                InsensitiveSearch::whereInsensitiveLike($query, 'name', $search);
            })
            ->orderBy('order_num', 'asc')
            ->paginate(10)
            ->withQueryString();

        return view('modules.index', [
            'title' => 'Módulos',
            'modules' => $modules,
            'operaciones' => $operaciones,
        ]);
    }
      
    
    
    public function store(Request $request)
    {
        try {
            Modules::create([
                'name'      => $request->name,
                'icon'      => $request->icon,     
                'order_num' => $request->order_num,
                'status'    => $request->status,  
            ]);

            $params = [];
            if ($request->has('view_id')) {
                $params['view_id'] = $request->input('view_id');
            }
            
            return redirect()->route('admin.modules.index', $params)
                ->with('status', 'Módulo creado correctamente');

        } catch (\Exception $e) {
            Log::error('Error al crear el modulo: ' . $e->getMessage());

            return back()->withInput()->withErrors(['error' => 'Error al guardar: ' . $e->getMessage()]);
        }
    }

    public function edit($id)
    {
        $module = Modules::findOrFail($id);

        return view('modules.edit', [
            'module' => $module
        ]);
    }

    public function update(Request $request, $id)
    {
        $module = Modules::findOrFail($id);

        try {
            $module->update([
                'name'      => $request->input('name'),
                'icon'      => $request->input('icon'), 
                'order_num' => $request->input('order_num'),
                'status'    => $request->input('status'),
            ]);

            $params = [];
            if ($request->has('view_id')) {
                $params['view_id'] = $request->input('view_id');
            }

            return redirect()->route('admin.modules.index', $params)
                ->with('status', 'Módulo actualizado correctamente');

        } catch (\Exception $e) {
            Log::error('Error al actualizar el modulo: ' . $e->getMessage());
            return back()->withInput()->withErrors(['error' => 'Error al actualizar: ' . $e->getMessage()]);
        }
    }

    public function destroy(Request $request, $id)
    {
        $module = Modules::findOrFail($id);

        try {
            $module->update([
                'status' => 0
            ]);

            $module->delete();

            $params = [];
            if ($request->has('view_id')) {
                $params['view_id'] = $request->input('view_id');
            }

            return redirect()->route('admin.modules.index', $params)
                ->with('status', 'Módulo eliminado correctamente');

        } catch (\Exception $e) {
            Log::error('Error al eliminar el módulo', ['error' => $e->getMessage()]);

            return back()->withErrors(['error' => 'Error al eliminar el módulo: ' . $e->getMessage()]);
        }
    }
}

