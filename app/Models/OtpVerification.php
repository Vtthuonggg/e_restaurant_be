<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class OtpVerification extends Model
{
    protected $fillable = [
        'email',
        'otp',
        'expires_at',
        'is_verified',
        'attempts'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_verified' => 'boolean'
    ];

    /**
     * Tạo OTP mới
     */
    public static function createOtp($email)
    {
        // Xóa OTP cũ chưa verify
        self::where('email', $email)
            ->where('is_verified', false)
            ->delete();

        // Tạo mã OTP 6 số
        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

        return self::create([
            'email' => $email,
            'otp' => $otp,
            'expires_at' => Carbon::now()->addMinutes(2), // Hết hạn sau 2 phút
            'attempts' => 0
        ]);
    }

    /**
     * Kiểm tra OTP còn hợp lệ
     */
    public function isValid()
    {
        return !$this->is_verified
            && $this->expires_at->isFuture()
            && $this->attempts < 3; // Tối đa 3 lần thử
    }

    /**
     * Verify OTP
     */
    public function verify($inputOtp)
    {
        if (!$this->isValid()) {
            return false;
        }

        $this->increment('attempts');

        if ($this->otp === $inputOtp) {
            $this->update(['is_verified' => true]);
            return true;
        }

        return false;
    }
}
