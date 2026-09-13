<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <!-- Le titre doit suivre la langue de l'e-mail : il était figé en français
         alors que le corps était déjà traduit (e-mail « Bonjour … » +
         paragraphes en turc, signalé par le fondateur). -->
    <title>@if($locale === 'en')Verify your Leopardo RH email@elseif($locale === 'ar')تحقق من بريدك الإلكتروني في Leopardo RH@elseif($locale === 'tr')Leopardo RH e-postanızı doğrulayın@else Vérifiez votre email Leopardo RH@endif</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 40px 0; color: #334155;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
        <div style="background-color: #059669; padding: 24px; text-align: center;">
            <h1 style="color: #ffffff; margin: 0; font-size: 24px;">Leopardo RH</h1>
        </div>

        <div style="padding: 32px;">
            {{-- Salutation localisée : elle était en dur (« Bonjour ») pour les
                 4 langues, d'où l'e-mail hybride reçu par le fondateur. --}}
            <p style="font-size: 16px; line-height: 24px; margin-bottom: 24px;">
                @if($locale === 'en')
                    Hello {{ $managerName }},
                @elseif($locale === 'ar')
                    مرحباً {{ $managerName }}،
                @elseif($locale === 'tr')
                    Merhaba {{ $managerName }},
                @else
                    Bonjour {{ $managerName }},
                @endif
            </p>

            <p style="font-size: 16px; line-height: 24px; margin-bottom: 24px;">
                @if($locale === 'en')
                    Please use the verification code below to complete the creation of your workspace:
                @elseif($locale === 'ar')
                    يرجى استخدام رمز التحقق أدناه لإكمال إنشاء مساحة العمل الخاصة بك:
                @elseif($locale === 'tr')
                    Çalışma alanınızın oluşturulmasını tamamlamak için lütfen aşağıdaki doğrulama kodunu kullanın:
                @else
                    Veuillez utiliser le code de vérification ci-dessous pour finaliser la création de votre espace de travail :
                @endif
            </p>

            <div style="background-color: #f1f5f9; border-radius: 8px; padding: 24px; text-align: center; margin-bottom: 32px;">
                <span style="font-size: 32px; font-weight: bold; letter-spacing: 4px; color: #0f172a;">{{ $verificationToken }}</span>
            </div>

            <p style="font-size: 14px; color: #64748b; line-height: 20px;">
                @if($locale === 'en')
                    This code is valid for 30 minutes. If you did not request this, you can safely ignore this email.
                @elseif($locale === 'ar')
                    هذا الرمز صالح لمدة 30 دقيقة. إذا لم تطلب ذلك، يمكنك تجاهل هذه الرسالة.
                @elseif($locale === 'tr')
                    Bu kod 30 dakika geçerlidir. Bunu talep etmediyseniz, bu e-postayı güvenle yok sayabilirsiniz.
                @else
                    Ce code est valide pendant 30 minutes. Si vous n'avez pas fait cette demande, vous pouvez ignorer cet email.
                @endif
            </p>
        </div>

        <div style="background-color: #f8fafc; padding: 24px; text-align: center; border-top: 1px solid #e2e8f0;">
            <p style="font-size: 14px; color: #64748b; margin: 0;">
                &copy; {{ date('Y') }} Leopardo RH.
            </p>
        </div>
    </div>
</body>
</html>
