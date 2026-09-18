{{--
    Code de connexion à usage unique (issue #7490).

    Envoyé quand un compte SANS mot de passe défini demande « Recevoir un code
    de connexion » sur /auth/login. Même gabarit que la vérification
    d'inscription : layout de base, sujet et libellés depuis le catalogue
    `api/lang/*/emails.php`, locale portée par LoginCodeMail::locale().
--}}

@extends('emails.layouts.base')

@section('heading', $tpl->heading)

@section('content')
    <p style="margin:0 0 18px 0;">
        {{ __('emails.login_code_greeting') }}
    </p>

    <div style="margin:0 0 22px 0;">{!! $tpl->bodyHtml() !!}</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f1f5f9; border-radius:8px; margin:0 0 22px 0;">
        <tr>
            <td align="center" style="padding:22px;">
                <span style="display:block; font-size:30px; font-weight:700; letter-spacing:6px; color:#0f172a;">{{ $loginCode }}</span>
            </td>
        </tr>
    </table>

    <p style="margin:0; font-size:13px; line-height:20px; color:#64748b;">
        {{ __('emails.login_code_validity') }}
    </p>
@endsection
