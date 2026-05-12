@php
$translations = [
    'fr' => [
        'subject' => 'Réinitialisation de mot de passe',
        'greeting' => 'Bonjour :name,',
        'body' => 'Vous avez demandé la réinitialisation de votre mot de passe. Cliquez sur le bouton ci-dessous. Ce lien expire dans 60 minutes.',
        'button' => 'Réinitialiser le mot de passe',
        'ignore' => 'Si vous n\'avez pas fait cette demande, ignorez cet email.',
    ],
    'ar' => [
        'subject' => 'إعادة تعيين كلمة المرور',
        'greeting' => 'مرحبا :name،',
        'body' => 'لقد طلبت إعادة تعيين كلمة المرور الخاصة بك. انقر على الزر أدناه. تنتهي صلاحية هذا الرابط خلال 60 دقيقة.',
        'button' => 'إعادة تعيين كلمة المرور',
        'ignore' => 'إذا لم تقم بهذا الطلب، تجاهل هذا البريد.',
    ],
    'en' => [
        'subject' => 'Password Reset',
        'greeting' => 'Hello :name,',
        'body' => 'You requested a password reset. Click the button below. This link expires in 60 minutes.',
        'button' => 'Reset password',
        'ignore' => 'If you did not make this request, ignore this email.',
    ],
];
$t = $translations[$locale ?? 'fr'];
@endphp
<!DOCTYPE html>
<html lang="{{ $locale ?? 'fr' }}">
<head><meta charset="UTF-8"><title>{{ $t['subject'] }}</title></head>
<body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: #2563eb; color: white; padding: 20px; border-radius: 8px 8px 0 0; text-align: center;">
        <h1 style="margin: 0; font-size: 24px;">🔒 Leopardo RH</h1>
    </div>
    <div style="background: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; border-radius: 0 0 8px 8px;">
        <p style="font-size: 16px;">{{ str_replace(':name', $userName ?? '', $t['greeting']) }}</p>
        <p>{{ $t['body'] }}</p>
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $resetUrl ?? '#' }}" style="background: #2563eb; color: white; padding: 12px 30px; border-radius: 6px; text-decoration: none; font-weight: bold;">{{ $t['button'] }}</a>
        </div>
        <p style="color: #6b7280; font-size: 13px;">{{ $t['ignore'] }}</p>
    </div>
</body>
</html>
