{{--
    Essai - expire bientôt, cron historique (issue #7346).

    CORRECTION DE CONTENU : le titre utilisait `trial_day3_heading`, c’est-à-dire
    la SALUTATION du mail du jour 3 (« Bonjour :name, ») → l’e-mail s’ouvrait sur
    un titre faux. Il utilise désormais le sujet traduit dédié.
--}}
@extends('emails.layouts.base')

@section('heading', __('emails.trial_expiring_subject'))

@section('content')
    <p style="margin:0 0 16px 0;">{{ str_replace(':name', $managerName, trans('emails.trial_day3_heading')) }}</p>

    <p style="margin:0 0 12px 0;">{!! str_replace(':appName', $appName, e(trans('emails.trial_expiring_intro'))) !!}</p>
    <p style="margin:0 0 4px 0;">{{ __('emails.trial_expiring_body') }}</p>

    @include('emails.partials.button', [
        'url' => $appUrl.'/settings/billing',
        'label' => __('emails.trial_expiring_button'),
        'align' => 'center',
    ])

    <p style="margin:0 0 20px 0; font-size:14px; line-height:22px;">{{ str_replace(':company', $appName, trans('emails.team_signature')) }}</p>
@endsection
