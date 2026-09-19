{{--
    Facture émise (#7763).

    Layout canonique (issue #7346) : le corps vient du template résolu
    (surchargeable depuis l'admin via EmailTemplateResolver), la facture PDF
    est jointe par le Mailable. Aucun littéral : garde I18N CI.
--}}

@extends('emails.layouts.base')

@section('heading', $tpl->heading)

@section('content')
    <p style="margin:0 0 16px 0;">
        {{ str_replace(':name', $recipient->first_name ?? '', __('emails.invoice_issued_greeting')) }}
    </p>

    <div style="margin:0 0 16px 0;">{!! $tpl->bodyHtml() !!}</div>

    <p style="margin:0; font-size:13px; line-height:20px; color:#64748b;">
        {{ __('emails.invoice_issued_attachment_note') }}
    </p>
@endsection
