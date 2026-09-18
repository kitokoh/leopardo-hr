'use client';

import Script from 'next/script';

import { useConsent } from '@/modules/vitrine/components/ConsentProvider';
import { toConsentMode } from '@/modules/vitrine/lib/consent';

/**
 * Injection des traceurs **conditionnée au consentement** (issue #7593).
 *
 * Avant : GA4 et Mixpanel étaient injectés dans le `<head>` du layout racine
 * (`strategy="afterInteractive"`), donc chargés pour tout visiteur, avant même
 * qu'il ait pu exprimer un choix. Ici, rien n'est injecté tant que
 * `analytics` n'est pas accordé — et le Consent Mode v2 est mis à jour au même
 * moment, pour que Google n'écrive de cookie de mesure que sur autorisation.
 */
export function ConsentScripts({
  gaId,
  mixpanelToken,
}: {
  gaId?: string;
  mixpanelToken?: string;
}) {
  const { state } = useConsent();
  const analyticsAllowed = state?.analytics === true;

  if (!analyticsAllowed) return null;

  return (
    <>
      {gaId && (
        <>
          <Script
            id="consent-mode-update"
            strategy="afterInteractive"
            dangerouslySetInnerHTML={{
              __html: `
                window.dataLayer = window.dataLayer || [];
                function gtag(){dataLayer.push(arguments);}
                gtag('consent', 'update', ${JSON.stringify(
                  toConsentMode({ analytics: true, marketing: state?.marketing === true }),
                )});
                gtag('js', new Date());
                gtag('config', '${gaId}', {
                  page_path: window.location.pathname,
                  anonymize_ip: true,
                });
              `,
            }}
          />
          <Script
            src={`https://www.googletagmanager.com/gtag/js?id=${gaId}`}
            strategy="afterInteractive"
          />
        </>
      )}

      {mixpanelToken && (
        <Script
          id="mixpanel"
          strategy="afterInteractive"
          dangerouslySetInnerHTML={{
            __html: `
              (function(f,b){if(!b.__SV){var e,g,i,h;window.mixpanel=b;b._i=[];b.init=function(e,f,c){function g(a,d){var b=d.split(".");2==b.length&&(a=a[b[0]],d=b[1]);a[d]=function(){a.push([d].concat(Array.prototype.slice.call(arguments,0)))}}var a=b;"undefined"!=typeof c?a=b[c]=[]:c="mixpanel";a.people=a.people||[];a.toString=function(a){var d="mixpanel";"mixpanel"!=c&&(d+="."+c);a||(d+=" (stub)");return d};a.people.toString=function(){return a.toString(1)};i="disable time_event track track_pageview track_links track_forms track_with_groups add_group set_group remove_group unset_group increment append union track_revenue alias set_once union get_distinct_id get_user_id get_user_properties get_group_properties get_property get_properties identify alias reset register register_once unregister opt_in_tracking opt_out_tracking has_opted_in_tracking has_opted_out_tracking clear_opt_in_tracking_cookie clear_opt_out_tracking_cookie".split(" ");for(h=0;h<i.length;h++)g(a,i[h]);b._i.push([e,f,c])};b.__SV=1.2;e=f.createElement("script");e.type="text/javascript";e.async=!0;e.src="undefined"!=typeof MIXPANEL_CUSTOM_LIB_URL?MIXPANEL_CUSTOM_LIB_URL:"file:"===f.location.protocol&&"//cdn4.mxpnl.com/libs/mixpanel-2-latest.min.js".match(/^\\/\\//)?"https://cdn4.mxpnl.com/libs/mixpanel-2-latest.min.js":"//cdn4.mxpnl.com/libs/mixpanel-2-latest.min.js";f=f.getElementsByTagName("script")[0];f.parentNode.insertBefore(e,f)}})(document,window.mixpanel||[]);
              mixpanel.init('${mixpanelToken}', {track_pageview: false});
            `,
          }}
        />
      )}
    </>
  );
}
