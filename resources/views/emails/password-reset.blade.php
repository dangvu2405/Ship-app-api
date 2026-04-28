<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xác thực OTP</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f7fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
    <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05);">
        <tr>
            <td style="padding: 40px 0 20px 0; text-align: center; background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);">
                <h1 style="color: #ffffff; margin: 0; font-size: 24px; letter-spacing: 1px;">XÁC THỰC TÀI KHOẢN</h1>
            </td>
        </tr>

        <tr>
            <td style="padding: 40px 30px;">
                <p style="font-size: 16px; color: #374151; margin-top: 0;">Xin chào <strong>Quý khách</strong>,</p>
                
                <p style="font-size: 15px; color: #4b5563; line-height: 1.6;">
                    Bạn đang thực hiện thao tác đăng nhập hoặc thay đổi mật khẩu. Vui lòng sử dụng mã xác thực (OTP) dưới đây để hoàn tất:
                </p>

                <div style="text-align: center; margin: 30px 0; padding: 20px; background-color: #f8fafc; border: 2px dashed #e2e8f0; border-radius: 8px;">
                    <span style="display: block; font-size: 13px; color: #64748b; text-transform: uppercase; margin-bottom: 10px; font-weight: 600;">Mã OTP của bạn là</span>
                    <span style="font-size: 36px; font-weight: 800; color: #1e40af; letter-spacing: 8px; font-family: monospace;">{{ $otp }}</span>
                </div>

                <p style="font-size: 14px; color: #ef4444; font-weight: 500; text-align: center;">
                    Mã này sẽ hết hạn trong vòng <strong>05 phút</strong>.
                </p>

                <hr style="border: 0; border-top: 1px solid #f1f5f9; margin: 30px 0;">

                <p style="font-size: 13px; color: #94a3b8; line-height: 1.5; margin-bottom: 0;">
                    Nếu bạn không yêu cầu mã này, vui lòng bỏ qua email này hoặc liên hệ với bộ phận hỗ trợ nếu bạn lo ngại về an toàn tài khoản. <br>
                    <strong>Vì lý do bảo mật, tuyệt đối không chia sẻ mã này cho bất kỳ ai.</strong>
                </p>
            </td>
        </tr>

        <tr>
            <td style="padding: 20px 30px; background-color: #f1f5f9; text-align: center; font-size: 12px; color: #64748b;">
                © 2026 Tên Công Ty Của Bạn. All rights reserved.<br>
                Địa chỉ: 123 Đường ABC, Quận 1, TP. Hồ Chí Minh
            </td>
        </tr>
    </table>
</body>
</html>