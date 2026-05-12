@php
$translations = [
    'fr' => [
        'subject' => 'Bienvenue sur Leopardo RH',
        'greeting' => 'Bonjour :name,',
        'body' => 'Votre compte a été créé avec succès. Vous pouvez maintenant vous connecter et commencer à utiliser la plateforme.',
        'button' => 'Se connecter',
        'thanks' => 'Merci de votre confiance.',
    ],
    'ar' => [
        'subject' => 'مرحبا بك في ليوباردو HR',
        'greeting' => 'مرحبا :name،',
        'body' => 'تم إنشاء حسابك بنجاح. يمكنك الآن تسجيل الدخول والبدء في استخدام المنصة.',
        'button' => 'تسجيل الدخول',
        'thanks' => 'شكرا لثقتكم.',
    ],
    'en' => [
        'subject' => 'Welcome to Leopardo RH',
        'greeting' => 'Hello :name,',
        'body' => 'Your account has been created successfully. You can now log in and start using the platform.',
        'button' => 'Log in',
        'thanks' => 'Thank you for your trust.',
    ],
];
$t = $translations[$locale ?? 'fr'];
@endphp
<!DOCTYPE html>
<html lang="{{ $locale ?? 'fr' }}">
<head><meta charset="UTF-8"><title>{{ $t['subject'] }}</title></head>
<body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: #2563eb; color: white; padding: 20px; border-radius: 8px 8px 0 0; text-align: center;">
        <h1 style="margin: 0; font-size: 24px;">🐆 Leopardo RH</h1>
    </div>
    <div style="background: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; border-radius: 0 0 8px 8px;">
        <p style="font-size: 16px;">{{ str_replace(':name', $userName ?? '', $t['greeting']) }}</p>
        <p>{{ $t['body'] }}</p>
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $loginUrl ?? '#' }}" style="background: #2563eb; color: white; padding: 12px 30px; border-radius: 6px; text-decoration: none; font-weight: bold;">{{ $t['button'] }}</a>
        </div>
        <p style="color: #6b7280;">{{ $t['thanks'] }}</p>
    </div>
</body>
</html>
