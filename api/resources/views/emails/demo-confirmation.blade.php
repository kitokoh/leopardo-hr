@php
$translations = [
    'fr' => [
        'subject' => 'Votre démo Leopardo RH est confirmée',
        'greeting' => 'Bonjour :name,',
        'body' => 'Merci d\'avoir demandé une démonstration de Leopardo RH ! Notre équipe vous contactera sous 24h pour planifier votre session personnalisée.',
        'what_title' => 'Ce que nous couvrirons :',
        'what_1' => 'Présentation complète de la plateforme',
        'what_2' => 'Configuration adaptée à votre entreprise',
        'what_3' => 'Questions & réponses avec un expert RH',
        'what_4' => 'Plan d\'implémentation personnalisé',
        'details_title' => 'Vos informations',
        'company_label' => 'Entreprise',
        'employees_label' => 'Taille',
        'date_label' => 'Date souhaitée',
        'button' => 'Préparer ma démo',
        'thanks' => 'À très bientôt !',
        'team' => 'L\'équipe Leopardo RH',
    ],
    'ar' => [
        'subject' => 'تم تأكيد عرض ليوباردو HR التجريبي',
        'greeting' => 'مرحبا :name،',
        'body' => 'شكرا لطلب عرض توضيحي لـ ليوباردو HR! سيتواصل معك فريقنا خلال 24 ساعة لتحديد موعد جلستك.',
        'what_title' => 'ما سنغطيه:',
        'what_1' => 'عرض كامل للمنصة',
        'what_2' => 'إعداد مخصص لشركتك',
        'what_3' => 'أسئلة وأجوبة مع خبير',
        'what_4' => 'خطة تنفيذ مخصصة',
        'details_title' => 'معلوماتك',
        'company_label' => 'الشركة',
        'employees_label' => 'الحجم',
        'date_label' => 'التاريخ المطلوب',
        'button' => 'تحضير العرض التوضيحي',
        'thanks' => 'إلى اللقاء!',
        'team' => 'فريق ليوباردو HR',
    ],
    'en' => [
        'subject' => 'Your Leopardo RH Demo is Confirmed',
        'greeting' => 'Hello :name,',
        'body' => 'Thank you for requesting a Leopardo RH demo! Our team will contact you within 24 hours to schedule your personalized session.',
        'what_title' => 'What we\'ll cover:',
        'what_1' => 'Full platform walkthrough',
        'what_2' => 'Configuration tailored to your company',
        'what_3' => 'Q&A with an HR expert',
        'what_4' => 'Personalized implementation plan',
        'details_title' => 'Your details',
        'company_label' => 'Company',
        'employees_label' => 'Size',
        'date_label' => 'Preferred date',
        'button' => 'Prepare my demo',
        'thanks' => 'See you soon!',
        'team' => 'The Leopardo RH Team',
    ],
];
$t = $translations[$locale ?? 'fr'];
$dir = ($locale ?? 'fr') === 'ar' ? 'rtl' : 'ltr';
@endphp
<!DOCTYPE html>
<html lang="{{ $locale ?? 'fr' }}" dir="{{ $dir }}">
<head><meta charset="UTF-8"><title>{{ $t['subject'] }}</title></head>
<body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; direction: {{ $dir }};">
  <div style="background: linear-gradient(135deg, #3b82f6, #2563eb); padding: 32px 24px; border-radius: 12px 12px 0 0; text-align: center;">
    <h1 style="color: #ffffff; margin: 0; font-size: 24px;">🎯 Leopardo RH</h1>
    <p style="color: #bfdbfe; margin: 8px 0 0; font-size: 14px;">{{ $t['subject'] }}</p>
  </div>

  <div style="background: #ffffff; padding: 32px 24px; border: 1px solid #e5e7eb; border-top: none;">
    <p style="font-size: 16px; color: #111827; margin: 0 0 16px;">{{ str_replace(':name', $name ?? '', $t['greeting']) }}</p>
    <p style="font-size: 15px; color: #374151; line-height: 1.6; margin: 0 0 24px;">{{ $t['body'] }}</p>

    @if(!empty($company) || !empty($employees) || !empty($preferredDate))
    <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 20px; margin: 0 0 24px;">
      <p style="font-size: 14px; font-weight: 700; color: #1e40af; margin: 0 0 12px;">{{ $t['details_title'] }}</p>
      <table style="width: 100%; font-size: 14px; color: #374151;">
        @if(!empty($company))
        <tr><td style="padding: 4px 0; font-weight: 600;">{{ $t['company_label'] }}</td><td style="padding: 4px 0;">{{ $company }}</td></tr>
        @endif
        @if(!empty($employees))
        <tr><td style="padding: 4px 0; font-weight: 600;">{{ $t['employees_label'] }}</td><td style="padding: 4px 0;">{{ $employees }}</td></tr>
        @endif
        @if(!empty($preferredDate))
        <tr><td style="padding: 4px 0; font-weight: 600;">{{ $t['date_label'] }}</td><td style="padding: 4px 0;">{{ $preferredDate }}</td></tr>
        @endif
      </table>
    </div>
    @endif

    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 20px; margin: 0 0 24px;">
      <p style="font-size: 14px; font-weight: 700; color: #166534; margin: 0 0 12px;">{{ $t['what_title'] }}</p>
      <ul style="margin: 0; padding: 0 0 0 20px; color: #374151; font-size: 14px; line-height: 1.8;">
        <li>{{ $t['what_1'] }}</li>
        <li>{{ $t['what_2'] }}</li>
        <li>{{ $t['what_3'] }}</li>
        <li>{{ $t['what_4'] }}</li>
      </ul>
    </div>

    <div style="text-align: center; margin: 24px 0;">
      <a href="{{ $appUrl ?? 'https://leopardo.com' }}/demo" style="display: inline-block; padding: 14px 32px; background: #3b82f6; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 700; font-size: 15px;">{{ $t['button'] }}</a>
    </div>

    <p style="font-size: 15px; color: #374151; margin: 24px 0 4px;">{{ $t['thanks'] }}</p>
    <p style="font-size: 14px; color: #6b7280; margin: 0;">{{ $t['team'] }}</p>
  </div>

  <div style="background: #f9fafb; padding: 16px 24px; border-radius: 0 0 12px 12px; border: 1px solid #e5e7eb; border-top: none; text-align: center;">
    <p style="font-size: 11px; color: #d1d5db; margin: 0;">&copy; {{ date('Y') }} Leopardo RH. Tous droits réservés.</p>
  </div>
</body>
</html>
