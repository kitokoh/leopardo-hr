@component('mail::message')
# {{ __('emails.email_subscription_confirmed_subject', ['company' => $companyName]) }}

{{ __('emails.email_subscription_confirmed_thanks') }}
{{ __('emails.email_subscription_confirmed_plan_active', ['plan' => strtoupper($plan)]) }}

**{{ __('emails.email_subscription_confirmed_next_invoice') }}** {{ \Carbon\Carbon::parse($periodEnd)->translatedFormat('d/m/Y') }}

@component('mail::button', ['url' => $dashboardUrl])
{{ __('emails.email_subscription_confirmed_dashboard_button') }}
@endcomponent

@component('mail::button', ['url' => $invoiceUrl, 'color' => 'success'])
{{ __('emails.email_subscription_confirmed_invoice_button') }}
@endcomponent

{{ __('emails.email_subscription_confirmed_footer') }}
@endcomponent
