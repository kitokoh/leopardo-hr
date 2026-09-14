{{--
    Essai - jour 3, cron historique (issue #7346).

    CORRECTION DE CONTENU : ce fragment nu (ni en-tête, ni pied de page) est
    désormais rendu par le layout canonique. Il utilisait `trial_day3_heading`
    — qui est une SALUTATION (« Bonjour :name, ») — comme titre ; le titre vient
    maintenant du sujet traduit et la salutation redevient une phrase.
--}}
@extends('emails.layouts.base')

@section('heading', __('emails.trial_day3_subject'))

@section('content')
    <p style="margin:0 0 16px 0;">{{ str_replace(':name', $managerName, trans('emails.trial_day3_heading')) }}</p>

    <p style="margin:0 0 12px 0;">{{ str_replace(':appName', $appName, trans('emails.trial_day3_intro')) }}</p>
    <p style="margin:0 0 12px 0;">{{ __('emails.trial_day3_body') }}</p>
    <p style="margin:0 0 4px 0;">{{ __('emails.trial_day3_cta_intro') }}</p>

    @include('emails.partials.button', [
        'url' => $appUrl.'/dashboard',
        'label' => __('emails.trial_day3_button'),
        'align' => 'center',
    ])

    <p style="margin:0 0 20px 0; font-size:13px; line-height:20px; color:#64748b;">{{ __('emails.trial_day3_help') }}</p>

    <p style="margin:0; font-size:14px; line-height:22px;">
        {{ __('emails.regards') }}<br>
        {{ __('emails.team_signature', ['company' => config('mail.brand.name')]) }}
    </p>
@endsection
