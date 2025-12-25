<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\EmployeeManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\CreateEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 20);
        $page = (int) $request->query('page', 1);

        $query = EmployeeManager::forUser(User::getEffectiveUserId())
            ->with(['employee']);

        if ($request->filled('name')) {
            $name = $request->query('name');
            $query->whereHas('employee', function ($q) use ($name) {
                $q->where('name', 'like', '%' . $name . '%');
            });
        }


        $total = $query->count();
        $employeeRelations = $query->paginate($perPage, ['*'], 'page', $page);

        $employees = $employeeRelations->getCollection()->map(function ($relation) {
            $employee = $relation->employee;
            $employee->role = $relation->role;
            return $employee;
        });

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Thao tác thành công!',
            'data' => $employees,
            'meta' => [
                'total' => $total,
                'size' => $employees->count(),
                'current_page' => $page,
                'last_page' => $employeeRelations->lastPage()
            ]
        ]);
    }

    public function store(CreateEmployeeRequest $request)
    {
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $email = $validated['phone'] . '@employee.local';

            $employeeData = [
                'name' => $validated['name'],
                'email' => $email,
                'phone' => $validated['phone'],
                'user_type' => 3
            ];

            // Chỉ thêm password nếu được cung cấp
            if (isset($validated['password']) && !empty($validated['password'])) {
                $employeeData['password'] = Hash::make($validated['password']);
            }

            $employee = User::create($employeeData);

            // Tạo quan hệ trong bảng employee_manager
            EmployeeManager::create([
                'user_id' => User::getEffectiveUserId(),
                'employee_id' => $employee->id,
            ]);

            DB::commit();

            return response()->json(['status' => 'success', 'data' => $employee], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Lỗi khi tạo nhân viên: ' . $e->getMessage()], 500);
        }
    }
    public function show($id)
    {
        $relation = EmployeeManager::forUser(User::getEffectiveUserId())
            ->with(['employee'])
            ->whereHas('employee', function ($q) use ($id) {
                $q->where('id', $id);
            })
            ->first();

        if (!$relation) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy nhân viên'], 404);
        }

        $employee = $relation->employee;
        $employee->role = $relation->role;

        return response()->json(['status' => 'success', 'data' => $employee]);
    }

    public function update(UpdateEmployeeRequest $request, $id)
    {
        $validated = $request->validated();
        $relation = EmployeeManager::forUser(User::getEffectiveUserId())
            ->whereHas('employee', function ($q) use ($id) {
                $q->where('id', $id);
            })
            ->first();

        if (!$relation) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy nhân viên'], 404);
        }

        DB::beginTransaction();
        try {
            // Cập nhật thông tin user
            $updateData = [];
            if (isset($validated['name'])) $updateData['name'] = $validated['name'];
            if (isset($validated['phone'])) $updateData['phone'] = $validated['phone'];
            if (isset($validated['password'])) $updateData['password'] = Hash::make($validated['password']);

            if (!empty($updateData)) {
                $relation->employee->update($updateData);
            }



            DB::commit();

            $employee = $relation->fresh('employee')->employee;
            $employee->role = $relation->role;

            return response()->json(['status' => 'success', 'data' => $employee]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Lỗi khi cập nhật nhân viên: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        $relation = EmployeeManager::forUser(User::getEffectiveUserId())
            ->whereHas('employee', function ($q) use ($id) {
                $q->where('id', $id);
            })
            ->first();

        if (!$relation) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy nhân viên'], 404);
        }

        DB::beginTransaction();
        try {
            // Xóa quan hệ
            $relation->delete();

            // Xóa user (employee)
            $relation->employee->delete();

            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Xóa nhân viên thành công']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Lỗi khi xóa nhân viên: ' . $e->getMessage()], 500);
        }
    }
}
