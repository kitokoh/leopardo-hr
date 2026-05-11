<!doctype html>
<html lang="{{ $locale }}" @if($locale === 'ar') dir="rtl" @endif>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 14px; color: #333; background: #f5f5f5; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; padding: 30px; }
        .logo { font-size: 20px; font-weight: bold; color: #2563eb; margin-bottom: 20px; }
        .btn { display: inline-block; padding: 12px 24px; background: #2563eb; color: #fff; text-decoration: none; border-radius: 6px; font-weight: bold; }
        .footer { margin-top: 30px; font-size: 12px; color: #888; border-top: 1px solid #eee; padding-top: 15px; }
    </style>
</head>
<body>
<div class="container">
    <div class="logo">Leopardo RH</div>

    @if($locale === 'ar')
        <h2>مرحبًا {{ $user->name ?? '' }}!</h2>
        <p>شكرًا لانضمامك إلى <strong>Leopardo RH</strong>. شركتك <strong>{{ $company->name }}</strong> مسجلة بنجاح.</p>
        <p>إليك ما يمكنك فعله الآن:</p>
        <ul>
            <li>أكمل إعداد الشركة عبر قائمة التحقق</li>
            <li>أضف الموظفين الأوائل</li>
            <li>ابدأ تتبع الحضور</li>
        </ul>
        <p>فريق دعمنا متاح لمساعدتك في أي وقت.</p>
    @elseif($locale === 'en')
        <h2>Welcome {{ $user->name ?? '' }}!</h2>
        <p>Thank you for joining <strong>Leopardo RH</strong>. Your company <strong>{{ $company->name }}</strong> is registered.</p>
        <p>Here is what you can do now:</p>
        <ul>
            <li>Complete your setup via the onboarding checklist</li>
            <li>Add your first employees</li>
            <li>Start tracking attendance</li>
        </ul>
        <p>Our support team is available anytime to help you get started.</p>
    @else
        <h2>Bienvenue {{ $user->name ?? '' }} !</h2>
        <p>Merci d'avoir rejoint <strong>Leopardo RH</strong>. Votre entreprise <strong>{{ $company->name }}</strong> est enregistrée.</p>
        <p>Voici ce que vous pouvez faire maintenant :</p>
        <ul>
            <li>Complétez la configuration via la checklist d'onboarding</li>
            <li>Ajoutez vos premiers employés</li>
            <li>Démarrez le suivi de présence</li>
        </ul>
        <p>Notre équipe support est disponible pour vous aider à tout moment.</p>
    @endif

    <div class="footer">
        @if($locale === 'ar')
            <p>لقد تلقيت هذا البريد لأنك سجلت في Leopardo RH.</p>
        @elseif($locale === 'en')
            <p>You received this email because you registered on Leopardo RH.</p>
        @else
            <p>Vous recevez cet email car vous êtes inscrit(e) sur Leopardo RH.</p>
        @endif
    </div>
</div>
</body>
</html>
