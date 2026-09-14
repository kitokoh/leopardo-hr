{{--
    Aperçu (issue #7347) — rendu d’une surcharge dans le VRAI layout, pour que
    l’admin voie exactement ce que le destinataire recevra.
--}}
@extends('emails.layouts.base')

@section('heading', $heading)

@section('content')
    <div style="margin:0 0 4px 0;">{!! $bodyHtml !!}</div>

    @if (! empty($ctaLabel))
        @include('emails.partials.button', [
            'url' => $ctaUrl ?? config('mail.brand.website_url'),
            'label' => $ctaLabel,
            'align' => 'center',
        ])
    @endif
@endsection
