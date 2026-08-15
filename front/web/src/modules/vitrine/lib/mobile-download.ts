/**
 * Cible de téléchargement des apps mobiles (Employee / Manager / Platform Admin).
 *
 * Ordre de priorité (identique pour /download et /mobile) :
 * 1. URL configurée via NEXT_PUBLIC_LEOPARDO_*_URL (stores réels) ;
 * 2. Lien Firebase App Distribution (builds de test) ;
 * 3. Fallback AGENTS.md : /signup?source=download_<slug>_<platform> — jamais
 *    d'ancre morte #android-* / #ios-*.
 */

export type MobilePlatform = 'android' | 'ios';
export type MobileAppSlug = 'employee' | 'manager' | 'platform-admin';

export type MobileDownloadTarget = {
  href: string;
  isFallback: boolean;
};

export const mobileDownloadEnv: Record<
  MobileAppSlug,
  Record<MobilePlatform, string | undefined>
> = {
  employee: {
    android: process.env.NEXT_PUBLIC_LEOPARDO_EMPLOYEE_ANDROID_URL,
    ios: process.env.NEXT_PUBLIC_LEOPARDO_EMPLOYEE_IOS_URL,
  },
  manager: {
    android: process.env.NEXT_PUBLIC_LEOPARDO_MANAGER_ANDROID_URL,
    ios: process.env.NEXT_PUBLIC_LEOPARDO_MANAGER_IOS_URL,
  },
  'platform-admin': {
    android: process.env.NEXT_PUBLIC_LEOPARDO_PLATFORM_ADMIN_ANDROID_URL,
    ios: process.env.NEXT_PUBLIC_LEOPARDO_PLATFORM_ADMIN_IOS_URL,
  },
};

export const firebaseTesterLinks: Partial<Record<MobileAppSlug, Partial<Record<MobilePlatform, string>>>> = {
  employee: {
    android: 'https://appdistribution.firebase.dev/i/e2bde6595da9d96e',
  },
  manager: {
    android: 'https://appdistribution.firebase.dev/i/e51102534a5dff22',
  },
  'platform-admin': {
    android: 'https://appdistribution.firebase.dev/i/f37b128b1c89a006',
  },
};

export function mobileDownloadTarget(
  slug: MobileAppSlug,
  platform: MobilePlatform,
): MobileDownloadTarget {
  const configured = mobileDownloadEnv[slug][platform]?.trim()
    || firebaseTesterLinks[slug]?.[platform]?.trim();

  if (configured) {
    return { href: configured, isFallback: false };
  }

  return {
    href: `/signup?source=download_${slug}_${platform}`,
    isFallback: true,
  };
}

export type MobileDownloadLocale = 'fr' | 'en' | 'tr' | 'ar';

export function testerFallbackLabel(locale: MobileDownloadLocale): string {
  switch (locale) {
    case 'en':
      return 'Join the tester list';
    case 'tr':
      return 'Test listesine katil';
    case 'ar':
      return 'انضم إلى قائمة الاختبار';
    default:
      return 'Rejoindre les testeurs';
  }
}

export function firebaseTesterLabel(locale: MobileDownloadLocale): string {
  switch (locale) {
    case 'en':
      return 'Install tester build';
    case 'tr':
      return 'Test surumunu yukle';
    case 'ar':
      return 'تثبيت نسخة الاختبار';
    default:
      return 'Installer la version test';
  }
}

export function mobileDownloadLabel(
  target: MobileDownloadTarget,
  configuredLabel: string,
  locale: MobileDownloadLocale,
): string {
  if (target.isFallback) {
    return testerFallbackLabel(locale);
  }

  if (target.href.includes('appdistribution.firebase.dev')) {
    return firebaseTesterLabel(locale);
  }

  return configuredLabel;
}
