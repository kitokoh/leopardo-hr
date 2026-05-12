@php
$translations = [
    'fr' => [
        'subject' => 'Votre période d\'essai expire bientôt',
        'greeting' => 'Bonjour :name,',
        'body' => 'Votre période d\'essai Leopardo RH expire dans :days jours. Pour continuer à utiliser toutes les fonctionnalités, passez à un abonnement payant.',
        'button' => 'Choisir un plan',
        'thanks' => 'L\'équipe Leopardo RH',
    ],
    'ar' => [
        'subject' => 'فترة التجربة الخاصة بك تنتهي قريبا',
        'greeting' => 'مرحبا :name،',
        'body' => 'فترة التجربة الخاصة بك في ليوباردو HR تنتهي خلال :days أيام. لمواصلة استخدام جميع الميزات، قم بالترقية إلى اشتراك مدفوع.',
        'button' => 'اختيار خطة',
        'thanks' => 'فريق ليوباردو HR',
    ],
    'en' => [
        'subject' => 'Your trial period is expiring soon',
        'greeting' => 'Hello :name,',
        'body' => 'Your Leopardo RH trial expires in :days days. To continue using all features, upgrade to a paid plan.',
        'button' => 'Choose a plan',
        'thanks' => 'The Leopardo RH team',
    ],
];
$t = $translations[$locale ?? 'fr'];
@endphp
<!DOCTYPE html>
<html lang="{{ $locale ?? 'fr' }}">
<head><meta charset="UTF-8"><title>{{ $t['subject'] }}</title></head>
<body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: #f59e0b; color: white; padding: 20px; border-radius: 8px 8px 0 0; text-align: center;">
        <h1 style="margin: 0; font-size: 24px;">⏰ Leopardo RH</h1>
    </div>
    <div style="background: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; border-radius: 0 0 8px 8px;">
        <p style="font-size: 16px;">{{ str_replace(':name', $userName ?? '', $t['greeting']) }}</p>
        <p>{{ str_replace(':days', $daysLeft ?? '3', $t['body']) }}</p>
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $pricingUrl ?? '#' }}" style="background: #f59e0b; color: white; padding: 12px 30px; border-radius: 6px; text-decoration: none; font-weight: bold;">{{ $t['button'] }}</a>
        </div>
        <p style="color: #6b7280;">{{ $t['thanks'] }}</p>
    </div>
</body>
</html>
