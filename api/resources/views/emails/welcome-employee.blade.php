@component('mail::message')
# {{ __('emails.email_welcome_employee_subject', ['company' => $companyName, 'name' => $employeeName]) }}

{{ __('emails.email_welcome_employee_intro') }}

**{{ __('emails.email_welcome_employee_email_label') }} :** {{ $employeeName }}
**{{ __('emails.email_welcome_employee_password_label') }} :** `{{ $temporaryPassword }}`

{{ __('emails.email_welcome_employee_change_note') }}

@component('mail::button', ['url' => $loginUrl])
{{ __('emails.email_welcome_employee_button') }}
@endcomponent

{{ __('emails.email_welcome_employee_help') }}

{{ __('emails.email_welcome_employee_regards') }},
{{ __('emails.email_welcome_employee_team') }}
@endcomponent
