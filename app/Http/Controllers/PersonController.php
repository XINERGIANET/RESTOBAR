<?php

namespace App\Http\Controllers;

use App\Helpers\MenuHelper;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Location;
use App\Models\Operation;
use App\Models\Person;
use App\Models\Profile;
use App\Models\Role;
use App\Models\User;
use App\Models\View as ViewModel;
use App\Support\InsensitiveSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

class PersonController extends Controller
{
    private function normalizePersonText(?string $value): string
    {
        $value = preg_replace('/\s+/', ' ', trim((string) $value));
        return mb_strtolower($value, 'UTF-8');
    }

    private function findExistingBranchPerson(Branch $branch, array $data): ?Person
    {
        $documentNumber = trim((string) ($data['document_number'] ?? ''));
        $firstName = $this->normalizePersonText($data['first_name'] ?? '');
        $lastName = $this->normalizePersonText($data['last_name'] ?? '');

        $query = Person::query()
            ->where('branch_id', $branch->id)
            ->whereNull('deleted_at');

        if ($documentNumber !== '') {
            $query->where('document_number', $documentNumber);
        }

        return $query->get()->first(function (Person $person) use ($firstName, $lastName) {
            return $this->normalizePersonText($person->first_name) === $firstName
                && $this->normalizePersonText($person->last_name) === $lastName;
        });
    }

