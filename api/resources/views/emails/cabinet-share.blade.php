{{--
    Partage depuis le cabinet (issue #7346) — fragment nu migré sur le layout
    canonique (couleur #1e40af codée en dur remplacée par le token de marque).
--}}
@extends('emails.layouts.base')

@section('heading', __('cabinet.share_email_heading'))

@section('content')
    <p style="margin:0 0 16px 0;">{{ __('cabinet.share_email_body', ['name' => $ownerName, 'type' => __('cabinet.type_'.$shareableType), 'item' => $shareableName]) }}</p>

    @include('emails.partials.button', [
        'url' => $shareUrl,
        'label' => __('cabinet.share_email_button'),
        'align' => 'center',
    ])

    @if ($share->expires_at)
        <p style="margin:0 0 16px 0; font-size:13px; line-height:20px; color:#64748b;">
            {{ __('cabinet.share_email_expires', ['date' => $share->expires_at->format('d/m/Y H:i')]) }}
        </p>
    @endif
@endsection
