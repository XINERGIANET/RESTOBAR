<?php

namespace App\Http\Controllers;

use App\Models\Module;
use App\Models\MenuOption;
use App\Models\Branch;
use App\Models\Profile;
use App\Models\View; 
use App\Models\Operation;
use App\Support\InsensitiveSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MenuOptionController extends Controller
{
    public function index(Request $request, Module $module)
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
       
        // 2. Obtener las vistas activas para el select
        $views = View::where('status', 1)->orderBy('name', 'asc')->get();

        $menuOptions = $module->menuOptions()
            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    InsensitiveSearch::whereInsensitiveLike($inner, 'name', $search);
                    InsensitiveSearch::whereInsensitiveLike($inner, 'action', $search, 'or');
                });
            })
            ->orderBy('id', 'asc')
            ->paginate(10)
            ->withQueryString();

        return view('menu_options.index', [
            'module' => $module,      
            'menuOptions' => $menuOptions,
            'search' => $search,
            'views' => $views,
            'operaciones' => $operaciones,
        ]);
    }

    public function create(Module $module)
    {
        $views = View::where('status', 1)->orderBy('name', 'asc')->get();

        return view('menu_options.create', [
            'module' => $module,
            'views' => $views,
        ]);
    }

    public function store(Request $request, Module $module)
    {
        $data = $this->validateData($request);

        DB::transaction(function () use ($module, $data) {
            $menuOption = $module->menuOptions()->create($data);
            $branchIds = Branch::query()->pluck('id');
            $profileIds = Profile::query()->pluck('id');

            if ($branchIds->isEmpty() || $profileIds->isEmpty()) {
                return;
            }

            $now = now();
            $rows = [];
            foreach ($branchIds as $branchId) {
                foreach ($profileIds as $profileId) {
                    $rows[] = [
                        'id' => (string) Str::uuid(),
                        'name' => $menuOption->name,
                        'profile_id' => $profileId,
                        'menu_option_id' => $menuOption->id,
                        'branch_id' => $branchId,
                        'status' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            DB::table('user_permission')->insert($rows);
        });

        return redirect()
            ->route('admin.modules.menu_options.index', request('view_id') ? [$module, 'view_id' => request('view_id')] : $module)
            ->with('status', 'Opción de menú creada correctamente.')
            ->with('status_route', 'admin.modules.menu_options');
    }

    public function show(Module $module, MenuOption $menuOption)
    {
        $menuOption = $this->resolveScope($module, $menuOption);

        return view('menu_options.show', [
            'module' => $module,
            'menuOption' => $menuOption,
        ]);
    }

    public function edit(Module $module, MenuOption $menuOption)
    {
        $menuOption = $this->resolveScope($module, $menuOption);
        $views = View::where('status', 1)->orderBy('name', 'asc')->get(); 
        
        return view('menu_options.edit', [
            'module' => $module,
            'menuOption' => $menuOption,
            'views' => $views,
        ]);
    }

    public function update(Request $request, Module $module, MenuOption $menuOption)
    {
        $menuOption = $this->resolveScope($module, $menuOption);
        $data = $this->validateData($request);

        $menuOption->update($data);

        return redirect()
            ->route('admin.modules.menu_options.index', request('view_id') ? [$module, 'view_id' => request('view_id')] : $module)
            ->with('status', 'Opción de menú actualizada correctamente.')
            ->with('status_route', 'admin.modules.menu_options');
    }

    public function destroy(Module $module, MenuOption $menuOption)
    {
        $menuOption = $this->resolveScope($module, $menuOption);
        DB::transaction(function () use ($menuOption) {
            DB::table('user_permission')
                ->where('menu_option_id', $menuOption->id)
                ->delete();

            $menuOption->delete();
        });

        return redirect()
            ->route('admin.modules.menu_options.index', request('view_id') ? [$module, 'view_id' => request('view_id')] : $module)
            ->with('status', 'Opción de menú eliminada correctamente.')
            ->with('status_route', 'admin.modules.menu_options');
    }

    // --- Validaciones y Helpers ---

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:255'],
            'action' => ['required', 'string', 'max:255'],
            'view_id' => ['required', 'integer', 'exists:views,id'], 
            'status' => ['required', 'boolean'],
            'quick_access' => ['required', 'boolean'],
        ]);
    }

    private function resolveScope(Module $module, MenuOption $menuOption): MenuOption
    {
        if ($menuOption->module_id !== $module->id) {
            abort(404);
        }

        return $menuOption;
    }
}
