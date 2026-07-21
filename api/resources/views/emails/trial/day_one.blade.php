@component('mail::message')
# {{ str_replace(':name', $managerName, trans('emails.trial_day1_heading')) }}

{!! str_replace(':company', $company->name, trans('emails.trial_day1_intro')) !!}

@component('mail::panel')
1. {{ __('emails.trial_day1_step1') }}
2. {{ __('emails.trial_day1_step2') }}
3. {{ __('emails.trial_day1_step3') }}
@endcomponent

@component('mail::button', ['url' => $loginUrl])
{{ __('emails.trial_day1_button') }}
@endcomponent

{!! str_replace(':docsUrl', $docsUrl, trans('emails.trial_day1_help')) !!}

{{ __('emails.regards') }}
{{ str_replace(':company', 'Leopardo RH', trans('emails.team_signature')) }}
@endcomponent
