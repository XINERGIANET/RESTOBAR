<?php

if (!function_exists('effective_branch_id')) {
    /**
     * Devuelve el branch_id a usar para filtrar listados.
     * Si el usuario es Administrador de sistema, devuelve null (ve todo).
     * Si no, devuelve el branch_id de la sesión.
     */
    function effective_branch_id(): ?int
    {
        $user = auth()->user();
        if ($user && $user->isSystemAdmin()) {
            return null;
        }
        $id = session('branch_id');
        return $id !== null ? (int) $id : null;
    }
}

if (!function_exists('current_user_is_mozo')) {
    /**
     * Indica si el perfil actual en sesión corresponde al perfil Mozo.
     */
    function current_user_is_mozo(): bool
    {
        $profileId = session('profile_id') ?? auth()->user()?->profile_id;
        $resolved = $profileId !== null && $profileId !== '' ? (int) $profileId : null;

        return \App\Models\Profile::userHasMozoProfile($resolved);
    }
}

if (!function_exists('effective_cash_register_id')) {
    /**
     * Devuelve la caja seleccionada en sesión si pertenece a la sucursal actual.
     */
    function effective_cash_register_id(?int $branchId = null): ?int
    {
        $branchId = $branchId ?? effective_branch_id() ?? (session('branch_id') !== null ? (int) session('branch_id') : null);
        $cashRegisterId = session('cash_register_id');

        if ($cashRegisterId === null) {
            return null;
        }

        $cashRegisterId = (int) $cashRegisterId;
        if ($cashRegisterId <= 0) {
            return null;
        }

        $query = \App\Models\CashRegister::query()->where('id', $cashRegisterId);
        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        return $query->exists() ? $cashRegisterId : null;
    }
}

if (!function_exists('cash_register_selection_required')) {
    /**
     * Indica si la sucursal actual tiene cajas y aún no hay una seleccionada en sesión.
     */
    function cash_register_selection_required(?int $branchId = null): bool
    {
        if (current_user_is_mozo()) {
            return false;
        }

        $branchId = $branchId ?? effective_branch_id() ?? (session('branch_id') !== null ? (int) session('branch_id') : null);

        if ($branchId === null) {
            return false;
        }

        $hasRegisters = \App\Models\CashRegister::query()
            ->where('branch_id', $branchId)
            ->exists();

        if (! $hasRegisters) {
            return false;
        }

        return effective_cash_register_id($branchId) === null;
    }
}

if (!function_exists('effective_default_sale_document_type_id')) {
    /**
     * Resuelve el tipo de documento por defecto para ventas/cobros en la sucursal.
     * Prioriza branch_parameters -> parameters.value -> fallback id 5.
     */
    function effective_default_sale_document_type_id(?int $branchId = null, array $movementTypeIds = [2]): ?int
    {
        $branchId = $branchId ?? effective_branch_id() ?? (session('branch_id') !== null ? (int) session('branch_id') : null);

        $parameterQuery = \Illuminate\Support\Facades\DB::table('parameters')
            ->whereNull('parameters.deleted_at')
            ->where(function ($query) {
                $query->whereRaw('LOWER(TRIM(parameters.description)) = ?', ['tipo de comprobante por defecto'])
                    ->orWhereRaw('LOWER(TRIM(parameters.description)) = ?', ['tipo documento por defecto'])
                    ->orWhereRaw('LOWER(TRIM(parameters.description)) = ?', ['tipo de documento por defecto']);
            });

        $parameter = $parameterQuery->first(['parameters.id', 'parameters.value']);

        $resolvedId = null;
        if ($parameter && $branchId) {
            $branchValue = \Illuminate\Support\Facades\DB::table('branch_parameters')
                ->whereNull('deleted_at')
                ->where('branch_id', $branchId)
                ->where('parameter_id', $parameter->id)
                ->value('value');

            $rawValue = $branchValue !== null && $branchValue !== '' ? $branchValue : $parameter->value;
            if (is_numeric($rawValue)) {
                $resolvedId = (int) $rawValue;
            }
        } elseif ($parameter && is_numeric($parameter->value)) {
            $resolvedId = (int) $parameter->value;
        }

        if (! $resolvedId) {
            $resolvedId = 5;
        }

        $query = \App\Models\DocumentType::query()->where('id', $resolvedId);
        if (! empty($movementTypeIds)) {
            $query->whereIn('movement_type_id', $movementTypeIds);
        }
        if ($query->exists()) {
            return $resolvedId;
        }

        return \App\Models\DocumentType::query()
            ->when(! empty($movementTypeIds), fn ($q) => $q->whereIn('movement_type_id', $movementTypeIds))
            ->orderBy('id')
            ->value('id');
    }
}
