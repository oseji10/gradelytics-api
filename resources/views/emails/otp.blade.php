<!DOCTYPE html>
<html>
<head>
    <title>Your gradelytics Verification Code</title>
</head>
<body>
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <img
                    src="https://app.gradelytics.app/images/logo/logo.png"
                    alt="gradelytics"
                    width="140"
                >

        <h2 style="color: #1F6F43;">gradelytics - Email Verification</h2>

        <p>Hello,</p>

        <p>Thank you for signing up with gradelytics! Use the verification code below to complete your registration:</p>

        <div style="background-color: #f3f4f6; padding: 20px; text-align: center; margin: 20px 0; border-radius: 8px;">
            <h1 style="color: #2563eb; font-size: 32px; letter-spacing: 8px; margin: 0;">{{ $otp }}</h1>
        </div>

        <p><strong>This code will expire in {{ $expires_in }}.</strong></p>

        <p>If you didn't request this code, please ignore this email.</p>

        <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 20px 0;">

        <p style="color: #6b7280; font-size: 14px;">
            Best regards,<br>
            The gradelytics Team
        </p>
    </div>
      <tr>
            <td class="footer">
                <p>
                    <a href="https://gradelytics.app">gradelytics.app</a> ·
                    <a href="mailto:info@gradelytics.app">info@gradelytics.app</a>
                </p>
                <p>© {{ date('Y') }} gradelytics Ltd.</p>
            </td>
        </tr>
</body>
</html>
