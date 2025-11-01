<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 20);
        $page = (int) $request->query('page', 1);

        $query = Employee::where('user_id', Auth::id());
        if ($request->filled('name')) {
            $name = $request->query('name');
            $query->where('name', 'like', '%' . $name . '%');
        }
        $total = $query->count();
        $employees = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Thao tác thành công!',
            'data' => $employees->items(),
            'meta' => [
                'total' => $total,
                'size' => $employees->count(),
                'current_page' => $page,
                'last_page' => $employees->lastPage()
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20|unique:employees,phone',
            'password' => 'nullable|string|min:6',
        ]);

        $validated['user_id'] = Auth::id();
        $employee = Employee::create($validated);

        return response()->json(['status' => 'success', 'data' => $employee], 201);
    }

    public function show($id)
    {
        $employee = Employee::where('user_id', Auth::id())->find($id);
        if (!$employee) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy nhân viên'], 404);
        }
        return response()->json(['status' => 'success', 'data' => $employee]);
    }

    public function update(Request $request, $id)
    {
        $employee = Employee::where('user_id', Auth::id())->find($id);
        if (!$employee) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy nhân viên'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|string|max:20|unique:employees,phone,' . $id,
            'password' => 'nullable|string|min:6',
        ]);

        $employee->update($validated);
        return response()->json(['status' => 'success', 'data' => $employee]);
    }

    public function destroy($id)
    {
        $employee = Employee::where('user_id', Auth::id())->find($id);
        if (!$employee) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy nhân viên'], 404);
        }

        $employee->delete();
        return response()->json(['status' => 'success', 'message' => 'Xóa nhân viên thành công']);
    }
}
