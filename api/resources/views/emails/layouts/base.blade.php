{{--
    Layout e-mail canonique (issue #7346).

    TOUS les e-mails clients passent par ici : une seule charte, un seul pied de
    page, un seul en-tête. Avant ce layout, `resources/views/emails/**` contenait
    4 systèmes parallèles (5 vues HTML autonomes, 9 vues Markdown au thème Laravel
    par défaut avec pied de page ANGLAIS, 6 fragments nus), 6 couleurs primaires
    et 3 piles de polices.

    Règles de conception (contraintes réelles des clients de messagerie) :
      - structure en TABLE + styles INLINE : Gmail supprime fréquemment les blocs
        `<style>`, ce qui faisait perdre boutons et pied de page ;
      - pas de `display:flex` (non supporté) ;
      - pré-en-tête masqué pour maîtriser l’aperçu en boîte de réception ;
      - `dir="rtl"` + alignement déduits de la locale (l’arabe était géré au cas
        par cas, parfois pas du tout) ;
      - images facultatives : les clients bloquent les images, le nom de marque
        est donc TOUJOURS affiché en texte.

    Sections disponibles :
      @section('preheader')     – texte d’aperçu (défaut : le titre)
      @section('heading')       – titre de l’e-mail (défaut : sujet)
      @section('content')       – corps (obligatoire)
      @section('footer_extra')  – ligne additionnelle avant le pied de page
      $unsubscribeUrl           – si fourni, affiche le lien de désinscription
      $supportAddress           – adresse de contact (défaut : config mail.brand)

    Aucun littéral : tout texte passe par le catalogue (garde I18N CI).
--}}
@php
    $brandName = \App\Core\Mail\MailBrand::name();
    $brandTagline = config('mail.brand.tagline');
    $brandLogo = config('mail.brand.logo_url');
    $primary = config('mail.brand.primary_color', '#0d9488');
    $primaryDark = config('mail.brand.primary_dark_color', '#042f2e');
    $supportAddress = $supportAddress ?? config('mail.brand.support_address');
    $legalName = config('mail.brand.legal_name');
    $legalAddress = config('mail.brand.legal_address');
    $brandUrl = config('mail.brand.website_url');

    $mailLocale = $locale ?? app()->getLocale();
    $isRtl = in_array($mailLocale, ['ar'], true);
    $dir = $isRtl ? 'rtl' : 'ltr';
    $textAlign = $isRtl ? 'right' : 'left';

    $pageTitle = trim($__env->yieldContent('heading')) ?: ($subject ?? $brandName);
    $preheaderText = trim($__env->yieldContent('preheader')) ?: $pageTitle;

    $fontStack = \App\Core\Mail\MailBrand::fontStack();
    // Styles composés calculés ici : interpoler une expression DANS un attribut
    // `style="…"` fait apparaître une chaîne non déclarative à la garde i18n.
    $logoStyle = $isRtl
        ? 'display:block; margin-right:0; margin-bottom:8px; border-radius:8px;'
        : 'display:block; margin-left:0; margin-bottom:8px; border-radius:8px;';
    $supportHref = \App\Core\Mail\MailBrand::supportHref();
    $textColor = '#334155';
    $mutedColor = '#64748b';
    $surfaceColor = '#f8fafc';
    $borderColor = '#e2e8f0';
@endphp
<!DOCTYPE html>
<html lang="{{ $mailLocale }}" dir="{{ $dir }}" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="{{ \App\Core\Mail\MailBrand::viewport() }}">
    <title>{{ $pageTitle }}</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        /* Uniquement des améliorations progressives : le rendu de base tient
           sans ce bloc (styles inline ci-dessous). */
        body { margin: 0; padding: 0; width: 100% !important; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table { border-collapse: collapse; }
        img { border: 0; line-height: 100%; outline: none; text-decoration: none; }
        a { text-decoration: underline; }
        @media (max-width: 620px) {
            .email-shell { width: 100% !important; }
            .email-pad { padding-left: 20px !important; padding-right: 20px !important; }
            .email-title { font-size: 22px !important; line-height: 28px !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; background-color:{{ $surfaceColor }}; color:{{ $textColor }}; font-family:{{ $fontStack }};">
    {{-- Pré-en-tête : masqué dans le corps, affiché par les clients dans la liste. --}}
    <div style="display:none; font-size:1px; color:{{ $surfaceColor }}; line-height:1px; max-height:0; max-width:0; opacity:0; overflow:hidden; mso-hide:all;">
        {{ $preheaderText }}
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:{{ $surfaceColor }}; width:100%;">
        <tr>
            <td align="center" style="padding:24px 12px;">
                <table role="presentation" class="email-shell" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px; max-width:600px; background-color:#ffffff; border:1px solid {{ $borderColor }}; border-radius:12px; overflow:hidden;">

                    {{-- En-tête de marque --}}
                    <tr>
                        <td class="email-pad" dir="{{ $dir }}" align="{{ $textAlign }}" style="background-color:{{ $primaryDark }}; padding:22px 32px;">
                            @if ($brandLogo)
                                <img src="{{ $brandLogo }}" alt="{{ $brandName }}" width="40" height="40" style="{{ $logoStyle }}">
                            @endif
                            <span style="display:block; color:#ffffff; font-size:18px; font-weight:700; letter-spacing:0.3px;">{{ $brandName }}</span>
                            @if ($brandTagline)
                                <span style="display:block; color:#99f6e4; font-size:12px; margin-top:2px;">{{ $brandTagline }}</span>
                            @endif
                        </td>
                    </tr>

                    {{-- Titre --}}
                    <tr>
                        <td class="email-pad" dir="{{ $dir }}" align="{{ $textAlign }}" style="padding:28px 32px 0 32px;">
                            <h1 class="email-title" style="margin:0; font-size:24px; line-height:32px; font-weight:700; color:#0f172a;">@yield('heading', $pageTitle)</h1>
                        </td>
                    </tr>

                    {{-- Corps --}}
                    <tr>
                        <td class="email-pad" dir="{{ $dir }}" align="{{ $textAlign }}" style="padding:16px 32px 28px 32px; font-size:15px; line-height:24px; color:{{ $textColor }};">
                            @yield('content')
                        </td>
                    </tr>

                    @hasSection('footer_extra')
                        <tr>
                            <td class="email-pad" dir="{{ $dir }}" align="{{ $textAlign }}" style="padding:0 32px 24px 32px; font-size:13px; line-height:20px; color:{{ $mutedColor }};">
                                @yield('footer_extra')
                            </td>
                        </tr>
                    @endif

                    {{-- Pied de page unique --}}
                    <tr>
                        <td class="email-pad" dir="{{ $dir }}" align="{{ $textAlign }}" style="background-color:{{ $surfaceColor }}; border-top:1px solid {{ $borderColor }}; padding:20px 32px; font-size:12px; line-height:18px; color:{{ $mutedColor }};">
                            <p style="margin:0 0 6px 0; color:#0f172a; font-weight:600;">{{ $brandName }}</p>
                            <p style="margin:0 0 6px 0;">{{ __('emails.layout_footer_context', ['brand' => $brandName], $mailLocale) }}</p>
                            <p style="margin:0 0 6px 0;">
                                {{ __('emails.layout_footer_support', [], $mailLocale) }}
                                <a href="{{ $supportHref }}" style="color:{{ $primary }};">{{ $supportAddress }}</a>
                            </p>
                            @if ($legalName || $legalAddress)
                                <p style="margin:0 0 6px 0;">{{ trim(($legalName ? $legalName.($legalAddress ? ' — ' : '') : '').($legalAddress ?? '')) }}</p>
                            @endif
                            @if (!empty($unsubscribeUrl))
                                <p style="margin:0 0 6px 0;">
                                    <a href="{{ $unsubscribeUrl }}" style="color:{{ $mutedColor }};">{{ __('emails.communication_unsubscribe_link', [], $mailLocale) }}</a>
                                </p>
                            @endif
                            <p style="margin:0;">
                                &copy; {{ date('Y') }} {{ $brandName }}.
                                {{ __('emails.layout_rights_reserved', [], $mailLocale) }}
                                @if ($brandUrl)
                                    &middot; <a href="{{ $brandUrl }}" style="color:{{ $mutedColor }};">{{ $brandUrl }}</a>
                                @endif
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
