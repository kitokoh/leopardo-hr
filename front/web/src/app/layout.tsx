import { LocaleSsrProvider } from "@/modules/vitrine/lib/locale-ssr-provider";
import type { Metadata } from "next";
import Script from "next/script";
import { cache } from "react";
import { headers } from "next/headers";

import "./globals.css";
import { LocaleSync } from "@/components/locale-sync";
import { PWAProvider } from "@/components/PWAProvider";
import { DarkModeProvider } from "@/components/DarkModeProvider";
import { OrganizationJsonLd } from "@/components/JsonLd";

import { inter } from '@/lib/fonts';
import { SITE_URL as siteUrl } from '@/lib/site-url';
import { t } from '@/lib/i18n/locale-catalog';
import { pageMetadataI18n, rootSeoL10n } from '@/modules/vitrine/lib/seo';
import type { AppLocale } from '@/lib/i18n';

// #3807 : og:locale doit suivre la locale SSR réelle (Accept-Language) au lieu
// de fr_FR codé en dur (deep-merge racine qui faussait toutes les pages
// en/tr/ar). Le cache React garantit une seule lecture de headers() par requête
// entre generateMetadata et RootLayout.
const getSsrLocale = cache(async (): Promise<AppLocale> => {
  const headerList = await headers();
  // #4173 : `?lang=` (normalisé par le middleware en x-vitrine-lang, #4004)
  // prime sur Accept-Language au SSR — les crawlers qui suivent les variantes
  // hreflang `/?lang=en|tr|ar` reçoivent enfin du HTML lang/dir cohérent.
  const urlLang = headerList.get('x-vitrine-lang');
  return ((urlLang as AppLocale | null) ?? resolveSsrLang(headerList.get('accept-language'))) as AppLocale;
});

function ogLocale(locale: AppLocale): string {
  const map: Record<AppLocale, string> = {
    fr: 'fr_FR',
    en: 'en_US',
    tr: 'tr_TR',
    ar: 'ar_AR',
  };
  return map[locale];
}

// #4300 : metadata racine localisées selon la locale SSR (?lang= / Accept-Language).
const ROOT_METADATA: Record<AppLocale, { title: string; description: string }> = {
  fr: {
    title: 'Leopardo RH - SaaS RH multilingue pour equipes terrain',
    description:
      'Leopardo RH centralise pointage, paie, absences, onboarding, notifications et operations terrain sur web, mobile et kiosque.',
  },
  en: {
    title: 'Leopardo RH - Multilingual HR SaaS for field teams',
    description:
      'Leopardo RH centralizes attendance, payroll, leave, onboarding, notifications and field operations across web, mobile and kiosk.',
  },
  tr: {
    title: 'Leopardo RH - Saha ekipleri icin cok dilli IK SaaS',
    description:
      'Leopardo RH; yoklama, maaş, izin, onboarding, bildirim ve saha operasyonlarını web, mobil ve kiosk üzerinden merkezileştirir.',
  },
  ar: {
    title: 'Leopardo RH - نظام موارد بشرية سحابي متعدد اللغات للفرق الميدانية',
    description:
      'يجمع Leopardo RH الحضور والرواتب والإجازات والتأهيل والإشعارات والعمليات الميدانية عبر الويب والجوال وجهاز الحضور.',
  },
};

