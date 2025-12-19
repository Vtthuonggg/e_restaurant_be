<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 20);
        $page = (int) $request->query('page', 1);
        $categories = Category::paginate($perPage);
        $query = Category::where('user_id', User::getEffectiveUserId());
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

    public function store(CreateCategoryRequest $request)
    {
        $validated = $request->validated();

        $validated['user_id'] = User::getEffectiveUserId();
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

    public function update(UpdateCategoryRequest $request, $id)
    {
        $category = Category::find($id);
        if (!$category) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy danh mục'], 404);
        }

        $category->update($request->validated());
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
