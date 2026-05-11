<!doctype html>
<html lang="{{ $locale }}" @if($locale === 'ar') dir="rtl" @endif>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 14px; color: #333; background: #f5f5f5; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; padding: 30px; }
        .logo { font-size: 20px; font-weight: bold; color: #2563eb; margin-bottom: 20px; }
        .alert-box { background: #fee2e2; border: 1px solid #ef4444; border-radius: 6px; padding: 15px; margin: 15px 0; }
        .btn { display: inline-block; padding: 12px 24px; background: #2563eb; color: #fff; text-decoration: none; border-radius: 6px; font-weight: bold; }
        .footer { margin-top: 30px; font-size: 12px; color: #888; border-top: 1px solid #eee; padding-top: 15px; }
    </style>
</head>
<body>
<div class="container">
    <div class="logo">Leopardo RH</div>

    @if($locale === 'ar')
        <h2>فشل الدفع</h2>
        <div class="alert-box">
            <strong>فشلت عملية الدفع بقيمة {{ number_format($amount, 2) }} {{ $currency }} لحساب {{ $company->name }}.</strong>
        </div>
        <p>يرجى التحقق من معلومات الدفع وإعادة المحاولة.</p>
    @elseif($locale === 'en')
        <h2>Payment Failed</h2>
        <div class="alert-box">
            <strong>A payment of {{ number_format($amount, 2) }} {{ $currency }} failed for {{ $company->name }}.</strong>
        </div>
        <p>Please verify your payment information and try again.</p>
    @else
        <h2>Échec de paiement</h2>
        <div class="alert-box">
            <strong>Un paiement de {{ number_format($amount, 2) }} {{ $currency }} a échoué pour {{ $company->name }}.</strong>
        </div>
        <p>Veuillez vérifier vos informations de paiement et réessayer.</p>
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
