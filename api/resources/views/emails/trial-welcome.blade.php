@extends('emails.layouts.premium')

@section('header')
@if($locale === 'ar')
مساحة عملك جاهزة — {{ $company->name }}
@elseif($locale === 'en')
Your workspace is ready — {{ $company->name }}
@elseif($locale === 'tr')
Calisma alaniniz hazir — {{ $company->name }}
@else
Votre espace est prêt — {{ $company->name }}
@endif
@endsection

@section('content')
<div @if($locale === 'ar') dir="rtl" @endif>
    @if($locale === 'ar')
        <h2>مرحبًا {{ $manager->first_name }} 👋</h2>
        <p>تم إنشاء مساحة عمل <strong>{{ $company->name }}</strong> بنجاح. إليك بيانات الاتصال الخاصة بك:</p>
    @elseif($locale === 'en')
        <h2>Hello {{ $manager->first_name }} 👋</h2>
        <p>Your <strong>{{ $company->name }}</strong> workspace has been created. Here are your login credentials:</p>
    @elseif($locale === 'tr')
        <h2>Merhaba {{ $manager->first_name }} 👋</h2>
        <p><strong>{{ $company->name }}</strong> calisma alaniniz olusturuldu. Giris bilgileriniz:</p>
    @else
        <h2>Bonjour {{ $manager->first_name }} 👋</h2>
        <p>Votre espace <strong>{{ $company->name }}</strong> a été créé avec succès. Voici vos identifiants de connexion :</p>
    @endif

    <div class="data-box">
        <p>
            <strong>Email:</strong> {{ $manager->email }}<br>
            <strong>
                @if($locale === 'ar') كلمة المرور
                @elseif($locale === 'en') Password
                @elseif($locale === 'tr') Sifre
                @else Mot de passe
                @endif
            :</strong> 
            <span style="font-family: monospace; background: #fff; padding: 4px 8px; border-radius: 4px; border: 1px solid #e2e8f0; color:#0f172a;">{{ $tempPassword }}</span>
        </p>
    </div>

    <div style="background: #ecfdf5; color: #059669; padding: 8px 16px; border-radius: 20px; font-size: 14px; font-weight: 700; display: inline-block; margin-bottom: 20px;">
        @if($locale === 'ar') تجربة مجانية {{ $trialDays }} يوم
        @elseif($locale === 'en') {{ $trialDays }}-day free trial
        @elseif($locale === 'tr') {{ $trialDays }} gun ucretsiz deneme
        @else Essai gratuit {{ $trialDays }} jours
        @endif
    </div>

    <div style="background: #fef3c7; border: 1px solid #fde68a; border-radius: 10px; padding: 14px 16px; margin: 20px 0; font-size: 14px; color: #92400e;">
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
        <h3>الخطوات التالية</h3>
        <ul>
            <li>سجّل الدخول بالبيانات أعلاه</li>
            <li>أضف أول موظفيك</li>
            <li>جرّب تسجيل الحضور</li>
        </ul>
    @elseif($locale === 'en')
        <h3>Next steps</h3>
        <ul>
            <li>Log in with the credentials above</li>
            <li>Add your first employees</li>
            <li>Try your first attendance check-in</li>
        </ul>
    @elseif($locale === 'tr')
        <h3>Sonraki adimlar</h3>
        <ul>
            <li>Yukaridaki bilgilerle giris yapin</li>
            <li>Ilk calisanlarinizi ekleyin</li>
            <li>Ilk yoklama girisi yapin</li>
        </ul>
    @else
        <h3>Prochaines étapes</h3>
        <ul>
            <li>Connectez-vous avec les identifiants ci-dessus</li>
            <li>Ajoutez vos premiers employés</li>
            <li>Testez votre premier pointage</li>
        </ul>
    @endif

    <div style="margin-top: 30px; text-align: center;">
        <a href="https://gestionemployerbackend.onrender.com" class="btn-primary">
            @if($locale === 'ar') تسجيل الدخول
            @elseif($locale === 'en') Log in
            @elseif($locale === 'tr') Giris yap
            @else Se connecter
            @endif
        </a>
    </div>
</div>
@endsection
