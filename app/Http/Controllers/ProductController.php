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

        $query = Product::where('user_id', Auth::id());

        // Filter name TRƯỚC khi paginate
        if ($request->filled('name')) {
            $name = $request->query('name');
            $query->where('name', 'like', '%' . $name . '%');
        }

        // Filter category
        if ($request->has('category_id')) {
            $categoryId = (int) $request->query('category_id');
            $query->where(function ($q) use ($categoryId) {
                $q->whereHas('categories', function ($q2) use ($categoryId) {
                    $q2->where('categories.id', $categoryId);
                })->orWhereJsonContains('category_ids', $categoryId);
            });
        }

        $total = $query->count();
        $products = $query->with('categories')->paginate($perPage, ['*'], 'page', $page);

        // Đảm bảo mỗi product có categories đầy đủ thông tin
        $items = collect($products->items())->map(function ($product) {
            if ($product->relationLoaded('categories') && $product->categories && $product->categories->isNotEmpty()) {
            } else {
                if (!empty($product->category_ids)) {
                    $ids = is_array($product->category_ids) ? $product->category_ids : json_decode($product->category_ids, true);
                    if (is_array($ids) && count($ids) > 0) {
                        $cats = \App\Models\Category::whereIn('id', $ids)
                            ->where('user_id', Auth::id())
                            ->get();
                        $product->setRelation('categories', $cats);
                    } else {
                        $product->setRelation('categories', collect());
                    }
                } else {
                    $product->setRelation('categories', collect());
                }
            }
            $ingredients = [];
            if (method_exists($product, 'getIngredientsWithDetails')) {
                $ingredients = $product->getIngredientsWithDetails();
            } else {
                $raw = $product->ingredients;
                if (!empty($raw)) {
                    $decoded = is_array($raw) ? $raw : json_decode($raw, true);
                    if (is_array($decoded)) {
                        foreach ($decoded as $it) {
                            $ingId = $it['id'] ?? $it['ingredient_id'] ?? null;
                            $ingModel = $ingId ? \App\Models\Ingredient::find($ingId) : null;
                            $ingredients[] = [
                                'id' => $ingModel ? $ingModel->id : $ingId,
                                'name' => $ingModel ? $ingModel->name : ($it['name'] ?? null),
                                'unit' => $ingModel ? $ingModel->unit : ($it['unit'] ?? null),
                                'image' => $ingModel ? ($ingModel->image ?? null) : ($it['image'] ?? null),
                                'quantity' => $it['quantity'] ?? ($it['qty'] ?? 0),
                            ];
                        }
                    }
                }
            }
            $product->setAttribute('ingredients', $ingredients);

            return $product;
        });

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Thao tác thành công!',
            'data' => $items->values()->all(),
            'meta' => [
                'total' => $total,
                'size' => $items->count(),
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
            'base_cost' => 'nullable|integer',
            'image' => 'nullable|string|max:255',
            'unit' => 'nullable|string|max:50',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer|exists:categories,id',
            'ingredients' => 'nullable|array',
            'ingredients.*.id' => 'required|integer|exists:ingredients,id',
            'ingredients.*.quantity' => 'required|numeric|min:0',
        ]);

        $validated['retail_cost'] = $validated['retail_cost'] ?? 0;
        $validated['base_cost'] = $validated['base_cost'] ?? 0; // Thêm dòng này
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
            'base_cost' => 'nullable|integer',
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
        if (array_key_exists('base_cost', $validated)) {
            $validated['base_cost'] = $validated['base_cost'] ?? 0;
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
