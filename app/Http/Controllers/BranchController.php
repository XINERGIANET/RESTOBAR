<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BranchElectronicBillingConfig;
use App\Models\Company;
use App\Models\Location;
use App\Models\Module;
use App\Models\Operation;
use App\Models\Person;
use App\Models\ProductType;
use App\Models\Profile;
use App\Models\Role;
use App\Models\User;
use App\Models\View;
use App\Support\InsensitiveSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BranchController extends Controller
{
    /**
     * Fija la sucursal en sesión (cambiar de sucursal).
     */
    public function setSessionBranch(Request $request)
    {
        $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
        ]);
        $request->session()->put('branch_id', (int) $request->branch_id);
        $url = $request->input('redirect', url()->previous());
        return redirect($url)->with('status', 'Sucursal actualizada.');
    }

    public function index(Request $request, Company $company)
    {
        $search = $request->input('search');
        $viewId = $request->input('view_id');
        if ($viewId) {
            $request->session()->put('branch_view_id', $viewId);
        }
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
     

        $perPage = (int) $request->input('per_page', 10);
        $allowedPerPage = [10, 20, 50, 100];
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        $branches = $company->branches()
            ->with('location')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    InsensitiveSearch::whereInsensitiveLike($inner, 'legal_name', $search);
                    InsensitiveSearch::whereInsensitiveLike($inner, 'ruc', $search, 'or');
                    InsensitiveSearch::whereInsensitiveLike($inner, 'address', $search, 'or');
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('branches.index', [
            'company' => $company,
            'branches' => $branches,
            'search' => $search,
            'perPage' => $perPage,
            'operaciones' => $operaciones,
        ] + $this->getLocationData());
    }

    public function create(Company $company)
    {
        return view('branches.create', [
            'company' => $company,
        ] + $this->getLocationData());
    }

    public function store(Request $request, Company $company)
    {
        $data = $this->validateBranch($request);
        $data['company_id'] = $company->id;
        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('branches/logos', 'public');
            $data['logo'] = Storage::url($path);
        }

        DB::transaction(function () use ($company, $data, $request) {
            $branch = $company->branches()->create($data);
            $this->syncElectronicBillingConfig($branch, $request);
            $this->replicateBranchConfiguration($branch->id);
            $this->setupNewBranch($branch);
        });

        $params = [];
        if ($request->filled('view_id')) {
            $params['view_id'] = $request->input('view_id');
        }
        if ($request->filled('branch_view_id')) {
            $params['branch_view_id'] = $request->input('branch_view_id');
        }
        if ($request->filled('company_view_id')) {
            $params['company_view_id'] = $request->input('company_view_id');
        }
        if ($request->filled('icon')) {
            $params['icon'] = $request->input('icon');
        }
        $redirectParams = !empty($params) ? array_merge([$company], $params) : $company;

        return redirect()
            ->route('admin.companies.branches.index', $redirectParams)
            ->with('status', 'Sucursal creada correctamente.');
    }

    public function show(Company $company, Branch $branch)
    {
        $branch = $this->resolveBranch($company, $branch);

        return view('branches.show', [
            'company' => $company,
            'branch' => $branch,
        ]);
    }

    public function edit(Company $company, Branch $branch)
    {
        $branch = $this->resolveBranch($company, $branch);

        // Parámetro por sucursal: requerir PIN de mozo al tomar pedidos
        // Parámetro global: usar la descripción del parámetro creado en la pantalla de Parámetros
        // Descripción configurada: "Requerir PIN a mozo"
        $waiterPinEnabledValue = DB::table('branch_parameters as bp')
            ->join('parameters as p', 'p.id', '=', 'bp.parameter_id')
            ->whereNull('bp.deleted_at')
            ->whereNull('p.deleted_at')
            ->where('bp.branch_id', $branch->id)
            ->where('p.description', 'Requerir PIN a mozo')
            ->value('bp.value');
        $waiterPinEnabled = $waiterPinEnabledValue !== null && (string) $waiterPinEnabledValue !== '0';

        return view('branches.edit', [
            'company' => $company,
            'branch' => $branch,
            'waiterPinEnabled' => $waiterPinEnabled,
        ] + $this->getLocationData($branch));
    }

    public function profiles(Request $request, Company $company, Branch $branch)
    {
        $branch = $this->resolveBranch($company, $branch);
        $search = $request->input('search');
        if ($request->filled('view_id')) {
            $request->session()->put('profile_view_id', $request->input('view_id'));
        }
        $viewId = $request->input('view_id');
        $sessionBranchId = $request->session()->get('branch_id');
        $profileId = $request->session()->get('profile_id') ?? $request->user()?->profile_id;
        $operaciones = collect();

        if ($viewId) {
            $operaciones = Operation::query()
                ->select('operations.*')
                ->where('operations.status', 1)
                ->where('operations.view_id', $viewId)
                ->whereNull('operations.deleted_at')
                ->where(function ($query) use ($branch, $sessionBranchId) {
                    // Operaciones habilitadas para la sucursal que se está gestionando
                    $query->whereExists(function ($q) use ($branch) {
                        $q->select(DB::raw(1))
                            ->from('branch_operation')
                            ->whereColumn('branch_operation.operation_id', 'operations.id')
                            ->where('branch_operation.branch_id', $branch->id)
                            ->where('branch_operation.status', 1)
                            ->whereNull('branch_operation.deleted_at');
                    });
                    // O para la sucursal de sesión del admin (si es diferente)
                    if ($sessionBranchId && $sessionBranchId !== $branch->id) {
                        $query->orWhereExists(function ($q) use ($sessionBranchId) {
                            $q->select(DB::raw(1))
                                ->from('branch_operation')
                                ->whereColumn('branch_operation.operation_id', 'operations.id')
                                ->where('branch_operation.branch_id', $sessionBranchId)
                                ->where('branch_operation.status', 1)
                                ->whereNull('branch_operation.deleted_at');
                        });
                    }
                })
                ->orderBy('operations.id')
                ->distinct()
                ->get();
        }

        $perPage = (int) $request->input('per_page', 10);
        $allowedPerPage = [10, 20, 50, 100];
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        $profiles = Profile::query()
            ->whereNull('profiles.deleted_at')
            ->whereExists(function ($query) use ($branch) {
                $query->select(DB::raw(1))
                    ->from('profile_branch')
                    ->whereColumn('profile_branch.profile_id', 'profiles.id')
                    ->where('profile_branch.branch_id', $branch->id)
                    ->whereNull('profile_branch.deleted_at');
            })
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('profiles.name')
            ->paginate($perPage)
            ->withQueryString();

        $allProfiles = Profile::query()
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name', 'status']);

        $assignedProfileIds = DB::table('profile_branch')
            ->where('branch_id', $branch->id)
            ->whereNull('deleted_at')
            ->pluck('profile_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        return view('branches.profiles.index', [
            'company' => $company,
            'branch' => $branch,
            'profiles' => $profiles,
            'search' => $search,
            'perPage' => $perPage,
            'operaciones' => $operaciones,
            'allProfiles' => $allProfiles,
            'assignedProfileIds' => $assignedProfileIds,
        ]);
    }

    public function assignBranchProfiles(Request $request, Company $company, Branch $branch)
    {
        $branch = $this->resolveBranch($company, $branch);
        $selectedIds = array_map('intval', $request->input('profiles', []));
        $now = now();

        $currentIds = DB::table('profile_branch')
            ->where('branch_id', $branch->id)
            ->whereNull('deleted_at')
            ->pluck('profile_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        // Quitar los que ya no están seleccionados
        $toRemove = array_diff($currentIds, $selectedIds);
        if (!empty($toRemove)) {
            DB::table('profile_branch')
                ->where('branch_id', $branch->id)
                ->whereIn('profile_id', $toRemove)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => $now, 'updated_at' => $now]);
        }

        // Agregar los nuevos seleccionados
        $toAdd = array_diff($selectedIds, $currentIds);
        foreach ($toAdd as $profileId) {
            $existing = DB::table('profile_branch')
                ->where('branch_id', $branch->id)
                ->where('profile_id', $profileId)
                ->first();

            if ($existing) {
                DB::table('profile_branch')
                    ->where('id', $existing->id)
                    ->update(['deleted_at' => null, 'updated_at' => $now]);
            } else {
                DB::table('profile_branch')->insert([
                    'profile_id' => $profileId,
                    'branch_id'  => $branch->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $viewId = $request->input('view_id');

        return redirect()
            ->route('admin.companies.branches.profiles.index', array_merge(
                [$company, $branch],
                array_filter(['view_id' => $viewId])
            ))
            ->with('status', 'Perfiles actualizados correctamente.');
    }

    public function viewsIndex(Request $request, Company $company, Branch $branch)
    {
        $branch = $this->resolveBranch($company, $branch);
        $search = $request->input('search');
        $viewId = $request->input('view_id');
        $branchViewId = $request->input('branch_view_id');
        if ($branchViewId) {
            $request->session()->put('branch_view_id', $branchViewId);
        }
        if ($viewId) {
            $request->session()->put('branch_views_view_id', $viewId);
        }

        $perPage = (int) $request->input('per_page', 10);
        $allowedPerPage = [10, 20, 50, 100];
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        $assignedViewIds = DB::table('view_branch')
            ->where('branch_id', $branch->id)
            ->whereNull('deleted_at')
            ->pluck('view_id')
            ->all();

        $assignedViews = View::query()
            ->whereIn('id', $assignedViewIds)
            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('abbreviation', 'like', "%{$search}%");
                });
            })
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
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
     
        $allViews = View::query()
            ->orderBy('name')
            ->get(['id', 'name', 'abbreviation', 'status']);

        return view('branches.views.index', [
            'company' => $company,
            'branch' => $branch,
            'assignedViews' => $assignedViews,
            'allViews' => $allViews,
            'assignedViewIds' => $assignedViewIds,
            'search' => $search,
            'perPage' => $perPage,
            'operaciones' => $operaciones,
        ]);
    }

    public function updateViews(Request $request, Company $company, Branch $branch)
    {
        $branch = $this->resolveBranch($company, $branch);
        $data = $request->validate([
            'views' => ['nullable', 'array'],
            'views.*' => ['integer', 'exists:views,id'],
        ]);

        $viewIds = array_values(array_unique(array_map('intval', $data['views'] ?? [])));

        DB::transaction(function () use ($branch, $viewIds) {
            DB::table('view_branch')
                ->where('branch_id', $branch->id)
                ->whereNull('deleted_at')
                ->whereNotIn('view_id', $viewIds)
                ->update(['deleted_at' => now(), 'updated_at' => now()]);

            foreach ($viewIds as $viewId) {
                $existing = DB::table('view_branch')
                    ->where('branch_id', $branch->id)
                    ->where('view_id', $viewId)
                    ->first();

                if ($existing) {
                    if ($existing->deleted_at !== null) {
                        DB::table('view_branch')
                            ->where('branch_id', $branch->id)
                            ->where('view_id', $viewId)
                            ->update(['deleted_at' => null, 'updated_at' => now()]);
                    }
                    continue;
                }

                DB::table('view_branch')->insert([
                    'branch_id' => $branch->id,
                    'view_id' => $viewId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        $viewId = $request->input('view_id');
        $companyViewId = $request->input('company_view_id');
        $branchViewId = $request->input('branch_view_id');
        $icon = $request->input('icon');

        return redirect()
            ->route('admin.companies.branches.views.index', array_merge(
                [$company, $branch],
                array_filter([
                    'view_id' => $viewId,
                    'company_view_id' => $companyViewId,
                    'branch_view_id' => $branchViewId,
                    'icon' => $icon,
                ])
            ))
            ->with('status', 'Vistas asignadas correctamente.');
    }

    public function removeViewAssignment(Request $request, Company $company, Branch $branch, View $view)
    {
        $branch = $this->resolveBranch($company, $branch);
        $this->ensureViewAssignedToBranch($view->id, $branch->id);

        $viewBranch = DB::table('view_branch')
            ->where('branch_id', $branch->id)
            ->where('view_id', $view->id)
            ->whereNull('deleted_at')
            ->first();

        DB::table('view_branch')
            ->where('branch_id', $branch->id)
            ->where('view_id', $view->id)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now(), 'updated_at' => now()]);

        $viewId = $request->input('view_id');
        $companyViewId = $request->input('company_view_id');
        $branchViewId = $request->input('branch_view_id');
        $icon = $request->input('icon');

        return redirect()
            ->route('admin.companies.branches.views.index', array_merge(
                [$company, $branch],
                array_filter([
                    'view_id' => $viewId,
                    'company_view_id' => $companyViewId,
                    'branch_view_id' => $branchViewId,
                    'icon' => $icon,
                ])
            ))
            ->with('status', 'Vista desasignada correctamente.')
            ->with('viewBranch', $viewBranch);
    }

    public function viewOperationsIndex(Request $request, Company $company, Branch $branch, View $view)
    {
        $branch = $this->resolveBranch($company, $branch);
        $this->ensureViewAssignedToBranch($view->id, $branch->id);

        $search = $request->input('search');
        $perPage = (int) $request->input('per_page', 10);
        $allowedPerPage = [10, 20, 50, 100];
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        $operations = DB::table('branch_operation')
            ->join('operations', 'operations.id', '=', 'branch_operation.operation_id')
            ->where('branch_operation.branch_id', $branch->id)
            ->whereNull('branch_operation.deleted_at')
            ->whereNull('operations.deleted_at')
            ->where('operations.view_id', $view->id)
            ->when($search, function ($query) use ($search) {
                $query->where('operations.name', 'like', "%{$search}%");
            })
            ->orderBy('operations.name')
            ->select([
                'branch_operation.id',
                'branch_operation.status',
                'operations.name',
                'operations.icon',
                'operations.action',
                'operations.color',
            ])
            ->paginate($perPage)
            ->withQueryString();

        $assignedOperationIds = DB::table('branch_operation')
            ->where('branch_id', $branch->id)
            ->whereNull('deleted_at')
            ->pluck('operation_id')
            ->all();

        $availableOperations = DB::table('operations')
            ->where('view_id', $view->id)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name', 'icon', 'action', 'color']);

        return view('branches.views.operations', [
            'company' => $company,
            'branch' => $branch,
            'view' => $view,
            'operations' => $operations,
            'availableOperations' => $availableOperations,
            'assignedOperationIds' => $assignedOperationIds,
            'search' => $search,
            'perPage' => $perPage,
        ]);
    }

    public function assignViewOperations(Request $request, Company $company, Branch $branch, View $view)
    {
        $branch = $this->resolveBranch($company, $branch);
        $this->ensureViewAssignedToBranch($view->id, $branch->id);

        $data = $request->validate([
            'operations' => ['nullable', 'array'],
            'operations.*' => ['integer', 'exists:operations,id'],
        ]);

        $operationIds = array_values(array_unique(array_map('intval', $data['operations'] ?? [])));

        DB::transaction(function () use ($branch, $view, $operationIds) {
            DB::table('branch_operation')
                ->where('branch_id', $branch->id)
                ->whereIn('operation_id', function ($query) use ($view) {
                    $query->select('id')
                        ->from('operations')
                        ->where('view_id', $view->id)
                        ->whereNull('deleted_at');
                })
                ->whereNull('deleted_at')
                ->whereNotIn('operation_id', $operationIds)
                ->update(['deleted_at' => now(), 'updated_at' => now()]);

            foreach ($operationIds as $operationId) {
                $operation = DB::table('operations')
                    ->where('id', $operationId)
                    ->where('view_id', $view->id)
                    ->whereNull('deleted_at')
                    ->first();

                if (!$operation) {
                    continue;
                }

                $existing = DB::table('branch_operation')
                    ->where('branch_id', $branch->id)
                    ->where('operation_id', $operationId)
                    ->first();

                if ($existing) {
                    if ($existing->deleted_at !== null) {
                        DB::table('branch_operation')
                            ->where('branch_id', $branch->id)
                            ->where('operation_id', $operationId)
                            ->update(['deleted_at' => null, 'updated_at' => now()]);
                    }
                    continue;
                }

                DB::table('branch_operation')->insert([
                    'branch_id' => $branch->id,
                    'operation_id' => $operationId,
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return redirect()
            ->route('admin.companies.branches.views.operations.index', [$company, $branch, $view])
            ->with('status', 'Operaciones asignadas correctamente.');
    }

    public function toggleViewOperation(Request $request, Company $company, Branch $branch, View $view, $branchOperation)
    {
        $branch = $this->resolveBranch($company, $branch);
        $this->ensureViewAssignedToBranch($view->id, $branch->id);

        $operationRow = DB::table('branch_operation')
            ->join('operations', 'operations.id', '=', 'branch_operation.operation_id')
            ->where('branch_operation.id', $branchOperation)
            ->where('branch_operation.branch_id', $branch->id)
            ->whereNull('branch_operation.deleted_at')
            ->whereNull('operations.deleted_at')
            ->where('operations.view_id', $view->id)
            ->select('branch_operation.status')
            ->first();

        if (!$operationRow) {
            return back()->withErrors(['error' => 'No se encontró la operación en esta sucursal.']);
        }

        $newStatus = $operationRow->status ? 0 : 1;

        DB::table('branch_operation')
            ->where('id', $branchOperation)
            ->update([
                'status' => $newStatus,
                'updated_at' => now(),
            ]);

        $params = array_filter([
            'view_id' => $request->input('view_id'),
            'company_view_id' => $request->input('company_view_id'),
            'branch_view_id' => $request->input('branch_view_id'),
            'views_view_id' => $request->input('views_view_id'),
            'icon' => $request->input('icon'),
        ]);

        return redirect()
            ->route('admin.companies.branches.views.operations.index', array_merge([$company, $branch, $view], $params))
            ->with('status', $newStatus ? 'Operación activada.' : 'Operación desactivada.');
    }

    public function profileOperationsIndex(Request $request, Company $company, Branch $branch, Profile $profile)
    {
        $branch = $this->resolveBranch($company, $branch);
        $this->ensureProfileAssignedToBranch($profile->id, $branch->id);

        $search = $request->input('search');
        $perPage = (int) $request->input('per_page', 10);
        $allowedPerPage = [10, 20, 50, 100];
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        $operations = DB::table('operation_profile_branch')
            ->join('operations', 'operations.id', '=', 'operation_profile_branch.operation_id')
            ->join('branch_operation', function ($join) use ($branch) {
                $join->on('branch_operation.operation_id', '=', 'operation_profile_branch.operation_id')
                    ->where('branch_operation.branch_id', $branch->id)
                    ->whereNull('branch_operation.deleted_at');
            })
            ->where('operation_profile_branch.branch_id', $branch->id)
            ->where('operation_profile_branch.profile_id', $profile->id)
            ->whereNull('operation_profile_branch.deleted_at')
            ->whereNull('operations.deleted_at')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('operations.name', 'like', "%{$search}%")
                        ->orWhere('operations.action', 'like', "%{$search}%");
                });
            })
            ->orderBy('operations.name')
            ->select([
                'operations.id',
                'operation_profile_branch.status',
                'operations.name',
                'operations.icon',
                'operations.action',
                'operations.color',
            ])
            ->paginate($perPage)
            ->withQueryString();

        $assignedOperationIds = DB::table('operation_profile_branch')
            ->where('branch_id', $branch->id)
            ->where('profile_id', $profile->id)
            ->whereNull('deleted_at')
            ->pluck('operation_id')
            ->all();

        $availableOperations = DB::table('branch_operation')
            ->join('operations', 'operations.id', '=', 'branch_operation.operation_id')
            ->where('branch_operation.branch_id', $branch->id)
            ->whereNull('branch_operation.deleted_at')
            ->whereNull('operations.deleted_at')
            ->orderBy('operations.name')
            ->select([
                'operations.id',
                'operations.name',
                'operations.icon',
                'operations.action',
                'operations.color',
            ])
            ->get();

        return view('branches.profiles.operations', [
            'company' => $company,
            'branch' => $branch,
            'profile' => $profile,
            'operations' => $operations,
            'availableOperations' => $availableOperations,
            'assignedOperationIds' => $assignedOperationIds,
            'search' => $search,
            'perPage' => $perPage,
        ]);
    }

    public function assignProfileOperations(Request $request, Company $company, Branch $branch, Profile $profile)
    {
        $branch = $this->resolveBranch($company, $branch);
        $this->ensureProfileAssignedToBranch($profile->id, $branch->id);

        $data = $request->validate([
            'operations' => ['nullable', 'array'],
            'operations.*' => ['integer', 'exists:operations,id'],
        ]);

        $operationIds = array_values(array_unique(array_map('intval', $data['operations'] ?? [])));

        DB::transaction(function () use ($branch, $profile, $operationIds) {
            DB::table('operation_profile_branch')
                ->where('branch_id', $branch->id)
                ->where('profile_id', $profile->id)
                ->whereIn('operation_id', function ($query) use ($branch) {
                    $query->select('operation_id')
                        ->from('branch_operation')
                        ->where('branch_id', $branch->id)
                        ->whereNull('deleted_at');
                })
                ->whereNull('deleted_at')
                ->whereNotIn('operation_id', $operationIds)
                ->update(['deleted_at' => now(), 'updated_at' => now()]);

            foreach ($operationIds as $operationId) {
                $allowed = DB::table('branch_operation')
                    ->where('branch_id', $branch->id)
                    ->where('operation_id', $operationId)
                    ->whereNull('deleted_at')
                    ->exists();

                if (!$allowed) {
                    continue;
                }

                $existing = DB::table('operation_profile_branch')
                    ->where('branch_id', $branch->id)
                    ->where('profile_id', $profile->id)
                    ->where('operation_id', $operationId)
                    ->first();

                if ($existing) {
                    if ($existing->deleted_at !== null) {
                        DB::table('operation_profile_branch')
                            ->where('branch_id', $branch->id)
                            ->where('profile_id', $profile->id)
                            ->where('operation_id', $operationId)
                            ->update(['deleted_at' => null, 'updated_at' => now()]);
                    }
                    continue;
                }

                DB::table('operation_profile_branch')->insert([
                    'branch_id' => $branch->id,
                    'profile_id' => $profile->id,
                    'operation_id' => $operationId,
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return redirect()
            ->route('admin.companies.branches.profiles.operations.index', [$company, $branch, $profile])
            ->with('status', 'Operaciones asignadas correctamente.');
    }

    public function profilePermissions(Request $request, Company $company, Branch $branch, Profile $profile)
    {
        $branch = $this->resolveBranch($company, $branch);
        $this->ensureProfileAssignedToBranch($profile->id, $branch->id);

        $search = $request->input('search');
        $perPage = (int) $request->input('per_page', 10);
        $allowedPerPage = [10, 20, 50, 100];
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        $permissions = DB::table('user_permission')
            ->join('menu_option', 'menu_option.id', '=', 'user_permission.menu_option_id')
            ->join('modules', 'modules.id', '=', 'menu_option.module_id')
            ->where('user_permission.profile_id', $profile->id)
            ->where('user_permission.branch_id', $branch->id)
            ->whereNull('user_permission.deleted_at')
            ->when($search, function ($query) use ($search) {
                $query->where('user_permission.name', 'like', "%{$search}%");
            })
            ->orderBy('modules.name')
            ->orderBy('user_permission.name')
            ->select([
                'user_permission.id',
                'user_permission.name',
                'user_permission.status',
                'modules.name as module_name',
            ])
            ->paginate($perPage)
            ->withQueryString();

        $modules = Module::query()
            ->where('status', 1)
            ->orderBy('order_num')
            ->with(['menuOptions' => function ($query) {
                $query->whereNull('menu_option.deleted_at')
                    ->where('menu_option.status', 1)
                    ->orderBy('menu_option.name')
                    ->select([
                        'menu_option.id',
                        'menu_option.name',
                        'menu_option.icon',
                        'menu_option.action',
                        'menu_option.module_id',
                        'menu_option.status',
                    ]);
            }])
            ->get(['id', 'name', 'icon', 'order_num', 'status']);

        $modules = $modules->filter(fn ($module) => $module->menuOptions->isNotEmpty())->values();

        $assignedMenuOptionIds = DB::table('user_permission')
            ->where('profile_id', $profile->id)
            ->where('branch_id', $branch->id)
            ->whereNull('deleted_at')
            ->pluck('menu_option_id')
            ->all();

        return view('branches.profiles.permissions.index', [
            'company' => $company,
            'branch' => $branch,
            'profile' => $profile,
            'permissions' => $permissions,
            'modules' => $modules,
            'assignedMenuOptionIds' => $assignedMenuOptionIds,
            'search' => $search,
            'perPage' => $perPage,
        ]);
    }

    public function assignProfilePermissions(Request $request, Company $company, Branch $branch, Profile $profile)
    {
        $branch = $this->resolveBranch($company, $branch);
        $this->ensureProfileAssignedToBranch($profile->id, $branch->id);

        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:menu_option,id'],
        ]);

        $selectedIds = array_values(array_unique(array_map('intval', $data['permissions'] ?? [])));

        $allowedIds = DB::table('menu_option')
            ->join('modules', 'modules.id', '=', 'menu_option.module_id')
            ->whereNull('menu_option.deleted_at')
            ->whereNull('modules.deleted_at')
            ->where('menu_option.status', 1)
            ->where('modules.status', 1)
            ->pluck('menu_option.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $allowedIds = array_values(array_unique($allowedIds));
        $selectedIds = array_values(array_intersect($selectedIds, $allowedIds));

        $menuOptionNames = DB::table('menu_option')
            ->whereIn('id', $selectedIds)
            ->pluck('name', 'id');

        DB::transaction(function () use ($branch, $profile, $allowedIds, $selectedIds, $menuOptionNames) {
            if (!empty($allowedIds)) {
                $deleteQuery = DB::table('user_permission')
                    ->where('profile_id', $profile->id)
                    ->where('branch_id', $branch->id)
                    ->whereNull('deleted_at')
                    ->whereIn('menu_option_id', $allowedIds);

                if (!empty($selectedIds)) {
                    $deleteQuery->whereNotIn('menu_option_id', $selectedIds);
                }

                $deleteQuery->update([
                    'deleted_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ($selectedIds as $menuOptionId) {
                $existing = DB::table('user_permission')
                    ->where('profile_id', $profile->id)
                    ->where('branch_id', $branch->id)
                    ->where('menu_option_id', $menuOptionId)
                    ->first();

                $name = $menuOptionNames[$menuOptionId] ?? '';

                if ($existing) {
                    if ($existing->deleted_at !== null) {
                        DB::table('user_permission')
                            ->where('id', $existing->id)
                            ->update([
                                'deleted_at' => null,
                                'status' => true,
                                'name' => $name ?: $existing->name,
                                'updated_at' => now(),
                            ]);
                    } elseif ($name && $existing->name !== $name) {
                        DB::table('user_permission')
                            ->where('id', $existing->id)
                            ->update([
                                'name' => $name,
                                'updated_at' => now(),
                            ]);
                    }

                    continue;
                }

                DB::table('user_permission')->insert([
                    'id' => (string) Str::uuid(),
                    'name' => $name,
                    'profile_id' => $profile->id,
                    'menu_option_id' => $menuOptionId,
                    'branch_id' => $branch->id,
                    'status' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return redirect()
            ->route('admin.companies.branches.profiles.permissions.index', [$company, $branch, $profile])
            ->with('status', 'Permisos asignados correctamente.');
    }

    public function toggleProfilePermission(Company $company, Branch $branch, Profile $profile, string $permission)
    {
        $branch = $this->resolveBranch($company, $branch);
        $this->ensureProfileAssignedToBranch($profile->id, $branch->id);

        $record = DB::table('user_permission')
            ->where('id', $permission)
            ->where('profile_id', $profile->id)
            ->where('branch_id', $branch->id)
            ->whereNull('deleted_at')
            ->first();

        if (!$record) {
            abort(404);
        }

        DB::table('user_permission')
            ->where('id', $permission)
            ->update([
                'status' => !$record->status,
                'updated_at' => now(),
            ]);

        return redirect()
            ->route('admin.companies.branches.profiles.permissions.index', [$company, $branch, $profile])
            ->with('status', 'Permiso actualizado correctamente.');
    }

    public function toggleProfileOperation(Request $request, Company $company, Branch $branch, Profile $profile, string $operation)
    {
        $branch = $this->resolveBranch($company, $branch);
        $this->ensureProfileAssignedToBranch($profile->id, $branch->id);

        $record = DB::table('operation_profile_branch')
            ->where('operation_id', $operation)
            ->where('profile_id', $profile->id)
            ->where('branch_id', $branch->id)
            ->whereNull('deleted_at')
            ->first();

        if (!$record) {
            abort(404);
        }

        DB::table('operation_profile_branch')
            ->where('operation_id', $operation)
            ->where('profile_id', $profile->id)
            ->where('branch_id', $branch->id)
            ->update([
                'status' => !$record->status,
                'updated_at' => now(),
            ]);

        $redirectParams = array_filter([
            'view_id' => $request->input('view_id'),
            'company_view_id' => $request->input('company_view_id'),
            'branch_view_id' => $request->input('branch_view_id'),
            'profile_view_id' => $request->input('profile_view_id'),
            'icon' => $request->input('icon'),
        ]);

        return redirect()
            ->route('admin.companies.branches.profiles.operations.index', array_merge([$company, $branch, $profile], $redirectParams))
            ->with('status', 'Operación actualizada correctamente.');
    }

    public function update(Request $request, Company $company, Branch $branch)
    {
        $branch = $this->resolveBranch($company, $branch);
        $data = $this->validateBranch($request);
        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('branches/logos', 'public');
            $data['logo'] = Storage::url($path);
        } else {
            unset($data['logo']);
        }

        DB::transaction(function () use ($branch, $data, $request) {
            $branch->update($data);
            $this->syncElectronicBillingConfig($branch, $request);
        });

        // Actualizar parámetro por sucursal: requerir PIN de mozo al tomar pedidos
        // Buscar el parámetro global por su descripción visible en la UI
        $parameterId = DB::table('parameters')
            ->whereNull('deleted_at')
            ->where('description', 'Requerir PIN a mozo')
            ->value('id');

        if ($parameterId) {
            $value = $request->has('waiter_pin_enabled') ? '1' : '0';
            $now = now();

            $existing = DB::table('branch_parameters')
                ->where('branch_id', $branch->id)
                ->where('parameter_id', $parameterId)
                ->first();

            if ($existing) {
                DB::table('branch_parameters')
                    ->where('branch_id', $branch->id)
                    ->where('parameter_id', $parameterId)
                    ->update([
                        'value' => $value,
                        'deleted_at' => null,
                        'updated_at' => $now,
                    ]);
            } else {
                DB::table('branch_parameters')->insert([
                    'parameter_id' => $parameterId,
                    'branch_id' => $branch->id,
                    'value' => $value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $params = [];
        if ($request->filled('view_id')) {
            $params['view_id'] = $request->input('view_id');
        }
        if ($request->filled('company_view_id')) {
            $params['company_view_id'] = $request->input('company_view_id');
        }
        if ($request->filled('icon')) {
            $params['icon'] = $request->input('icon');
        }
        $redirectParams = !empty($params) ? array_merge([$company], $params) : $company;

        return redirect()
            ->route('admin.companies.branches.index', $redirectParams)
            ->with('status', 'Sucursal actualizada correctamente.');
    }

    public function destroy(Company $company, Branch $branch)
    {
        $branch = $this->resolveBranch($company, $branch);
        $branch->delete();

        $params = [];
        if (request()->filled('view_id')) {
            $params['view_id'] = request()->input('view_id');
        }
        if (request()->filled('company_view_id')) {
            $params['company_view_id'] = request()->input('company_view_id');
        }
        if (request()->filled('icon')) {
            $params['icon'] = request()->input('icon');
        }
        $redirectParams = !empty($params) ? array_merge([$company], $params) : $company;

        return redirect()
            ->route('admin.companies.branches.index', $redirectParams)
            ->with('status', 'Sucursal eliminada correctamente.');
    }

    private function validateBranch(Request $request): array
    {
        $data = $request->validate([
            'ruc' => ['required', 'string', 'max:255'],
            'legal_name' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'address' => ['nullable', 'string', 'max:255'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'allow_zero_stock_sales' => ['nullable', 'boolean'],
            'electronic_billing_enabled' => ['nullable', 'boolean'],
            'electronic_billing_api_url' => ['nullable', 'url', 'max:255'],
            'electronic_billing_persona_id' => ['nullable', 'string', 'max:255'],
            'electronic_billing_persona_token' => ['nullable', 'string', 'max:255'],
            'electronic_billing_series_boleta' => ['nullable', 'string', 'max:8'],
            'electronic_billing_series_factura' => ['nullable', 'string', 'max:8'],
        ]);

        // Checkbox por sucursal: permitir vender/agregar productos sin stock (stock <= 0)
        $data['allow_zero_stock_sales'] = $request->boolean('allow_zero_stock_sales');

        return $data;
    }

    private function syncElectronicBillingConfig(Branch $branch, Request $request): void
    {
        $config = BranchElectronicBillingConfig::query()->firstOrNew([
            'branch_id' => $branch->id,
        ]);

        $config->fill([
            'provider' => 'apisunat',
            'enabled' => $request->boolean('electronic_billing_enabled'),
            'api_url' => $request->input('electronic_billing_api_url') ?: config('apisunat.url'),
            'persona_id' => $request->input('electronic_billing_persona_id'),
            'persona_token' => $request->input('electronic_billing_persona_token'),
            'series_boleta' => strtoupper(trim((string) ($request->input('electronic_billing_series_boleta') ?: config('apisunat.series.boleta', 'B001')))),
            'series_factura' => strtoupper(trim((string) ($request->input('electronic_billing_series_factura') ?: config('apisunat.series.factura', 'F001')))),
        ]);

        $config->save();
    }

    private function resolveBranch(Company $company, Branch $branch): Branch
    {
        if ($branch->company_id !== $company->id) {
            abort(404);
        }

        return $branch;
    }

    private function getLocationData(?Branch $branch = null): array
    {
        
        $departments = Location::query()
            ->where('type', 'department')
            ->orderBy('name')
            ->get(['id', 'name']);

        $provinces = Location::query()
            ->where('type', 'province')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_location_id']);

        $districts = Location::query()
            ->where('type', 'district')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_location_id']);

        $selectedDistrictId = $branch?->location_id;
        $selectedProvinceId = null;
        $selectedDepartmentId = null;

        if ($selectedDistrictId) {
            $district = Location::find($selectedDistrictId);
            
            if ($district) {
                $province = $district->parent;
                $selectedProvinceId = $province?->id;
                $selectedDepartmentId = $province?->parent_location_id;
            }
        }

        return [
            'departments' => $departments,
            'provinces' => $provinces,
            'districts' => $districts,
            'selectedDepartmentId' => $selectedDepartmentId,
            'selectedProvinceId' => $selectedProvinceId,
            'selectedDistrictId' => $selectedDistrictId,
        ];
    }

    private function ensureProfileAssignedToBranch(int $profileId, int $branchId): void
    {
        $assigned = DB::table('profile_branch')
            ->where('profile_id', $profileId)
            ->where('branch_id', $branchId)
            ->whereNull('deleted_at')
            ->exists();

        if (!$assigned) {
            abort(404);
        }
    }

    private function ensureViewAssignedToBranch(int $viewId, int $branchId): void
    {
        $assigned = DB::table('view_branch')
            ->where('view_id', $viewId)
            ->where('branch_id', $branchId)
            ->whereNull('deleted_at')
            ->exists();

        if (!$assigned) {
            abort(404);
        }
    }

    public function replicateBranchConfiguration(int $newBranchId): void
    {
        $templateBranchId = (int) env('BRANCH_TEMPLATE_ID', 4);
        if ($newBranchId === $templateBranchId) {
            return;
        }   

        $now = now();

        $templateViews = DB::table('view_branch')
            ->where('branch_id', $templateBranchId)
            ->whereNull('deleted_at')
            ->get(['view_id']);

        foreach ($templateViews as $row) {
            $exists = DB::table('view_branch')
                ->where('branch_id', $newBranchId)
                ->where('view_id', $row->view_id)
                ->whereNull('deleted_at')
                ->exists();

            if (!$exists) {
                DB::table('view_branch')->insert([
                    'view_id' => $row->view_id,
                    'branch_id' => $newBranchId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $templateProfiles = DB::table('profile_branch')
            ->where('branch_id', $templateBranchId)
            ->whereNull('deleted_at')
            ->get(['profile_id']);

        foreach ($templateProfiles as $row) {
            $exists = DB::table('profile_branch')
                ->where('branch_id', $newBranchId)
                ->where('profile_id', $row->profile_id)
                ->whereNull('deleted_at')
                ->exists();

            if (!$exists) {
                DB::table('profile_branch')->insert([
                    'profile_id' => $row->profile_id,
                    'branch_id' => $newBranchId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $templateBranchOperations = DB::table('branch_operation')
            ->where('branch_id', $templateBranchId)
            ->whereNull('deleted_at')
            ->get(['operation_id', 'status']);

        foreach ($templateBranchOperations as $row) {
            $exists = DB::table('branch_operation')
                ->where('branch_id', $newBranchId)
                ->where('operation_id', $row->operation_id)
                ->whereNull('deleted_at')
                ->exists();

            if (!$exists) {
                DB::table('branch_operation')->insert([
                    'operation_id' => $row->operation_id,
                    'branch_id' => $newBranchId,
                    'status' => $row->status,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $templateOperationProfiles = DB::table('operation_profile_branch')
            ->where('branch_id', $templateBranchId)
            ->whereNull('deleted_at')
            ->get(['operation_id', 'profile_id', 'status']);

        foreach ($templateOperationProfiles as $row) {
            $exists = DB::table('operation_profile_branch')
                ->where('branch_id', $newBranchId)
                ->where('operation_id', $row->operation_id)
                ->where('profile_id', $row->profile_id)
                ->whereNull('deleted_at')
                ->exists();

            if (!$exists) {
                DB::table('operation_profile_branch')->insert([
                    'operation_id' => $row->operation_id,
                    'profile_id' => $row->profile_id,
                    'branch_id' => $newBranchId,
                    'status' => $row->status,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $templateParameters = DB::table('branch_parameters')
            ->where('branch_id', $templateBranchId)
            ->whereNull('deleted_at')
            ->get(['parameter_id', 'value']);

        foreach ($templateParameters as $row) {
            $exists = DB::table('branch_parameters')
                ->where('branch_id', $newBranchId)
                ->where('parameter_id', $row->parameter_id)
                ->whereNull('deleted_at')
                ->exists();

            if (!$exists) {
                DB::table('branch_parameters')->insert([
                    'parameter_id' => $row->parameter_id,
                    'value' => $row->value,
                    'branch_id' => $newBranchId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $templateCategoryBranches = DB::table('category_branch')
            ->where('branch_id', $templateBranchId)
            ->whereNull('deleted_at')
            ->get(['category_id', 'menu_type', 'status']);

        foreach ($templateCategoryBranches as $row) {
            $existing = DB::table('category_branch')
                ->where('branch_id', $newBranchId)
                ->where('category_id', $row->category_id)
                ->where('menu_type', $row->menu_type)
                ->first();

            if ($existing) {
                DB::table('category_branch')
                    ->where('id', $existing->id)
                    ->update([
                        'status' => (string) $row->status,
                        'deleted_at' => null,
                        'updated_at' => $now,
                    ]);
            } else {
                DB::table('category_branch')->insert([
                    'menu_type' => (string) $row->menu_type,
                    'status' => (string) $row->status,
                    'category_id' => $row->category_id,
                    'branch_id' => $newBranchId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $templateProductBranches = DB::table('product_branch')
            ->where('branch_id', $templateBranchId)
            ->whereNull('deleted_at')
            ->get();

        foreach ($templateProductBranches as $row) {
            $exists = DB::table('product_branch')
                ->where('branch_id', $newBranchId)
                ->where('product_id', $row->product_id)
                ->whereNull('deleted_at')
                ->exists();

            if (!$exists) {
                DB::table('product_branch')->insert([
                    'product_id' => $row->product_id,
                    'branch_id' => $newBranchId,
                    'status' => $row->status ?? 'E',
                    'stock' => 0,
                    'price' => $row->price ?? 0,
                    'purchase_price' => $row->purchase_price ?? 0,
                    'expiration_date' => $row->expiration_date,
                    'stock_minimum' => $row->stock_minimum ?? 0,
                    'stock_maximum' => $row->stock_maximum ?? 0,
                    'minimum_sell' => $row->minimum_sell ?? 0,
                    'minimum_purchase' => $row->minimum_purchase ?? 0,
                    'favorite' => $row->favorite ?? 'N',
                    'tax_rate_id' => $row->tax_rate_id,
                    'unit_sale' => $row->unit_sale ?? 'N',
                    'duration_minutes' => $row->duration_minutes ?? 0,
                    'supplier_id' => $row->supplier_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $templatePermissions = DB::table('user_permission as up')
            ->join('menu_option as mo', 'mo.id', '=', 'up.menu_option_id')
            ->where('up.branch_id', $templateBranchId)
            ->whereNull('up.deleted_at')
            ->where('mo.module_id', '!=', 1) 
            ->get(['up.name', 'up.profile_id', 'up.menu_option_id', 'up.status']);

        foreach ($templatePermissions as $row) {
            $exists = DB::table('user_permission')
                ->where('branch_id', $newBranchId)
                ->where('profile_id', $row->profile_id)
                ->where('menu_option_id', $row->menu_option_id)
                ->whereNull('deleted_at')
                ->exists();

            if (!$exists) {
                DB::table('user_permission')->insert([
                    'id' => (string) Str::uuid(),
                    'name' => $row->name,
                    'profile_id' => $row->profile_id,
                    'menu_option_id' => $row->menu_option_id,
                    'branch_id' => $newBranchId,
                    'status' => (bool) $row->status,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * Crea tipos de producto por defecto y usuario/persona por defecto para una sucursal nueva.
     * Usado al crear sucursal desde BranchController o al crear empresa+sucursal desde CompanyController.
     */
    public function setupNewBranch(Branch $branch): void
    {
        $this->createDefaultProductTypes($branch);
        $this->createDefaultBranchPersonAndUser($branch);
    }

    private function createDefaultProductTypes(Branch $branch): void
    {
        if ($branch->productTypes()->exists()) {
            return;
        }
        $branch->productTypes()->createMany([
            [
                'name' => 'Producto final',
                'description' => 'Productos listos para la venta.',
                'behavior' => ProductType::BEHAVIOR_SELLABLE,
                'icon' => null,
            ],
            [
                'name' => 'Ingrediente',
                'description' => 'Repuestos, insumos o materiales de apoyo.',
                'behavior' => ProductType::BEHAVIOR_SUPPLY,
                'icon' => null,
            ],
        ]);
    }

    private function createDefaultBranchPersonAndUser(Branch $branch): void
    {
        $generatedEmail =  $branch->ruc . '@xinergia.local';
     

        $person = Person::create([
            'first_name' => $branch->legal_name,
            'last_name' => '',
            'person_type' => 'RUC',
            'phone' => '-',
            'email' => $generatedEmail,
            'document_number' => (string) $branch->ruc,
            'address' => $branch->address ?: '-',
            'location_id' => $branch->location_id,
            'branch_id' => $branch->id,
        ]);

     
        User::create([
            'name' => $branch->ruc,
            'email' => $generatedEmail,
            'password' => (string) $branch->ruc,
            'person_id' => $person->id,
            'profile_id' => 2,
        ]);

        $defaultRoleId = (int) env('BRANCH_DEFAULT_ROLE_ID', 1);
        if (!Role::where('id', $defaultRoleId)->exists()) {
            $defaultRoleId = (int) Role::query()->orderBy('id')->value('id');
        }

        if ($defaultRoleId > 0) {
            DB::table('role_person')->insert([
                'role_id' => $defaultRoleId,
                'person_id' => $person->id,
                'branch_id' => $branch->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
