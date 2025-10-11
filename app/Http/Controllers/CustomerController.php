<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Termwind\Components\Raw;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
       $perPage = (int) $request->query('per_page', 20);
        $page = (int) $request->query('page', 1);
        $customers = Customer::paginate($perPage);
        $query = Customer::query();
        $total = $query->count();
        $customers = $query->paginate($perPage, ['*'], 'page', $page);
        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Thao tác thành công!',
            'data' => $customers->items(),
            'meta' => [
                'total' => $total,
                'size' => $customers->count(),
                'current_page' => $page,
                'last_page' => $customers->lastPage()
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20|unique:customers,phone',
            'address' => 'nullable|string|max:255',
        ]);

        $validated['user_id'] = Auth::id();
        $customer = Customer::create($validated);
        return response()->json(['status' => 'success', 'data' => $customer], 201);
    }

    public function show($id)
    {
        $customer = Customer::find($id);
        if (!$customer) {
            return response()->json(['message' => 'Not found'], 404);
        }
        return response()->json($customer);
    }

    public function update(Request $request, $id)
    {
        $customer = Customer::find($id);
        if (!$customer) {
            return response()->json(['message' => 'Not found'], 404);
        }
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20|unique:customers,phone,' . $id,
            'address' => 'nullable|string|max:255',
        ]);
        $customer->update($validated);
        return response()->json($customer);
    }

    public function destroy($id)
    {
        $customer = Customer::find($id);
        if (!$customer) {
            return response()->json(['message' => 'Not found'], 404);
        }
        $customer->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
