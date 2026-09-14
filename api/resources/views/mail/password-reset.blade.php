{{--
    Réinitialisation du mot de passe (issue #7346).

    Ce template était rendu en **Markdown** (`mail::message`) : thème Laravel par
    défaut, bouton gris, pied de page ANGLAIS, et surtout trois phrases codées en
    dur en français (« Votre code de réinitialisation… », « Cordialement, »,
    « L'équipe Leopardo RH ») alors que le reste était traduit → e-mail hybride
    pour un destinataire en/ar/tr.

    Désormais rendu par le layout canonique (`view:` et non `markdown:`), donc
    localisé ×4, styles inline (Gmail-safe) et pied de page unique.
--}}
@extends('emails.layouts.base')

@section('heading', __('emails.email_password_reset_subject'))

@section('content')
    <p style="margin:0 0 16px 0;">
        {{ __('emails.email_password_reset_greeting', ['name' => $userName ?? $email]) }}
    </p>

    <p style="margin:0 0 20px 0;">
        {{ __('emails.email_password_reset_body') }}
    </p>

    <p style="margin:0 0 10px 0; font-size:14px;">{{ __('emails.email_password_reset_code_label') }}</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f1f5f9; border-radius:8px; margin:0 0 22px 0;">
        <tr>
            <td align="center" style="padding:20px;">
                <span style="display:block; font-size:28px; font-weight:700; letter-spacing:6px; color:#0f172a;">{{ $token }}</span>
            </td>
        </tr>
    </table>

    @php
        // Le client public est le web client (Next.js) : le lien de réinitialisation
        // doit y mener (page auth/reset-password?token=&email=), jamais au backend.
        // Repli : racine du site de marque si FRONTEND_URL n'est pas configuré.
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');
        $resetUrl = $frontendUrl !== ''
            ? $frontendUrl.'/auth/reset-password?token='.rawurlencode($token).'&email='.rawurlencode($email)
            : config('mail.brand.website_url');
    @endphp

    @include('emails.partials.button', [
        'url' => $resetUrl,
        'label' => __('emails.email_password_reset_button'),
        'align' => 'center',
    ])

    <p style="margin:0 0 20px 0; font-size:13px; line-height:20px; color:#64748b;">
        {{ __('emails.email_password_reset_ignore') }}
    </p>

    <p style="margin:0; font-size:14px; line-height:22px;">
        {{ __('emails.regards') }}<br>
        {{ __('emails.team_signature', ['company' => config('mail.brand.name')]) }}
    </p>
@endsection
