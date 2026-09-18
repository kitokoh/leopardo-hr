{{--
    Bienvenue dans l’essai (issue #7346, refonte #7490).

    #7490 : cet e-mail ne contient PLUS AUCUN secret en clair. Le mot de passe
    temporaire est remplacé par un lien magique de DÉFINITION de mot de passe
    (provisioning_token à usage unique, TTL 72 h côté serveur). L’e-mail
    rappelle l’adresse de connexion et pointe vers l’espace.
--}}

@extends('emails.layouts.base')

@section('heading', $tpl->heading)

@section('content')
    <p style="margin:0 0 16px 0;">
        {{ __('emails.email_trial_welcome_heading', ['name' => $manager->first_name]) }}
    </p>
    <div style="margin:0 0 20px 0;">{!! $tpl->bodyHtml() !!}</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; margin:0 0 20px 0;">
        <tr>
            <td style="padding:16px 18px; font-size:14px; line-height:22px;">
                <strong>{{ __('emails.email_trial_welcome_email_label') }}:</strong> {{ $manager->email }}
            </td>
        </tr>
    </table>

    @if (is_string($setPasswordUrl) && $setPasswordUrl !== '')
        <p style="margin:0 0 12px 0; font-size:14px; line-height:22px;">
            {{ __('emails.email_trial_welcome_set_pw_text') }}
        </p>

        @include('emails.partials.button', [
            'url' => $setPasswordUrl,
            'label' => __('emails.email_trial_welcome_set_pw_button'),
            'align' => 'center',
        ])

        <p style="margin:0 0 20px 0; font-size:13px; line-height:20px; color:#64748b;">
            {{ __('emails.email_trial_welcome_set_pw_validity') }}
        </p>
    @else
        {{-- Repli sans lien (ligne de provisioning absente) : l'espace reste
             accessible et le code de connexion couvre les prochaines sessions. --}}
        <p style="margin:0 0 20px 0; font-size:14px; line-height:22px;">
            {{ __('emails.email_trial_welcome_set_pw_fallback') }}
        </p>
    @endif

    <p style="margin:0 0 18px 0;">
        <span style="display:inline-block; background-color:#ccfbf1; color:#0f766e; padding:6px 14px; border-radius:999px; font-size:13px; font-weight:700;">
            {{ __('emails.email_trial_welcome_trial_badge', ['days' => $trialDays]) }}
        </span>
    </p>

    <h2 style="margin:0 0 10px 0; font-size:17px; line-height:24px; color:#0f172a;">{{ __('emails.email_trial_welcome_next_steps') }}</h2>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr><td style="padding:0 0 6px 0;">&bull;&nbsp;{{ __('emails.email_trial_welcome_step1') }}</td></tr>
        <tr><td style="padding:0 0 6px 0;">&bull;&nbsp;{{ __('emails.email_trial_welcome_step2') }}</td></tr>
        <tr><td style="padding:0;">&bull;&nbsp;{{ __('emails.email_trial_welcome_step3') }}</td></tr>
    </table>

    @include('emails.partials.button', [
        'url' => $appUrl.'/auth/login',
        'label' => $tpl->ctaLabel ?? __('emails.email_trial_welcome_button'),
        'align' => 'center',
    ])
@endsection
