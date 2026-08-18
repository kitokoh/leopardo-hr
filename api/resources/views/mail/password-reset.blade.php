<x-mail::message>
# {{ __('emails.email_password_reset_subject') }}

{{ __('emails.email_password_reset_greeting', ['name' => $userName ?? $email]) }}

{{ __('emails.email_password_reset_body') }}

Votre code de réinitialisation (valable **60 minutes**, usage unique) :

# {{ $token }}

<x-mail::button :url="config('app.url')">
{{ __('emails.email_password_reset_button') }}
</x-mail::button>

{{ __('emails.email_password_reset_ignore') }}

Cordialement,<br>
L'équipe Leopardo RH
</x-mail::message>
