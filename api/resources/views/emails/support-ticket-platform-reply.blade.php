{{--
    Réponse du support à un ticket client (issue #7760).

    Envoyée à l'employé auteur du ticket, dans SA langue (préférence de
    l'employé, sinon langue du tenant — même résolution que user-invitation).
    Le corps ne recopie jamais la réponse : elle se lit dans l'espace client
    (page Support). Contenu résolu par EmailTemplateResolver (#7347).
--}}

@extends('emails.layouts.base')

@section('heading', $tpl->heading)

@section('content')
    <p style="margin:0 0 16px 0;">
        {{ str_replace(':name', $recipient->first_name ?? '', __('emails.support_ticket_platform_reply_greeting')) }}
    </p>

    <div style="margin:0 0 16px 0;">{!! $tpl->bodyHtml() !!}</div>

    <p style="margin:0; font-size:13px; line-height:20px; color:#64748b;">{{ __('emails.support_ticket_platform_reply_footer') }}</p>
@endsection
