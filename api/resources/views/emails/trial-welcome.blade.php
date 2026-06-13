<!doctype html>
<html lang="{{ $locale }}" @if($locale === 'ar') dir="rtl" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; font-size: 15px; color: #1e293b; background: #f1f5f9; margin: 0; padding: 24px; }
        .container { max-width: 560px; margin: 0 auto; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.06); }
        .header { background: linear-gradient(135deg, #059669, #10b981); padding: 28px 32px; }
        .header h1 { color: #fff; font-size: 22px; margin: 0; font-weight: 800; letter-spacing: -0.5px; }
        .header p { color: rgba(255,255,255,0.85); font-size: 14px; margin: 8px 0 0; }
        .body { padding: 32px; }
        .credential-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin: 24px 0; }
        .credential-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; }
        .credential-row + .credential-row { border-top: 1px solid #e2e8f0; }
        .credential-label { font-size: 13px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .credential-value { font-family: 'SF Mono', 'Consolas', monospace; font-size: 15px; font-weight: 700; color: #0f172a; background: #fff; padding: 4px 12px; border-radius: 6px; border: 1px solid #e2e8f0; }
        .btn { display: inline-block; padding: 14px 28px; background: #059669; color: #fff; text-decoration: none; border-radius: 10px; font-weight: 700; font-size: 15px; margin: 8px 4px 8px 0; }
        .btn-outline { background: transparent; color: #059669; border: 2px solid #059669; }
        .steps { margin: 20px 0; padding: 0; list-style: none; }
        .steps li { padding: 8px 0 8px 28px; position: relative; font-size: 14px; color: #475569; }
        .steps li::before { content: attr(data-step); position: absolute; left: 0; top: 8px; width: 20px; height: 20px; background: #ecfdf5; color: #059669; border-radius: 50%; font-size: 11px; font-weight: 800; display: flex; align-items: center; justify-content: center; }
        .warning-box { background: #fef3c7; border: 1px solid #fde68a; border-radius: 10px; padding: 14px 16px; margin: 20px 0; font-size: 13px; color: #92400e; }
        .trial-badge { display: inline-block; background: #ecfdf5; color: #059669; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; margin: 8px 0; }
        .footer { padding: 20px 32px; background: #f8fafc; border-top: 1px solid #f1f5f9; font-size: 12px; color: #94a3b8; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🐆 Leopardo RH</h1>
        @if($locale === 'ar')
            <p>مساحة عملك جاهزة — {{ $company->name }}</p>
        @elseif($locale === 'en')
            <p>Your workspace is ready — {{ $company->name }}</p>
        @elseif($locale === 'tr')
            <p>Calisma alaniniz hazir — {{ $company->name }}</p>
        @else
            <p>Votre espace est prêt — {{ $company->name }}</p>
        @endif
    </div>

    <div class="body">
        @if($locale === 'ar')
            <h2 style="font-size: 20px; margin: 0 0 12px;">مرحبًا {{ $manager->first_name }} 👋</h2>
            <p>تم إنشاء مساحة عمل <strong>{{ $company->name }}</strong> بنجاح. إليك بيانات الاتصال الخاصة بك:</p>
        @elseif($locale === 'en')
            <h2 style="font-size: 20px; margin: 0 0 12px;">Hello {{ $manager->first_name }} 👋</h2>
            <p>Your <strong>{{ $company->name }}</strong> workspace has been created. Here are your login credentials:</p>
        @elseif($locale === 'tr')
            <h2 style="font-size: 20px; margin: 0 0 12px;">Merhaba {{ $manager->first_name }} 👋</h2>
            <p><strong>{{ $company->name }}</strong> calisma alaniniz olusturuldu. Giris bilgileriniz:</p>
        @else
            <h2 style="font-size: 20px; margin: 0 0 12px;">Bonjour {{ $manager->first_name }} 👋</h2>
            <p>Votre espace <strong>{{ $company->name }}</strong> a été créé avec succès. Voici vos identifiants de connexion :</p>
        @endif

        <div class="credential-box">
            <div class="credential-row">
                <span class="credential-label">Email</span>
                <span class="credential-value">{{ $manager->email }}</span>
            </div>
            <div class="credential-row">
                <span class="credential-label">
                    @if($locale === 'ar') كلمة المرور
                    @elseif($locale === 'en') Password
                    @elseif($locale === 'tr') Sifre
                    @else Mot de passe
                    @endif
                </span>
                <span class="credential-value">{{ $tempPassword }}</span>
            </div>
        </div>

        <span class="trial-badge">
            @if($locale === 'ar') تجربة مجانية {{ $trialDays }} يوم
            @elseif($locale === 'en') {{ $trialDays }}-day free trial
            @elseif($locale === 'tr') {{ $trialDays }} gun ucretsiz deneme
            @else Essai gratuit {{ $trialDays }} jours
            @endif
        </span>

        <div class="warning-box">
            @if($locale === 'ar')
                ⚠️ غيّر كلمة المرور فور تسجيل الدخول الأول من قائمة الحساب.
            @elseif($locale === 'en')
                ⚠️ Please change your password after your first login from the Account menu.
            @elseif($locale === 'tr')
                ⚠️ Ilk giristen sonra sifrenizi Hesap menusunden degistirin.
            @else
                ⚠️ Changez votre mot de passe dès la première connexion depuis le menu Compte.
            @endif
        </div>

        @if($locale === 'ar')
            <h3 style="font-size: 16px;">الخطوات التالية</h3>
            <ul class="steps">
                <li data-step="1">سجّل الدخول بالبيانات أعلاه</li>
                <li data-step="2">أضف أول موظفيك</li>
                <li data-step="3">جرّب تسجيل الحضور</li>
            </ul>
        @elseif($locale === 'en')
            <h3 style="font-size: 16px;">Next steps</h3>
            <ul class="steps">
                <li data-step="1">Log in with the credentials above</li>
                <li data-step="2">Add your first employees</li>
                <li data-step="3">Try your first attendance check-in</li>
            </ul>
        @elseif($locale === 'tr')
            <h3 style="font-size: 16px;">Sonraki adimlar</h3>
            <ul class="steps">
                <li data-step="1">Yukaridaki bilgilerle giris yapin</li>
                <li data-step="2">Ilk calisanlarinizi ekleyin</li>
                <li data-step="3">Ilk yoklama girisi yapin</li>
            </ul>
        @else
            <h3 style="font-size: 16px;">Prochaines étapes</h3>
            <ul class="steps">
                <li data-step="1">Connectez-vous avec les identifiants ci-dessus</li>
                <li data-step="2">Ajoutez vos premiers employés</li>
                <li data-step="3">Testez votre premier pointage</li>
            </ul>
        @endif

        <div style="margin-top: 24px; text-align: center;">
            <a href="https://gestionemployerbackend.onrender.com" class="btn">
                @if($locale === 'ar') تسجيل الدخول
                @elseif($locale === 'en') Log in
                @elseif($locale === 'tr') Giris yap
                @else Se connecter
                @endif
            </a>
        </div>
    </div>

    <div class="footer">
        @if($locale === 'ar')
            <p>تلقيت هذا البريد لأنك أنشأت حسابًا على Leopardo RH. إذا لم تفعل، تجاهل هذه الرسالة.</p>
        @elseif($locale === 'en')
            <p>You received this email because you created an account on Leopardo RH. If this wasn't you, please ignore this message.</p>
        @elseif($locale === 'tr')
            <p>Bu e-postayi Leopardo RH'de hesap olusturdugunuz icin aldiniz. Siz degilseniz bu mesaji dikkate almayin.</p>
        @else
            <p>Vous recevez cet email car vous avez créé un espace sur Leopardo RH. Si ce n'est pas vous, ignorez ce message.</p>
        @endif
        <p style="margin-top: 8px;">&copy; {{ date('Y') }} Leopardo RH — leopardo-rh.com</p>
    </div>
</div>
</body>
</html>
