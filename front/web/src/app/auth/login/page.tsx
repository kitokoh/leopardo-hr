'use client';

import { useCallback, useEffect, useMemo, useState, useSyncExternalStore } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import {
  ArrowRight,
  Chrome,
  Eye,
  EyeOff,
  Globe2,
  Loader2,
  LockKeyhole,
  ShieldCheck,
  Sparkles,
  X,
} from 'lucide-react';
import { ApiError, apiFetch } from '@/lib/api-client';
import { trackClientEvent } from '@/lib/client-analytics';
import {
  applyDocumentLocale,
  getCopy,
  getPreferredLocale,
  normalizeLocale,
  storeAuthSession,
  storePreferredLocale,
  type AppLocale,
  type StoredAuthUser,
} from '@/lib/i18n';

const emptySubscribe = () => () => {};

const ADMIN_FALLBACK_PATH = '/dashboard';

type LoginPayload = {
  data?: StoredAuthUser | { token?: string };
  token?: string;
};

type DemoPersona = {
  email: string;
  name: string;
  role: string;
  managerRole: string | null;
  password: string;
};

type DemoCompany = {
  name: string;
  slug: string;
  country: string;
  users: DemoPersona[];
};

type DemoUsersPayload = {
  data?: {
    companies?: Array<{
      name?: string;
      slug?: string;
      country?: string;
      users?: Array<{
        email?: string;
        name?: string;
        role?: string;
        manager_role?: string | null;
        managerRole?: string | null;
        password?: string;
      }>;
    }>;
  };
};

function extractToken(payload: LoginPayload): string | null {
  if (typeof payload.token === 'string' && payload.token.trim() !== '') {
    return payload.token;
  }

  if (payload.data && typeof payload.data === 'object' && 'token' in payload.data && typeof payload.data.token === 'string') {
    return payload.data.token;
  }

  return null;
}

function resolvePostLoginTarget(user: StoredAuthUser): string {
  if (user.role === 'super_admin') {
    return process.env.NEXT_PUBLIC_ADMIN_URL || ADMIN_FALLBACK_PATH;
  }

  return '/dashboard';
}

function goToPostLoginTarget(target: string, router: ReturnType<typeof useRouter>): void {
  if (/^https?:\/\//.test(target)) {
    window.location.assign(target);
    return;
  }

  router.push(target);
}

const fallbackDemoCompanies: DemoCompany[] = [
  {
    name: 'TechCorp Algerie SARL', slug: 'techcorp-algerie', country: 'DZ',
    users: [
      { email: 'ahmed.benali@techcorp-algerie.dz', name: 'Ahmed Benali', role: 'manager', managerRole: 'principal', password: 'password123' },
      { email: 'fatima.meziane@techcorp-algerie.dz', name: 'Fatima Meziane', role: 'manager', managerRole: 'rh', password: 'password123' },
      { email: 'samir.boukhalfa@techcorp-algerie.dz', name: 'Samir Boukhalfa', role: 'manager', managerRole: 'dept', password: 'password123' },
      { email: 'lina.haddad@techcorp-algerie.dz', name: 'Lina Haddad', role: 'manager', managerRole: 'comptable', password: 'password123' },
      { email: 'karim.aouad@techcorp-algerie.dz', name: 'Karim Aouad', role: 'employee', managerRole: null, password: 'password123' },
    ],
  },
  {
    name: 'PharmaPlus Casablanca', slug: 'pharmaplus-casablanca', country: 'MA',
    users: [
      { email: 'amina.tahiri@pharmaplus.ma', name: 'Amina Tahiri', role: 'manager', managerRole: 'principal', password: 'password123' },
      { email: 'sara.mansouri@pharmaplus.ma', name: 'Sara Mansouri', role: 'manager', managerRole: 'rh', password: 'password123' },
      { email: 'rachid.benjelloun@pharmaplus.ma', name: 'Rachid Benjelloun', role: 'manager', managerRole: 'comptable', password: 'password123' },
      { email: 'youssef.bennani@pharmaplus.ma', name: 'Youssef Bennani', role: 'employee', managerRole: null, password: 'password123' },
    ],
  },
  {
    name: 'DigitalFlow Tunis', slug: 'digitalflow-tunis', country: 'TN',
    users: [
      { email: 'sofiane.mrad@digitalflow.tn', name: 'Sofiane Mrad', role: 'manager', managerRole: 'principal', password: 'password123' },
      { email: 'olfa.trabelsi@digitalflow.tn', name: 'Olfa Trabelsi', role: 'manager', managerRole: 'rh', password: 'password123' },
      { email: 'aziz.khelifi@digitalflow.tn', name: 'Aziz Khelifi', role: 'employee', managerRole: null, password: 'password123' },
    ],
  },
];

