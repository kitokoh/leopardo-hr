@php
$translations = [
    'fr' => [
        'subject' => 'Bienvenue dans la newsletter Leopardo RH',
        'greeting' => 'Bonjour,',
        'body' => 'Merci de vous être inscrit à notre newsletter ! Vous recevrez régulièrement des conseils RH, des mises à jour produit et des études de cas de nos clients.',
        'features_title' => 'Ce que vous recevrez :',
        'feature_1' => 'Conseils et bonnes pratiques RH',
        'feature_2' => 'Nouveautés produit et fonctionnalités',
        'feature_3' => 'Études de cas clients',
        'feature_4' => 'Invitations à nos webinaires',
        'button' => 'Découvrir Leopardo RH',
        'unsubscribe' => 'Vous pouvez vous désabonner à tout moment en cliquant ici.',
        'thanks' => 'À bientôt !',
    ],
    'ar' => [
        'subject' => 'مرحبا بك في نشرة ليوباردو HR',
        'greeting' => 'مرحبا،',
        'body' => 'شكرا لاشتراككم في نشرتنا الإخبارية! ستتلقون بانتظام نصائح في الموارد البشرية وتحديثات المنتج ودراسات حالة.',
        'features_title' => 'ما ستتلقونه:',
        'feature_1' => 'نصائح وأفضل الممارسات في الموارد البشرية',
        'feature_2' => 'ميزات وتحديثات المنتج الجديدة',
        'feature_3' => 'دراسات حالة العملاء',
        'feature_4' => 'دعوات لندواتنا عبر الإنترنت',
        'button' => 'اكتشف ليوباردو HR',
        'unsubscribe' => 'يمكنك إلغاء الاشتراك في أي وقت بالنقر هنا.',
        'thanks' => 'إلى اللقاء!',
    ],
    'en' => [
        'subject' => 'Welcome to the Leopardo RH Newsletter',
        'greeting' => 'Hello,',
        'body' => 'Thank you for subscribing to our newsletter! You will regularly receive HR tips, product updates, and client case studies.',
        'features_title' => 'What you\'ll receive:',
        'feature_1' => 'HR tips and best practices',
        'feature_2' => 'New product features and updates',
        'feature_3' => 'Client case studies',
        'feature_4' => 'Webinar invitations',
        'button' => 'Discover Leopardo RH',
        'unsubscribe' => 'You can unsubscribe at any time by clicking here.',
        'thanks' => 'See you soon!',
    ],
];
$t = $translations[$locale ?? 'fr'];
$dir = ($locale ?? 'fr') === 'ar' ? 'rtl' : 'ltr';
@endphp
<!DOCTYPE html>
<html lang="{{ $locale ?? 'fr' }}" dir="{{ $dir }}">
<head><meta charset="UTF-8"><title>{{ $t['subject'] }}</title></head>
<body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; direction: {{ $dir }};">
  <div style="background: linear-gradient(135deg, #10b981, #059669); padding: 32px 24px; border-radius: 12px 12px 0 0; text-align: center;">
    <h1 style="color: #ffffff; margin: 0; font-size: 24px;">Leopardo RH</h1>
    <p style="color: #d1fae5; margin: 8px 0 0; font-size: 14px;">{{ $t['subject'] }}</p>
  </div>

  <div style="background: #ffffff; padding: 32px 24px; border: 1px solid #e5e7eb; border-top: none;">
    <p style="font-size: 16px; color: #111827; margin: 0 0 16px;">{{ $t['greeting'] }}</p>
    <p style="font-size: 15px; color: #374151; line-height: 1.6; margin: 0 0 24px;">{{ $t['body'] }}</p>

    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 20px; margin: 0 0 24px;">
      <p style="font-size: 14px; font-weight: 700; color: #166534; margin: 0 0 12px;">{{ $t['features_title'] }}</p>
      <ul style="margin: 0; padding: 0 0 0 20px; color: #374151; font-size: 14px; line-height: 1.8;">
        <li>{{ $t['feature_1'] }}</li>
        <li>{{ $t['feature_2'] }}</li>
        <li>{{ $t['feature_3'] }}</li>
        <li>{{ $t['feature_4'] }}</li>
      </ul>
    </div>

    <div style="text-align: center; margin: 24px 0;">
      <a href="{{ $appUrl ?? 'https://leopardo.com' }}" style="display: inline-block; padding: 14px 32px; background: #10b981; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 700; font-size: 15px;">{{ $t['button'] }}</a>
    </div>

    <p style="font-size: 15px; color: #374151; margin: 24px 0 0;">{{ $t['thanks'] }}</p>
  </div>

  <div style="background: #f9fafb; padding: 16px 24px; border-radius: 0 0 12px 12px; border: 1px solid #e5e7eb; border-top: none; text-align: center;">
    <p style="font-size: 12px; color: #9ca3af; margin: 0;">
      <a href="{{ $unsubscribeUrl ?? '#' }}" style="color: #6b7280; text-decoration: underline;">{{ $t['unsubscribe'] }}</a>
    </p>
    <p style="font-size: 11px; color: #d1d5db; margin: 8px 0 0;">&copy; {{ date('Y') }} Leopardo RH. Tous droits réservés.</p>
  </div>
</body>
</html>
