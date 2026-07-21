@component('mail::message')
# {{ str_replace(':name', $managerName, trans('emails.trial_day7_heading')) }}

{!! str_replace([':count', ':company'], [(string) $employeeCount, $company->name], trans('emails.trial_day7_intro')) !!}

{{ __('emails.trial_day7_body') }}

@component('mail::button', ['url' => $upgradeUrl])
{{ __('emails.trial_day7_upgrade_button') }}
@endcomponent

{{ __('emails.trial_day7_compare_intro') }}

@component('mail::button', ['url' => $pricingUrl])
{{ __('emails.trial_day7_pricing_button') }}
@endcomponent

{{ __('emails.trial_day7_help') }}

{{ __('emails.regards') }}
{{ str_replace(':company', 'Leopardo RH', trans('emails.team_signature')) }}
@endcomponent
