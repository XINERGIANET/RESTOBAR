<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\ProductType;
use App\Support\InsensitiveSearch;
use Illuminate\Http\Request;

class ProductTypeController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $perPage = (int) $request->input('per_page', 10);
        $allowedPerPage = [10, 20, 50, 100];
        $viewId = $request->input('view_id');
        $branchId = $request->session()->get('branch_id');

        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        $query = ProductType::query()->with('branch');
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }
        $query->when($search, function ($q) use ($search) {
            $q->where(function ($inner) use ($search) {
                InsensitiveSearch::whereInsensitiveLike($inner, 'name', $search);
                InsensitiveSearch::whereInsensitiveLike($inner, 'description', $search, 'or');
                InsensitiveSearch::whereInsensitiveLike($inner, 'behavior', $search, 'or');
            });
        });
        $productTypes = $query->orderByRaw("CASE behavior WHEN 'SELLABLE' THEN 1 WHEN 'BOTH' THEN 2 WHEN 'SUPPLY' THEN 3 ELSE 4 END")
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return view('product_types.index', [
            'productTypes' => $productTypes,
            'search' => $search,
            'perPage' => $perPage,
            'viewId' => $viewId,
            'currentBranch' => $branchId ? Branch::find($branchId) : null,
        ]);
    }

    public function store(Request $request)
    {
        $branchId = $request->session()->get('branch_id');
        if (!$branchId) {
            return redirect()
                ->route('product_types.index', $request->only('view_id'))
                ->with('error', 'Debes tener una sucursal seleccionada para crear tipos de producto.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'behavior' => ['required', 'string', 'in:SELLABLE,SUPPLY,BOTH'],
            'icon' => ['nullable', 'string', 'max:100'],
        ]);
        $data['branch_id'] = $branchId;

        ProductType::create($data);
        $viewId = $request->input('view_id');

        return redirect()
            ->route('product_types.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Tipo de producto creado correctamente.');
    }

    public function edit(Request $request, ProductType $productType)
    {
        $productType->load('branch');
        return view('product_types.edit', [
            'productType' => $productType,
            'viewId' => $request->input('view_id'),
        ]);
    }

    public function update(Request $request, ProductType $productType)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'behavior' => ['required', 'string', 'in:SELLABLE,SUPPLY,BOTH'],
            'icon' => ['nullable', 'string', 'max:100'],
        ]);

        $productType->update($data);
        $viewId = $request->input('view_id');

        return redirect()
            ->route('product_types.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Tipo de producto actualizado correctamente.');
    }

    public function destroy(Request $request, ProductType $productType)
    {
        if ($productType->products()->exists()) {
            return redirect()
                ->route('product_types.index', $request->only('view_id'))
                ->with('error', 'No se puede eliminar: hay productos asociados a este tipo.');
        }
        $productType->delete();
        $viewId = $request->input('view_id');

        return redirect()
            ->route('product_types.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Tipo de producto eliminado correctamente.');
    }
}
