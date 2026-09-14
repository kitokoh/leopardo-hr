{{--
    Bienvenue dans l’essai (issue #7346).

    Migré de `emails/layouts/premium` (dont les couleurs venaient de classes CSS
    dans un bloc `<style>` supprimé par Gmail) vers le layout canonique : styles
    inline, en-tête de marque, pré-en-tête et pied de page uniques.
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
                <strong>{{ __('emails.email_trial_welcome_email_label') }}:</strong> {{ $manager->email }}<br>
                <strong>{{ __('emails.email_trial_welcome_password_label') }}:</strong>
                <span style="font-family:'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; background-color:#ffffff; padding:3px 8px; border-radius:4px; border:1px solid #e2e8f0; color:#0f172a;">{{ $tempPassword }}</span>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 18px 0;">
        <span style="display:inline-block; background-color:#ccfbf1; color:#0f766e; padding:6px 14px; border-radius:999px; font-size:13px; font-weight:700;">
            {{ __('emails.email_trial_welcome_trial_badge', ['days' => $trialDays]) }}
        </span>
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#fffbeb; border:1px solid #fde68a; border-radius:8px; margin:0 0 22px 0;">
        <tr>
            <td style="padding:14px 16px; font-size:14px; line-height:21px; color:#92400e;">
                {{ __('emails.email_trial_welcome_change_pw') }}
            </td>
        </tr>
    </table>

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
