export type AppLocale = 'fr' | 'ar' | 'tr' | 'en';

export type StoredAuthUser = {
  id?: number | string;
  first_name?: string | null;
  last_name?: string | null;
  name?: string | null;
  email?: string | null;
  language?: string | null;
  is_rtl?: boolean;
  role?: string | null;
  manager_role?: string | null;
};

export const SUPPORTED_LOCALES: AppLocale[] = ['fr', 'ar', 'tr', 'en'];
export const AUTH_TOKEN_KEY = 'auth_token';
export const AUTH_USER_KEY = 'auth_user';
export const PREFERRED_LOCALE_KEY = 'preferred_locale';

type CopyTree = {
  login: {
    title: string;
    back: string;
    email: string;
    password: string;
    remember: string;
    forgot: string;
    submit: string;
    loading: string;
  };
  dashboard: {
    heading: string;
    employees: string;
    present: string;
    late: string;
    activity: string;
    team: string;
    attendance: string;
    payroll: string;
    settings: string;
    logout: string;
    language: string;
    presentBadge: string;
    employeeLabel: string;
    checkInAt: string;
  };
};

const copy: Record<AppLocale, CopyTree> = {
  fr: {
    login: {
      title: 'Connexion a Leopardo RH',
      back: 'Retour au site',
      email: 'Adresse email',
      password: 'Mot de passe',
      remember: 'Se souvenir de moi',
      forgot: 'Mot de passe oublie ?',
      submit: 'Se connecter',
      loading: 'Connexion...',
    },
    dashboard: {
      heading: 'Tableau de bord',
      employees: 'Employes actifs',
      present: 'presents',
      late: 'Retards',
      activity: 'Activite recente',
      team: 'Employes',
      attendance: 'Pointages',
      payroll: 'Paie',
      settings: 'Parametres',
      logout: 'Deconnexion',
      language: 'Langue',
      presentBadge: 'Present',
      employeeLabel: 'Employe',
      checkInAt: 'Check-in a',
    },
  },
  ar: {
    login: {
      title: 'تسجيل الدخول إلى ليوباردو',
      back: 'العودة إلى الموقع',
      email: 'البريد الإلكتروني',
      password: 'كلمة المرور',
      remember: 'تذكرني',
      forgot: 'هل نسيت كلمة المرور؟',
      submit: 'تسجيل الدخول',
      loading: 'جار تسجيل الدخول...',
    },
    dashboard: {
      heading: 'لوحة التحكم',
      employees: 'الموظفون النشطون',
      present: 'حاضرون',
      late: 'التأخير',
      activity: 'النشاط الأخير',
      team: 'الموظفون',
      attendance: 'الحضور',
      payroll: 'الرواتب',
      settings: 'الإعدادات',
      logout: 'تسجيل الخروج',
      language: 'اللغة',
      presentBadge: 'حاضر',
      employeeLabel: 'موظف',
      checkInAt: 'تسجيل الدخول عند',
    },
  },
  tr: {
    login: {
      title: 'Leopardo IK girisi',
      back: 'Siteye don',
      email: 'E-posta',
      password: 'Sifre',
      remember: 'Beni hatirla',
      forgot: 'Sifremi unuttum?',
      submit: 'Giris yap',
      loading: 'Giris yapiliyor...',
    },
    dashboard: {
      heading: 'Kontrol paneli',
      employees: 'Aktif calisanlar',
      present: 'mevcut',
      late: 'Gecikmeler',
      activity: 'Son etkinlik',
      team: 'Calisanlar',
      attendance: 'Devam',
      payroll: 'Bordro',
      settings: 'Ayarlar',
      logout: 'Cikis yap',
      language: 'Dil',
      presentBadge: 'Burada',
      employeeLabel: 'Calisan',
      checkInAt: 'Giris saati',
    },
  },
  en: {
    login: {
      title: 'Sign in to Leopardo RH',
      back: 'Back to site',
      email: 'Email address',
      password: 'Password',
      remember: 'Remember me',
      forgot: 'Forgot password?',
      submit: 'Sign in',
      loading: 'Signing in...',
    },
    dashboard: {
      heading: 'Dashboard',
      employees: 'Active employees',
      present: 'present',
      late: 'Late arrivals',
      activity: 'Recent activity',
      team: 'Employees',
      attendance: 'Attendance',
      payroll: 'Payroll',
      settings: 'Settings',
      logout: 'Sign out',
      language: 'Language',
      presentBadge: 'Present',
      employeeLabel: 'Employee',
      checkInAt: 'Check-in at',
    },
  },
};

