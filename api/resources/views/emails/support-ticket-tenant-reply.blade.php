{{--
    Réponse d'un client sur un ticket support (issue #7760).

    Notification interne : envoyée au super-admin assigné au ticket
    (`assigned_super_admin_id`), sinon à l'équipe `support.manage`.
    Le corps ne recopie jamais la réponse : elle se lit dans la console
    plateforme. Contenu résolu par EmailTemplateResolver (#7347).
--}}

@extends('emails.layouts.base')

@section('heading', $tpl->heading)

@section('content')
    <div style="margin:0 0 16px 0;">{!! $tpl->bodyHtml() !!}</div>

    <p style="margin:0; font-size:13px; line-height:20px; color:#64748b;">{{ __('emails.support_ticket_tenant_reply_footer') }}</p>
@endsection