export async function generateMetadata(): Promise<Metadata> {
  const ssrLocale = await getSsrLocale();

  // #4405 : title/description localisés (en/tr/ar) — avant : FR en dur pour
  // toutes les locales (catalogue pageMetadataI18n jamais appliqué à /).
  const landingMeta = pageMetadataI18n[ssrLocale as 'en' | 'tr' | 'ar']?.landing;
  const title = landingMeta?.title ?? "Leopardo RH - SaaS RH multilingue pour equipes terrain";
  const description = landingMeta?.description
    ?? "Leopardo RH centralise pointage, paie, absences, onboarding, notifications et operations terrain sur web, mobile et kiosque.";
  // #4707 : keywords + alt de l'image sociale localisés (avant : FR pour
  // toutes les locales — la meta keywords et l'alt OG étaient les derniers
  // résidus FR de la metadata racine). Données dans seo.ts (hors surface de
  // la garde check-i18n-diff).
  const rootL10n = rootSeoL10n[ssrLocale] ?? rootSeoL10n.fr;

  return {
    title: {
      default: title,
      // #4612 : template localisé — les titres de page n'embarquent plus la
      // marque (retirée des catalogues) ; le template la pose dans la langue
      // de la page pour éviter doublon + mix FR/autre.
      template: `%s | ${
        ssrLocale === 'en'
          ? 'Leopardo HR'
          : ssrLocale === 'tr'
            ? 'Leopardo İK'
            : ssrLocale === 'ar'
              ? 'ليوباردو'
              : 'Leopardo RH'
      }`,
    },
    description,
    keywords: rootL10n.keywords,
    manifest: "/manifest",
    metadataBase: new URL(siteUrl),
    icons: {
      icon: [
        { url: "/icon.svg", type: "image/svg+xml" },
        { url: "/favicon.svg", type: "image/svg+xml" },
      ],
      // Issue #2756 — iOS exige un PNG 180×180 pour apple-touch-icon (SVG ignoré).
      apple: [{ url: "/apple-touch-icon.png", type: "image/png" }],
    },
    openGraph: {
      type: 'website',
      locale: ogLocale(ssrLocale),
      siteName: 'Leopardo RH',
      title,
      description,
      url: siteUrl,
      images: [
        {
          url: '/opengraph-image',
          width: 1200,
          height: 630,
          alt: rootL10n.ogImageAlt,
        },
      ],
    },
    twitter: {
      card: 'summary_large_image',
      title,
      description,
      images: ['/twitter-image'],
    },
    appleWebApp: {
      capable: true,
      statusBarStyle: "black-translucent",
      title: "Leopardo RH",
    },
    formatDetection: {
      telephone: false,
    },
    alternates: {
      // QA 2026-08-15 (#2656) : le layout racine n'épingle plus de canonical
      // global — chaque page porte le sien (sinon toutes les pages sans
      // metadata propre émettaient canonical = homepage).
      canonical: siteUrl,
      // QA 2026-08-15 (#3417) : la homepage (client component, pas de metadata
      // propre) doit émettre les alternates hreflang comme le sitemap.xml —
      // aligné sur la logique de generateMetadata (seo.ts).
      languages: {
        fr: siteUrl,
        en: `${siteUrl}/?lang=en`,
        tr: `${siteUrl}/?lang=tr`,
        ar: `${siteUrl}/?lang=ar`,
      },
    },
  };
}

// QA 2026-08-15 (#2657) : l'attribut lang est posé au SSR à partir de
// Accept-Language (normalisé fr/en/ar/tr, défaut fr) au lieu de « fr »
// codé en dur — les crawlers voyaient lang=fr sur du contenu en/tr/ar.
// Le client corrige ensuite via LocaleSync (préférence localStorage).
function resolveSsrLang(acceptLanguage: string | null): string {
  const base = (acceptLanguage ?? '')
    .split(',')[0]
    .trim()
    .toLowerCase()
    .slice(0, 2);

  return ['fr', 'en', 'ar', 'tr'].includes(base) ? base : 'fr';
}

// Issue #2719 — dir SSR : l'arabe est rendu RTL (pas de FOUC ltr→rtl).
function resolveSsrDir(lang: string): 'rtl' | 'ltr' {
  return lang === 'ar' ? 'rtl' : 'ltr';
}

