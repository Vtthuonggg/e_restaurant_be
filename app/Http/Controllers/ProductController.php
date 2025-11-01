<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Ingredient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\CloudinaryService;

class ProductController extends Controller
{

    protected $cloudinaryService;

    public function __construct(CloudinaryService $cloudinaryService)
    {
        $this->cloudinaryService = $cloudinaryService;
    }
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 20);
        $page = (int) $request->query('page', 1);

        $query = Product::where('user_id', Auth::id())->with('categories');
        if ($request->has('category_id')) {
            $categoryId = $request->query('category_id');
            $query->whereJsonContains('category_ids', (int)$categoryId);
        }

        $total = $query->count();
        $products = $query->paginate($perPage, ['*'], 'page', $page);
        if ($request->filled('name')) {
            $name = $request->query('name');
            $query->where('name', 'like', '%' . $name . '%');
        }
        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Thao tác thành công!',
            'data' => $products->items(),
            'meta' => [
                'total' => $total,
                'size' => $products->count(),
                'current_page' => $page,
                'last_page' => $products->lastPage()
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'retail_cost' => 'nullable|integer',
            'image' => 'nullable|string|max:255',
            'unit' => 'nullable|string|max:50',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer|exists:categories,id',
            'ingredients' => 'nullable|array',
            'ingredients.*.id' => 'required|integer|exists:ingredients,id',
            'ingredients.*.quantity' => 'required|numeric|min:0',
        ]);

        $validated['retail_cost'] = $validated['retail_cost'] ?? 0;
        $validated['user_id'] = Auth::id();

        $product = Product::create($validated);

        if ($request->has('category_ids')) {
            $userCategoryIds = \App\Models\Category::where('user_id', Auth::id())
                ->whereIn('id', $request->category_ids)
                ->pluck('id')
                ->toArray();
            $product->categories()->attach($userCategoryIds);
        }

        $product->load('categories');
        $product = Product::create($validated);

        return response()->json(['status' => 'success', 'data' => $product], 201);
    }

    public function show($id)
    {
        $product = Product::where('user_id', Auth::id())->find($id);
        if (!$product) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy sản phẩm'], 404);
        }

        $product->load('categories');
        $product->ingredients_details = $product->getIngredientsWithDetails();

        return response()->json(['status' => 'success', 'data' => $product]);
    }

    public function update(Request $request, $id)
    {
        $product = Product::where('user_id', Auth::id())->find($id);
        if (!$product) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy sản phẩm'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'retail_cost' => 'nullable|integer',
            'image' => 'nullable|string|max:255',
            'unit' => 'nullable|string|max:50',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer|exists:categories,id',
            'ingredients' => 'nullable|array',
            'ingredients.*.id' => 'required|integer|exists:ingredients,id',
            'ingredients.*.quantity' => 'required|numeric|min:0',
        ]);

        if (isset($validated['image']) && $validated['image'] !== $product->image && $product->image) {
            $this->cloudinaryService->deleteImageByUrl($product->image);
        }
        if (array_key_exists('retail_cost', $validated)) {
            $validated['retail_cost'] = $validated['retail_cost'] ?? 0;
        }
        if ($request->has('category_ids')) {
            $userCategoryIds = \App\Models\Category::where('user_id', Auth::id())
                ->whereIn('id', $request->category_ids)
                ->pluck('id')
                ->toArray();
            $product->categories()->sync($userCategoryIds);
        }

        $product->load('categories');

        $product->update($validated);
        return response()->json(['status' => 'success', 'data' => $product]);
    }

    public function destroy($id)
    {
        $product = Product::where('user_id', Auth::id())->find($id);
        if (!$product) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy sản phẩm'], 404);
        }
        if ($product->image) {
            $this->cloudinaryService->deleteImageByUrl($product->image);
        }
        $product->categories()->detach();
        $product->delete();
        return response()->json(['status' => 'success', 'message' => 'Xóa sản phẩm thành công']);
    }
}
