export type AppLocale = 'fr' | 'ar' | 'tr' | 'en';

export const AUTH_TOKEN_KEY = 'auth_token';
const USER_STORAGE_KEY = 'auth_user';
const LOCALE_STORAGE_KEY = 'preferred-locale';

export interface StoredAuthUser {
  id?: number | string;
  first_name?: string | null;
  last_name?: string | null;
  name?: string | null;
  email?: string | null;
  language?: string | null;
  is_rtl?: boolean;
  role?: string | null;
  manager_role?: string | null;
}

const translations = {
  fr: {
    login: {
      title: 'Connexion',
      back: 'Retour au site',
      email: 'Email',
      password: 'Mot de passe',
      remember: 'Se souvenir de moi',
      forgot: 'Mot de passe oublie ?',
      loading: 'Connexion...',
      submit: 'Se connecter',
    },
    dashboard: {
      title: 'Tableau de bord',
      heading: 'Tableau de bord',
      welcome: 'Bienvenue ! Voici ce qui se passe aujourd\'hui.',
      team: 'Equipe',
      attendance: 'Pointages',
      logout: 'Deconnexion',
      language: 'Langue',
      present: 'presents',
    },
  },
  ar: {
    login: {
      title: 'تسجيل الدخول',
      back: 'العودة إلى الموقع',
      email: 'البريد الإلكتروني',
      password: 'كلمة المرور',
      remember: 'تذكرني',
      forgot: 'نسيت كلمة المرور؟',
      loading: 'جار تسجيل الدخول...',
      submit: 'دخول',
    },
    dashboard: {
      title: 'لوحة التحكم',
      heading: 'لوحة التحكم',
      welcome: 'مرحباً! هذا ما يحدث اليوم.',
      team: 'الفريق',
      attendance: 'الحضور',
      logout: 'خروج',
      language: 'اللغة',
      present: 'حاضرون',
    },
  },
  tr: {
    login: {
      title: 'Giris',
      back: 'Siteye don',
      email: 'E-posta',
      password: 'Sifre',
      remember: 'Beni hatirla',
      forgot: 'Sifremi unuttum',
      loading: 'Giris yapiliyor...',
      submit: 'Giris yap',
    },
    dashboard: {
      title: 'Kontrol paneli',
      heading: 'Kontrol paneli',
      welcome: 'Hos geldiniz! Bugun olanlar burada.',
      team: 'Ekip',
      attendance: 'Puantaj',
      logout: 'Cikis',
      language: 'Dil',
      present: 'mevcut',
    },
  },
  en: {
    login: {
      title: 'Sign in',
      back: 'Back to site',
      email: 'Email',
      password: 'Password',
      remember: 'Remember me',
      forgot: 'Forgot password?',
      loading: 'Signing in...',
      submit: 'Sign in',
    },
    dashboard: {
      title: 'Dashboard',
      heading: 'Dashboard',
      welcome: 'Welcome! Here\'s what\'s happening today.',
      team: 'Team',
      attendance: 'Attendance',
      logout: 'Logout',
      language: 'Language',
      present: 'present',
    },
  },
};

export function normalizeLocale(value?: string | null): AppLocale {
  return value === 'ar' || value === 'tr' || value === 'en' ? value : 'fr';
}

export function getCopy(locale: AppLocale) {
  return translations[locale] || translations.fr;
}

export function getPreferredLocale(): AppLocale {
  if (typeof window === 'undefined') return 'fr';
  
  const stored = localStorage.getItem(LOCALE_STORAGE_KEY);
  if (stored) {
    return normalizeLocale(stored);
  }
  
  const browserLang = navigator.language.split('-')[0];
  return normalizeLocale(browserLang);
}

export function storePreferredLocale(locale: AppLocale): void {
  if (typeof window === 'undefined') return;
  localStorage.setItem(LOCALE_STORAGE_KEY, locale);
}

export function applyDocumentLocale(locale: AppLocale, isRtl?: boolean): void {
  if (typeof document === 'undefined') return;
  document.documentElement.lang = locale;
  document.documentElement.dir = isRtl ?? locale === 'ar' ? 'rtl' : 'ltr';
}

export function storeAuthSession(token: string, user: StoredAuthUser): void {
  if (typeof window === 'undefined') return;
  localStorage.setItem(AUTH_TOKEN_KEY, token);
  localStorage.setItem(USER_STORAGE_KEY, JSON.stringify(user));
  storePreferredLocale(normalizeLocale(user.language));
}

export function clearAuthSession(): void {
  if (typeof window === 'undefined') return;
  localStorage.removeItem(AUTH_TOKEN_KEY);
  localStorage.removeItem(USER_STORAGE_KEY);
}

export function getStoredUser(): StoredAuthUser | null {
  if (typeof window === 'undefined') return null;

  const raw = localStorage.getItem(USER_STORAGE_KEY);
  if (!raw) return null;

  try {
    return JSON.parse(raw) as StoredAuthUser;
  } catch {
    clearAuthSession();
    return null;
  }
}

export function getDisplayName(user?: StoredAuthUser | null): string {
  if (!user) return 'Leopardo RH';

  const fullName = [user.first_name, user.last_name].filter(Boolean).join(' ').trim();
  return fullName || user.name || user.email || 'Leopardo RH';
}

export function getApiErrorMessage(payload: unknown, fallback: string): string {
  if (!payload || typeof payload !== 'object') {
    return fallback;
  }

  const data = payload as Record<string, unknown>;

  if (typeof data.localized_message === 'string') return data.localized_message;
  if (typeof data.message === 'string') return data.message;
  if (typeof data.error === 'string') return data.error;

  return fallback;
}
