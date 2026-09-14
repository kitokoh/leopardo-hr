{{--
    Essai - expiré, cron historique (issue #7346). Même correction de titre que
    `expiring` (la salutation du jour 3 servait de titre).
--}}
@extends('emails.layouts.base')

@section('heading', __('emails.trial_expired_subject'))

@section('content')
    <p style="margin:0 0 16px 0;">{{ str_replace(':name', $managerName, trans('emails.trial_day3_heading')) }}</p>

    <p style="margin:0 0 12px 0;">{{ __('emails.trial_expired_intro') }}</p>
    <p style="margin:0 0 4px 0;">{{ __('emails.trial_expired_body') }}</p>

    @include('emails.partials.button', [
        'url' => $appUrl.'/settings/billing',
        'label' => __('emails.trial_expired_button'),
        'align' => 'center',
    ])

    <p style="margin:0 0 20px 0; font-size:14px; line-height:22px;">{{ str_replace(':company', $appName, trans('emails.team_signature')) }}</p>
@endsection
