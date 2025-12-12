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
            'password' => Hash::make($data['password']),
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

    public function login(LoginRequest $request)
    {
        $data = $request->validated();

        if (!empty($data['is_employee'])) {
            $employee = User::where('phone', $data['phone'])
                ->where('user_type', 3)
                ->whereHas('employeeRelations')
                ->first();

            if (!$employee || !Hash::check($data['password'], $employee->password)) {
                return response()->json(['status' => 'error', 'message' => 'Số điện thoại hoặc mật khẩu không đúng'], 401);
            }

            $token = $employee->createToken('employee_token')->plainTextToken;

            return response()->json([
                'status' => 'success',
                'message' => 'Đăng nhập thành công',
                'data' => [
                    'user' => $employee,
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                    'role' => 'employee'
                ]
            ], 200);
        }

        $user = User::where('phone', $data['phone'])
            ->where('user_type', 2)
            ->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['status' => 'error', 'message' => 'Số điện thoại hoặc mật khẩu không đúng'], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Đăng nhập thành công',
            'data' => [
                'user' => $user,
                'access_token' => $token,
                'token_type' => 'Bearer',
                'role' => 'owner'
            ]
        ], 200);
    }
    public function updateProfile(UpdateProfileRequest $request)
    {
        $user = $request->user();
        $validated = $request->validated();
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
