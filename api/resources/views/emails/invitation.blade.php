{{--
    Invitation à rejoindre une entreprise (issue #7346).

    Le sujet était traduit mais le CORPS était intégralement en français codé en
    dur (titre, invitation, libellé du rôle, bouton, mention d'expiration,
    signature) : un invité en/ar/tr recevait un e-mail hybride. Tout passe
    désormais par le catalogue, et la marque n'est plus écrite en dur.
--}}
@extends('emails.layouts.base')

@section('heading', __('emails.invitation_heading', ['company' => $companyName]))

@section('content')
    <p style="margin:0 0 16px 0;">
        {{ __('emails.invitation_intro', ['inviter' => $inviterName, 'company' => $companyName, 'brand' => config('mail.brand.name')]) }}
    </p>

    @if ($role)
        <p style="margin:0 0 4px 0;">{{ __('emails.invitation_role_line', ['role' => $role]) }}</p>
    @endif

    @include('emails.partials.button', [
        'url' => $invitationUrl,
        'label' => __('emails.invitation_accept_button'),
        'align' => 'center',
    ])

    <p style="margin:0 0 20px 0; font-size:13px; line-height:20px; color:#64748b;">{{ __('emails.invitation_expiry') }}</p>

    <p style="margin:0; font-size:14px; line-height:22px;">
        {{ __('emails.regards') }}<br>
        {{ __('emails.team_signature', ['company' => config('mail.brand.name')]) }}
    </p>
@endsection
