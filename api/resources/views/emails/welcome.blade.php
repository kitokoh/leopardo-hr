@php
$translations = [
    'fr' => [
        'subject' => 'Bienvenue sur Leopardo RH',
        'greeting' => 'Bonjour :name,',
        'body' => 'Votre compte a été créé avec succès. Vous pouvez maintenant vous connecter et commencer à utiliser la plateforme.',
        'button' => 'Se connecter',
        'thanks' => 'Merci de votre confiance.',
    ],
    'ar' => [
        'subject' => 'مرحبا بك في ليوباردو HR',
        'greeting' => 'مرحبا :name،',
        'body' => 'تم إنشاء حسابك بنجاح. يمكنك الآن تسجيل الدخول والبدء في استخدام المنصة.',
        'button' => 'تسجيل الدخول',
        'thanks' => 'شكرا لثقتكم.',
    ],
    'en' => [
        'subject' => 'Welcome to Leopardo RH',
        'greeting' => 'Hello :name,',
        'body' => 'Your account has been created successfully. You can now log in and start using the platform.',
        'button' => 'Log in',
        'thanks' => 'Thank you for your trust.',
    ],
];
$t = $translations[$locale ?? 'fr'];
@endphp
@extends('emails.layouts.premium')

@section('header', $t['subject'])

@section('content')
    <h2 dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">{{ str_replace(':name', $userName ?? '', $t['greeting']) }}</h2>
    <p dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">{{ $t['body'] }}</p>
    
    <div style="text-align: center; margin-top: 30px; margin-bottom: 30px;">
        <a href="{{ $loginUrl ?? '#' }}" class="btn-primary">{{ $t['button'] }}</a>
    </div>

    <p style="color: #64748b;" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">{{ $t['thanks'] }}</p>
@endsection
