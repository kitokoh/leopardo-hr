<x-mail::message>
# {{ __('emails.email_password_reset_subject') }}

{{ __('emails.email_password_reset_greeting', ['name' => $userName ?? $email]) }}

{{ __('emails.email_password_reset_body') }}

Votre code de réinitialisation (valable **60 minutes**, usage unique) :

# {{ $token }}

@php
    // Le client public est le web client (Next.js) : le lien de réinitialisation
    // doit y mener (page auth/reset-password?token=&email=), jamais au backend.
    // Fallback : racine APP_URL si FRONTEND_URL n'est pas configuré (dev).
    $frontendUrl = rtrim((string) config('app.frontend_url'), '/');
    $resetUrl = $frontendUrl !== ''
        ? $frontendUrl.'/auth/reset-password?token='.rawurlencode($token).'&email='.rawurlencode($email)
        : config('app.url');
@endphp

<x-mail::button :url="$resetUrl">
{{ __('emails.email_password_reset_button') }}
</x-mail::button>

{{ __('emails.email_password_reset_ignore') }}

Cordialement,<br>
L'équipe Leopardo RH
</x-mail::message>
