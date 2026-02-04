<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Services\CloudinaryService;

class   AuthController extends Controller
{
    protected $cloudinaryService;

    public function __construct(CloudinaryService $cloudinaryService)
    {
        $this->cloudinaryService = $cloudinaryService;
    }
    public function register(RegisterRequest $request)
    {
        $data = $request->validated();
        $user = User::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'password' => $data['password'],
            'user_type' => 2,  // Owner
            'store_name' => $data['store_name'] ?? null,
            'address' => $data['address'] ?? null,
            'api_key' => bin2hex(random_bytes(32)),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Đăng ký thành công',
            'data' => [
                'user' => $user,
                'access_token' => $token,
                'token_type' => 'Bearer',
            ]
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        $data = $request->validated();
        $phone = $data['phone'];
        $password = $data['password'];

        // Đăng nhập cho EMPLOYEE (user_type = 3)
        if (!empty($data['is_employee'])) {
            $employee = User::where('phone', $phone)
                ->where('user_type', 3)
                ->whereHas('employeeManagerRelation')
                ->first();

            if (!$employee) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Số điện thoại không tồn tại hoặc không phải tài khoản nhân viên'
                ], 401);
            }

            // Kiểm tra nếu nhân viên chưa có mật khẩu (chưa kích hoạt)
            if (empty($employee->password)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tài khoản chưa được kích hoạt. Vui lòng liên hệ quản lý.'
                ], 401);
            }

            if (!Hash::check($password, $employee->password)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Số điện thoại hoặc mật khẩu không đúng'
                ], 401);
            }

            $token = $employee->createToken('employee_token')->plainTextToken;

            // Lấy role từ bảng employee_manager
            $employeeRelation = $employee->employeeManagerRelation()->first();
            $role = $employeeRelation ? $employeeRelation->role : 'employee';

            return response()->json([
                'status' => 'success',
                'message' => 'Đăng nhập thành công',
                'data' => [
                    'user' => $employee,
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                    'user_type' => 3,
                    'role' => $role,
                ]
            ], 200);
        }

        // Đăng nhập cho OWNER (user_type = 2)
        $user = User::where('phone', $phone)
            ->where('user_type', 2)
            ->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Số điện thoại không tồn tại'
            ], 401);
        }

        if (!Hash::check($password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Số điện thoại hoặc mật khẩu không đúng'
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Đăng nhập thành công',
            'data' => [
                'user' => $user,
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user_type' => 2,
                'role' => 'owner',
            ]
        ], 200);
    }

    public function updateProfile(UpdateProfileRequest $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Người dùng không tồn tại'
                ], 401);
            }

            $data = $request->validated();

            // Cập nhật thông tin user (image là string URL từ frontend)
            $user->update($data);

            return response()->json([
                'status' => 'success',
                'message' => 'Cập nhật thông tin thành công',
                'data' => [
                    'user' => $user->fresh()
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi khi cập nhật thông tin: ' . $e->getMessage()
            ], 500);
        }
    }
}
