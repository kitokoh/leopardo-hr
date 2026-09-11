import { getSiteUrl } from '@/lib/site-url';

/**
 * Environment variables validation and access
 */

export interface EnvironmentConfig {
  // API
  apiUrl: string;

  // Analytics
  gaId: string;
  mixpanelToken: string;

  // Forms
  formEndpoint: string;

  // SEO
  siteUrl: string;
  siteName: string;

  // Feature flags
  enableAnalytics: boolean;
  enableForms: boolean;
  enableBlog: boolean;
}

/**
 * Get environment configuration
 */
export function getEnvConfig(): EnvironmentConfig {
  return {
    // API
    apiUrl: process.env.NEXT_PUBLIC_API_URL || "/api/v1",

    // Analytics
    gaId: process.env.NEXT_PUBLIC_GA_ID || "",
    mixpanelToken: process.env.NEXT_PUBLIC_MIXPANEL_TOKEN || "",

    // Forms
    formEndpoint: process.env.NEXT_PUBLIC_FORM_ENDPOINT || "/api/forms",

    // SEO
    siteUrl: getSiteUrl(),
    siteName: process.env.NEXT_PUBLIC_SITE_NAME || "Leopardo",

    // Feature flags
    enableAnalytics: process.env.NEXT_PUBLIC_ENABLE_ANALYTICS === "true",
    // Aligné sur le runtime serveur (src/app/api/forms/_lib/lead-capture.ts) et
    // sur .env.local.example : le flag est ON par défaut et n'est désactivé que
    // par un `false` explicite. Avant, le client testait `=== "true"` (donc OFF
    // si non défini) alors que le serveur testait `!== "false"` (donc ON) : deux
    // défauts contradictoires pour le même flag.
    enableForms: process.env.NEXT_PUBLIC_ENABLE_FORMS !== "false",
    // #2906 : blog activé par défaut — contenu prêt, sitemap existant.
    // Pour désactiver explicitement : NEXT_PUBLIC_ENABLE_BLOG=false.
    enableBlog: process.env.NEXT_PUBLIC_ENABLE_BLOG !== "false",
  };
}
