export type AppLocale = 'fr' | 'en';

const translations = {
  fr: {
    dashboard: {
      title: 'Tableau de bord',
      welcome: 'Bienvenue ! Voici ce qui se passe aujourd\'hui.',
    },
  },
  en: {
    dashboard: {
      title: 'Dashboard',
      welcome: 'Welcome! Here\'s what\'s happening today.',
    },
  },
};

export function getCopy(locale: AppLocale) {
  return translations[locale] || translations.fr;
}

export function getPreferredLocale(): AppLocale {
  if (typeof window === 'undefined') return 'fr';

  const stored = localStorage.getItem('preferred-locale');
  if (stored && (stored === 'fr' || stored === 'en')) {
    return stored as AppLocale;
  }

  const browserLang = navigator.language.split('-')[0];
  return browserLang === 'en' ? 'en' : 'fr';
}