    /**
     * Busca una persona por DNI consultando la API externa configurada en services.php.
     * Ruta: GET /api/dni/{dni}
     */
    public function searchByDni(string $dni)
    {
        $dni = preg_replace('/\D/', '', $dni);

        if (strlen($dni) !== 8) {
            return response()->json(['error' => 'El DNI debe tener 8 dígitos.'], 422);
        }

        $apiUrl   = config('services.dni_api.url');
        $apiToken = config('services.dni_api.token');

        if (!$apiUrl || !$apiToken) {
            return response()->json(['error' => 'La API de DNI no está configurada. Verifica DNI_API_URL y DNI_API_TOKEN en .env'], 503);
        }

        try {
            // Perudevs suele usar ?document=XXXXXXXX&key=TOKEN
            $response = Http::get($apiUrl, [
                'document' => $dni,
                'key'      => $apiToken,
            ]);

            if ($response->failed()) {
                $status = $response->status();
                $errorData = $response->json();
                return response()->json([
                    'error' => $errorData['error'] ?? $errorData['message'] ?? 'Error en el servicio externo.',
                    'details' => $errorData
                ], $status == 404 ? 404 : 502);
            }

            $data = $response->json();

            // Si llegamos aquí pero la API devuelve un error estructurado
            if (isset($data['error']) || (isset($data['queries']) && $data['queries'] <= 0)) {
                return response()->json(['error' => $data['error'] ?? 'No se encontró información para el DNI proporcionado.'], 404);
            }

            // Perudevs devuelve un objeto 'resultado'
            $res = $data['resultado'] ?? $data;

            return response()->json([
                'id'               => $res['id'] ?? $dni,
                'nombres'          => $res['nombres'] ?? '',
                'apellido_paterno' => $res['apellido_paterno'] ?? '',
                'apellido_materno' => $res['apellido_materno'] ?? '',
                'nombre_completo'  => $res['nombre_completo'] ?? trim(($res['nombres'] ?? '').' '.($res['apellido_paterno'] ?? '').' '.($res['apellido_materno'] ?? '')),
                'genero'           => $res['genero'] ?? '',
                'fecha_nacimiento' => $res['fecha_nacimiento'] ?? '',
                'codigo_verificacion' => $res['codigo_verificacion'] ?? '',
                'documento'        => $res['documento'] ?? $dni,
                'raw'              => $data
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error inesperado al consultar el DNI: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Busca una empresa por RUC consultando la API externa configurada en services.php.
     * Ruta: GET /api/ruc/{ruc}
     */
    public function searchByRuc(string $ruc)
    {
        $ruc = preg_replace('/\D/', '', $ruc);

        if (strlen($ruc) !== 11) {
            return response()->json(['error' => 'El RUC debe tener 11 dígitos.'], 422);
        }

        $apiUrl   = config('services.ruc_api.url');
        $apiToken = config('services.ruc_api.token');

        if (!$apiUrl || !$apiToken) {
            return response()->json(['error' => 'La API de RUC no está configurada. Verifica RUC_API_URL y DNI_API_TOKEN en .env'], 503);
        }

        try {
            // Perudevs suele usar ?document=XXXXXXXXXXX&key=TOKEN
            $response = Http::get($apiUrl, [
                'document' => $ruc,
                'key'      => $apiToken,
            ]);

            if ($response->failed()) {
                $status = $response->status();
                $errorData = $response->json();
                return response()->json([
                    'error' => $errorData['error'] ?? $errorData['message'] ?? 'Error en el servicio externo.',
                    'details' => $errorData
                ], $status == 404 ? 404 : 502);
            }

            $data = $response->json();

            // Si llegamos aquí pero la API devuelve un error estructurado
            if (isset($data['error']) || (isset($data['queries']) && $data['queries'] <= 0)) {
                return response()->json(['error' => $data['error'] ?? 'No se encontró información para el RUC proporcionado.'], 404);
            }

            // Perudevs devuelve un objeto 'resultado'
            $res = $data['resultado'] ?? $data;

            return response()->json([
                'id'             => $res['id'] ?? $ruc,
                'razon_social' => $res['razon_social'] ?? '',
                'direccion'    => $res['direccion'] ?? '',
                'estado'       => $res['estado'] ?? '',
                'condicion'    => $res['condicion'] ?? '',
                'departamento' => $res['departamento'] ?? null,
                'provincia'    => $res['provincia'] ?? null,
                'distrito'     => $res['distrito'] ?? null,
                'fecha_inscripcion' => $res['fecha_inscripcion'] ?? null,
                'tipo'         => $res['tipo'] ?? '',
                'nombre_comercial' => $res['nombre_comercial'] ?? '',
                'documento'    => $res['ruc'] ?? $res['id'] ?? $ruc,
                'raw'          => $data
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error inesperado al consultar el RUC: ' . $e->getMessage()], 500);
        }
    }


    public function indexBranch(Request $request)
    {
        $branchId = $request->session()->get('branch_id');
        if (!$branchId) {
            $branchId = $request->user()?->person?->branch_id;
        }

        if (!$branchId) {
            return redirect()->route('dashboard')->with('error', 'Debe seleccionar una sucursal.');
        }

        $branch = Branch::with('company')->findOrFail($branchId);
        $company = $branch->company;

        $request->merge(['from_config' => true]);

        return $this->index($request, $company, $branch);
    }

    public function index(Request $request, Company $company, Branch $branch)
    {
        $branch = $this->resolveBranch($company, $branch);
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
        $roles = Role::query()->orderBy('name')->get(['id', 'name']);
        $profiles = Profile::query()
            ->where('id', '!=', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'default_view_id']);

        $perPage = (int) $request->input('per_page', 10);
        $allowedPerPage = [10, 20, 50, 100];
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        $roleId = $request->input('role_id');
        $profileIdParam = $request->input('profile_id');

        $people = $branch->people()
            ->with(['location', 'user.profile', 'roles'])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    InsensitiveSearch::whereInsensitiveLike($inner, 'first_name', $search);
                    InsensitiveSearch::whereInsensitiveLike($inner, 'last_name', $search, 'or');
                    InsensitiveSearch::whereInsensitiveLike($inner, 'document_number', $search, 'or');
                    InsensitiveSearch::whereInsensitiveLike($inner, 'email', $search, 'or');
                });
            })
            ->when($roleId, function($query) use ($roleId) {
                $query->whereHas('roles', function($q) use ($roleId) {
                    $q->where('roles.id', $roleId);
                });
            })
            ->when($profileIdParam, function($query) use ($profileIdParam) {
                $query->whereHas('user', function($q) use ($profileIdParam) {
                    $q->where('profile_id', $profileIdParam);
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('branches.people.index', [
            'company' => $company,
            'branch' => $branch,
            'people' => $people,
            'search' => $search,
            'roleId' => $roleId,
            'profileIdParam' => $profileIdParam,
            'perPage' => $perPage,
            'roles' => $roles,
            'profiles' => $profiles,
            'selectedRoleIds' => old('roles', []),
            'selectedProfileId' => old('profile_id'),
            'userName' => old('user_name'),
            'operaciones' => $operaciones,
            'viewOptions' => $this->viewOptionsForCombobox(),
        ] + $this->getLocationData(null, $branch->location_id));
    }

    public function store(Request $request, Company $company, Branch $branch)
    {
        $branch = $this->resolveBranch($company, $branch);
        $data = $this->validatePerson($request);
        $data['branch_id'] = $branch->id;
        $roleIds = $this->validateRoles($request);

        // Si viene desde POS (from_pos) y no se seleccionó ningún rol,
        // asignar automáticamente el rol "Cliente" para que aparezca en los combobox.
        if ($request->boolean('from_pos') && empty($roleIds)) {
            $clienteRoleId = Role::query()
                ->whereNull('deleted_at')
                ->whereRaw('LOWER(TRIM(name)) = ?', ['cliente'])
                ->value('id');
            if ($clienteRoleId) {
                $roleIds = [$clienteRoleId];
            }
        }
        $hasUserRole = in_array(1, $roleIds, true);
        $userData = $this->validateUserData($request, $hasUserRole, null);

        $person = null;
        $alreadyExisted = false;
        DB::transaction(function () use ($branch, $data, $roleIds, $hasUserRole, $userData, &$person, &$alreadyExisted) {
            $person = $this->findExistingBranchPerson($branch, $data);
            if (! $person) {
                $person = $branch->people()->create($data);
            } else {
                $alreadyExisted = true;
            }
            $this->syncRoles($person, $roleIds, $branch->id);

            if ($hasUserRole) {
                $user = $person->user;
                if (! $user) {
                    User::create([
                        'name' => $userData['user_name'],
                        'email' => $person->email,
                        'password' => Hash::make($userData['password']),
                        'person_id' => $person->id,
                        'profile_id' => $userData['profile_id'],
                    ]);
                    $this->syncProfileDefaultView((int) $userData['profile_id'], $userData['default_view_id'] ?? null);
                }
            }
        });
        if (($request->expectsJson() || $request->ajax()) && $request->boolean('from_pos')) {
            $fullName = trim(($person?->first_name ?? '').' '.($person?->last_name ?? ''));
            if ($fullName === '') {
                $fullName = trim((string) ($person?->first_name ?? ''));
            }
            $documentNumber = trim((string) ($person?->document_number ?? ''));
            $description = trim($documentNumber !== '' ? ($documentNumber.' - '.$fullName) : $fullName);
            return response()->json([
                'success' => true,
                'id' => $person?->id,
                'person_id' => $person?->id,
                'name' => $fullName !== '' ? $fullName : ($person?->document_number ?? 'Cliente'),
                'description' => $description !== '' ? $description : ($documentNumber ?: 'Cliente'),
                'document_number' => $person?->document_number,
                'message' => $alreadyExisted ? 'Cliente ya existente, se reutilizó el registro.' : 'Cliente creado correctamente.',
            ]);
        }
        // Si viene redirect_to (por ejemplo, desde POS), volver a esa URL
        if ($request->filled('redirect_to')) {
            return redirect($request->input('redirect_to'))
                ->with('status', $alreadyExisted ? 'Cliente ya existente, se reutilizó el registro.' : 'Cliente creado correctamente.');
        }

        $viewId = $request->input('view_id');
        $fromConfig = $request->input('from_config');

        if ($fromConfig) {
            return redirect()
                ->route('configuracion.personal.index', array_filter(['view_id' => $viewId, 'from_config' => 1]))
                ->with('status', $alreadyExisted ? 'Cliente ya existente, se reutilizó el registro.' : 'Personal creado correctamente.');
        }

        return redirect()
            ->route('admin.companies.branches.people.index', $viewId ? [$company, $branch, 'view_id' => $viewId] : [$company, $branch])
            ->with('status', $alreadyExisted ? 'Cliente ya existente, se reutilizó el registro.' : 'Personal creado correctamente.');
    }

    public function edit(Company $company, Branch $branch, Person $person)
    {
        $branch = $this->resolveBranch($company, $branch);
        $person = $this->resolvePerson($branch, $person);
        $roles = Role::query()->orderBy('name')->get(['id', 'name']);
        $profiles = Profile::query()->orderBy('name')->get(['id', 'name', 'default_view_id']);
        $selectedRoleIds = old('roles', $person->roles()->pluck('roles.id')->all());
        $person->loadMissing(['user.profile']);

        return view('branches.people.edit', [
            'company' => $company,
            'branch' => $branch,
            'person' => $person,
            'roles' => $roles,
            'profiles' => $profiles,
            'selectedRoleIds' => $selectedRoleIds,
            'selectedProfileId' => old('profile_id', $person->user?->profile_id),
            'userName' => old('user_name', $person->user?->name),
            'viewOptions' => $this->viewOptionsForCombobox(),
        ] + $this->getLocationData($person));
    }

    public function update(Request $request, Company $company, Branch $branch, Person $person)
    {
        $branch = $this->resolveBranch($company, $branch);
        $person = $this->resolvePerson($branch, $person);
        $data = $this->validatePerson($request);
        if (array_key_exists('pin', $data) && (string) $data['pin'] === '') {
            unset($data['pin']);
        }
        $roleIds = $this->validateRoles($request);
        $hasUserRole = in_array(1, $roleIds, true);
        $userData = $this->validateUserData($request, $hasUserRole, $person);

        DB::transaction(function () use ($person, $branch, $data, $roleIds, $hasUserRole, $userData) {
            $person->update($data);
            $this->syncRoles($person, $roleIds, $branch->id);

            if ($hasUserRole) {
                $user = $person->user;
                if ($user) {
                    $user->update([
                        'name' => $userData['user_name'],
                        'email' => $person->email,
                        'profile_id' => $userData['profile_id'],
                    ]);
                    if (!empty($userData['password'])) {
                        $user->update([
                            'password' => Hash::make($userData['password']),
                        ]);
                    }
                } else {
                    User::create([
                        'name' => $userData['user_name'],
                        'email' => $person->email,
                        'password' => Hash::make($userData['password']),
                        'person_id' => $person->id,
                        'profile_id' => $userData['profile_id'],
                    ]);
                }
                $this->syncProfileDefaultView((int) $userData['profile_id'], $userData['default_view_id'] ?? null);
            }
        });

        $viewId = $request->input('view_id');
        $fromConfig = $request->input('from_config');

        if ($fromConfig) {
            return redirect()
                ->route('configuracion.personal.index', array_filter(['view_id' => $viewId, 'from_config' => 1]))
                ->with('status', 'Personal actualizado correctamente.');
        }

        return redirect()
            ->route('admin.companies.branches.people.index', $viewId ? [$company, $branch, 'view_id' => $viewId] : [$company, $branch])
            ->with('status', 'Personal actualizado correctamente.');
    }

    public function destroy(Company $company, Branch $branch, Person $person)
    {
        $branch = $this->resolveBranch($company, $branch);
        $person = $this->resolvePerson($branch, $person);
        $person->delete();

        $viewId = request()->input('view_id');
        $fromConfig = request()->input('from_config');

        if ($fromConfig) {
            return redirect()
                ->route('configuracion.personal.index', array_filter(['view_id' => $viewId, 'from_config' => 1]))
                ->with('status', 'Personal eliminado correctamente.');
        }

        return redirect()
            ->route('admin.companies.branches.people.index', $viewId ? [$company, $branch, 'view_id' => $viewId] : [$company, $branch])
            ->with('status', 'Personal eliminado correctamente.');
    }

    public function updatePassword(Request $request, Company $company, Branch $branch, Person $person)
    {
        $branch = $this->resolveBranch($company, $branch);
        $person = $this->resolvePerson($branch, $person);

        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $person->user;
        if (!$user) {
            $viewId = $request->input('view_id');

            return redirect()
                ->route('admin.companies.branches.people.index', $viewId ? [$company, $branch, 'view_id' => $viewId] : [$company, $branch])
                ->with('status', 'La persona no tiene usuario asociado.');
        }

        $user->update([
            'password' => Hash::make($data['password']),
        ]);

        $viewId = $request->input('view_id');
        $fromConfig = $request->input('from_config');

        if ($fromConfig) {
            return redirect()
                ->route('configuracion.personal.index', array_filter(['view_id' => $viewId, 'from_config' => 1]))
                ->with('status', 'Contraseña actualizada correctamente.');
        }

        return redirect()
            ->route('admin.companies.branches.people.index', $viewId ? [$company, $branch, 'view_id' => $viewId] : [$company, $branch])
            ->with('status', 'Contraseña actualizada correctamente.');
    }

    private function validatePerson(Request $request): array
    {
        $fromPos = $request->boolean('from_pos');
        $personType = strtoupper(trim((string) $request->input('person_type')));
        $isPosRuc = $fromPos && $personType === 'RUC';

        $data = $request->validate([
            'first_name'      => $isPosRuc ? ['required', 'string', 'max:255'] : ['required', 'string', 'max:255'],
            'last_name'       => $isPosRuc ? ['nullable', 'string', 'max:255'] : ['required', 'string', 'max:255'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'genero'          => ['nullable', 'string', 'max:30'],
            // En el POS solo nombre y apellido son obligatorios
            'person_type'     => $fromPos ? ['nullable', 'string', 'max:100'] : ['required', 'string', 'max:100'],
            'phone'           => ['nullable', 'string', 'max:50'],
            'email'           => ['nullable', 'email', 'max:255'],
            'document_number' => $fromPos ? ['nullable', 'string', 'max:50'] : ['required', 'string', 'max:50'],
            'address'         => ['nullable', 'string', 'max:255'],
            'location_id'     => $fromPos ? ['nullable', 'integer', 'exists:locations,id'] : ['required', 'integer', 'exists:locations,id'],
            'pin'             => ['nullable', 'string', 'max:20'],
        ]);

        if ($isPosRuc && !array_key_exists('last_name', $data)) {
            $data['last_name'] = '';
        }

        if ($isPosRuc && $data['last_name'] === null) {
            $data['last_name'] = '';
        }

        return $data;
    }


    private function validateRoles(Request $request): array
    {
        $validated = $request->validate([
            'roles' => ['nullable', 'array'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ]);

        return array_values(array_unique(array_map('intval', $validated['roles'] ?? [])));
    }

    private function validateUserData(Request $request, bool $hasUserRole, ?Person $person): array
    {
        if (!$hasUserRole) {
            return [];
        }

        if ($request->has('default_view_id') && $request->input('default_view_id') === '') {
            $request->merge(['default_view_id' => null]);
        }

        $rules = [
            'user_name' => ['required', 'string', 'max:255'],
            'profile_id' => ['required', 'integer', 'exists:profiles,id'],
            'default_view_id' => [
                'nullable',
                'integer',
                'exists:views,id',
                function (string $attribute, mixed $value, \Closure $fail) use ($request) {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $profileId = (int) $request->input('profile_id');
                    if ($profileId === 0) {
                        return;
                    }
                    $allowed = MenuHelper::allowedViewIdsForProfileAnyBranch($profileId);
                    if ($allowed === []) {
                        return;
                    }
                    if (! in_array((int) $value, $allowed, true)) {
                        $fail('La vista por defecto debe coincidir con una opción de menú asignada al perfil elegido.');
                    }
                },
            ],
        ];

        if ($person && $person->user) {
            $rules['password'] = ['nullable', 'string', 'min:8', 'confirmed'];
        } else {
            $rules['password'] = ['required', 'string', 'min:8', 'confirmed'];
        }

        $messages = [
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.required' => 'La contraseña es obligatoria y debe tener al menos 8 caracteres.',
        ];

        return $request->validate($rules, $messages);
    }

    /**
     * Opciones { id, description } para el combobox de vista por defecto (personal con rol Usuario).
     */
    private function viewOptionsForCombobox(): array
    {
        return ViewModel::query()
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name', 'abbreviation'])
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

    /**
     * Actualiza la vista por defecto del perfil (compartida por todos los usuarios con ese perfil).
     */
    private function syncProfileDefaultView(int $profileId, ?int $defaultViewId): void
    {
        Profile::query()->whereKey($profileId)->update([
            'default_view_id' => $defaultViewId,
        ]);
    }

    private function syncRoles(Person $person, array $roleIds, int $branchId): void
    {
        $syncData = [];
        foreach ($roleIds as $roleId) {
            $syncData[$roleId] = ['branch_id' => $branchId];
        }
        $person->roles()->sync($syncData);
    }

    private function resolveBranch(Company $company, Branch $branch): Branch
    {
        if ($branch->company_id !== $company->id) {
            abort(404);
        }

        return $branch;
    }

    private function resolvePerson(Branch $branch, Person $person): Person
    {
        if ($person->branch_id !== $branch->id) {
            abort(404);
        }

        return $person;
    }

    private function getLocationData(?Person $person = null, ?int $defaultLocationId = null): array
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

        $selectedDistrictId = $person?->location_id ?? $defaultLocationId;
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
}
