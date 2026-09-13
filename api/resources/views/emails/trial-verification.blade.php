<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    {{-- All user-visible strings come from the api/lang/*/emails.php catalog
         (CI I18N guard). The locale is carried by
         TrialVerificationMail::locale(), so __() resolves in the language
         chosen by the user instead of the application default. --}}
    <title>{{ __('emails.trial_verification_subject') }}</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 40px 0; color: #334155;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
        <div style="background-color: #059669; padding: 24px; text-align: center;">
            <h1 style="color: #ffffff; margin: 0; font-size: 24px;">Leopardo RH</h1>
        </div>

        <div style="padding: 32px;">
            <p style="font-size: 16px; line-height: 24px; margin-bottom: 24px;">
                {{ __('emails.trial_verification_greeting', ['name' => $managerName]) }}
            </p>

            <p style="font-size: 16px; line-height: 24px; margin-bottom: 24px;">
                {{ __('emails.trial_verification_intro') }}
            </p>

            <div style="background-color: #f1f5f9; border-radius: 8px; padding: 24px; text-align: center; margin-bottom: 32px;">
                <span style="font-size: 32px; font-weight: bold; letter-spacing: 4px; color: #0f172a;">{{ $verificationToken }}</span>
            </div>

            <p style="font-size: 14px; color: #64748b; line-height: 20px;">
                {{ __('emails.trial_verification_validity') }}
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
