{{--
    Relance d’onboarding (issue #7346) — migré du thème Markdown Laravel
    (pied de page anglais, bouton gris) vers le layout canonique.
--}}

@extends('emails.layouts.base')

@section('heading', $tpl->heading)

@section('content')
    <div style="margin:0 0 16px 0;">{!! $tpl->bodyHtml() !!}</div>

    <p style="margin:0 0 4px 0;">{{ trans('emails.onboarding_reminder_steps') }}</p>

    @include('emails.partials.button', [
        'url' => $setupUrl,
        'label' => $tpl->ctaLabel ?? trans('emails.onboarding_reminder_cta'),
        'align' => 'center',
    ])

    <p style="margin:0 0 16px 0; font-size:13px; line-height:20px; color:#64748b;">
        {{ trans('emails.onboarding_reminder_support') }}
    </p>

    <p style="margin:0; font-size:14px; line-height:22px;">
        {{ trans('emails.regards') }}<br>
        {{ trans('emails.team_signature', ['company' => config('mail.brand.name')]) }}
    </p>
@endsection
