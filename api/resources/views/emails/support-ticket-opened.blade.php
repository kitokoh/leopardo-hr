{{--
    Nouveau ticket support (issue #7760).

    Notification interne : envoyée à chaque super-admin porteur de la
    permission plateforme `support.manage` quand un client ouvre un ticket.
    Le corps ne recopie jamais le message du ticket (l'e-mail est une
    notification, pas un canal de données) : la conversation se lit dans la
    console plateforme. Contenu résolu par EmailTemplateResolver (#7347) —
    surcharge admin possible, défauts dans `api/lang/<locale>/emails.php`.
--}}

@extends('emails.layouts.base')

@section('heading', $tpl->heading)

@section('content')
    <div style="margin:0 0 16px 0;">{!! $tpl->bodyHtml() !!}</div>

    <p style="margin:0; font-size:13px; line-height:20px; color:#64748b;">{{ __('emails.support_ticket_opened_footer') }}</p>
@endsection
