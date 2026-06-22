<?php

namespace App\Http\Controllers;

use App\Models\Movement;
use App\Models\MovementType;
use App\Models\DocumentType;
use App\Models\Operation;
use App\Models\Product;
use App\Models\ProductBranch;
use App\Models\WarehouseMovement;
use App\Models\WarehouseMovementDetail;
use App\Services\KardexSyncService;
use App\Support\InsensitiveSearch;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WarehouseMovementController extends Controller
{
    public function input(Request $request)
    {
        $branchId = session('branch_id');
        $viewId = $request->input('view_id');

        if (!$branchId) {
            abort(403, 'No se ha seleccionado una sucursal');
        }

        // Movimiento de almacén: todos los tipos de producto con product_branch y categoría en sucursal
        $products = Product::query()
            ->whereHas('productBranches', fn ($q) => $q->where('branch_id', $branchId))
            ->whereExists(function ($sub) use ($branchId) {
                $sub->select(DB::raw(1))
                    ->from('category_branch')
                    ->whereColumn('category_branch.category_id', 'products.category_id')
                    ->where('category_branch.branch_id', $branchId)
                    ->whereNull('category_branch.deleted_at');
            })
            ->with(['category', 'baseUnit'])
            ->orderBy('description')
            ->get();

        // Todos los product_branches de la sucursal (vendibles e ingredientes) para stock en almacén
        $productBranches = ProductBranch::where('branch_id', $branchId)
            ->with('product')
            ->get()
            ->keyBy('product_id');

        return view('warehouse_movements.entry', [
            'products' => $products,
            'productBranches' => $productBranches,
            'viewId' => $viewId,
            'title' => 'Entrada de Productos',
        ]);
    }

    public function entry(Request $request)
    {
        // Alias para mantener compatibilidad
        return $this->input($request);
    }

    public function output(Request $request)
    {
        $branchId = session('branch_id');
        $viewId = $request->input('view_id');

        if (!$branchId) {
            abort(403, 'No se ha seleccionado una sucursal');
        }

        // Movimiento de almacén: todos los tipos de producto con product_branch y categoría en sucursal
        $products = Product::query()
            ->whereHas('productBranches', fn ($q) => $q->where('branch_id', $branchId))
            ->whereExists(function ($sub) use ($branchId) {
                $sub->select(DB::raw(1))
                    ->from('category_branch')
                    ->whereColumn('category_branch.category_id', 'products.category_id')
                    ->where('category_branch.branch_id', $branchId)
                    ->whereNull('category_branch.deleted_at');
            })
            ->with(['category', 'baseUnit'])
            ->orderBy('description')
            ->get();

        // Todos los product_branches de la sucursal (vendibles e ingredientes) para stock en almacén
        $productBranches = ProductBranch::where('branch_id', $branchId)
            ->with('product')
            ->get()
            ->keyBy('product_id');

        $productsMapped = $products->map(function ($product) use ($productBranches) {
            $productBranch = $productBranches->get($product->id);
            $imageUrl = null;
            if ($product->image && !empty(trim($product->image))) {
                $imagePath = trim($product->image);
                if (strpos($imagePath, '\\') !== false || strpos($imagePath, 'C:') !== false || strpos($imagePath, 'Temp') !== false || strpos($imagePath, 'Windows') !== false) {
                    $imageUrl = null;
                } elseif (str_starts_with($imagePath, 'http://') || str_starts_with($imagePath, 'https://')) {
                    $imageUrl = (strpos($imagePath, '\\') === false && strpos($imagePath, 'C:') === false) ? $imagePath : null;
                } elseif (str_starts_with($imagePath, 'storage/')) {
                    $imageUrl = asset($imagePath);
                } elseif (str_starts_with($imagePath, '/storage/')) {
                    $imageUrl = asset(ltrim($imagePath, '/'));
                } else {
                    $imageUrl = asset('storage/' . $imagePath);
                }
            }
            return [
                'id' => $product->id,
                'code' => $product->code ?? '',
                'name' => $product->description ?? 'Sin nombre',
                'img' => $imageUrl,
                'category' => $product->category ? $product->category->description : 'Sin categoría',
                'unit' => $product->baseUnit ? $product->baseUnit->description : 'Unidad',
                'currentStock' => $productBranch ? (int) ($productBranch->stock ?? 0) : 0,
                'price' => $productBranch ? (float) ($productBranch->price ?? 0) : 0,
            ];
        })->filter(fn($p) => !empty($p['name']))->values();

        return view('warehouse_movements.output', [
            'products' => $products,
            'productBranches' => $productBranches,
            'productsMapped' => $productsMapped,
            'branchId' => $branchId,
            'viewId' => $viewId,
            'title' => 'Salida de Productos',
        ]);
    }

    public function outputStore(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'comment' => 'nullable|string|max:500',
        ]);

        $branchId = session('branch_id');
        $userId = session('user_id');
        $userName = session('user_name') ?? 'Sistema';
        $personId = session('person_id');
        $personName = session('person_fullname') ?? 'Sistema';

        try {
            DB::beginTransaction();

            $movementType = MovementType::where(function ($query) {
                $query->where('description', 'like', '%Almacén%')
                    ->orWhere('description', 'like', '%Warehouse%')
                    ->orWhere('description', 'like', '%Inventario%');
            })->first();

            if (!$movementType) {
                $movementType = MovementType::first();
                if (!$movementType) {
                    throw new \Exception('No se encontró un tipo de movimiento válido para almacén.');
                }
            }

            // DocumentType para salida (ID 8 según la base de datos)
            $documentType = DocumentType::find(8);
            if (!$documentType) {
                $documentType = DocumentType::where(function ($query) {
                    $query->where('name', 'like', '%Salida%')
                        ->orWhere('name', 'like', '%exit%')
                        ->orWhere('name', 'like', '%output%');
                })->first();
            }
            if (!$documentType) {
                $documentType = DocumentType::where('movement_type_id', $movementType->id)->first();
            }
            if (!$documentType) {
                throw new \Exception('No se encontró un tipo de documento válido para salida.');
            }

            // Generar número de movimiento (secuencia independiente por tipo de documento)
            $todayCount = Movement::where('document_type_id', $documentType->id)
                ->whereDate('created_at', Carbon::today())
                ->where('branch_id', $branchId)
                ->count();
            $number = str_pad($todayCount + 1, 8, '0', STR_PAD_LEFT);

            // Validar stock antes de crear el movimiento
            foreach ($request->items as $item) {
                $productBranch = ProductBranch::where('product_id', $item['product_id'])
                    ->where('branch_id', $branchId)
                    ->first();
                $stock = $productBranch ? (float) $productBranch->stock : 0;
                if ($stock < $item['quantity']) {
                    $product = Product::find($item['product_id']);
                    $name = $product ? $product->description : 'Producto #' . $item['product_id'];
                    throw new \Exception("Stock insuficiente para \"{$name}\". Disponible: {$stock}, solicitado: {$item['quantity']}.");
                }
            }

            $movement = Movement::create([
                'number' => $number,
                'moved_at' => now(),
                'user_id' => $userId,
                'user_name' => $userName,
                'person_id' => $personId,
                'person_name' => $personName,
                'responsible_id' => $userId,
                'responsible_name' => $personName ?: $userName,
                'comment' => $request->comment ?? 'Salida de productos del almacén',
                'status' => 'A',
                'movement_type_id' => $movementType->id,
                'document_type_id' => $documentType->id,
                'branch_id' => $branchId,
                'parent_movement_id' => null,
            ]);

            $warehouseMovement = WarehouseMovement::create([
                'status' => 'FINALIZADO',
                'movement_id' => $movement->id,
                'branch_id' => $branchId,
            ]);

            foreach ($request->items as $item) {
                $product = Product::with('baseUnit')->findOrFail($item['product_id']);
                $productBranch = ProductBranch::where('product_id', $item['product_id'])
                    ->where('branch_id', $branchId)
                    ->lockForUpdate()
                    ->first();

                if (!$productBranch) {
                    throw new \Exception('Producto sin registro en esta sucursal (product_id: ' . $item['product_id'] . ').');
                }

                $currentStock = (float) ($productBranch->stock ?? 0);
                if ($currentStock < $item['quantity']) {
                    throw new \Exception('Stock insuficiente para ' . ($product->description ?? $product->id) . '. Disponible: ' . $currentStock);
                }

                WarehouseMovementDetail::create([
                    'warehouse_movement_id' => $warehouseMovement->id,
                    'product_id' => $product->id,
                    'product_snapshot' => [
                        'id' => $product->id,
                        'code' => $product->code,
                        'description' => $product->description,
                    ],
                    'unit_id' => $product->baseUnit->id ?? 1,
                    'quantity' => (float) $item['quantity'],
                    'comment' => $item['comment'] ?? '',
                    'status' => 'A',
                    'branch_id' => $branchId,
                ]);

                $newStock = $currentStock - $item['quantity'];
                $productBranch->update(['stock' => $newStock]);
            }

            app(KardexSyncService::class)->syncMovement($movement);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Salida de productos guardada correctamente',
                'movement_id' => $movement->id,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar la salida: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function index(Request $request)
    {
        $search = $request->input('search');
        $perPage = (int) $request->input('per_page', 10);
        $allowedPerPage = [10, 20, 50, 100];
        $viewId = $request->input('view_id');
        $branchId = \effective_branch_id();
        $profileId = $request->session()->get('profile_id') ?? $request->user()?->profile_id;
        $operaciones = collect();

        // Obtener operaciones relacionadas con la vista, branch y profile
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

        $warehouseMovements = WarehouseMovement::query()
            ->join('movements', 'warehouse_movements.movement_id', '=', 'movements.id')
            ->select('warehouse_movements.*')
            ->with(['movement.movementType', 'movement.documentType', 'branch'])
            ->when($branchId, fn ($q) => $q->where('warehouse_movements.branch_id', $branchId))
            ->when(! $branchId, fn ($q) => $q->whereRaw('1 = 0'))
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    InsensitiveSearch::whereInsensitiveLike($q, 'movements.number', $search);
                    InsensitiveSearch::whereInsensitiveLike($q, 'movements.person_name', $search, 'or');
                    InsensitiveSearch::whereInsensitiveLike($q, 'movements.user_name', $search, 'or');
                });
            })
            ->orderByDesc('movements.moved_at')
            ->orderByDesc('warehouse_movements.id')
            ->paginate($perPage)
            ->withQueryString();

        return view('warehouse_movements.index', [
            'warehouseMovements' => $warehouseMovements,
            'search' => $search,
            'perPage' => $perPage,
            'operaciones' => $operaciones,
            'title' => 'Movimientos de Almacén',
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'comment' => 'nullable|string|max:500',
        ]);

        $branchId = session('branch_id');
        $userId = session('user_id');
        $userName = session('user_name') ?? 'Sistema';
        $personId = session('person_id');
        $personName = session('person_fullname') ?? 'Sistema';

        try {
            DB::beginTransaction();

            // Buscar MovementType para almacén (asumiendo que existe uno con descripción relacionada)
            $movementType = MovementType::where(function ($query) {
                $query->where('description', 'like', '%Almacén%')
                    ->orWhere('description', 'like', '%Warehouse%')
                    ->orWhere('description', 'like', '%Inventario%');
            })->first();

            if (!$movementType) {
                // Si no existe, usar el primero disponible
                $movementType = MovementType::first();
                if (!$movementType) {
                    throw new \Exception('No se encontró un tipo de movimiento válido para almacén.');
                }
            }

            // Buscar DocumentType para entrada (ID 7 según la base de datos)
            // Primero intentar buscar por ID 7 directamente
            $documentType = DocumentType::find(7);

            // Si no existe el ID 7, buscar por nombre "Entrada" sin filtrar por movement_type_id
            if (!$documentType) {
                $documentType = DocumentType::where(function ($query) {
                    $query->where('name', 'like', '%Entrada%')
                        ->orWhere('name', 'like', '%entry%');
                })->first();
            }

            // Si aún no se encuentra, usar el primero del movement_type encontrado
            if (!$documentType) {
                $documentType = DocumentType::where('movement_type_id', $movementType->id)->first();
            }

            if (!$documentType) {
                throw new \Exception('No se encontró un tipo de documento válido para entrada.');
            }

            // Generar número de movimiento (secuencia independiente por tipo de documento)
            $todayCount = Movement::where('document_type_id', $documentType->id)
                ->whereDate('created_at', Carbon::today())
                ->where('branch_id', $branchId)
                ->count();
            $number = str_pad($todayCount + 1, 8, '0', STR_PAD_LEFT);

            // Crear Movement
            $movement = Movement::create([
                'number' => $number,
                'moved_at' => now(),
                'user_id' => $userId,
                'user_name' => $userName,
                'person_id' => $personId,
                'person_name' => $personName,
                'responsible_id' => $userId,
                'responsible_name' => $personName ?: $userName,
                'comment' => $request->comment ?? 'Entrada de productos al almacén',
                'status' => 'A',
                'movement_type_id' => $movementType->id,
                'document_type_id' => $documentType->id,
                'branch_id' => $branchId,
                'parent_movement_id' => null,
            ]);

            // Crear WarehouseMovement
            $warehouseMovement = WarehouseMovement::create([
                'status' => 'FINALIZADO',
                'movement_id' => $movement->id,
                'branch_id' => $branchId,
            ]);

            // Crear detalles y actualizar stock
            foreach ($request->items as $item) {
                $product = Product::with('baseUnit')->findOrFail($item['product_id']);
                $productBranch = ProductBranch::where('product_id', $item['product_id'])
                    ->where('branch_id', $branchId)
                    ->lockForUpdate()
                    ->first();

                if (!$productBranch) {
                    // Si no existe ProductBranch, crearlo
                    $productBranch = ProductBranch::create([
                        'product_id' => $product->id,
                        'branch_id' => $branchId,
                        'stock' => 0,
                        'price' => 0,
                        'status' => 'A',
                    ]);
                }

                // Crear WarehouseMovementDetail
                WarehouseMovementDetail::create([
                    'warehouse_movement_id' => $warehouseMovement->id,
                    'product_id' => $product->id,
                    'product_snapshot' => [
                        'id' => $product->id,
                        'code' => $product->code,
                        'description' => $product->description,
                    ],
                    'unit_id' => $product->baseUnit->id ?? 1,
                    'quantity' => (float) $item['quantity'],
                    'comment' => $item['comment'] ?? '',
                    'status' => 'A',
                    'branch_id' => $branchId,
                ]);

                // Actualizar stock (sumar para entrada)
                $newStock = ($productBranch->stock ?? 0) + $item['quantity'];
                $productBranch->update([
                    'stock' => $newStock
                ]);
            }

            app(KardexSyncService::class)->syncMovement($movement);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Entrada de productos guardada correctamente',
                'movement_id' => $movement->id,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar la entrada: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($warehouseMovement)
    {
        $warehouseMovement = WarehouseMovement::with([
            'movement.movementType',
            'movement.documentType',
            'movement.branch',
            'branch',
            'details.unit',
            'details.product',
        ])->findOrFail($warehouseMovement->id ?? $warehouseMovement);
        return view('warehouse_movements.show', [
            'warehouseMovement' => $warehouseMovement,
            'title' => 'Ver Movimiento de Almacén',
        ]);
    }
    public function edit($warehouseMovement)
    {
        $branchId = session('branch_id');
        $warehouseMovement = WarehouseMovement::with([
            'movement.movementType',
            'movement.documentType',
            'movement.branch',
            'branch',
            'details.unit',
            'details.product',
        ])->findOrFail($warehouseMovement->id ?? $warehouseMovement);
        return view('warehouse_movements.edit', [
            'warehouseMovement' => $warehouseMovement,
            'branchId' => $branchId,
            'title' => 'Editar Movimiento de Almacén',
        ]);
    }
    public function update(Request $request, $warehouseMovement)
    {
        $request->validate([
            'status' => 'required|string|in:A,C',
        ]);
        $wm = WarehouseMovement::with(['movement', 'branch'])->findOrFail($warehouseMovement->id ?? $warehouseMovement);
        $wm->update(['status' => $request->input('status')]);
        if ($wm->movement) {
            app(KardexSyncService::class)->syncMovement($wm->movement);
        }
        return redirect()->route('warehouse_movements.show', ['warehouseMovement' => $wm->id])->with('success', 'Movimiento de Almacén actualizado correctamente');
    }

    public function destroy($warehouseMovement)
    {
        $wm = WarehouseMovement::with(['movement.documentType', 'details'])->findOrFail($warehouseMovement->id ?? $warehouseMovement);
        try {
            DB::beginTransaction();

            // Restablecer stock: revertir el efecto del movimiento antes de borrar
            $branchId = $wm->branch_id;
            $docTypeId = $wm->movement?->document_type_id;
            $docName = $wm->movement?->documentType?->name ?? '';
            $isEntrada = $docTypeId == 7 || stripos($docName, 'Entrada') !== false || stripos($docName, 'entry') !== false;
            $isSalida = $docTypeId == 8 || stripos($docName, 'Salida') !== false || stripos($docName, 'exit') !== false || stripos($docName, 'output') !== false;

            if ($branchId && ($isEntrada || $isSalida)) {
                foreach ($wm->details as $detail) {
                    $productBranch = ProductBranch::where('product_id', $detail->product_id)
                        ->where('branch_id', $branchId)
                        ->lockForUpdate()
                        ->first();
                    if (!$productBranch) {
                        continue;
                    }
                    $qty = (float) $detail->quantity;
                    $current = (float) ($productBranch->stock ?? 0);
                    if ($isEntrada) {
                        $newStock = max(0, $current - $qty);
                    } else {
                        $newStock = $current + $qty;
                    }
                    $productBranch->update(['stock' => $newStock]);
                }
            }

            if ($wm->movement_id) {
                app(KardexSyncService::class)->deleteMovement((int) $wm->movement_id);
            }

            $wm->details()->delete();
            $wm->movement?->delete();
            $wm->delete();
            DB::commit();
            $params = request()->has('view_id') ? ['view_id' => request()->input('view_id')] : [];
            return redirect()->route('warehouse_movements.index', $params)->with('success', 'Movimiento de almacén eliminado correctamente. Se restableció el stock.');
        } catch (\Exception $e) {
            DB::rollBack();
            $params = request()->has('view_id') ? ['view_id' => request()->input('view_id')] : [];
            return redirect()->route('warehouse_movements.index', $params)->with('error', 'No se pudo eliminar el movimiento: ' . $e->getMessage());
        }
    }
}
