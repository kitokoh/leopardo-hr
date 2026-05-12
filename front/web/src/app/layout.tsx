import type { Metadata } from "next";
import Script from "next/script";
import "./globals.css";
import { LocaleSync } from "@/components/locale-sync";
import { PWAProvider } from "@/components/PWAProvider";
import { DarkModeProvider } from "@/components/DarkModeProvider";
import { OrganizationJsonLd } from "@/components/JsonLd";

const siteUrl = process.env.NEXT_PUBLIC_SITE_URL || 'https://leopardo.com';

export const metadata: Metadata = {
  title: "Leopardo RH - Plateforme RH multilingue pour PME et groupes terrain",
  description:
    "Leopardo RH centralise pointage, paie, absences, onboarding et operations terrain sur web, mobile et kiosque.",
  manifest: "/manifest.json",
  metadataBase: new URL(siteUrl),
  openGraph: {
    type: 'website',
    locale: 'fr_FR',
    siteName: 'Leopardo RH',
    title: 'Leopardo RH - Plateforme RH multilingue pour PME',
    description: 'Leopardo RH centralise pointage, paie, absences, onboarding et operations terrain.',
    url: siteUrl,
  },
  twitter: {
    card: 'summary_large_image',
    title: 'Leopardo RH - Plateforme RH multilingue pour PME',
    description: 'Leopardo RH centralise pointage, paie, absences, onboarding et operations terrain.',
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
    canonical: "/",
  },
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  const gaId = process.env.NEXT_PUBLIC_GA_ID;
  const mixpanelToken = process.env.NEXT_PUBLIC_MIXPANEL_TOKEN;

  return (
    <html lang="fr" suppressHydrationWarning>
      <head>
        <meta name="theme-color" content="#10b981" />
        <meta name="mobile-web-app-capable" content="yes" />
        <meta name="apple-mobile-web-app-capable" content="yes" />
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
        <meta name="apple-mobile-web-app-title" content="Leopardo" />
        <link rel="apple-touch-icon" href="/icon-192.png" />
        <link rel="icon" type="image/png" sizes="192x192" href="/icon-192.png" />
        <link rel="icon" type="image/png" sizes="512x512" href="/icon-512.png" />

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
        <OrganizationJsonLd />
        <DarkModeProvider>
          <PWAProvider>
            <LocaleSync />
            {children}
          </PWAProvider>
        </DarkModeProvider>
      </body>
    </html>
  );
}
