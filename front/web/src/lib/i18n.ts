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
    subtitle: string;
    clientSpace: string;
    heroTitle: string;
    heroCopy: string;
    secureBadge: string;
    trustPoints: string[];
    back: string;
    email: string;
    password: string;
    showPassword: string;
    hidePassword: string;
    remember: string;
    forgot: string;
    submit: string;
    loading: string;
    demoAccess: string;
    demoTitle: string;
    demoSubtitle: string;
    close: string;
    supportCopy: string;
    supportLink: string;
    errors: {
      generic: string;
      missingToken: string;
      missingUser: string;
    };
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
      subtitle: 'Accedez a votre espace RH, suivez vos equipes et pilotez les modules actifs de votre entreprise.',
      clientSpace: 'Espace client',
      heroTitle: 'Un acces RH clair pour chaque manager, chaque pays et chaque equipe.',
      heroCopy: 'Votre portail client reste connecte a l API Leopardo RH, avec permissions, langue et contexte tenant appliques des la connexion.',
      secureBadge: 'Connexion securisee',
      trustPoints: [
        'Session liee a votre tenant',
        'Permissions appliquees par role',
        'Interface prete pour manager, RH et employe',
      ],
      back: 'Retour au site',
      email: 'Adresse email',
      password: 'Mot de passe',
      showPassword: 'Afficher le mot de passe',
      hidePassword: 'Masquer le mot de passe',
      remember: 'Se souvenir de moi',
      forgot: 'Mot de passe oublie ?',
      submit: 'Se connecter',
      loading: 'Connexion...',
      demoAccess: 'Tester avec un compte demo',
      demoTitle: 'Choisir un compte demo',
      demoSubtitle: 'Selectionnez un role pour pre-remplir le formulaire, puis lancez la connexion.',
      close: 'Fermer',
      supportCopy: 'Besoin d aide pour recuperer un acces ?',
      supportLink: 'Contacter le support',
      errors: {
        generic: 'Une erreur est survenue.',
        missingToken: 'Le jeton de connexion est absent de la reponse API.',
        missingUser: 'Le profil utilisateur est absent de la reponse API.',
      },
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
      title: 'تسجيل الدخول إلى Leopardo RH',
      subtitle: 'ادخل إلى مساحة الموارد البشرية مع اللغة والدور والصلاحيات المناسبة.',
      clientSpace: 'مساحة العميل',
      heroTitle: 'دخول واضح وآمن للمديرين وفرق الموارد البشرية والموظفين.',
      heroCopy: 'تطبق البوابة سياق الشركة واللغة والصلاحيات مباشرة بعد تسجيل الدخول.',
      secureBadge: 'تسجيل دخول آمن',
      trustPoints: [
        'جلسة مرتبطة بالشركة',
        'صلاحيات حسب الدور',
        'واجهة تدعم العربية و RTL',
      ],
      back: 'العودة إلى الموقع',
      email: 'البريد الإلكتروني',
      password: 'كلمة المرور',
      showPassword: 'إظهار كلمة المرور',
      hidePassword: 'إخفاء كلمة المرور',
      remember: 'تذكرني',
      forgot: 'نسيت كلمة المرور؟',
      submit: 'تسجيل الدخول',
      loading: 'جار تسجيل الدخول...',
      demoAccess: 'تجربة حساب تجريبي',
      demoTitle: 'اختيار حساب تجريبي',
      demoSubtitle: 'اختر دورا لملء النموذج ثم سجل الدخول.',
      close: 'إغلاق',
      supportCopy: 'تحتاج مساعدة لاسترجاع الدخول؟',
      supportLink: 'اتصل بالدعم',
      errors: {
        generic: 'حدث خطأ.',
        missingToken: 'رمز تسجيل الدخول غير موجود في رد ال API.',
        missingUser: 'ملف المستخدم غير موجود في رد ال API.',
      },
    },
    dashboard: {
      heading: 'لوحة التحكم',
      employees: 'الموظفون النشطون',
      present: 'حاضرون',
      late: 'التأخيرات',
      activity: 'النشاط الأخير',
      team: 'الموظفون',
      attendance: 'الحضور',
      payroll: 'الرواتب',
      settings: 'الإعدادات',
      logout: 'تسجيل الخروج',
      language: 'اللغة',
      presentBadge: 'حاضر',
      employeeLabel: 'موظف',
      checkInAt: 'تسجيل الدخول في',
    },
  },
  tr: {
    login: {
      title: 'Leopardo IK girisi',
      subtitle: 'Sirket alaniniza, ekiplerinize ve aktif IK modullerinize guvenli sekilde erisin.',
      clientSpace: 'Musteri alani',
      heroTitle: 'Her yonetici, ulke ve ekip icin net bir IK girisi.',
      heroCopy: 'Leopardo RH portali giristen itibaren tenant, rol, dil ve izin baglaminizi uygular.',
      secureBadge: 'Guvenli giris',
      trustPoints: [
        'Tenant bazli oturum',
        'Rol bazli izinler',
        'Yonetici, IK ve calisan icin hazir arayuz',
      ],
      back: 'Siteye don',
      email: 'E-posta',
      password: 'Sifre',
      showPassword: 'Sifreyi goster',
      hidePassword: 'Sifreyi gizle',
      remember: 'Beni hatirla',
      forgot: 'Sifremi unuttum?',
      submit: 'Giris yap',
      loading: 'Giris yapiliyor...',
      demoAccess: 'Demo hesapla dene',
      demoTitle: 'Demo hesabi sec',
      demoSubtitle: 'Formu doldurmak icin bir rol secin, sonra girisi baslatin.',
      close: 'Kapat',
      supportCopy: 'Erisim kurtarma icin yardim mi gerekiyor?',
      supportLink: 'Destekle iletisime gec',
      errors: {
        generic: 'Bir hata olustu.',
        missingToken: 'API yanitinda giris tokeni yok.',
        missingUser: 'API yanitinda kullanici profili yok.',
      },
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
      subtitle: 'Access your HR workspace, follow your teams, and manage the modules enabled for your company.',
      clientSpace: 'Client workspace',
      heroTitle: 'A clear HR access point for every manager, country, and team.',
      heroCopy: 'Your client portal stays connected to the Leopardo RH API with tenant context, language, and permissions applied after sign-in.',
      secureBadge: 'Secure sign-in',
      trustPoints: [
        'Session bound to your tenant',
        'Permissions applied by role',
        'Ready for managers, HR and employees',
      ],
      back: 'Back to site',
      email: 'Email address',
      password: 'Password',
      showPassword: 'Show password',
      hidePassword: 'Hide password',
      remember: 'Remember me',
      forgot: 'Forgot password?',
      submit: 'Sign in',
      loading: 'Signing in...',
      demoAccess: 'Try a demo account',
      demoTitle: 'Choose a demo account',
      demoSubtitle: 'Select a role to prefill the form, then sign in.',
      close: 'Close',
      supportCopy: 'Need help recovering access?',
      supportLink: 'Contact support',
      errors: {
        generic: 'Something went wrong.',
        missingToken: 'The login token is missing from the API response.',
        missingUser: 'The authenticated user profile is missing from the API response.',
      },
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
