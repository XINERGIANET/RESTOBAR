<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\Category;
use App\Models\Unit;
use App\Models\Product;
use App\Models\ProductBranch;
use App\Models\Branch; 
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class RecipeBookController extends Controller
{
    public function index(Request $request)
    {
        $currentBranchId = session('branch_id');         
        $companyId = Branch::where('id', $currentBranchId)->value('company_id');
        $companyBranchIds = Branch::where('company_id', $companyId)->pluck('id');
        $productsForRecipe = Product::whereHas('branches', function ($query) use ($companyBranchIds) {
                $query->whereIn('branches.id', $companyBranchIds);
            })
            ->where('recipe', true) 
            ->whereNotIn('id', function($subquery) use ($companyId) {
                $subquery->select('product_id')
                            ->from('recipes')
                            ->where('company_id', $companyId)
                            ->whereNull('deleted_at');
            })
            ->orderBy('description')
            ->get();

        $ingredientsList = Product::whereHas('branches', function ($query) use ($companyBranchIds) {
                $query->whereIn('branches.id', $companyBranchIds);
            })
            ->with(['branches' => function($query) use ($currentBranchId) {
                $query->where('branches.id', $currentBranchId);
            }])
            ->where('type', 'INGREDENT') 
            ->orderBy('description')
            ->get()
            ->map(function ($product) {
                $branch = $product->branches->first();
                $product->current_price = ($branch && $branch->pivot) ? $branch->pivot->price : 0;
                
                return $product;
            });

        $query = Recipe::query()->where('company_id', $companyId);

        if ($request->search) {
            $search = '%' . $request->search . '%';
            $query->where(function($q) use ($search) {
                $q->whereHas('product', function($p) use ($search) {
                    $p->where('description', 'like', $search)
                    ->orWhere('code', 'like', $search);
                })
                ->orWhere('description', 'like', $search);
            });
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $recipes = $query->with(['product', 'unit'])->paginate(12);
        $categories = Category::whereExists(function ($sub) use ($currentBranchId) {
                $sub->select(DB::raw(1))
                    ->from('category_branch')
                    ->whereColumn('category_branch.category_id', 'categories.id')
                    ->where('category_branch.branch_id', $currentBranchId)
                    ->whereNull('category_branch.deleted_at');
            })
            ->orderBy('description')
            ->get();
        $units = Unit::all();
        $recipe = new Recipe();

        $totalRecipes = Recipe::where('company_id', $companyId)->count();
        $activeRecipes = Recipe::where('company_id', $companyId)->where('status', 'A')->count();
        $inactiveRecipes = Recipe::where('company_id', $companyId)->where('status', 'I')->count();

        $viewId = $request->input('view_id');

        return view('recipe_book.index', compact(
            'recipes', 
            'categories', 
            'totalRecipes', 
            'activeRecipes', 
            'inactiveRecipes', 
            'viewId', 
            'units', 
            'productsForRecipe', 
            'ingredientsList', 
            'recipe'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
                'product_id'         => 'required|exists:products,id',
                'yield_unit_id'      => 'required|exists:units,id',
                'yield_quantity'     => 'required|numeric|min:0.01',
                'status'             => 'required|in:A,I',
                'preparation_time'   => 'nullable|integer|min:0',
                'preparation_method' => 'nullable|string',
                'description'        => 'nullable|string',
                'notes'              => 'nullable|string',
                'ingredients'        => 'required|array|min:1',
                'ingredients.*.product_id' => 'required|exists:products,id',
                'ingredients.*.quantity'   => 'required|numeric|min:0.0001',
                'ingredients.*.unit_cost'  => 'required|numeric|min:0',
            ]);

        DB::beginTransaction();

        try {
            $branchId = session('branch_id');
            $branch = Branch::findOrFail($branchId);
            $companyId = $branch->company_id;
            $baseProduct = Product::findOrFail($validated['product_id']);
            $totalCost = collect($request->ingredients)->sum(function($item) {
                return (float)$item['quantity'] * (float)$item['unit_cost'];
            });

            $dataToInsert = [
                'product_id'         => $baseProduct->id,
                'code'               => $baseProduct->code,       
                'name'               => $baseProduct->description,
                'description'        => $validated['description'],
                'category_id'        => $baseProduct->category_id,
                'yield_unit_id'      => $validated['yield_unit_id'],
                'preparation_time'   => $validated['preparation_time'],
                'preparation_method' => $validated['preparation_method'],
                'yield_quantity'     => $validated['yield_quantity'],
                'cost_total'         => $totalCost,
                'status'             => $validated['status'],
                'image'              => $baseProduct->image,      
                'notes'              => $validated['notes'],
                'branch_id'          => $branchId,
                'company_id'         => $companyId,
            ];

            // Si existe una receta eliminada (soft-delete) con el mismo código,
            // se elimina físicamente para evitar conflicto con la restricción de unicidad.
            Recipe::withTrashed()
                ->where('code', $baseProduct->code)
                ->whereNotNull('deleted_at')
                ->forceDelete();

            $recipe = Recipe::create($dataToInsert);

            $this->saveIngredients($recipe->id, $validated['ingredients']);

            DB::commit();

            return redirect()->route('recipe-book.index')
                            ->with('success', 'Receta "' . $recipe->name . '" creada exitosamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Error al guardar: ' . $e->getMessage());
        }
    }

    private function saveIngredients($recipeId, $ingredients)
    {
        foreach ($ingredients as $index => $item) {
            $ingProduct = Product::find($item['product_id']);
            RecipeIngredient::create([
                'recipe_id' => $recipeId,
                'product_id' => $item['product_id'],
                'unit_id'    => $ingProduct->base_unit_id ?? null, 
                'quantity'   => $item['quantity'],
                'notes'      => $item['notes'] ?? null,
                'unit_cost'  => $item['unit_cost'],
                'order'      => $index,
            ]);
        }
    }

    public function edit(Request $request, Recipe $recipe)
    {
        $viewId = $request->input('view_id');
        $categories = Category::all();
        $units = Unit::all();
        $currentBranchId = session('branch_id');         
        $companyId = Branch::where('id', $currentBranchId)->value('company_id');
        $companyBranchIds = Branch::where('company_id', $companyId)->pluck('id');        
        $products = Product::where('recipe', true)
                            ->orderBy('description')
                            ->get();
        $productsForRecipe = Product::whereHas('branches', function ($query) use ($companyBranchIds) {
                $query->whereIn('branches.id', $companyBranchIds);
            })
            ->where('recipe', true)
            ->whereNotIn('id', function($subquery) use ($companyId, $recipe) {
                $subquery->select('product_id')
                            ->from('recipes')
                            ->where('company_id', $companyId)
                            ->where('id', '!=', $recipe->id)
                            ->whereNull('deleted_at'); 
            })
            ->orderBy('description')
            ->get();
        $branches = Branch::where('company_id', Auth::user()->company_id)->get();
        $currentBranchId = session('branch_id');
        $companyId = Branch::where('id', $currentBranchId)->value('company_id');
        $companyBranchIds = Branch::where('company_id', $companyId)->pluck('id');

        $ingredientsList = Product::whereHas('branches', function ($query) use ($companyBranchIds) {
                $query->whereIn('branches.id', $companyBranchIds);
            })
            ->with(['branches' => function($query) use ($currentBranchId) {
                $query->where('branches.id', $currentBranchId);
            }])
            ->where('type', 'INGREDENT')
            ->orderBy('description')
            ->get()
            ->map(function ($product) {
                $branch = $product->branches->first();
                $product->current_price = ($branch && $branch->pivot) ? $branch->pivot->price : 0;
                return $product;
            });

        $recipe->load('ingredients.product'); 

        return view('recipe_book.edit', compact(
            'recipe', 
            'categories', 
            'units', 
            'products', 
            'branches', 
            'ingredientsList',
            'productsForRecipe',
            'viewId'
        ));
    }

    public function update(Request $request, Recipe $recipe)
    {
        $validated = $request->validate([
            'yield_unit_id'      => 'required|exists:units,id',
            'yield_quantity'     => 'required|numeric|min:0.01',
            'status'             => 'required|in:A,I',
            'preparation_time'   => 'nullable|integer|min:0',
            'preparation_method' => 'nullable|string',
            'description'        => 'nullable|string',
            'notes'              => 'nullable|string',            
            'image'              => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', 
            
            // Ingredientes
            'ingredients'        => 'required|array|min:1',
            'ingredients.*.product_id' => 'required|exists:products,id',
            'ingredients.*.quantity'   => 'required|numeric|min:0.0001',
            'ingredients.*.unit_cost'  => 'required|numeric|min:0',
            'ingredients.*.notes'      => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $totalCost = collect($request->ingredients)->sum(function($item) {
                return (float)$item['quantity'] * (float)$item['unit_cost'];
            });

            if ($request->hasFile('image')) {
                if ($recipe->image) {
                    Storage::disk('public')->delete($recipe->image);
                }
                $recipe->image = $request->file('image')->store('recipes', 'public');
            }

            $recipe->update([
                'yield_unit_id'      => $validated['yield_unit_id'],
                'yield_quantity'     => $validated['yield_quantity'],
                'preparation_time'   => $validated['preparation_time'],
                'preparation_method' => $validated['preparation_method'],
                'description'        => $validated['description'],
                'status'             => $validated['status'],
                'notes'              => $validated['notes'],
                'cost_total'         => $totalCost, 
            ]);

            $recipe->ingredients()->delete(); // Borra los viejos
            
            $this->saveIngredients($recipe->id, $validated['ingredients']);

            DB::commit();

            return redirect()->route('recipe-book.index', ['view_id' => $request->input('view_id')])
                 ->with('success', 'Receta actualizada exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Error al actualizar: ' . $e->getMessage());
        }
    }

    public function destroy(Recipe $recipe)
    {
        try {
            if ($recipe->image && Storage::disk('public')->exists($recipe->image)) {
                Storage::disk('public')->delete($recipe->image);
            }
            // forceDelete para eliminar físicamente y evitar conflictos
            // de unicidad si se vuelve a crear la receta del mismo plato.
            $recipe->forceDelete();
            return redirect()->route('recipe-book.index')->with('success', 'Eliminado');
        } catch (\Exception $e) {
            return back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function show(Request $request, Recipe $recipe)
    {
        $viewId = $request->input('view_id');
        $categories = Category::all();
        $units = Unit::all();
        $currentBranchId = session('branch_id');         
        $companyId = Branch::where('id', $currentBranchId)->value('company_id');
        $companyBranchIds = Branch::where('company_id', $companyId)->pluck('id');        
        $products = Product::where('recipe', true)
                            ->orderBy('description')
                            ->get();
        $productsForRecipe = Product::whereHas('branches', function ($query) use ($companyBranchIds) {
                $query->whereIn('branches.id', $companyBranchIds);
            })
            ->where('recipe', true)
            ->whereNotIn('id', function($subquery) use ($companyId, $recipe) {
                $subquery->select('product_id')
                            ->from('recipes')
                            ->where('company_id', $companyId)
                            ->where('id', '!=', $recipe->id)
                            ->whereNull('deleted_at'); 
            })
            ->orderBy('description')
            ->get();
        $branches = Branch::where('company_id', Auth::user()->company_id)->get();
        $currentBranchId = session('branch_id');
        $companyId = Branch::where('id', $currentBranchId)->value('company_id');
        $companyBranchIds = Branch::where('company_id', $companyId)->pluck('id');

        $ingredientsList = Product::whereHas('branches', function ($query) use ($companyBranchIds) {
                $query->whereIn('branches.id', $companyBranchIds);
            })
            ->with(['branches' => function($query) use ($currentBranchId) {
                $query->where('branches.id', $currentBranchId);
            }])
            ->where('type', 'INGREDENT')
            ->orderBy('description')
            ->get()
            ->map(function ($product) {
                $branch = $product->branches->first();
                $product->current_price = ($branch && $branch->pivot) ? $branch->pivot->price : 0;
                return $product;
            });

        $recipe->load('ingredients.product'); 

        return view('recipe_book.show', compact(
            'recipe', 
            'categories', 
            'units', 
            'products', 
            'branches', 
            'ingredientsList',
            'productsForRecipe',
            'viewId'
        ));
    }
}