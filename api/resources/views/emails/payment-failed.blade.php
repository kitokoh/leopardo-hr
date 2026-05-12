@php
$translations = [
    'fr' => [
        'subject' => 'Échec de paiement',
        'greeting' => 'Bonjour :name,',
        'body' => 'Le paiement de votre abonnement Leopardo RH a échoué. Veuillez mettre à jour votre moyen de paiement pour éviter toute interruption de service.',
        'button' => 'Mettre à jour le paiement',
        'thanks' => 'L\'équipe Leopardo RH',
    ],
    'ar' => [
        'subject' => 'فشل الدفع',
        'greeting' => 'مرحبا :name،',
        'body' => 'فشل دفع اشتراك ليوباردو HR الخاص بك. يرجى تحديث وسيلة الدفع لتجنب أي انقطاع في الخدمة.',
        'button' => 'تحديث الدفع',
        'thanks' => 'فريق ليوباردو HR',
    ],
    'en' => [
        'subject' => 'Payment Failed',
        'greeting' => 'Hello :name,',
        'body' => 'Your Leopardo RH subscription payment has failed. Please update your payment method to avoid service interruption.',
        'button' => 'Update payment',
        'thanks' => 'The Leopardo RH team',
    ],
];
$t = $translations[$locale ?? 'fr'];
@endphp
<!DOCTYPE html>
<html lang="{{ $locale ?? 'fr' }}">
<head><meta charset="UTF-8"><title>{{ $t['subject'] }}</title></head>
<body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: #ef4444; color: white; padding: 20px; border-radius: 8px 8px 0 0; text-align: center;">
        <h1 style="margin: 0; font-size: 24px;">⚠️ Leopardo RH</h1>
    </div>
    <div style="background: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; border-radius: 0 0 8px 8px;">
        <p style="font-size: 16px;">{{ str_replace(':name', $userName ?? '', $t['greeting']) }}</p>
        <p>{{ $t['body'] }}</p>
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $billingUrl ?? '#' }}" style="background: #ef4444; color: white; padding: 12px 30px; border-radius: 6px; text-decoration: none; font-weight: bold;">{{ $t['button'] }}</a>
        </div>
        <p style="color: #6b7280;">{{ $t['thanks'] }}</p>
    </div>
</body>
</html>
