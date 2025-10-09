<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SupplierController extends Controller
{
    public function index()
    {
        $suppliers = Supplier::all();
        return response()->json(['status' => 'success', 'data' => $suppliers]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20|unique:suppliers,phone',
            'address' => 'nullable|string|max:500',
        ]);
        $validated['user_id'] = Auth::id();
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

    public function update(Request $request, $id)
    {
        $supplier = Supplier::find($id);
        if (!$supplier) {
            return response()->json(['status' => 'error', 'message' => 'Supplier not found'], 404);
        }

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|string|max:20|unique:suppliers,phone,' . $id,
            'address' => 'nullable|string|max:500',
        ]);

        $supplier->update($request->only('name', 'phone', 'address'));
        return response()->json(['status' => 'success', 'data' => $supplier]);
    }

    public function destroy($id)
    {
        $supplier = Supplier::find($id);
        if (!$supplier) {
            return response()->json(['status' => 'error', 'message' => 'Supplier not found'], 404);
        }

        $supplier->delete();
        return response()->json(['status' => 'success', 'message' => 'Supplier deleted']);
    }
}
