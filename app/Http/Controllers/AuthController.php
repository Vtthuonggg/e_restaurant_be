<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Services\CloudinaryService;

class   AuthController extends Controller
{
    protected $cloudinaryService;

    public function __construct(CloudinaryService $cloudinaryService)
    {
        $this->cloudinaryService = $cloudinaryService;
    }
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20|unique:users,phone',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation Error', 'data' => $validator->errors()], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'user_type' => 2,
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

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'password' => 'required|string',
            'is_employee' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation Error', 'data' => $validator->errors()], 422);
        }

        if ($request->is_employee) {
            // Tìm employee (user_type = 3) và kiểm tra có trong employee_manager
            $employee = User::where('phone', $request->phone)
                ->where('user_type', 3)
                ->whereHas('employeeRelations')
                ->first();

            if (!$employee || !Hash::check($request->password, $employee->password)) {
                return response()->json(['status' => 'error', 'message' => 'Số điện thoại hoặc mật khẩu không đúng'], 401);
            }

            $token = $employee->createToken('employee_token')->plainTextToken;

            return response()->json([
                'status' => 'success',
                'message' => 'Login successful',
                'data' => [
                    'user' => $employee,
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                    'role' => 'employee'
                ]
            ], 200);
        }

        // Login user thường (user_type = 2)
        $user = User::where('phone', $request->phone)
            ->where('user_type', 2)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['status' => 'error', 'message' => 'Số điện thoại hoặc mật khẩu không đúng'], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'data' => [
                'user' => $user,
                'access_token' => $token,
                'token_type' => 'Bearer',
                'role' => 'owner'
            ]
        ], 200);
    }
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'nullable|email|max:255',
            'image' => 'nullable|string|max:255',
            'store_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255'
        ]);
        if (isset($validated['image']) && $validated['image'] !== $user->image && $user->image) {
            $this->cloudinaryService->deleteImageByUrl($user->image);
        }

        $user->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật thông tin thành công',
            'data' => $user
        ]);
    }
}
