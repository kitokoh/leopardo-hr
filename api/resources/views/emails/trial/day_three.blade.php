{{--
    Essai - jour 3 (issue #7346) — migré du thème Markdown Laravel vers le layout
    canonique. Deux CTA distincts (pointage, applications mobiles) au lieu de
    deux boutons gris identiques.
--}}
@extends('emails.layouts.base')

@section('heading', __('emails.trial_day3_mail_subject'))

@section('content')
    <p style="margin:0 0 16px 0;">{{ str_replace(':name', $managerName, trans('emails.trial_day3_mail_heading')) }}</p>

    <p style="margin:0 0 12px 0;">{{ __('emails.trial_day3_mail_intro') }}</p>
    <p style="margin:0 0 4px 0;">{{ __('emails.trial_day3_mail_body') }}</p>

    @include('emails.partials.button', [
        'url' => $checkInUrl,
        'label' => __('emails.trial_day3_mail_button'),
        'align' => 'center',
    ])

    <p style="margin:0 0 4px 0;">{{ __('emails.trial_day3_mail_apps_intro') }}</p>

    @include('emails.partials.button', [
        'url' => $mobileAppsUrl,
        'label' => __('emails.trial_day3_mail_apps_button'),
        'align' => 'center',
    ])

    <p style="margin:0 0 20px 0; font-size:13px; line-height:20px; color:#64748b;">
        {{ __('emails.trial_day3_mail_help') }}
    </p>

    <p style="margin:0; font-size:14px; line-height:22px;">
        {{ __('emails.regards') }}<br>
        {{ __('emails.team_signature', ['company' => config('mail.brand.name')]) }}
    </p>
@endsection
