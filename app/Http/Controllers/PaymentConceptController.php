<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PaymentConcept;
use App\Models\Operation;
use App\Support\InsensitiveSearch;

class PaymentConceptController extends Controller
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

        $paymentConcepts = PaymentConcept::query()
            ->when($search, function ($query) use ($search) {
                InsensitiveSearch::whereInsensitiveLike($query, 'description', $search);
            })
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('payment_concept.index', compact('paymentConcepts', 'search', 'perPage', 'allowedPerPage', 'operaciones'));
    }

    public function create()
    {
        return view('payment_concept.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'description' => 'required|string|max:255',
            'type' => 'required|string|max:1',
        ]);

        PaymentConcept::create($request->all());

        $viewId = $request->input('view_id');
        return redirect()->route('admin.payment_concepts.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Concepto de pago creado correctamente');
    }

    public function edit(PaymentConcept $paymentConcept)
    {
        return view('payment_concept.edit', compact('paymentConcept'));
    }

    public function update(Request $request, PaymentConcept $paymentConcept)
    {
        $request->validate([
            'description' => 'required|string|max:255',
            'type' => 'required|string|max:1',
        ]);
        
        $paymentConcept->update($request->all());

        $viewId = $request->input('view_id');
        return redirect()->route('admin.payment_concepts.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Concepto de pago actualizado correctamente');
    }

    public function destroy(PaymentConcept $paymentConcept)
    {
        $paymentConcept->delete();

        $viewId = request('view_id');
        return redirect()->route('admin.payment_concepts.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Concepto de pago eliminado correctamente');
    }
}
