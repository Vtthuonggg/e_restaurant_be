<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\CreateSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Models\User;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 20);
        $page = (int) $request->query('page', 1);
        $suppliers = Supplier::paginate($perPage);
        $query = Supplier::where('user_id', User::getEffectiveUserId());
        if ($request->filled('name')) {
            $name = $request->query('name');
            $query->where('name', 'like', '%' . $name . '%');
        }
        $total = $query->count();
        $suppliers = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Thao tác thành công!',
            'data' => $suppliers->items(),
            'meta' => [
                'total' => $total,
                'size' => $suppliers->count(),
                'current_page' => $page,
                'last_page' => $suppliers->lastPage()
            ]
        ]);
    }

    public function store(CreateSupplierRequest $request)
    {
        $validated = $request->validated();
        $validated['user_id'] = User::getEffectiveUserId();
        $supplier = Supplier::create($validated);
        return response()->json(['status' => 'success', 'data' => $supplier], 201);
    }

    public function show($id)
    {
        $supplier = Supplier::find($id);
        if (!$supplier) {
            return response()->json(['status' => 'error', 'message' => 'Supplier not found'], 404);
        }
        return response()->json(['status' => 'success', 'data' => $supplier]);
    }

    public function update(UpdateSupplierRequest $request, $id)
    {
        $supplier = Supplier::find($id);
        if (!$supplier) {
            return response()->json(['status' => 'error', 'message' => 'Supplier not found'], 404);
        }



        $supplier->update($request->validated());
        return response()->json(['status' => 'success', 'data' => $supplier]);
    }

    public function destroy($id)
    {
        $supplier = Supplier::where('user_id', User::getEffectiveUserId())->find($id);
        if (!$supplier) {
            return response()->json(['status' => 'error', 'message' => 'Supplier not found'], 404);
        }

        $supplier->delete();
        return response()->json(['status' => 'success', 'message' => 'Supplier deleted']);
    }
}
