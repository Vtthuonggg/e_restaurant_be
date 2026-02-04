<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\OtpVerification;
use App\Mail\OtpMail;
use App\Http\Requests\SendOtpRequest;
use App\Http\Requests\VerifyOtpRequest;
use App\Http\Requests\RegisterWithOtpRequest;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class OtpController extends Controller
{
    /**
     * Gửi OTP đến email
     */
    public function sendOtp(SendOtpRequest $request)
    {
        $email = $request->email;

        // Kiểm tra email đã tồn tại chưa
        $userExists = User::where('email', $email)->exists();
        if ($userExists) {
            return response()->json([
                'status' => 'error',
                'message' => 'Email đã được đăng ký'
            ], 422);
        }

        // Kiểm tra rate limit: không cho gửi lại trong vòng 1 phút
        $recentOtp = OtpVerification::where('email', $email)
            ->where('created_at', '>=', now()->subMinute())
            ->exists();

        if ($recentOtp) {
            return response()->json([
                'status' => 'error',
                'message' => 'Vui lòng đợi 1 phút trước khi gửi lại mã OTP'
            ], 429);
        }

        // Tạo OTP mới
        $otpRecord = OtpVerification::createOtp($email);

        // Gửi email
        try {
            Mail::to($email)->send(new OtpMail($otpRecord->otp, 2));

            return response()->json([
                'status' => 'success',
                'message' => 'Mã OTP đã được gửi đến email của bạn',
                'data' => [
                    'email' => $email,
                    'expires_in' => 120, // 2 phút = 120 giây
                    // Chỉ hiển thị OTP trong development
                    'otp' => config('app.env') === 'local' ? $otpRecord->otp : null
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể gửi email. Vui lòng thử lại sau.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Xác thực OTP
     */
    public function verifyOtp(VerifyOtpRequest $request)
    {
        $email = $request->email;
        $otp = $request->otp;

        // Tìm OTP gần nhất chưa verify
        $otpRecord = OtpVerification::where('email', $email)
            ->where('is_verified', false)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$otpRecord) {
            return response()->json([
                'status' => 'error',
                'message' => 'Mã OTP không tồn tại hoặc đã hết hạn'
            ], 404);
        }

        // Kiểm tra OTP có hợp lệ không
        if (!$otpRecord->isValid()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Mã OTP đã hết hạn hoặc vượt quá số lần thử'
            ], 422);
        }

        // Verify OTP
        $verified = $otpRecord->verify($otp);

        if (!$verified) {
            $remainingAttempts = 3 - $otpRecord->attempts;
            return response()->json([
                'status' => 'error',
                'message' => 'Mã OTP không chính xác',
                'data' => [
                    'remaining_attempts' => max(0, $remainingAttempts)
                ]
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Xác thực OTP thành công',
            'data' => [
                'email' => $email,
                'verified' => true
            ]
        ], 200);
    }

    /**
     * Đăng ký với OTP
     */
    public function registerWithOtp(RegisterWithOtpRequest $request)
    {
        $validated = $request->validated();

        // Verify OTP
        $otpRecord = OtpVerification::where('email', $validated['email'])
            ->where('is_verified', true)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->first();

        if (!$otpRecord) {
            return response()->json([
                'status' => 'error',
                'message' => 'OTP chưa được xác thực hoặc đã hết hạn'
            ], 400);
        }

        // Tạo user với phone + email
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],  // ← Đảm bảo có phone
            'password' => Hash::make($validated['password']),
            'user_type' => 2,
            'api_key' => bin2hex(random_bytes(32)),
        ]);

        // Xóa OTP đã dùng
        $otpRecord->delete();

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

    /**
     * Gửi lại OTP
     */
    public function resendOtp(SendOtpRequest $request)
    {
        // Sử dụng lại logic sendOtp
        return $this->sendOtp($request);
    }
}
