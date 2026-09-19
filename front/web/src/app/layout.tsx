import { LocaleSsrProvider } from "@/modules/vitrine/lib/locale-ssr-provider";
import type { Metadata } from "next";
import Script from "next/script";
import { cache } from "react";
import { headers } from "next/headers";

import "./globals.css";
import { LocaleSync } from "@/components/locale-sync";
import { PWAProvider } from "@/components/PWAProvider";
import { DarkModeProvider } from "@/components/DarkModeProvider";
import { ConsentProvider } from "@/modules/vitrine/components/ConsentProvider";
import { ConsentBanner } from "@/modules/vitrine/components/ConsentBanner";
import { ConsentScripts } from "@/modules/vitrine/components/ConsentScripts";
import { OrganizationJsonLd, WebSiteJsonLd } from "@/components/JsonLd";

import { inter } from '@/lib/fonts';
import { SITE_URL as siteUrl } from '@/lib/site-url';
import { verificationMetadata } from '@/lib/seo-verification';
import { t } from '@/lib/i18n/locale-catalog';
import { BRAND_NAME_BY_LOCALE, pageMetadataI18n, rootSeoL10n } from '@/modules/vitrine/lib/seo';
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
// Issue #7428 : les phrases canoniques vivent désormais dans le catalogue
// partagé (`seoRoot.*`, propagé aux 4 locales par `shared/i18n/sync`) — une
// seule source de vérité pour la vitrine, le manifeste PWA et les aperçus
// sociaux, au lieu de copies littérales par surface.
const rootCopy = (locale: AppLocale, key: 'rootTitle' | 'rootDescription'): string =>
  String(t(locale, `seoRoot.${key}`) ?? '');

const ROOT_METADATA: Record<AppLocale, { title: string; description: string }> = {
  fr: { title: rootCopy('fr', 'rootTitle'), description: rootCopy('fr', 'rootDescription') },
  en: { title: rootCopy('en', 'rootTitle'), description: rootCopy('en', 'rootDescription') },
  tr: { title: rootCopy('tr', 'rootTitle'), description: rootCopy('tr', 'rootDescription') },
  ar: { title: rootCopy('ar', 'rootTitle'), description: rootCopy('ar', 'rootDescription') },
};



export async function generateMetadata(): Promise<Metadata> {
  const ssrLocale = await getSsrLocale();

  // #4405 : title/description localisés (en/tr/ar) — avant : FR en dur pour
  // toutes les locales (catalogue pageMetadataI18n jamais appliqué à /).
  const landingMeta = pageMetadataI18n[ssrLocale as 'en' | 'tr' | 'ar']?.landing;
  const title = landingMeta?.title ?? rootCopy(ssrLocale, 'rootTitle');
  const description = landingMeta?.description ?? rootCopy(ssrLocale, 'rootDescription');
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
      // #7708 : marque affichée « Leopardo » seul (décision #7428) — source
      // unique BRAND_NAME_BY_LOCALE (ليوباردو en AR, translittération).
      template: `%s | ${BRAND_NAME_BY_LOCALE[ssrLocale] ?? 'Leopardo'}`,
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
      // #AI-SEO : og:site_name suit la locale (avant : FR en dur sur toutes
      // les langues, alors que le titre et la description étaient localisés).
      siteName: BRAND_NAME_BY_LOCALE[ssrLocale] ?? 'Leopardo',
      title,
      description,
      url: siteUrl,
      images: [
        {
          // #6940 : image OG statique vérifiée 200 (le rendu dynamique
          // /opengraph-image répondait 404 sur DEV et PROD — partage social
          // sans visuel). Les pages qui ont leur propre og:image (/og/*.png)
          // ne sont pas affectées.
          url: '/og/default.png',
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
      images: ['/og/default.png'],
    },
    appleWebApp: {
      capable: true,
      statusBarStyle: "black-translucent",
      title: "Leopardo",
    },
    formatDetection: {
      telephone: false,
    },
    // #SEO-OPS : balises de vérification des consoles (Search Console, Bing).
    verification: verificationMetadata(),
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
        // #AI-SEO : variante de repli pour les langues non couvertes —
        // aligné sur sitemap.ts et sur les alternates de seo.ts.
        'x-default': siteUrl,
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

        {/* #7593 — Consent Mode v2 : le défaut est « refusé » et il est posé
            AVANT tout script de mesure. Les traceurs ne sont injectés qu'après
            un choix explicite (voir ConsentScripts). */}
        <Script id="consent-mode-default" strategy="beforeInteractive">
          {`
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            window.gtag = gtag;
            gtag('consent', 'default', {
              ad_storage: 'denied',
              ad_user_data: 'denied',
              ad_personalization: 'denied',
              analytics_storage: 'denied',
              wait_for_update: 500,
            });
          `}
        </Script>
      </head>
      <body className="font-sans antialiased">
        <a
          href="#main-content"
          className="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-[100] focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-indigo-600 focus:shadow-lg"
        >
          {t(ssrLang, 'a11y.skip_to_content', 'Aller au contenu principal')}
        </a>
        <OrganizationJsonLd locale={ssrLang} />
        {/* #AI-SEO : nœud WebSite racine (identité du site pour les moteurs
            de réponse et l'ancrage des alias de marque). */}
        <WebSiteJsonLd locale={ssrLang} />
        <ConsentProvider>
        <DarkModeProvider>
          <PWAProvider>
            <LocaleSync />
            {/* Traceurs conditionnés au consentement (#7593) */}
            <ConsentScripts gaId={gaId} mixpanelToken={mixpanelToken} />
            <main id="main-content" className="flex min-h-screen flex-col">
              <LocaleSsrProvider lang={ssrLang}>
                {children}
              </LocaleSsrProvider>
            </main>
          </PWAProvider>
        </DarkModeProvider>
        <ConsentBanner />
        </ConsentProvider>
      </body>
    </html>
  );
}
