@extends('emails.layouts.premium')

@section('header', __('emails.email_trial_welcome_subject', ['company' => $company->name]))

@section('content')
<div @if($locale === 'ar') dir="rtl" @endif>
    <h2>{{ __('emails.email_trial_welcome_heading', ['name' => $manager->first_name]) }}</h2>
    <p>{{ __('emails.email_trial_welcome_intro', ['company' => $company->name]) }}</p>

    <div class="data-box">
        <p>
            <strong>{{ __('emails.email_trial_welcome_email_label') }}:</strong> {{ $manager->email }}<br>
            <strong>{{ __('emails.email_trial_welcome_password_label') }}:</strong>
            <span style="font-family: monospace; background: #fff; padding: 4px 8px; border-radius: 4px; border: 1px solid #e2e8f0; color:#0f172a;">{{ $tempPassword }}</span>
        </p>
    </div>

    <div style="background: #ecfdf5; color: #059669; padding: 8px 16px; border-radius: 20px; font-size: 14px; font-weight: 700; display: inline-block; margin-bottom: 20px;">
        {{ __('emails.email_trial_welcome_trial_badge', ['days' => $trialDays]) }}
    </div>

    <div style="background: #fef3c7; border: 1px solid #fde68a; border-radius: 10px; padding: 14px 16px; margin: 20px 0; font-size: 14px; color: #92400e;">
        {{ __('emails.email_trial_welcome_change_pw') }}
    </div>

    <h3>{{ __('emails.email_trial_welcome_next_steps') }}</h3>
    <ul>
        <li>{{ __('emails.email_trial_welcome_step1') }}</li>
        <li>{{ __('emails.email_trial_welcome_step2') }}</li>
        <li>{{ __('emails.email_trial_welcome_step3') }}</li>
    </ul>

    <div style="margin-top: 30px; text-align: center;">
        <a href="https://gestionemployerbackend.onrender.com" class="btn-primary">
            {{ __('emails.email_trial_welcome_button') }}
        </a>
    </div>
</div>
@endsection
