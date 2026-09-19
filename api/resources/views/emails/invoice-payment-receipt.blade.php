{{--
    Reçu de paiement (#7763).

    Envoyé sur la transition unique `Invoice::transitionTo(Paid)` (Stripe et
    Chargily confondus). Layout canonique (issue #7346), corps surchargeable
    depuis l'admin via EmailTemplateResolver, PDF joint par le Mailable.
--}}

@extends('emails.layouts.base')

@section('heading', $tpl->heading)

@section('content')
    <p style="margin:0 0 16px 0;">
        {{ str_replace(':name', $recipient->first_name ?? '', __('emails.invoice_payment_receipt_greeting')) }}
    </p>

    <div style="margin:0 0 16px 0;">{!! $tpl->bodyHtml() !!}</div>

    <p style="margin:0; font-size:13px; line-height:20px; color:#64748b;">
        {{ __('emails.invoice_payment_receipt_attachment_note') }}
    </p>
@endsection
