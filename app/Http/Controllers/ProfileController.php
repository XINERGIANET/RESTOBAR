<?php

namespace App\Http\Controllers;

use App\Helpers\MenuHelper;
use App\Models\Branch;
use App\Models\Operation;
use App\Models\Profile;
use App\Models\View as ViewModel;
use App\Support\InsensitiveSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
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

        $profiles = Profile::query()
            ->when($search, function ($query) use ($search) {
                InsensitiveSearch::whereInsensitiveLike($query, 'name', $search);
            })
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('profiles.index', [
            'profiles' => $profiles,
            'search' => $search,
            'perPage' => $perPage,
            'operaciones' => $operaciones,
            'viewOptions' => $this->viewOptionsForCombobox(),
            'title' => 'Perfiles',
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateProfile($request, null);

        DB::transaction(function () use ($data) {
            $profile = Profile::create($data);
            $branchIds = Branch::query()->pluck('id');

            if ($branchIds->isNotEmpty()) {
                $now = now();
                $rows = $branchIds->map(fn ($branchId) => [
                    'profile_id' => $profile->id,
                    'branch_id' => $branchId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('profile_branch')->insert($rows->all());
            }
        });

        $viewId = $request->input('view_id');

        return redirect()
            ->route('admin.profiles.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Perfil creado correctamente.');
    }

    public function edit(Profile $profile)
    {
        return view('profiles.edit', [
            'profile' => $profile,
            'viewOptions' => $this->viewOptionsForCombobox(),
            'title' => 'Perfiles',
        ]);
    }

    public function update(Request $request, Profile $profile)
    {
        $data = $this->validateProfile($request, $profile);
        $profile->update($data);

        $viewId = $request->input('view_id');

        return redirect()
            ->route('admin.profiles.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Perfil actualizado correctamente.');
    }

    public function destroy(Profile $profile)
    {
        $profile->delete();

        $viewId = request('view_id');

        return redirect()
            ->route('admin.profiles.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Perfil eliminado correctamente.');
    }

    private function validateProfile(Request $request, ?Profile $existingProfile): array
    {
        if ($request->has('default_view_id') && $request->input('default_view_id') === '') {
            $request->merge(['default_view_id' => null]);
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'boolean'],
            'default_view_id' => [
                'nullable',
                'integer',
                'exists:views,id',
                function (string $attribute, mixed $value, \Closure $fail) use ($existingProfile) {
                    if ($value === null || $value === '' || $existingProfile === null) {
                        return;
                    }
                    $allowed = MenuHelper::allowedViewIdsForProfileAnyBranch((int) $existingProfile->id);
                    if ($allowed === []) {
                        return;
                    }
                    if (! in_array((int) $value, $allowed, true)) {
                        $fail('La vista por defecto debe coincidir con una opción de menú asignada a este perfil en alguna sucursal.');
                    }
                },
            ],
        ]);
    }

    /**
     * Vistas activas para selector "vista por defecto" al iniciar sesión.
     */
    private function activeViewsList()
    {
        return ViewModel::query()
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name', 'abbreviation']);
    }

    /**
     * Opciones para <x-form.select.combobox> (requiere id + description).
     */
    private function viewOptionsForCombobox(): array
    {
        return $this->activeViewsList()
            ->map(function ($v) {
                $desc = $v->name;
                if (! empty($v->abbreviation)) {
                    $desc .= ' — '.$v->abbreviation;
                }

                return ['id' => (int) $v->id, 'description' => $desc];
            })
            ->values()
            ->all();
    }

    private function assignProfileToBranch(Profile $profile, Branch $branch)
    {
        $now = now();

        $rows = Branch::query()->pluck('id')->map(fn ($branchId) => [
            'profile_id' => $profile->id,
            'branch_id' => $branchId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('profile_branch')->insert($rows->all());
    }
}
