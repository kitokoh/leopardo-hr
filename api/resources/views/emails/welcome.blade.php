@extends('emails.layouts.premium')

@section('header', __('emails.email_welcome_subject'))

@section('content')
    <h2 dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">{{ __('emails.email_welcome_greeting', ['name' => $userName ?? '']) }}</h2>
    <p dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">{{ __('emails.email_welcome_body') }}</p>

    <div style="text-align: center; margin-top: 30px; margin-bottom: 30px;">
        <a href="{{ $loginUrl ?? '#' }}" class="btn-primary">{{ __('emails.email_welcome_button') }}</a>
    </div>

    <p style="color: #64748b;" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">{{ __('emails.email_welcome_thanks') }}</p>
@endsection
