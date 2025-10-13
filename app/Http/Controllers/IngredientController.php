<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IngredientController extends Controller
{
    public function index(Request $request)
    {

        $perPage = (int) $request->query('per_page', 20);
        $page = (int) $request->query('page', 1);
        $ingredients = Ingredient::paginate($perPage);
        $query = Ingredient::where('user_id', Auth::id());

        $total = $query->count();
        $ingredients = $query->paginate($perPage, ['*'], 'page', $page);
        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Thao tác thành công!',
            'data' => $ingredients->items(),
            'meta' => [
                'total' => $total,
                'size' => $ingredients->count(),
                'current_page' => $page,
                'last_page' => $ingredients->lastPage()
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'base_cost' => 'nullable|integer',
            'retail_cost' => 'nullable|integer',
            'in_stock' => 'nullable|numeric',
            'image' => 'nullable|string|max:255',
            'unit' => 'nullable|string|max:50',
        ]);

        $validated['base_cost'] = $validated['base_cost'] ?? 0;
        $validated['retail_cost'] = $validated['retail_cost'] ?? 0;
        $validated['in_stock'] = $validated['in_stock'] ?? 0;
        $validated['user_id'] = Auth::id();
        $ingredient = Ingredient::create($validated);
        return response()->json(['status' => 'success', 'data' => $ingredient], 201);
    }

    public function show($id)
    {
        $ingredient = Ingredient::where('user_id', Auth::id())->find($id);
        if (!$ingredient) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy nguyên liệu'], 404);
        }
        return response()->json(['status' => 'success', 'data' => $ingredient]);
    }

    public function update(Request $request, $id)
    {
        $ingredient = Ingredient::where('user_id', Auth::id())->find($id);
        if (!$ingredient) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy nguyên liệu'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'base_cost' => 'nullable|integer',
            'retail_cost' => 'nullable|integer',
            'in_stock' => 'nullable|numeric',
            'image' => 'nullable|string|max:255',
            'unit' => 'nullable|string|max:50',
        ]);

        if (array_key_exists('base_cost', $validated)) {
            $validated['base_cost'] = $validated['base_cost'] ?? 0;
        }
        if (array_key_exists('retail_cost', $validated)) {
            $validated['retail_cost'] = $validated['retail_cost'] ?? 0;
        }
        if (array_key_exists('in_stock', $validated)) {
            $validated['in_stock'] = $validated['in_stock'] ?? 0;
        }

        $ingredient->update($validated);
        return response()->json(['status' => 'success', 'data' => $ingredient]);
    }

    public function destroy($id)
    {
        $ingredient = Ingredient::where('user_id', Auth::id())->find($id);
        if (!$ingredient) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy nguyên liệu'], 404);
        }
        $ingredient->delete();
        return response()->json(['status' => 'success', 'message' => 'Xóa nguyên liệu thành công']);
    }
}
