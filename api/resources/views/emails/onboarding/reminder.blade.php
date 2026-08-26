@component('mail::message')
# {{ trans('emails.onboarding_reminder_heading', ['name' => $managerName]) }}

{!! trans('emails.onboarding_reminder_intro', ['company' => $company->name]) !!}

{{ trans('emails.onboarding_reminder_steps') }}

@component('mail::button', ['url' => $setupUrl])
{{ trans('emails.onboarding_reminder_cta') }}
@endcomponent

{{ trans('emails.onboarding_reminder_support') }}

{{ trans('emails.regards') }}
{{ trans('emails.team_signature', ['company' => 'Leopardo RH']) }}
@endcomponent