export function isSupportedLocale(value: unknown): value is AppLocale {
  return typeof value === 'string' && SUPPORTED_LOCALES.includes(value as AppLocale);
}

export function normalizeLocale(value: unknown): AppLocale {
  if (typeof value !== 'string' || value.trim() === '') {
    return 'fr';
  }

  const normalized = value.toLowerCase().slice(0, 2);
  return isSupportedLocale(normalized) ? normalized : 'fr';
}

export function getLocaleDirection(locale: AppLocale, isRtl?: boolean): 'ltr' | 'rtl' {
  return isRtl === true || locale === 'ar' ? 'rtl' : 'ltr';
}

export function getCopy(locale: AppLocale) {
  return copy[locale];
}

export function getStoredUser(): StoredAuthUser | null {
  if (typeof window === 'undefined') return null;

  const raw = window.localStorage.getItem(AUTH_USER_KEY);
  if (!raw) return null;

  try {
    return JSON.parse(raw) as StoredAuthUser;
  } catch {
    clearAuthSession();
    return null;
  }
}

export function getPreferredLocale(): AppLocale {
  if (typeof window === 'undefined') return 'fr';

  const storedUser = getStoredUser();
  if (storedUser?.language) {
    return normalizeLocale(storedUser.language);
  }

  const raw = window.localStorage.getItem(PREFERRED_LOCALE_KEY);
  if (raw) {
    return normalizeLocale(raw);
  }

  return normalizeLocale(window.navigator.language);
}

export function storePreferredLocale(locale: AppLocale): void {
  if (typeof window === 'undefined') return;
  window.localStorage.setItem(PREFERRED_LOCALE_KEY, locale);
}

export function storeAuthSession(token: string, user: StoredAuthUser): void {
  if (typeof window === 'undefined') return;
  window.localStorage.setItem(AUTH_TOKEN_KEY, token);
  window.localStorage.setItem(AUTH_USER_KEY, JSON.stringify(user));
  storePreferredLocale(normalizeLocale(user.language));
}

export function clearAuthSession(): void {
  if (typeof window === 'undefined') return;
  window.localStorage.removeItem(AUTH_TOKEN_KEY);
  window.localStorage.removeItem(AUTH_USER_KEY);
  window.localStorage.removeItem(PREFERRED_LOCALE_KEY);
}

export function applyDocumentLocale(locale: AppLocale, isRtl?: boolean): void {
  if (typeof document === 'undefined') return;
  document.documentElement.lang = locale;
  document.documentElement.dir = getLocaleDirection(locale, isRtl);
}

export function getDisplayName(user?: StoredAuthUser | null): string {
  if (!user) return 'Leopardo RH';

  const fullName = `${user.first_name ?? ''} ${user.last_name ?? ''}`.trim();
  return fullName || user.name || user.email || 'Leopardo RH';
}

export function getApiErrorMessage(payload: unknown, fallback: string): string {
  if (!payload || typeof payload !== 'object') {
    return fallback;
  }

  const data = payload as Record<string, unknown>;

  if (typeof data.localized_message === 'string' && data.localized_message.trim() !== '') {
    return data.localized_message;
  }

  if (typeof data.message === 'string' && data.message.trim() !== '') {
    return data.message;
  }

  if (typeof data.error === 'string' && data.error.trim() !== '') {
    return data.error;
  }

  return fallback;
}
