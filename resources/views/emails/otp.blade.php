<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mã OTP</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .container {
            background-color: #f9f9f9;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #ff5722;
        }

        .otp-box {
            background-color: #fff;
            border: 2px dashed #ff5722;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
        }

        .otp-code {
            font-size: 36px;
            font-weight: bold;
            color: #ff5722;
            letter-spacing: 8px;
            margin: 10px 0;
        }

        .warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 12px;
            margin: 20px 0;
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <div class="logo">🍽️ E-Restaurant</div>
            <h2>Xác thực tài khoản</h2>
        </div>

        <p>Xin chào,</p>
        <p>Bạn đã yêu cầu mã OTP để đăng ký tài khoản E-Restaurant. Dưới đây là mã xác thực của bạn:</p>

        <div class="otp-box">
            <p style="margin: 0; color: #666;">Mã OTP của bạn</p>
            <div class="otp-code">{{ $otp }}</div>
            <p style="margin: 0; font-size: 14px; color: #999;">
                Mã có hiệu lực trong {{ $expiryMinutes }} phút
            </p>
        </div>

        <div class="warning">
            <strong>⚠️ Lưu ý bảo mật:</strong>
            <ul style="margin: 10px 0;">
                <li>Không chia sẻ mã này với bất kỳ ai</li>
                <li>E-Restaurant sẽ không bao giờ yêu cầu mã OTP qua điện thoại</li>
                <li>Nếu bạn không thực hiện yêu cầu này, vui lòng bỏ qua email</li>
            </ul>
        </div>

        <p>Nếu bạn cần hỗ trợ, vui lòng liên hệ với chúng tôi.</p>

        <div class="footer">
            <p>Email này được gửi tự động, vui lòng không trả lời.</p>
            <p>&copy; {{ date('Y') }} E-Restaurant. All rights reserved.</p>
        </div>
    </div>
</body>

</html>