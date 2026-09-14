{{--
    Essai - jour 7 (issue #7346) — migré du thème Markdown Laravel vers le layout
    canonique ; la marque du pied de signature n'est plus codée en dur.
--}}
@extends('emails.layouts.base')

@section('heading', __('emails.trial_day7_subject'))

@section('content')
    <p style="margin:0 0 16px 0;">{{ str_replace(':name', $managerName, trans('emails.trial_day7_heading')) }}</p>

    <p style="margin:0 0 16px 0;">{!! str_replace([':count', ':company'], [(string) $employeeCount, $company->name], trans('emails.trial_day7_intro')) !!}</p>

    <p style="margin:0 0 4px 0;">{{ __('emails.trial_day7_body') }}</p>

    @include('emails.partials.button', [
        'url' => $upgradeUrl,
        'label' => __('emails.trial_day7_upgrade_button'),
        'align' => 'center',
    ])

    <p style="margin:0 0 4px 0;">{{ __('emails.trial_day7_compare_intro') }}</p>

    @include('emails.partials.button', [
        'url' => $pricingUrl,
        'label' => __('emails.trial_day7_pricing_button'),
        'align' => 'center',
    ])

    <p style="margin:0 0 20px 0; font-size:13px; line-height:20px; color:#64748b;">
        {{ __('emails.trial_day7_help') }}
    </p>

    <p style="margin:0; font-size:14px; line-height:22px;">
        {{ __('emails.regards') }}<br>
        {{ __('emails.team_signature', ['company' => config('mail.brand.name')]) }}
    </p>
@endsection
