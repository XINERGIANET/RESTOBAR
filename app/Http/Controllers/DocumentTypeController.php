<?php

namespace App\Http\Controllers;

use App\Models\DocumentType;
use App\Models\MovementType;
use App\Models\Operation;
use App\Support\InsensitiveSearch;
use Illuminate\Http\Request;

class DocumentTypeController extends Controller
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

        $documentTypes = DocumentType::query()
            ->with('movementType')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    InsensitiveSearch::whereInsensitiveLike($inner, 'name', $search);
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $movementTypes = MovementType::query()->orderBy('description')->get(['id', 'description']);

        return view('document_types.index', [
            'documentTypes' => $documentTypes,
            'movementTypes' => $movementTypes,
            'search' => $search,
            'perPage' => $perPage,
            'operaciones' => $operaciones,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'stock' => ['required', 'in:add,subtract,none'],
            'movement_type_id' => ['required', 'integer', 'exists:movement_types,id'],
        ]);

        DocumentType::create($data);

        $viewId = $request->input('view_id');

        return redirect()
            ->route('admin.document-types.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Tipo de documento creado correctamente.');
    }

    public function edit(DocumentType $documentType)
    {
        $movementTypes = MovementType::query()->orderBy('description')->get(['id', 'description']);

        return view('document_types.edit', [
            'documentType' => $documentType,
            'movementTypes' => $movementTypes,
        ]);
    }

    public function update(Request $request, DocumentType $documentType)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'stock' => ['required', 'in:add,subtract,none'],
            'movement_type_id' => ['required', 'integer', 'exists:movement_types,id'],
        ]);

        $documentType->update($data);

        $viewId = $request->input('view_id');

        return redirect()
            ->route('admin.document-types.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Tipo de documento actualizado correctamente.');
    }

    public function destroy(DocumentType $documentType)
    {
        $documentType->delete();

        $viewId = request('view_id');

        return redirect()
            ->route('admin.document-types.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Tipo de documento eliminado correctamente.');
    }
}
