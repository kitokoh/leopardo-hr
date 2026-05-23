'use client';

import { useCallback, useEffect, useMemo, useState, useSyncExternalStore } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import {
  ArrowRight,
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
  const locale: AppLocale = localeOverride ?? storedLocale;
  const labels = useMemo(() => getCopy(locale), [locale]);

  useEffect(() => {
    setMounted(true);
  }, []);

  useEffect(() => {
    applyDocumentLocale(locale);
  }, [locale]);

  const handleLocaleChange = (value: string) => {
    const nextLocale = normalizeLocale(value);
    setLocaleOverride(nextLocale);
    storePreferredLocale(nextLocale);
    applyDocumentLocale(nextLocale);
  };

  const performLogin = useCallback(async (loginEmail: string, loginPassword: string, deviceName = 'Web App') => {
    setSubmitting(true);
    setError(null);
    const startedAt = performance.now();

    try {
      const loginResponse = await apiFetch('/auth/login', {
        method: 'POST',
        body: JSON.stringify({
          email: loginEmail,
          password: loginPassword,
          device_name: deviceName,
        }),
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
      setSubmitting(false);
    }
  }, [labels.login.errors.generic, labels.login.errors.missingToken, labels.login.errors.missingUser, router]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    await performLogin(email, password);
  };

  const demoCompanies = useMemo(() => [
    {
      name: 'TechCorp Algerie SARL', slug: 'techcorp-algerie', country: 'DZ',
      users: [
        { email: 'ahmed.benali@techcorp-algerie.dz', name: 'Ahmed Benali', role: 'manager', managerRole: 'principal', password: 'password123' },
        { email: 'fatima.meziane@techcorp-algerie.dz', name: 'Fatima Meziane', role: 'manager', managerRole: 'rh', password: 'password123' },
        { email: 'karim.aouad@techcorp-algerie.dz', name: 'Karim Aouad', role: 'employee', managerRole: null, password: 'password123' },
      ],
    },
    {
      name: 'PharmaPlus Casablanca', slug: 'pharmaplus-casablanca', country: 'MA',
      users: [
        { email: 'amina.tahiri@pharmaplus.ma', name: 'Amina Tahiri', role: 'manager', managerRole: 'principal', password: 'password123' },
        { email: 'sara.mansouri@pharmaplus.ma', name: 'Sara Mansouri', role: 'manager', managerRole: 'rh', password: 'password123' },
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
  ], []);

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
    <main className="min-h-screen bg-[#f6f7fb] px-4 py-6 text-slate-950 sm:px-6 lg:px-8">
      <div className="mx-auto grid min-h-[calc(100vh-3rem)] w-full max-w-6xl overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-2xl shadow-slate-200/70 lg:grid-cols-[0.95fr_1.05fr]">
        <section className="relative hidden flex-col justify-between bg-slate-950 p-10 text-white lg:flex">
          <div className="absolute inset-0 opacity-70 [background:radial-gradient(circle_at_18%_12%,rgba(45,212,191,0.20),transparent_32%),radial-gradient(circle_at_84%_20%,rgba(56,189,248,0.18),transparent_30%)]" />
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
              <p className="text-sm font-bold uppercase tracking-[0.16em] text-teal-700">{labels.login.clientSpace}</p>
              <h2 className="text-3xl font-bold tracking-normal text-slate-950">{labels.login.title}</h2>
              <p className="text-sm leading-6 text-slate-600">{labels.login.subtitle}</p>
            </div>

            <form className="mt-8 space-y-5" onSubmit={handleSubmit}>
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
                  className="block h-12 w-full rounded-xl border border-slate-300 bg-white px-4 text-slate-950 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-teal-500 focus:ring-4 focus:ring-teal-500/15"
                  placeholder="manager@entreprise.com"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                />
              </div>

              <div className="space-y-4">
                <label htmlFor="password" className="block text-sm font-semibold text-slate-800">
                  {labels.login.password}
                </label>
                <div className="relative">
                  <input
                    id="password"
                    name="password"
                    type={showPassword ? 'text' : 'password'}
                    autoComplete="current-password"
                    required
                    className="block h-12 w-full rounded-xl border border-slate-300 bg-white px-4 pr-12 text-slate-950 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-teal-500 focus:ring-4 focus:ring-teal-500/15"
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
                className="inline-flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 text-sm font-bold text-white shadow-lg shadow-slate-900/15 transition hover:bg-slate-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600 disabled:cursor-not-allowed disabled:opacity-70"
              >
                {submitting ? <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" /> : <LockKeyhole className="h-4 w-4" aria-hidden="true" />}
                {submitting ? labels.login.loading : labels.login.submit}
              </button>

              <button
                type="button"
                onClick={() => setShowDemoModal(true)}
                className="inline-flex h-12 w-full items-center justify-center gap-2 rounded-xl border border-teal-200 bg-teal-50 px-4 text-sm font-bold text-teal-900 transition hover:border-teal-300 hover:bg-teal-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600"
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
