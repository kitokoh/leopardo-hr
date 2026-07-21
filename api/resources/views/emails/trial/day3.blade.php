@php
    $__dir = \App\Support\I18nCatalog::isRtl($locale ?? app()->getLocale()) ? 'rtl' : 'ltr';
@endphp
<div dir="{{ $__dir }}">
<h1>{{ str_replace(':name', $managerName, trans('emails.trial_day3_heading')) }}</h1>
<p>{{ str_replace(':appName', $appName, trans('emails.trial_day3_intro')) }}</p>
<p>{{ __('emails.trial_day3_body') }}</p>
<p>{{ __('emails.trial_day3_cta_intro') }}</p>
<a href="{{ $appUrl }}/dashboard" style="display:inline-block;padding:10px 20px;background:#10b981;color:white;text-decoration:none;border-radius:5px;">{{ __('emails.trial_day3_button') }}</a>
<p>{{ __('emails.trial_day3_help') }}</p>
<p>{{ str_replace(':company', $appName, trans('emails.team_signature')) }}</p>
</div>
