
const safeLog = (..._args: unknown[]) => {};
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
  sendgridApiKey: string;

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
    apiUrl: process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000/api/v1",

    // Analytics
    gaId: process.env.NEXT_PUBLIC_GA_ID || "",
    mixpanelToken: process.env.NEXT_PUBLIC_MIXPANEL_TOKEN || "",

    // Forms
    formEndpoint: process.env.NEXT_PUBLIC_FORM_ENDPOINT || "/api/forms",
    sendgridApiKey: process.env.SENDGRID_API_KEY || "",

    // SEO
    siteUrl: process.env.NEXT_PUBLIC_SITE_URL || "http://localhost:3000",
    siteName: process.env.NEXT_PUBLIC_SITE_NAME || "Leopardo",

    // Feature flags
    enableAnalytics: process.env.NEXT_PUBLIC_ENABLE_ANALYTICS === "true",
    enableForms: process.env.NEXT_PUBLIC_ENABLE_FORMS === "true",
    enableBlog: process.env.NEXT_PUBLIC_ENABLE_BLOG === "true",
  };
}

/**
 * Validate environment variables
 */
export function validateEnv(): { isValid: boolean; errors: string[] } {
  const errors: string[] = [];

  // Required variables
  const required = [
    "NEXT_PUBLIC_SITE_URL",
    "NEXT_PUBLIC_SITE_NAME",
  ];

  required.forEach((key) => {
    if (!process.env[key]) {
      errors.push(`Missing required environment variable: ${key}`);
    }
  });

  // Validate URLs
  if (process.env.NEXT_PUBLIC_SITE_URL) {
    try {
      new URL(process.env.NEXT_PUBLIC_SITE_URL);
    } catch {
      errors.push("NEXT_PUBLIC_SITE_URL is not a valid URL");
    }
  }

  if (process.env.NEXT_PUBLIC_API_URL) {
    try {
      new URL(process.env.NEXT_PUBLIC_API_URL);
    } catch {
      errors.push("NEXT_PUBLIC_API_URL is not a valid URL");
    }
  }

  return {
    isValid: errors.length === 0,
    errors,

  };
}

/**
 * Log environment configuration (safe - no secrets)
 */
export function logEnvConfig(): void {
  const config = getEnvConfig();
  safeLog("Environment Configuration:");
  safeLog(`  Site URL: ${config.siteUrl}`);
  safeLog(`  Site Name: ${config.siteName}`);
  safeLog(`  Analytics Enabled: ${config.enableAnalytics}`);
  safeLog(`  Forms Enabled: ${config.enableForms}`);
  safeLog(`  Blog Enabled: ${config.enableBlog}`);
}