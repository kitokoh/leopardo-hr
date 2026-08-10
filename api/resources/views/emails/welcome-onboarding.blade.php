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

    <h2>{{ __('emails.email_welcome_onboarding_subject', ['name' => $user->name ?? '']) }}</h2>
    <p>{{ __('emails.email_welcome_onboarding_intro', ['company' => $company->name]) }}</p>
    <p>{{ __('emails.email_welcome_onboarding_what_now') }}</p>
    <ul>
        <li>{{ __('emails.email_welcome_onboarding_step1') }}</li>
        <li>{{ __('emails.email_welcome_onboarding_step2') }}</li>
        <li>{{ __('emails.email_welcome_onboarding_step3') }}</li>
    </ul>
    <p>{{ __('emails.email_welcome_onboarding_support') }}</p>

    <div class="footer">
        <p>{{ __('emails.email_welcome_onboarding_footer') }}</p>
    </div>
</div>
</body>
</html>
