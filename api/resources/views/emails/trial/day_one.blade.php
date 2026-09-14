{{--
    Essai - jour 1 (issue #7346) — migré du thème Markdown Laravel (pied de page
    anglais, bouton gris) vers le layout canonique. Le titre ne mélange plus la
    salutation et le titre : la salutation redevient une phrase, le titre vient
    du sujet traduit.
--}}
@extends('emails.layouts.base')

@section('heading', __('emails.trial_day1_subject'))

@section('content')
    <p style="margin:0 0 16px 0;">{{ str_replace(':name', $managerName, trans('emails.trial_day1_heading')) }}</p>

    <p style="margin:0 0 20px 0;">{!! str_replace(':company', $company->name, trans('emails.trial_day1_intro')) !!}</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; margin:0 0 20px 0;">
        <tr>
            <td style="padding:16px 18px; font-size:14px; line-height:23px;">
                <p style="margin:0 0 8px 0;">1. {{ __('emails.trial_day1_step1') }}</p>
                <p style="margin:0 0 8px 0;">2. {{ __('emails.trial_day1_step2') }}</p>
                <p style="margin:0;">3. {{ __('emails.trial_day1_step3') }}</p>
            </td>
        </tr>
    </table>

    @include('emails.partials.button', [
        'url' => $loginUrl,
        'label' => __('emails.trial_day1_button'),
        'align' => 'center',
    ])

    <p style="margin:0 0 20px 0; font-size:13px; line-height:20px; color:#64748b;">
        {!! str_replace(':docsUrl', $docsUrl, trans('emails.trial_day1_help')) !!}
    </p>

    <p style="margin:0; font-size:14px; line-height:22px;">
        {{ __('emails.regards') }}<br>
        {{ __('emails.team_signature', ['company' => config('mail.brand.name')]) }}
    </p>
@endsection
