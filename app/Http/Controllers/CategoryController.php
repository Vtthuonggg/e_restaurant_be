<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Category;
use Illuminate\Support\Facades\Auth;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 20);
        $page = (int) $request->query('page', 1);
        $categories = Category::paginate($perPage);
        $query = Category::where('user_id', Auth::id());
        if ($request->filled('name')) {
            $name = $request->query('name');
            $query->where('name', 'like', '%' . $name . '%');
        }
        $total = $query->count();
        $categories = $query->paginate($perPage, ['*'], 'page', $page);
        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Thao tác thành công!',
            'data' => $categories->items(),
            'meta' => [
                'total' => $total,
                'size' => $categories->count(),
                'current_page' => $page,
                'last_page' => $categories->lastPage()
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);
        $validated['user_id'] = Auth::id();
        $category = Category::create($validated);
        return response()->json(['status' => 'success', 'data' => $category], 201);
    }

    public function show($id)
    {
        $category = Category::find($id);
        if (!$category) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy danh mục'], 404);
        }
        return response()->json(['status' => 'success', 'data' => $category]);
    }

    public function update(Request $request, $id)
    {
        $category = Category::find($id);
        if (!$category) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy danh mục'], 404);
        }

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        $category->update($request->only('name', 'description'));
        return response()->json(['status' => 'success', 'data' => $category]);
    }

    public function destroy($id)
    {
        $category = Category::find($id);
        if (!$category) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy danh mục'], 404);
        }

        $category->delete();
        return response()->json(['status' => 'success', 'message' => 'Danh mục đã được xóa']);
    }
}
