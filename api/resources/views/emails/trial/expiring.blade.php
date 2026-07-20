@php
    $__dir = \App\Support\I18nCatalog::isRtl($locale ?? app()->getLocale()) ? 'rtl' : 'ltr';
@endphp
<div dir="{{ $__dir }}">
<h1>{{ str_replace(':name', $managerName, trans('emails.trial_day3_heading')) }}</h1>
<p>{!! str_replace(':appName', $appName, e(trans('emails.trial_expiring_intro'))) !!}</p>
<p>{{ __('emails.trial_expiring_body') }}</p>
<a href="{{ $appUrl }}/settings/billing" style="display:inline-block;padding:10px 20px;background:#f59e0b;color:white;text-decoration:none;border-radius:5px;">{{ __('emails.trial_expiring_button') }}</a>
<p>{{ str_replace(':company', $appName, trans('emails.team_signature')) }}</p>
</div>
