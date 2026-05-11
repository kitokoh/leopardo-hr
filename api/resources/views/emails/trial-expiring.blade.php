<!doctype html>
<html lang="{{ $locale }}" @if($locale === 'ar') dir="rtl" @endif>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 14px; color: #333; background: #f5f5f5; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; padding: 30px; }
        .logo { font-size: 20px; font-weight: bold; color: #2563eb; margin-bottom: 20px; }
        .alert-box { background: #fef3c7; border: 1px solid #f59e0b; border-radius: 6px; padding: 15px; margin: 15px 0; }
        .btn { display: inline-block; padding: 12px 24px; background: #2563eb; color: #fff; text-decoration: none; border-radius: 6px; font-weight: bold; }
        .footer { margin-top: 30px; font-size: 12px; color: #888; border-top: 1px solid #eee; padding-top: 15px; }
    </style>
</head>
<body>
<div class="container">
    <div class="logo">Leopardo RH</div>

    @if($locale === 'ar')
        <h2>تجربتك تنتهي قريبًا</h2>
        <div class="alert-box">
            <strong>تنتهي تجربة {{ $company->name }} خلال {{ $daysLeft }} يوم (أيام).</strong>
        </div>
        <p>للاستمرار في استخدام جميع الميزات، يرجى الاشتراك في إحدى خططنا.</p>
    @elseif($locale === 'en')
        <h2>Your trial is expiring soon</h2>
        <div class="alert-box">
            <strong>{{ $company->name }}'s trial expires in {{ $daysLeft }} day(s).</strong>
        </div>
        <p>To continue using all features, please subscribe to one of our plans.</p>
    @else
        <h2>Votre essai expire bientôt</h2>
        <div class="alert-box">
            <strong>L'essai de {{ $company->name }} expire dans {{ $daysLeft }} jour(s).</strong>
        </div>
        <p>Pour continuer à utiliser toutes les fonctionnalités, veuillez vous abonner à l'un de nos plans.</p>
    @endif

    <div class="footer">
        @if($locale === 'ar')
            <p>هذه رسالة تلقائية من Leopardo RH.</p>
        @elseif($locale === 'en')
            <p>This is an automated message from Leopardo RH.</p>
        @else
            <p>Ceci est un message automatique de Leopardo RH.</p>
        @endif
    </div>
</div>
</body>
</html>
