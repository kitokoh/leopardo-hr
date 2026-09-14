{{--
    Partage d’un document comptable (issue #7346) — fragment nu migré sur le
    layout canonique (une seule charte ; la couleur #0f766e codée en dur est
    remplacée par le token de marque et le bouton du layout).
--}}
@extends('emails.layouts.base')

@section('heading', __('accounting.email_heading'))

@section('content')
    <p style="margin:0 0 16px 0;">{{ __('accounting.email_body', ['number' => $documentName]) }}</p>

    @include('emails.partials.button', [
        'url' => $portalUrl,
        'label' => __('accounting.email_button'),
        'align' => 'center',
    ])

    @if ($share->expires_at)
        <p style="margin:0 0 16px 0; font-size:13px; line-height:20px; color:#64748b;">
            {{ __('accounting.email_expires', ['date' => $share->expires_at->format('d/m/Y H:i')]) }}
        </p>
    @endif
@endsection
