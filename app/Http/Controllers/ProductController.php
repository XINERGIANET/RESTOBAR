<?php

namespace App\Http\Controllers;

use App\Imports\ProductsImport;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Operation;
use App\Models\Person;
use App\Models\PrinterBranch;
use App\Models\Product;
use App\Models\ProductBranch;
use App\Models\ProductType;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Support\InsensitiveSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use App\Exports\PlantillaProductosExport;
use Maatwebsite\Excel\Facades\Excel;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $perPage = (int) $request->input('per_page', 10);
        $allowedPerPage = [10, 20, 50, 100];
        $viewId = $request->input('view_id');
        $branchId = \effective_branch_id();
        $printers = PrinterBranch::query()->with('branch')->where('branch_id', $branchId)->orderBy('name')->get();
        $profileId = $request->session()->get('profile_id') ?? $request->user()?->profile_id;
        $operaciones = collect();
        $productTypeId = $request->input('product_type_id');
        $categoryId = $request->input('category_id');
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

        $products = Product::query()
            ->with(['category', 'baseUnit', 'productBranches.branch', 'productBranches.taxRate'])
            ->when($branchId, function ($query) use ($branchId) {
                $query->whereHas('productBranches', fn($q) => $q->where('branch_id', $branchId));
            })
            ->when($productTypeId, function ($query) use ($productTypeId) {
                $query->where('product_type_id', $productTypeId);
            })
            ->when($categoryId, function ($query) use ($categoryId) {
                $query->where('category_id', $categoryId);
            })
            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    InsensitiveSearch::whereInsensitiveLike($inner, 'description', $search);
                    InsensitiveSearch::whereInsensitiveLike($inner, 'code', $search, 'or');
                    InsensitiveSearch::whereInsensitiveLike($inner, 'abbreviation', $search, 'or');
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $categories = Category::query()
            ->when($branchId, function ($query) use ($branchId) {
                $query->whereExists(function ($sub) use ($branchId) {
                    $sub->select(DB::raw(1))
                        ->from('category_branch')
                        ->whereColumn('category_branch.category_id', 'categories.id')
                        ->where('category_branch.branch_id', $branchId)
                        ->whereNull('category_branch.deleted_at');
                });
            })
            ->orderBy('description')
            ->get();
        $units = Unit::query()->orderBy('description')->get();
        $taxRates = TaxRate::query()->where('status', true)->orderBy('order_num')->get();
        $currentBranch = Branch::find(session('branch_id'));
        $branches = $currentBranch
            ? Branch::query()->where('company_id', $currentBranch->company_id)->orderBy('legal_name')->get(['id', 'legal_name'])
            : Branch::query()->orderBy('legal_name')->get(['id', 'legal_name']);
        $productTypes = $branchId
            ? ProductType::query()->where('branch_id', $branchId)->orderByRaw("CASE behavior WHEN 'SELLABLE' THEN 1 ELSE 2 END")->orderBy('name')->get()
            : collect();
        $igvByBranchId = DB::table('branch_parameters as bp')
            ->join('parameters as p', 'p.id', '=', 'bp.parameter_id')
            ->whereNull('bp.deleted_at')
            ->whereNull('p.deleted_at')
            ->where('p.description', 'igv_defecto')
            ->pluck('bp.value', 'bp.branch_id')
            ->map(fn($v) => is_numeric($v) ? (int) $v : null)
            ->filter()
            ->all();

        $proveedorRoleId = (int) env('PROVEEDOR_ROLE_ID', 4);
        $suppliers = Person::whereHas('roles', function ($query) use ($branchId, $proveedorRoleId) {
            $query->where('roles.id', $proveedorRoleId)
                ->where('role_person.branch_id', $branchId);
        })
        ->when(!$branchId, fn($q) => $q->whereRaw('1=0'))
        ->orderBy('last_name')
        ->orderBy('first_name')
        ->get()
        ->map(fn($p) => [
            'id' => $p->id,
            'description' => trim($p->first_name . ' ' . $p->last_name) . ($p->document_number ? ' - ' . $p->document_number : ''),
        ])
        ->values();

        return view('products.index', [
            'products' => $products,
            'categories' => $categories,
            'units' => $units,
            'taxRates' => $taxRates,
            'currentBranch' => $currentBranch,
            'branches' => $branches,
            'productTypes' => $productTypes,
            'igvByBranchId' => $igvByBranchId,
            'printers' => $printers,
            'search' => $search,
            'perPage' => $perPage,
            'operaciones' => $operaciones,
            'suppliers' => $suppliers,
            'productTypeId' => $productTypeId,
            'categoryId' => $categoryId,
        ]);
    }

    public function store(Request $request)
    {
        $imagePath = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');

            if ($file->isValid() && $file->getRealPath() && is_readable($file->getRealPath())) {
                try {
                    // Asegurar que el directorio existe
                    $directory = storage_path('app/public/product');
                    if (!is_dir($directory)) {
                        $created = @mkdir($directory, 0755, true);
                        if (!$created) {
                            Log::error(message: 'Failed to create directory: ' . $directory);
                        }
                    }

                    // Verificar permisos del directorio
                    if (is_dir($directory)) {
                    }
                    $path = $file->store('product', 'public');

                    if ($path && !empty($path)) {
                        $imagePath = $path;
                    } else {
                        Log::warning(message: 'El path de la imagen está vacío después de guardar');
                    }
                } catch (\Exception $e) {
                    Log::error(message: 'Error al guardar imagen del producto: ' . $e->getMessage());
                }
            } else {
                Log::warning(message: 'El archivo de imagen no es válido o no tiene path');
            }
        } else {
            Log::info(message: 'No image file in request');
        }

        $validated = $this->validateProduct($request);
        $productType = ProductType::find($validated['product_type_id']);
        $productData = $this->prepareProductData($validated, $productType);
        $branchData = $this->prepareBranchData($validated, $productType);

        if ($imagePath !== null && $imagePath !== '') {
            $productData['image'] = is_string($imagePath) ? $imagePath : (string) $imagePath;
            Log::info('Image path added to data: ' . $productData['image']);
        }

        $product = Product::create($productData);

        // Crear ProductBranch para la sucursal actual
        $branchId = (int) ($request->input('branch_id') ?: $request->session()->get('branch_id'));
        if ($branchId) {
            $branchData['product_id'] = $product->id;
            $branchData['branch_id'] = $branchId;
            $branchData['status'] = 'A';
            $productBranch = ProductBranch::create($branchData);
            $this->syncProductBranchPrinters($productBranch, $request, $branchId);
        }

        $viewId = $request->input('view_id');

        return redirect()
            ->route('products.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Producto creado correctamente.');
    }

    public function edit(Request $request, Product $product)
    {
        $product->load(['category', 'productType', 'productBranches.printers']);
        $branchId = $request->session()->get('branch_id');
        $categoryBranchId = \effective_branch_id();
        $categories = Category::query()
            ->when($categoryBranchId !== null, function ($query) use ($categoryBranchId) {
                $query->whereExists(function ($sub) use ($categoryBranchId) {
                    $sub->select(DB::raw(1))
                        ->from('category_branch')
                        ->whereColumn('category_branch.category_id', 'categories.id')
                        ->where('category_branch.branch_id', $categoryBranchId)
                        ->whereNull('category_branch.deleted_at');
                });
            })
            ->orderBy('description')
            ->get();
        $units = Unit::query()->orderBy('description')->get();
        $taxRates = TaxRate::query()->where('status', true)->orderBy('order_num')->get();
        $branchId = $request->session()->get('branch_id');
        $printers = PrinterBranch::query()->with('branch')->where('branch_id', $branchId)->orderBy('id')->get();
        $productBranch = $product->productBranches()
            ->where('branch_id', $branchId)
            ->with('printers')
            ->first();

        $productTypes = $branchId
            ? ProductType::query()->where('branch_id', $branchId)->orderByRaw("CASE behavior WHEN 'SELLABLE' THEN 1 ELSE 2 END")->orderBy('name')->get()
            : collect();
        if ($product->product_type_id === null && $productTypes->isNotEmpty()) {
            $product->product_type_id = $product->type === 'INGREDENT'
                ? $productTypes->firstWhere('behavior', ProductType::BEHAVIOR_SUPPLY)?->id ?? $productTypes->first()->id
                : $productTypes->firstWhere('behavior', ProductType::BEHAVIOR_SELLABLE)?->id ?? $productTypes->first()->id;
        }

        $currentBranch = Branch::find(session('branch_id'));
        $branches = $currentBranch
            ? Branch::query()->where('company_id', $currentBranch->company_id)->orderBy('legal_name')->get(['id', 'legal_name'])
            : Branch::query()->orderBy('legal_name')->get(['id', 'legal_name']);
        $igvByBranchId = DB::table('branch_parameters as bp')
            ->join('parameters as p', 'p.id', '=', 'bp.parameter_id')
            ->whereNull('bp.deleted_at')
            ->whereNull('p.deleted_at')
            ->where('p.description', 'igv_defecto')
            ->pluck('bp.value', 'bp.branch_id')
            ->map(fn($v) => is_numeric($v) ? (int) $v : null)
            ->filter()
            ->all();
        $productBranchesByBranchId = $product->productBranches
            ->keyBy('branch_id')
            ->map(fn(ProductBranch $pb) => [
                'price' => (float) ($pb->price ?? 0),
                'purchase_price' => (float) ($pb->purchase_price ?? 0),
                'stock' => (float) ($pb->stock ?? 0),
                'stock_minimum' => (float) ($pb->stock_minimum ?? 0),
                'stock_maximum' => (float) ($pb->stock_maximum ?? 0),
                'minimum_sell' => (float) ($pb->minimum_sell ?? 0),
                'minimum_purchase' => (float) ($pb->minimum_purchase ?? 0),
                'tax_rate_id' => $pb->tax_rate_id,
                'unit_sale' => is_numeric($pb->unit_sale) ? (int) $pb->unit_sale : null,
                'expiration_date' => $pb->expiration_date,
                'supplier_id' => $pb->supplier_id,
                'favorite' => $pb->favorite ?? 'N',
                'duration_minutes' => (float) ($pb->duration_minutes ?? 0),
                'printer_ids' => $pb->printers->pluck('id')->map(fn($id) => (int) $id)->values()->all(),
            ]);

        $editBranchId = $request->session()->get('branch_id');
        $editProveedorRoleId = (int) env('PROVEEDOR_ROLE_ID', 4);
        $editSuppliers = Person::whereHas('roles', function ($query) use ($editBranchId, $editProveedorRoleId) {
            $query->where('roles.id', $editProveedorRoleId)
                ->where('role_person.branch_id', $editBranchId);
        })
        ->when(!$editBranchId, fn($q) => $q->whereRaw('1=0'))
        ->orderBy('last_name')
        ->orderBy('first_name')
        ->get()
        ->map(fn($p) => [
            'id' => $p->id,
            'description' => trim($p->first_name . ' ' . $p->last_name) . ($p->document_number ? ' - ' . $p->document_number : ''),
        ])
        ->values();

        return view('products.edit', [
            'product' => $product,
            'productBranch' => $productBranch,
            'categories' => $categories,
            'units' => $units,
            'taxRates' => $taxRates,
            'productTypes' => $productTypes,
            'suppliers' => $editSuppliers,
            'branches' => $branches,
            'igvByBranchId' => $igvByBranchId,
            'productBranchesByBranchId' => $productBranchesByBranchId,
            'viewId' => $request->input('view_id'),
            'printers' => $printers,
        ]);
    }

    public function update(Request $request, Product $product)
    {
        // Al editar, rellenar campos vacíos o no enviados con los valores actuales del producto/productBranch.
        $request->merge($this->mergeRequestWithExistingProduct($request, $product));
        $validated = $this->validateProduct($request, $product->id);
        $productType = ProductType::find($validated['product_type_id']);
        $productData = $this->prepareProductData($validated, $productType);
        $branchData = $this->prepareBranchData($validated, $productType);

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            if ($file->isValid() && $file->getRealPath()) {
                try {
                    if ($product->image && !empty($product->image) && Storage::disk('public')->exists($product->image)) {
                        Storage::disk('public')->delete($product->image);
                    }
                    $directory = storage_path('app/public/product');
                    if (!is_dir($directory)) {
                        mkdir($directory, 0755, true);
                    }
                    $path = $file->store('product', 'public');
                    if ($path && $path !== '') {
                        $productData['image'] = is_string($path) ? $path : (string) $path;
                    } else {
                        Log::warning(message: 'El path de la imagen está vacío después de guardar');
                    }
                } catch (\Exception $e) {
                    Log::error(message: 'Error al actualizar imagen del producto: ' . $e->getMessage());
                }
            }
        }

        // Actualizar producto
        $product->update($productData);

        // Actualizar o crear ProductBranch para la sucursal seleccionada
        $branchId = (int) ($validated['branch_id'] ?? $request->session()->get('branch_id') ?? 0);
        if ($branchId) {
            $productBranch = $product->productBranches()
                ->where('branch_id', $branchId)
                ->first();

            if ($productBranch) {
                $productBranch->update($branchData);
            } else {
                $branchData['product_id'] = $product->id;
                $branchData['branch_id'] = $branchId;
                $branchData['status'] = 'E';
                $productBranch = ProductBranch::create($branchData);
            }
            $this->syncProductBranchPrinters($productBranch, $request, $branchId);
        }

        $viewId = $request->input('view_id');

        return redirect()
            ->route('products.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Producto actualizado correctamente.');
    }

    public function destroy(Request $request, Product $product)
    {
        // Eliminar la imagen si existe
        if ($product->image && !empty($product->image) && Storage::disk('public')->exists($product->image)) {
            Storage::disk('public')->delete($product->image);
        }

        $product->delete();
        $viewId = $request->input('view_id');

        return redirect()
            ->route('products.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Producto eliminado correctamente.');
    }

    /**
     * Rellena el request con valores existentes del producto/productBranch cuando faltan o están vacíos.
     * Evita errores al editar solo un campo.
     */
    private function mergeRequestWithExistingProduct(Request $request, Product $product): array
    {
        $product->load(['productBranches']);
        $branchId = (int) ($request->input('branch_id') ?: $request->session()->get('branch_id') ?: 0);
        $productBranch = $branchId ? $product->productBranches->firstWhere('branch_id', $branchId) : null;

        $merge = [];
        $productFields = ['code', 'description', 'abbreviation', 'product_type_id', 'category_id', 'base_unit_id', 'kardex', 'status', 'complement', 'complement_mode', 'classification', 'features', 'recipe', 'favorite', 'duration_minutes', 'supplier_id'];
        foreach ($productFields as $key) {
            if (!$request->filled($key)) {
                $val = $product->{$key} ?? null;
                if ($key === 'recipe') $val = (bool) ($val ?? false);
                if ($key === 'complement_mode' && $val === null) $val = '';
                $merge[$key] = $val;
            }
        }
        if (! $request->has('detail_options_lines')) {
            $merge['detail_options_lines'] = collect($product->detail_options ?? [])
                ->map(fn ($item) => trim((string) $item))
                ->filter()
                ->implode(PHP_EOL);
        }

        if ($productBranch) {
            $branchFields = ['price', 'purchase_price', 'stock', 'stock_minimum', 'stock_maximum', 'minimum_sell', 'minimum_purchase', 'tax_rate_id', 'unit_sale', 'expiration_date'];
            foreach ($branchFields as $key) {
                if (!$request->filled($key)) {
                    $val = $productBranch->{$key};
                    if ($key === 'tax_rate_id' && ($val === null || $val === '')) {
                        $val = DB::table('branch_parameters as bp')
                            ->join('parameters as p', 'p.id', '=', 'bp.parameter_id')
                            ->where('bp.branch_id', $branchId)
                            ->where('p.description', 'igv_defecto')
                            ->whereNull('bp.deleted_at')
                            ->whereNull('p.deleted_at')
                            ->value('bp.value');
                        $val = is_numeric($val) ? (int) $val : null;
                    }
                    if ($key === 'unit_sale') {
                        $val = is_numeric($val) ? (int) $val : ($product->base_unit_id ?? null);
                    }
                    $merge[$key] = $val;
                }
            }
            // unit_sale: si el valor enviado no es entero válido (ej. "", "N"), usar existente o base_unit_id
            $unitSale = $request->input('unit_sale');
            if ($request->has('unit_sale') && $unitSale !== null && $unitSale !== '' && !is_numeric($unitSale)) {
                $merge['unit_sale'] = is_numeric($productBranch->unit_sale) ? (int) $productBranch->unit_sale : ($product->base_unit_id ?? null);
            } elseif ($request->has('unit_sale') && ($unitSale === '' || $unitSale === null)) {
                $merge['unit_sale'] = is_numeric($productBranch->unit_sale) ? (int) $productBranch->unit_sale : ($product->base_unit_id ?? null);
            }
            // tax_rate_id: si está vacío, usar existente o IGV por defecto de la sucursal
            $taxRate = $request->input('tax_rate_id');
            if ($request->has('tax_rate_id') && ($taxRate === '' || $taxRate === null)) {
                $merge['tax_rate_id'] = $productBranch->tax_rate_id
                    ?? DB::table('branch_parameters as bp')
                    ->join('parameters as p', 'p.id', '=', 'bp.parameter_id')
                    ->where('bp.branch_id', $branchId)
                    ->where('p.description', 'igv_defecto')
                    ->whereNull('bp.deleted_at')
                    ->whereNull('p.deleted_at')
                    ->value('bp.value');
                if (isset($merge['tax_rate_id']) && $merge['tax_rate_id'] !== null) {
                    $merge['tax_rate_id'] = (int) $merge['tax_rate_id'];
                }
            }
        }

        if (!$request->filled('branch_id') && $branchId) {
            $merge['branch_id'] = $branchId;
        } elseif (!$request->filled('branch_id') && !$branchId && $product->productBranches->isNotEmpty()) {
            $merge['branch_id'] = (int) $product->productBranches->first()->branch_id;
        }

        return $merge;
    }

    private function validateProduct(Request $request, ?int $excludeId = null): array
    {
        $productType = $request->filled('product_type_id')
            ? ProductType::find($request->input('product_type_id'))
            : null;
        $isSupply = $productType && $productType->isSupply();

        // Reglas por sede: los campos pueden venir ocultos según el tipo de producto,
        // así que se validan como "nullable" y solo se aplican cuando realmente se envían.
        $branchRules = [
            'price' => ['nullable', 'numeric', 'min:0'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'stock' => array_values(array_filter([
                'nullable',
                'numeric',
                'min:0',
                'gte:stock_minimum',
                $request->input('stock_maximum', 0) > 0 ? 'lte:stock_maximum' : null,
            ])),
            'stock_minimum' => ['nullable', 'numeric', 'min:0'],
            'stock_maximum' => ['nullable', 'numeric', 'min:0', 'gte:stock_minimum'],
            'minimum_sell' => ['nullable', 'numeric', 'min:0'],
            'minimum_purchase' => ['nullable', 'numeric', 'min:0'],
            'tax_rate_id' => ['nullable', 'integer', 'exists:tax_rates,id'],
            'unit_sale' => ['nullable', 'integer', 'exists:units,id'],
            'expiration_date' => ['nullable', 'date'],
        ];

        $validated = $request->validate(array_merge([
            // Datos del Producto
            'code' => [
                'required',
                'string',
                'max:50',
                // Único por sucursal: el mismo código no puede existir en otra sede con product_branch
                function ($attribute, $value, $fail) use ($request, $excludeId) {
                    $branchId = (int) ($request->input('branch_id') ?: $request->session()->get('branch_id'));
                    if (!$branchId || !$value) {
                        return;
                    }
                    $exists = DB::table('products')
                        ->join('product_branch', 'product_branch.product_id', '=', 'products.id')
                        ->where('products.code', $value)
                        ->where('product_branch.branch_id', $branchId)
                        ->when($excludeId, fn($q) => $q->where('products.id', '!=', $excludeId))
                        ->exists();
                    if ($exists) {
                        $fail('El código ya está registrado en esta sucursal.');
                    }
                },
            ],
            'description' => ['required', 'string', 'max:255'],
            'abbreviation' => ['nullable', 'string', 'max:255'],
            'product_type_id' => ['required', 'integer', 'exists:product_types,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'base_unit_id' => ['required', 'integer', 'exists:units,id'],
            'kardex' => ['required', 'string', 'in:S,N'],
            'status' => ['required', 'string', 'in:A,I'],
            'image' => ['nullable', 'sometimes', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            'complement' => ['required', 'string', 'in:NO,HAS,IS'],
            'complement_mode' => ['nullable', 'string', 'in:,ALL,QUANTITY'],
            'classification' => ['required', 'string', 'in:GOOD,SERVICE'],
            'features' => ['nullable', 'string'],
            'detail_options_lines' => ['nullable', 'string'],
            'recipe' => ['required', 'boolean'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'favorite' => ['required', 'string', 'in:S,N'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'supplier_id' => ['nullable', 'integer'],
            'printer_ids' => ['nullable', 'array'],
            'printer_ids.*' => ['integer', 'exists:printers_branch,id'],
        ], $branchRules));

        if ($isSupply) {
            $validated['price'] = 0;
            $validated['minimum_sell'] = 0;
            $validated['tax_rate_id'] = null;
            $validated['unit_sale'] = 'N';
            $validated['expiration_date'] = null;
        }

        if (isset($validated['image']) && empty($validated['image'])) {
            unset($validated['image']);
        }

        return $validated;
    }

    private function prepareProductData(array $validated, ?ProductType $productType): array
    {
        $type = 'PRODUCT';
        if ($productType) {
            $type = $productType->isSellable() ? 'PRODUCT' : 'INGREDENT';
        }
        return [
            'code' => $validated['code'],
            'description' => $validated['description'],
            'abbreviation' => $validated['abbreviation'],
            'type' => $type,
            'product_type_id' => $validated['product_type_id'],
            'category_id' => $validated['category_id'],
            'base_unit_id' => $validated['base_unit_id'],
            'kardex' => $validated['kardex'],
            'complement' => $validated['complement'],
            'complement_mode' => $validated['complement_mode'],
            'classification' => $validated['classification'],
            'features' => $validated['features'],
            'detail_options' => $this->parseDetailOptions($validated['detail_options_lines'] ?? null),
            'recipe' => (bool) $validated['recipe'],
        ];
    }

    private function parseDetailOptions(?string $raw): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) ($raw ?? '')))
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function prepareBranchData(array $validated, ?ProductType $productType): array
    {
        if ($productType && $productType->isSupply()) {
            return [
                'status' => $validated['status'],
                'expiration_date' => $validated['expiration_date'] ?? null,
                'stock_minimum' => $validated['stock_minimum'] ?? 0,
                'stock_maximum' => $validated['stock_maximum'] ?? 0,
                'minimum_sell' => 0,
                'minimum_purchase' => $validated['minimum_purchase'] ?? 0,
                'favorite' => $validated['favorite'],
                'tax_rate_id' => $validated['tax_rate_id'] ?? null,
                'unit_sale' => $validated['unit_sale'] ?? $validated['base_unit_id'] ?? 'N',
                'duration_minutes' => $validated['duration_minutes'] ?? 0,
                'supplier_id' => $validated['supplier_id'] ?? null,
                'stock' => $validated['stock'] ?? 0,
                'price' => 0,
                'purchase_price' => $validated['purchase_price'] ?? 0,
            ];
        }





        $data = [
            'status' => $validated['status'],
            'expiration_date' => $validated['expiration_date'] ?? null,
            'stock_minimum' => $validated['stock_minimum'],
            'stock_maximum' => $validated['stock_maximum'],
            'minimum_sell' => $validated['minimum_sell'],
            'minimum_purchase' => $validated['minimum_purchase'] ?? 0,
            'favorite' => $validated['favorite'],
            'tax_rate_id' => $validated['tax_rate_id'] ?? null,
            'unit_sale' => $validated['unit_sale'] ?? $validated['base_unit_id'] ?? 'N',
            'duration_minutes' => $validated['duration_minutes'] ?? 0,
            'supplier_id' => $validated['supplier_id'] ?? null,
            'stock' => $validated['stock'] ?? 0,
            'price' => $validated['price'] ?? 0,
            'purchase_price' => $validated['purchase_price'] ?? 0,
        ];

        return $data;
    }

    /**
     * Guarda la relación producto+sucursal ↔ ticketeras en la pivote product_branch_printer.
     */
    private function syncProductBranchPrinters(ProductBranch $productBranch, Request $request, int $branchId): void
    {
        $ids = $request->input('printer_ids', []);
        if (! is_array($ids)) {
            $ids = [];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        $single = (int) $request->input('printer_id', 0);
        if ($single > 0 && $ids === []) {
            $ids = [$single];
        }

        $validIds = PrinterBranch::query()
            ->where('branch_id', $branchId)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();

        $productBranch->printers()->sync($validIds);
    }

    // ─── Importación desde Excel ──────────────────────────────────────────────

    public function importForm(Request $request)
    {
        $viewId = $request->input('view_id');
        return view('products.import', compact('viewId'));
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ], [
            'file.required' => 'Debes seleccionar un archivo.',
            'file.mimes'    => 'El archivo debe ser .xlsx, .xls o .csv.',
            'file.max'      => 'El archivo no debe superar 5 MB.',
        ]);

        $branchId = (int) session('branch_id');
        if (!$branchId) {
            return back()->withErrors(['file' => 'No hay sucursal activa en la sesión.']);
        }

        $import = new ProductsImport($branchId);
        Excel::import($import, $request->file('file'));

        $viewId = $request->input('view_id');

        return redirect()
            ->route('products.import.form', $viewId ? ['view_id' => $viewId] : [])
            ->with([
                'import_errors'   => $import->errors,
                'import_imported' => $import->imported,
                'import_updated'  => $import->updated,
            ]);
    }

    public function downloadTemplate()
    {
        return Excel::download(new PlantillaProductosExport(), 'plantilla_productos.xlsx');
    }
}
