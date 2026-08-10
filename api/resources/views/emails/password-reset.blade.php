<!DOCTYPE html>
<html lang="{{ $locale ?? 'fr' }}">
<head><meta charset="UTF-8"><title>{{ __('emails.email_password_reset_subject') }}</title></head>
<body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: #2563eb; color: white; padding: 20px; border-radius: 8px 8px 0 0; text-align: center;">
        <h1 style="margin: 0; font-size: 24px;">🔒 Leopardo RH</h1>
    </div>
    <div style="background: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; border-radius: 0 0 8px 8px;">
        <p style="font-size: 16px;">{{ __('emails.email_password_reset_greeting', ['name' => $userName ?? '']) }}</p>
        <p>{{ __('emails.email_password_reset_body') }}</p>
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $resetUrl ?? '#' }}" style="background: #2563eb; color: white; padding: 12px 30px; border-radius: 6px; text-decoration: none; font-weight: bold;">{{ __('emails.email_password_reset_button') }}</a>
        </div>
        <p style="color: #6b7280; font-size: 13px;">{{ __('emails.email_password_reset_ignore') }}</p>
    </div>
</body>
</html>
