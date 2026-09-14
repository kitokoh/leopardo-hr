{{--
    Attribution d’un rôle (issue #7346).

    Trois défauts corrigés au passage :
      1. HTML complet autonome avec un dégradé `linear-gradient` et un
         `display:flex` (non supporté par la plupart des clients de messagerie)
         → layout canonique + boutons en TABLE ;
      2. pied de page FR codé en dur (« Leopardo RH · Votre plateforme RH
         terrain ») et aucun lien de contact → pied de page unique du layout,
         avec la vraie adresse de support ;
      3. URL produit codée en dur (`https://app.leopardo-rh.com`) → source de
         configuration `mail.brand.website_url`.
--}}

@extends('emails.layouts.base')

@section('heading', $tpl->heading)

@section('content')
    <p style="margin:0 0 16px 0;">
        {{ str_replace(':name', $employee->first_name ?? '', __('emails.role_assignment_greeting')) }}
    </p>

    <div style="margin:0 0 22px 0;">{!! $tpl->bodyHtml() !!}</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f0fdfa; border:1px solid #99f6e4; border-radius:10px; margin:0 0 22px 0;">
        <tr>
            <td style="padding:18px 20px;">
                <p style="margin:0 0 10px 0; color:#0f766e; font-weight:700; font-size:15px;">
                    {{ __('emails.role_assignment_app_title') }}
                </p>
                <p style="margin:0 0 14px 0; font-size:14px; line-height:21px;">
                    {{ strtr(__('emails.role_assignment_app_body'), [
                        ':role' => $roleLabel,
                        ':appName' => $appLinks['name'],
                    ]) }}
                </p>
                <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td style="padding:0 8px 0 0;">
                            @include('emails.partials.button', [
                                'url' => $appLinks['android'],
                                'label' => __('emails.role_assignment_android'),
                                'align' => 'left',
                            ])
                        </td>
                        @if (! empty($appLinks['ios']))
                            <td style="padding:0;">
                                @include('emails.partials.button', [
                                    'url' => $appLinks['ios'],
                                    'label' => __('emails.role_assignment_ios'),
                                    'align' => 'left',
                                ])
                            </td>
                        @endif
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <p style="margin:0; font-size:13px; line-height:20px; color:#64748b;">
        {{ strtr(__('emails.role_assignment_web_note'), [':url' => config('mail.brand.website_url')]) }}
    </p>
@endsection