export default async function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  // Issue #2719 — lang/dir calculés par requête (Accept-Language) au SSR :
  // plus de lang="fr" systématique pour les visiteurs en/tr/ar (LocaleSync
  // ajuste ensuite côté client selon les préférences utilisateur).
  const ssrLang = await getSsrLocale();
  // Analytics scripts (GA4, Mixpanel) are only loaded when the vitrine
  // feature flag is explicitly enabled. Previously `gaId`/`mixpanelToken`
  // were read and injected independently of `NEXT_PUBLIC_ENABLE_ANALYTICS`,
  // so setting the flag to `false` (its documented default) had no effect
  // on whether these third-party trackers actually loaded (issue #1305).
  const analyticsEnabled = process.env.NEXT_PUBLIC_ENABLE_ANALYTICS === 'true';
  const gaId = analyticsEnabled ? process.env.NEXT_PUBLIC_GA_ID : undefined;
  const mixpanelToken = analyticsEnabled ? process.env.NEXT_PUBLIC_MIXPANEL_TOKEN : undefined;

  return (
    <html lang={ssrLang} dir={resolveSsrDir(ssrLang)} className={inter.variable} suppressHydrationWarning>
      <head>
        <meta name="theme-color" content="#10b981" />
        <meta name="mobile-web-app-capable" content="yes" />
        <meta name="apple-mobile-web-app-capable" content="yes" />
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
        <meta name="apple-mobile-web-app-title" content="Leopardo" />
        <link rel="apple-touch-icon" href="/apple-touch-icon.png" />
        <link rel="icon" type="image/svg+xml" href="/icon.svg" />

        {gaId && (
          <>
            <Script
              src={`https://www.googletagmanager.com/gtag/js?id=${gaId}`}
              strategy="afterInteractive"
            />
            <Script
              id="google-analytics"
              strategy="afterInteractive"
              dangerouslySetInnerHTML={{
                __html: `
                  window.dataLayer = window.dataLayer || [];
                  function gtag(){dataLayer.push(arguments);}
                  gtag('js', new Date());
                  gtag('config', '${gaId}', {
                    page_path: window.location.pathname,
                    anonymize_ip: true,
                  });
                `,
              }}
            />
          </>
        )}

        {mixpanelToken && (
          <Script
            id="mixpanel"
            strategy="afterInteractive"
            dangerouslySetInnerHTML={{
              __html: `
                (function(f,b){if(!b.__SV){var e,g,i,h;window.mixpanel=b;b._i=[];b.init=function(e,f,c){function g(a,d){var b=d.split(".");2==b.length&&(a=a[b[0]],d=b[1]);a[d]=function(){a.push([d].concat(Array.prototype.slice.call(arguments,0)))}}var a=b;"undefined"!=typeof c?a=b[c]=[]:c="mixpanel";a.people=a.people||[];a.toString=function(a){var d="mixpanel";"mixpanel"!=c&&(d+="."+c);a||(d+=" (stub)");return d};a.people.toString=function(){return a.toString(1)};i="disable time_event track track_pageview track_links track_forms track_with_groups add_group set_group remove_group unset_group increment append union track_revenue alias set_once union get_distinct_id get_user_id get_user_properties get_group_properties identify alias reset register register_once unregister opt_in_tracking opt_out_tracking has_opted_in_tracking has_opted_out_tracking clear_opt_in_tracking_cookie clear_opt_out_tracking_cookie".split(" ");for(h=0;h<i.length;h++)g(a,i[h]);b._i.push([e,f,c])};b.__SV=1.2;e=f.createElement("script");e.type="text/javascript";e.async=!0;e.src="undefined"!=typeof MIXPANEL_CUSTOM_LIB_URL?MIXPANEL_CUSTOM_LIB_URL:"file:"===f.location.protocol&&"//cdn4.mxpnl.com/libs/mixpanel-2-latest.min.js".match(/^\\/\\//)?"https://cdn4.mxpnl.com/libs/mixpanel-2-latest.min.js":"//cdn4.mxpnl.com/libs/mixpanel-2-latest.min.js";f=f.getElementsByTagName("script")[0];f.parentNode.insertBefore(e,f)}})(document,window.mixpanel||[]);
                mixpanel.init('${mixpanelToken}', {track_pageview: false});
              `,
            }}
          />
        )}
      </head>
      <body className="font-sans antialiased">
        <a
          href="#main-content"
          className="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-[100] focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-indigo-600 focus:shadow-lg"
        >
          {t(ssrLang, 'a11y.skip_to_content', 'Aller au contenu principal')}
        </a>
        <OrganizationJsonLd locale={ssrLang} />
        <DarkModeProvider>
          <PWAProvider>
            <LocaleSync />
            <main id="main-content" className="flex min-h-screen flex-col">
              <LocaleSsrProvider lang={ssrLang}>
                {children}
              </LocaleSsrProvider>
            </main>
          </PWAProvider>
        </DarkModeProvider>
      </body>
    </html>
  );
}