function normalizeDemoCompanies(payload: DemoUsersPayload): DemoCompany[] {
  return (payload.data?.companies ?? [])
    .map((company) => ({
      name: company.name ?? 'Demo company',
      slug: company.slug ?? company.name ?? 'demo-company',
      country: company.country ?? 'GLOBAL',
      users: (company.users ?? [])
        .filter((user) => typeof user.email === 'string' && typeof user.password === 'string')
        .map((user) => ({
          email: user.email as string,
          name: user.name ?? (user.email as string),
          role: user.role ?? 'employee',
          managerRole: user.manager_role ?? user.managerRole ?? null,
          password: user.password as string,
        })),
    }))
    .filter((company) => company.users.length > 0);
}

function googleAuthHref(): string {
  const directApi = process.env.NEXT_PUBLIC_API_URL?.replace(/\/$/, '');
  const baseUrl = process.env.NEXT_PUBLIC_API_DIRECT === 'true' && directApi
    ? directApi
    : 'https://gestionemployerbackend.onrender.com/api/v1';

  return `${baseUrl}/auth/google`;
}

export default function LoginPage() {
  const router = useRouter();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [mounted, setMounted] = useState(false);
  const storedLocale = useSyncExternalStore<AppLocale>(emptySubscribe, getPreferredLocale, () => 'fr');
  const [localeOverride, setLocaleOverride] = useState<AppLocale | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [showDemoModal, setShowDemoModal] = useState(false);
  const [demoCompanies, setDemoCompanies] = useState<DemoCompany[]>(fallbackDemoCompanies);
  const locale: AppLocale = localeOverride ?? storedLocale;
  const labels = useMemo(() => getCopy(locale), [locale]);

  useEffect(() => {
    setMounted(true);
  }, []);

  useEffect(() => {
    applyDocumentLocale(locale);
  }, [locale]);

  useEffect(() => {
    let active = true;

    apiFetch('/demo-users', { method: 'GET' }, { maxRetries: 1 })
      .then((response) => response.json() as Promise<DemoUsersPayload>)
      .then((payload) => {
        if (!active) return;
        const companies = normalizeDemoCompanies(payload);
        if (companies.length > 0) {
          setDemoCompanies(companies);
        }
      })
      .catch(() => {
        if (active) {
          setDemoCompanies(fallbackDemoCompanies);
        }
      });

    return () => {
      active = false;
    };
  }, []);

  const handleLocaleChange = (value: string) => {
    const nextLocale = normalizeLocale(value);
    setLocaleOverride(nextLocale);
    storePreferredLocale(nextLocale);
    applyDocumentLocale(nextLocale);
  };

  const [coldStartHint, setColdStartHint] = useState(false);
  const [retryAttempt, setRetryAttempt] = useState(0);

  const performLogin = useCallback(async (loginEmail: string, loginPassword: string, deviceName = 'Web App') => {
    setSubmitting(true);
    setError(null);
    setRetryAttempt(0);
    const startedAt = performance.now();

    const coldStartTimer = setTimeout(() => setColdStartHint(true), 5000);

    try {
      const loginResponse = await apiFetch('/auth/login', {
        method: 'POST',
        body: JSON.stringify({
          email: loginEmail,
          password: loginPassword,
          device_name: deviceName,
        }),
      }, {
        maxRetries: 3,
        onRetry: (attempt) => {
          setColdStartHint(true);
          setRetryAttempt(attempt);
          setError(
            locale === 'fr'
              ? `Le serveur demarre, tentative ${attempt + 1}/4...`
              : locale === 'tr'
                ? `Sunucu baslatiliyor, deneme ${attempt + 1}/4...`
                : locale === 'ar'
                  ? `...${attempt + 1}/4 الخادم يستيقظ، المحاولة`
                  : `Server is waking up, attempt ${attempt + 1}/4...`,
          );
        },
      });

      const loginPayload = await loginResponse.json() as LoginPayload;
      const token = extractToken(loginPayload);

      if (!token) {
        throw new Error(labels.login.errors.missingToken);
      }

      localStorage.setItem('auth_token', token);

      const meResponse = await apiFetch('/auth/me');
      const mePayload = await meResponse.json() as { data?: StoredAuthUser };
      const user = mePayload.data;

      if (!user) {
        throw new Error(labels.login.errors.missingUser);
      }

      storeAuthSession(token, user);
      applyDocumentLocale(normalizeLocale(user.language), user.is_rtl);
      const target = resolvePostLoginTarget(user);
      trackClientEvent('login_success', {
        duration_ms: Math.round(performance.now() - startedAt),
        role: user.role,
        manager_role: user.manager_role ?? null,
        locale: normalizeLocale(user.language),
        target,
        company_id: user.company?.id ?? null,
      });
      goToPostLoginTarget(target, router);
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
        trackClientEvent('login_failed', {
          duration_ms: Math.round(performance.now() - startedAt),
          status: err.status,
          code: err.code ?? null,
        });
      } else if (err instanceof Error) {
        setError(err.message);
        trackClientEvent('login_failed', {
          duration_ms: Math.round(performance.now() - startedAt),
          status: null,
          code: err.name,
        });
      } else {
        setError(labels.login.errors.generic);
        trackClientEvent('login_failed', {
          duration_ms: Math.round(performance.now() - startedAt),
          status: null,
          code: 'unknown',
        });
      }
    } finally {
      clearTimeout(coldStartTimer);
      setColdStartHint(false);
      setRetryAttempt(0);
      setSubmitting(false);
    }
  }, [labels.login.errors.generic, labels.login.errors.missingToken, labels.login.errors.missingUser, locale, router]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    await performLogin(email, password);
  };

  const selectDemoUser = useCallback((demoEmail: string, demoPassword: string, role?: string | null, country?: string | null) => {
    setEmail(demoEmail);
    setPassword(demoPassword);
    setError(null);
    setShowDemoModal(false);
    trackClientEvent('demo_user_selected', {
      role: role ?? null,
      country: country ?? null,
      email_domain: demoEmail.split('@')[1] ?? null,
    });
    void performLogin(demoEmail, demoPassword, 'Web Demo');
  }, [performLogin]);

  if (!mounted) return null;

  return (
    <main className="min-h-screen bg-slate-50 dark:bg-slate-950 px-4 py-6 text-slate-950 dark:text-white sm:px-6 lg:px-8 relative overflow-hidden">
      {/* Animated Background */}
      <div className="absolute inset-0 z-0">
        <div className="absolute top-[-10%] left-[-10%] w-[50%] h-[50%] bg-brand-500/10 rounded-full blur-[120px] animate-pulse-slow"></div>
        <div className="absolute bottom-[-10%] right-[-10%] w-[50%] h-[50%] bg-emerald-500/10 rounded-full blur-[120px] animate-pulse-slow" style={{ animationDelay: '1.5s' }}></div>
      </div>

      {/* Grid Pattern overlay */}
      <div className="absolute inset-0 z-0 opacity-10" style={{ backgroundImage: 'radial-gradient(#14b8a6 0.5px, transparent 0.5px)', backgroundSize: '24px 24px' }}></div>

      <div className="mx-auto grid min-h-[calc(100vh-3rem)] w-full max-w-6xl overflow-hidden rounded-[28px] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/50 backdrop-blur-xl shadow-2xl shadow-slate-200/70 dark:shadow-black/50 lg:grid-cols-[0.95fr_1.05fr] relative z-10 animate-fade-in">
        <section className="relative hidden flex-col justify-between bg-slate-950 p-10 text-white lg:flex overflow-hidden">
          <div className="absolute inset-0 opacity-70 [background:radial-gradient(circle_at_18%_12%,rgba(45,212,191,0.20),transparent_32%),radial-gradient(circle_at_84%_20%,rgba(56,189,248,0.18),transparent_30%)]" />

          {/* Internal section decoration */}
          <div className="absolute -right-20 -bottom-20 w-64 h-64 bg-brand-500/20 rounded-full blur-3xl"></div>

          <div className="relative">
            <Link href="/" className="inline-flex items-center gap-2 text-sm font-semibold text-slate-200 transition hover:text-white">
              <ArrowRight className="h-4 w-4 rotate-180" aria-hidden="true" />
              {labels.login.back}
            </Link>
            <div className="mt-16 space-y-5">
              <span className="inline-flex items-center gap-2 rounded-full border border-teal-300/30 bg-teal-300/10 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-teal-100">
                <ShieldCheck className="h-4 w-4" aria-hidden="true" />
                {labels.login.secureBadge}
              </span>
              <h1 className="max-w-md text-4xl font-bold leading-tight tracking-normal">
                {labels.login.heroTitle}
              </h1>
              <p className="max-w-md text-sm leading-6 text-slate-300">
                {labels.login.heroCopy}
              </p>
            </div>
          </div>

          <div className="relative grid gap-3">
            {labels.login.trustPoints.map((item) => (
              <div key={item} className="flex items-center gap-3 rounded-xl border border-white/10 bg-white/[0.06] px-4 py-3 text-sm text-slate-200">
                <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-teal-300/15 text-teal-100">
                  <Sparkles className="h-4 w-4" aria-hidden="true" />
                </span>
                {item}
              </div>
            ))}
          </div>
        </section>

        <section className="flex items-center justify-center px-5 py-8 sm:px-10 lg:px-14">
          <div className="w-full max-w-md">
            <div className="mb-8 flex items-center justify-between gap-4">
              <Link href="/" className="inline-flex items-center gap-2 text-sm font-semibold text-slate-600 transition hover:text-slate-950 lg:hidden">
                <ArrowRight className="h-4 w-4 rotate-180" aria-hidden="true" />
                {labels.login.back}
              </Link>
              <label className="ml-auto inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm">
                <Globe2 className="h-4 w-4 text-slate-500" aria-hidden="true" />
                <span className="sr-only">{labels.dashboard.language}</span>
                <select
                  aria-label={labels.dashboard.language}
                  className="bg-transparent text-sm font-semibold outline-none"
                  value={locale}
                  onChange={(e) => handleLocaleChange(e.target.value)}
                >
                  <option value="fr">Francais</option>
                  <option value="ar">Arabic</option>
                  <option value="tr">Turkce</option>
                  <option value="en">English</option>
                </select>
              </label>
            </div>

            <div className="space-y-3">
              <p className="text-[10px] font-black uppercase tracking-[0.2em] text-brand-600 dark:text-brand-400">{labels.login.clientSpace}</p>
              <h2 className="text-3xl font-black tracking-tight text-slate-950 dark:text-white uppercase italic">
                {labels.login.title.split(' ')[0]} <span className="text-brand-600 not-italic font-black">{labels.login.title.split(' ').slice(1).join(' ')}</span>
              </h2>
              <p className="text-sm font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">{labels.login.subtitle}</p>
            </div>

            <form className="mt-8 space-y-6" onSubmit={handleSubmit}>
              {error ? (
                <div
                  role="alert"
                  className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800"
                >
                  {error}
                </div>
              ) : null}

              <div className="space-y-4">
                <label htmlFor="email-address" className="block text-sm font-semibold text-slate-800">
                  {labels.login.email}
                </label>
                <input
                  id="email-address"
                  name="email"
                  type="email"
                  autoComplete="email"
                  required
                  className="block h-12 w-full rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 px-4 text-slate-950 dark:text-white shadow-sm outline-none transition placeholder:text-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 font-bold text-sm"
                  placeholder="manager@entreprise.com"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                />
              </div>

              <div className="space-y-4">
                <label htmlFor="password" className="block text-[10px] font-black uppercase tracking-widest text-slate-500 ml-1">
                  {labels.login.password}
                </label>
                <div className="relative">
                  <input
                    id="password"
                    name="password"
                    type={showPassword ? 'text' : 'password'}
                    autoComplete="current-password"
                    required
                    className="block h-12 w-full rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 px-4 pr-12 text-slate-950 dark:text-white shadow-sm outline-none transition placeholder:text-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 font-bold text-sm"
                    placeholder={labels.login.password}
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                  />
                  <button
                    type="button"
                    aria-label={showPassword ? labels.login.hidePassword : labels.login.showPassword}
                    className="absolute inset-y-0 right-2 my-auto flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600"
                    onClick={() => setShowPassword((value) => !value)}
                  >
                    {showPassword ? <EyeOff className="h-5 w-5" aria-hidden="true" /> : <Eye className="h-5 w-5" aria-hidden="true" />}
                  </button>
                </div>
              </div>

              <div className="flex flex-wrap items-center justify-between gap-3">
                <label className="flex items-center gap-2 text-sm text-slate-700">
                  <input
                    id="remember-me"
                    name="remember-me"
                    type="checkbox"
                    className="h-4 w-4 rounded border-slate-300 text-teal-700 focus:ring-teal-600"
                  />
                  {labels.login.remember}
                </label>

                <Link href="/contact?topic=password" className="text-sm font-semibold text-teal-700 transition hover:text-teal-900">
                  {labels.login.forgot}
                </Link>
              </div>

              <button
                type="submit"
                disabled={submitting}
                className="inline-flex h-12 w-full items-center justify-center gap-2 rounded-2xl bg-brand-600 px-4 text-xs font-black uppercase tracking-widest text-white shadow-lg shadow-brand-500/20 transition hover:bg-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-70"
              >
                {submitting ? <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" /> : <LockKeyhole className="h-4 w-4" aria-hidden="true" />}
                {submitting ? labels.login.loading : labels.login.submit}
              </button>

              <a
                href={googleAuthHref()}
                className="inline-flex h-12 w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-bold text-slate-800 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600"
              >
                <Chrome className="h-4 w-4" aria-hidden="true" />
                {locale === 'fr'
                  ? 'Continuer avec Google'
                  : locale === 'tr'
                    ? 'Google ile devam et'
                    : locale === 'ar'
                      ? 'المتابعة عبر Google'
                      : 'Continue with Google'}
              </a>

              {coldStartHint && submitting ? (
                <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 space-y-2">
                  <p className="font-medium">
                    {locale === 'fr'
                      ? 'Le serveur de demo se reveille, cela peut prendre jusqu\'a 60 secondes...'
                      : locale === 'tr'
                        ? 'Demo sunucusu uyaniyor, 60 saniye kadar surebilir...'
                        : locale === 'ar'
                          ? '...خادم العرض يستيقظ، قد يستغرق حتى 60 ثانية'
                          : 'Demo server is waking up, this may take up to 60 seconds...'}
                  </p>
                  {retryAttempt > 0 && (
                    <div className="flex items-center gap-2">
                      <div className="flex-1 h-1.5 bg-amber-200 rounded-full overflow-hidden">
                        <div
                          className="h-full bg-amber-500 rounded-full transition-all duration-500"
                          style={{ width: `${Math.min((retryAttempt / 3) * 100, 100)}%` }}
                        />
                      </div>
                      <span className="text-xs font-mono text-amber-600">{retryAttempt}/3</span>
                    </div>
                  )}
                </div>
              ) : null}

              <button
                type="button"
                onClick={() => setShowDemoModal(true)}
                className="inline-flex h-12 w-full items-center justify-center gap-2 rounded-2xl border border-emerald-500/20 bg-emerald-500/5 px-4 text-xs font-black uppercase tracking-widest text-emerald-600 transition hover:bg-emerald-500/10 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
              >
                <Sparkles className="h-4 w-4" aria-hidden="true" />
                {labels.login.demoAccess}
              </button>

              <p className="text-center text-xs leading-5 text-slate-500">
                {labels.login.supportCopy}{' '}
                <Link href="/contact" className="font-semibold text-slate-800 underline-offset-4 hover:underline">
                  {labels.login.supportLink}
                </Link>
              </p>
            </form>

            {showDemoModal ? (
              <div
                className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4"
                onClick={(e) => { if (e.target === e.currentTarget) setShowDemoModal(false); }}
              >
                <div className="max-h-[84vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
                  <div className="sticky top-0 flex items-center justify-between gap-4 border-b border-slate-200 bg-white px-6 py-5">
                    <div>
                      <h3 className="text-lg font-bold text-slate-950">{labels.login.demoTitle}</h3>
                      <p className="mt-1 text-sm text-slate-500">{labels.login.demoSubtitle}</p>
                    </div>
                    <button
                      type="button"
                      aria-label={labels.login.close}
                      className="flex h-10 w-10 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600"
                      onClick={() => setShowDemoModal(false)}
                    >
                      <X className="h-5 w-5" aria-hidden="true" />
                    </button>
                  </div>
                  <div className="space-y-5 p-6">
                    {demoCompanies.map((company) => (
                      <div key={company.slug}>
                        <h4 className="mb-2 text-xs font-bold uppercase tracking-[0.16em] text-slate-500">
                          {company.name} ({company.country})
                        </h4>
                        <div className="grid gap-2">
                          {company.users.map((user) => (
                            <button
                              key={user.email}
                              type="button"
                              className="w-full rounded-xl border border-slate-200 p-4 text-left transition hover:border-teal-300 hover:bg-teal-50/60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600"
                              onClick={() => selectDemoUser(user.email, user.password, user.managerRole ?? user.role, company.country)}
                            >
                              <div className="flex items-center justify-between gap-3">
                                <span className="font-semibold text-slate-950">{user.name}</span>
                                <span className="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700">
                                  {user.managerRole ?? user.role}
                                </span>
                              </div>
                              <span className="mt-1 block text-sm text-slate-500">{user.email}</span>
                            </button>
                          ))}
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              </div>
            ) : null}
          </div>
        </section>
      </div>
    </main>
  );
}
