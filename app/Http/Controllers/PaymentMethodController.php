<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use App\Models\Operation;
use App\Support\InsensitiveSearch;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
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

        $paymentMethods = PaymentMethod::query()
            ->when($search, function ($query) use ($search) {
                InsensitiveSearch::whereInsensitiveLike($query, 'description', $search);
            })
            ->orderBy('order_num')
            ->paginate($perPage)
            ->withQueryString();

        return view('payment_method.index', compact('paymentMethods', 'search', 'perPage', 'allowedPerPage', 'operaciones'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'description' => 'required|string|max:255',
            'order_num' => 'nullable|integer',
            'status' => 'nullable|boolean',
        ]);

        try {
            PaymentMethod::create($request->all());
            $viewId = $request->input('view_id');
            return redirect()->route('admin.payment_methods.index', $viewId ? ['view_id' => $viewId] : [])
                ->with('status', 'Medio de pago creado correctamente');
        } catch (\Exception $e) {
            $viewId = $request->input('view_id');
            return redirect()->route('admin.payment_methods.index', $viewId ? ['view_id' => $viewId] : [])
                ->withErrors(['error' => 'Error al crear el medio de pago: ' . $e->getMessage()]);
        }
    }

    public function update(Request $request, PaymentMethod $paymentMethod)
    {
        $request->validate([
            'description' => 'required|string|max:255',
            'order_num' => 'nullable|integer',
            'status' => 'nullable|boolean',
        ]);

        try {
            $paymentMethod->update($request->all());
            $viewId = $request->input('view_id');
            return redirect()->route('admin.payment_methods.index', $viewId ? ['view_id' => $viewId] : [])
                ->with('status', 'Medio de pago actualizado correctamente');
        } catch (\Exception $e) {
            $viewId = $request->input('view_id');
            return redirect()->route('admin.payment_methods.index', $viewId ? ['view_id' => $viewId] : [])
                ->withErrors(['error' => 'Error al actualizar el medio de pago: ' . $e->getMessage()]);
        }
    }

    public function destroy(PaymentMethod $paymentMethod)
    {
        $paymentMethod->update(['status' => 0]);
        $paymentMethod->delete();
        $viewId = request('view_id');
        return redirect()->route('admin.payment_methods.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Medio de pago eliminado correctamente');
    }
}
