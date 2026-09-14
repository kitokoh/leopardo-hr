{{--
    Vérification d'e-mail à la création d'un essai (issue #7346).

    Migré sur `emails/layouts/base.blade.php` : ce template portait son propre
    HTML complet (doctype, palette verte #059669, pied de page FR codé en dur
    « © année Leopardo RH. ») sans en-tête de marque ni pré-en-tête.

    Le sujet et les libellés viennent du catalogue `api/lang/*/emails.php` ; la
    locale est portée par `TrialVerificationMail::locale()`.
--}}
@extends('emails.layouts.base')

@section('heading', __('emails.trial_verification_subject'))

@section('content')
    <p style="margin:0 0 18px 0;">
        {{ __('emails.trial_verification_greeting', ['name' => $managerName]) }}
    </p>

    <p style="margin:0 0 22px 0;">
        {{ __('emails.trial_verification_intro') }}
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f1f5f9; border-radius:8px; margin:0 0 22px 0;">
        <tr>
            <td align="center" style="padding:22px;">
                <span style="display:block; font-size:30px; font-weight:700; letter-spacing:6px; color:#0f172a;">{{ $verificationToken }}</span>
            </td>
        </tr>
    </table>

    <p style="margin:0; font-size:13px; line-height:20px; color:#64748b;">
        {{ __('emails.trial_verification_validity') }}
    </p>
@endsection
