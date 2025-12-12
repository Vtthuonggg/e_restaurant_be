<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Models\Customer;
use Illuminate\Http\Request;
use App\Http\Requests\CreateCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use Illuminate\Support\Facades\Log;
use Termwind\Components\Raw;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 20);
        $page = (int) $request->query('page', 1);
        $customers = Customer::paginate($perPage);

        $query = Customer::where('user_id', Auth::id());
        if ($request->filled('name')) {
            $name = $request->query('name');
            $query->where('name', 'like', '%' . $name . '%');
        }
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

    public function store(CreateCustomerRequest $request)
    {
        $validated = $request->validated();
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

    public function update(UpdateCustomerRequest $request, $id)
    {
        $customer = Customer::find($id);
        if (!$customer) {
            return response()->json(['message' => 'Not found'], 404);
        }
        $validated = $request->validated();
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
