<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Operation;
use App\Models\Person;
use App\Models\User;
use App\Support\InsensitiveSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CompanyController extends Controller
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

        if (!$viewId && $branchId) {
            $defaultViewId = (int) DB::table('operations')
                ->join('view_branch', function ($join) use ($branchId) {
                    $join->on('view_branch.view_id', '=', 'operations.view_id')
                        ->where('view_branch.branch_id', $branchId)
                        ->whereNull('view_branch.deleted_at');
                })
                ->whereIn('operations.action', ['admin.companies.index', 'companies.index'])
                ->where('operations.status', 1)
                ->whereNull('operations.deleted_at')
                ->orderBy('operations.view_id')
                ->value('operations.view_id');
            if ($defaultViewId <= 0) {
                $defaultViewId = (int) DB::table('operations')
                    ->join('view_branch', function ($join) use ($branchId) {
                        $join->on('view_branch.view_id', '=', 'operations.view_id')
                            ->where('view_branch.branch_id', $branchId)
                            ->whereNull('view_branch.deleted_at');
                    })
                    ->where(function ($q) {
                        $q->where('operations.action', 'like', 'admin.companies.%')
                            ->orWhere('operations.action', 'like', 'companies.%');
                    })
                    ->where('operations.status', 1)
                    ->whereNull('operations.deleted_at')
                    ->orderBy('operations.view_id')
                    ->value('operations.view_id');
            }
            if ($defaultViewId <= 0) {
                $defaultViewId = (int) DB::table('view_branch')
                    ->where('branch_id', $branchId)
                    ->whereNull('deleted_at')
                    ->orderBy('view_id')
                    ->value('view_id');
            }
            if ($defaultViewId > 0) {
                return redirect()->route('admin.companies.index', array_merge(
                    array_filter($request->only(['search', 'per_page'])),
                    ['view_id' => $defaultViewId]
                ));
            }
        }

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
        $companies = Company::query()
            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    InsensitiveSearch::whereInsensitiveLike($inner, 'legal_name', $search);
                    InsensitiveSearch::whereInsensitiveLike($inner, 'tax_id', $search, 'or');
                    InsensitiveSearch::whereInsensitiveLike($inner, 'address', $search, 'or');
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('companies.index', [
            'companies' => $companies,
            'search' => $search,
            'perPage' => $perPage,
            'operaciones' => $operaciones,
            'title' => 'Empresas',
        ]);
    }

    public function create(Request $request)
    {
        $viewId = $request->input('view_id');
        if (!$viewId && $request->session()->get('branch_id')) {
            $branchId = (int) $request->session()->get('branch_id');
            $viewId = (int) DB::table('operations')
                ->join('view_branch', function ($join) use ($branchId) {
                    $join->on('view_branch.view_id', '=', 'operations.view_id')
                        ->where('view_branch.branch_id', $branchId)
                        ->whereNull('view_branch.deleted_at');
                })
                ->whereIn('operations.action', ['admin.companies.index', 'companies.index'])
                ->where('operations.status', 1)
                ->whereNull('operations.deleted_at')
                ->orderBy('operations.view_id')
                ->value('operations.view_id');
            if ($viewId <= 0) {
                $viewId = (int) DB::table('operations')
                    ->join('view_branch', function ($join) use ($branchId) {
                        $join->on('view_branch.view_id', '=', 'operations.view_id')
                            ->where('view_branch.branch_id', $branchId)
                            ->whereNull('view_branch.deleted_at');
                    })
                    ->where(function ($q) {
                        $q->where('operations.action', 'like', 'admin.companies.%')
                            ->orWhere('operations.action', 'like', 'companies.%');
                    })
                    ->where('operations.status', 1)
                    ->whereNull('operations.deleted_at')
                    ->orderBy('operations.view_id')
                    ->value('operations.view_id');
            }
            if ($viewId <= 0) {
                $viewId = (int) DB::table('view_branch')
                    ->where('branch_id', $branchId)
                    ->whereNull('deleted_at')
                    ->orderBy('view_id')
                    ->value('view_id');
            }
            $viewId = $viewId > 0 ? $viewId : null;
        }
        return view('companies.create', compact('viewId'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tax_id' => ['required', 'string', 'max:255'],
            'legal_name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
        ]);

        $logoPath = null;
        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            if ($file->isValid()) {
                $logoPath = $file->store('companies', 'public');
            }
        }

        $data['logo'] = $logoPath ? Storage::url($logoPath) : null;

        $company = Company::create($data);

        $branchLogoPath = $logoPath ? str_replace('companies/', 'branches/', $logoPath) : null;
        if ($branchLogoPath) {
            Storage::disk('public')->copy($logoPath, $branchLogoPath);
        }

        $branch = Branch::create([
            'ruc' => $data['tax_id'],
            'company_id' => $company->id,
            'legal_name' => $data['legal_name'],
            'address' => $data['address'],
            'logo' => $branchLogoPath ? Storage::url($branchLogoPath) : null,
            'location_id' => 1477,
        ]);

        $this->replicateCategoryBranches($branch->id);

        $branchController = app(BranchController::class);
        $branchController->replicateBranchConfiguration($branch->id);
        $branchController->setupNewBranch($branch);

        // El admin permanece en la sucursal anterior; usar su branch_id para el view_id del redirect
        $branchIdForRedirect = (int) $request->session()->get('branch_id') ?: $branch->id;

        $defaultViewId = (int) DB::table('operations')
            ->join('view_branch', function ($join) use ($branchIdForRedirect) {
                $join->on('view_branch.view_id', '=', 'operations.view_id')
                    ->where('view_branch.branch_id', $branchIdForRedirect)
                    ->whereNull('view_branch.deleted_at');
            })
            ->whereIn('operations.action', ['admin.companies.index', 'companies.index'])
            ->where('operations.status', 1)
            ->whereNull('operations.deleted_at')
            ->orderBy('operations.view_id')
            ->value('operations.view_id');
        if ($defaultViewId <= 0) {
            $defaultViewId = (int) DB::table('operations')
                ->join('view_branch', function ($join) use ($branchIdForRedirect) {
                    $join->on('view_branch.view_id', '=', 'operations.view_id')
                        ->where('view_branch.branch_id', $branchIdForRedirect)
                        ->whereNull('view_branch.deleted_at');
                })
                ->where(function ($q) {
                    $q->where('operations.action', 'like', 'admin.companies.%')
                        ->orWhere('operations.action', 'like', 'companies.%');
                })
                ->where('operations.status', 1)
                ->whereNull('operations.deleted_at')
                ->orderBy('operations.view_id')
                ->value('operations.view_id');
        }
        if ($defaultViewId <= 0) {
            $defaultViewId = (int) DB::table('view_branch')
                ->where('branch_id', $branchIdForRedirect)
                ->whereNull('deleted_at')
                ->orderBy('view_id')
                ->value('view_id');
        }
        $redirectParams = $defaultViewId > 0 ? ['view_id' => $defaultViewId] : [];

        return redirect()->route('admin.companies.index', $redirectParams)
            ->with('status', 'Empresa y sucursal creadas correctamente.');
    }

    public function edit(Company $company)
    {
        return view('companies.edit', compact('company'));
    }

    public function update(Request $request, Company $company)
    {
        $data = $request->validate([
            'tax_id' => ['required', 'string', 'max:255'],
            'legal_name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
        ]);

        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            if ($file->isValid()) {
                if ($company->logo && Storage::disk('public')->exists($company->logo)) {
                    Storage::disk('public')->delete($company->logo);
                }
                $logoPath = $file->store('companies', 'public');
                $branchLogoPath = str_replace('companies/', 'branches/', $logoPath);
                Storage::disk('public')->copy($logoPath, $branchLogoPath);
                $data['logo'] = Storage::url($logoPath);

                // Update branch logo
                $branch = Branch::where('company_id', $company->id)->first();
                if ($branch) {
                    if ($branch->logo) {
                        $oldPath = str_replace('/storage/', '', $branch->logo);
                        Storage::disk('public')->delete($oldPath);
                    }
                    $branch->update(['logo' => Storage::url($branchLogoPath)]);
                }
            }
        }

        $company->update($data);

        $viewId = $request->input('view_id');

        return redirect()->route('admin.companies.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Empresa actualizada correctamente.');
    }

    public function destroy(Company $company)
    {
        DB::transaction(function () use ($company) {
            // Eliminar logo de la empresa si existe
            if ($company->logo) {
                $path = str_replace('/storage/', '', $company->logo);
                Storage::disk('public')->delete($path);
            }

            // Obtener todas las sucursales de la empresa
            $branches = Branch::where('company_id', $company->id)->get();
            $branchIds = $branches->pluck('id');

            // Eliminar logos de sucursales si existen
            foreach ($branches as $branch) {
                if ($branch->logo) {
                    $path = str_replace('/storage/', '', $branch->logo);
                    Storage::disk('public')->delete($path);
                }
            }

            // Inhabilitar usuarios asociados a personas de estas sucursales (soft delete)
            $peopleIds = Person::whereIn('branch_id', $branchIds)->pluck('id');
            if ($peopleIds->isNotEmpty()) {
                User::whereIn('person_id', $peopleIds)->get()->each->delete();
                Person::whereIn('id', $peopleIds)->get()->each->delete();
            }

            // Inhabilitar sucursales (soft delete)
            if ($branchIds->isNotEmpty()) {
                Branch::whereIn('id', $branchIds)->get()->each->delete();
            }

            // Finalmente, inhabilitar la empresa (soft delete)
            $company->delete();
        });

        $redirectParams = request()->filled('view_id') ? ['view_id' => request()->input('view_id')] : [];

        return redirect()->route('admin.companies.index', $redirectParams)
            ->with('status', 'Empresa eliminada correctamente. Usuarios y sucursales asociados han sido inhabilitados.');
    }

    private function replicateCategoryBranches(int $newBranchId): void
    {
        $templateBranchId = (int) env('BRANCH_TEMPLATE_ID', 4);
        if ($newBranchId === $templateBranchId) {
            return;
        }

        $now = now();

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
    }
}
