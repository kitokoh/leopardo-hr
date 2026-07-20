@component('mail::message')
# {{ str_replace(':name', $managerName, trans('emails.trial_day3_mail_heading')) }}

{{ __('emails.trial_day3_mail_intro') }}

{{ __('emails.trial_day3_mail_body') }}

@component('mail::button', ['url' => $checkInUrl])
{{ __('emails.trial_day3_mail_button') }}
@endcomponent

{{ __('emails.trial_day3_mail_apps_intro') }}

@component('mail::button', ['url' => $mobileAppsUrl])
{{ __('emails.trial_day3_mail_apps_button') }}
@endcomponent

{{ __('emails.trial_day3_mail_help') }}

{{ __('emails.regards') }}
{{ str_replace(':company', 'Leopardo RH', trans('emails.team_signature')) }}
@endcomponent
