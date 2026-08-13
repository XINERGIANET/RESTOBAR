<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\PrinterBranch;
use App\Models\Operation;

class PrinterBranchController extends Controller
{
    public function index(Request $request){
        $branchId = $request->session()->get('branch_id');
        $profileId = $request->session()->get('profile_id') ?? $request->user()?->profile_id;
        $search = $request->input('search');
        $perPage = (int) $request->input('per_page', 10);
        $allowedPerPage = [10, 20, 50, 100];
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }
        $viewId = $request->input('view_id');

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

        $printers = PrinterBranch::query()
            ->with('branch')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($search, function ($query, $search) {
                $query->where('name', 'like', '%' . $search . '%');
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('printers_branch.index', [
            'printers' => $printers,
            'search' => $search,
            'perPage' => $perPage,
            'allowedPerPage' => $allowedPerPage,
            'operaciones' => $operaciones,
        ]);
    }

    public function create(){
        return view('printers_branch.create');
    }

    public function store(Request $request){
        $branchId = $request->session()->get('branch_id');
        if (!$branchId) {
            return redirect()->back()->with('error', 'No se detectó una sucursal activa en la sesión.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'width' => 'nullable|string|max:50',
            'ip' => 'nullable|string|max:45',
            'status' => 'nullable|string|in:E,I',
        ]);

        $validated['branch_id'] = $branchId;
        $validated['status'] = $validated['status'] ?? 'E';

        PrinterBranch::create($validated);

        return redirect()
            ->route('printers_branch.index', $request->input('view_id') ? ['view_id' => $request->input('view_id')] : [])
            ->with('success', 'Ticketera creada correctamente');
    }

    public function edit(Request $request, PrinterBranch $printerBranch)
    {
        $branchId = $request->session()->get('branch_id');
        if (!$branchId) {
            return redirect()->route('printers_branch.index', $request->input('view_id') ? ['view_id' => $request->input('view_id')] : [])
                ->with('error', 'No se detectó una sucursal activa en la sesión.');
        }
        if ((int) $printerBranch->branch_id !== (int) $branchId) {
            return redirect()->route('printers_branch.index', $request->input('view_id') ? ['view_id' => $request->input('view_id')] : [])
                ->with('error', 'No autorizado para editar ticketeras de otra sucursal.');
        }

        return view('printers_branch.edit', [
            'printer' => $printerBranch,
            'viewId' => $request->input('view_id'),
        ]);
    }

    public function update(Request $request, PrinterBranch $printerBranch)
    {
        $branchId = $request->session()->get('branch_id');
        if (!$branchId) {
            return redirect()->route('printers_branch.index', $request->input('view_id') ? ['view_id' => $request->input('view_id')] : [])
                ->with('error', 'No se detectó una sucursal activa en la sesión.');
        }
        if ((int) $printerBranch->branch_id !== (int) $branchId) {
            return redirect()->route('printers_branch.index', $request->input('view_id') ? ['view_id' => $request->input('view_id')] : [])
                ->with('error', 'No autorizado para editar ticketeras de otra sucursal.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'width' => 'nullable|string|max:50',
            'ip' => 'nullable|string|max:45',
            'status' => 'required|string|in:E,I',
        ]);

        $printerBranch->fill($validated);
        $printerBranch->save();

        return redirect()
            ->route('printers_branch.edit', ['printerBranch' => $printerBranch->id] + ($request->input('view_id') ? ['view_id' => $request->input('view_id')] : []))
            ->with('success', 'Ticketera actualizada correctamente');
    }

    public function destroy(Request $request, PrinterBranch $printerBranch)
    {
        $branchId = $request->session()->get('branch_id');
        if ($branchId && (int) $printerBranch->branch_id !== (int) $branchId) {
            abort(403, 'No autorizado para eliminar ticketeras de otra sucursal.');
        }

        $printerBranch->delete();

        return redirect()
            ->route('printers_branch.index', $request->input('view_id') ? ['view_id' => $request->input('view_id')] : [])
            ->with('success', 'Ticketera eliminada correctamente');
    }
    
}
