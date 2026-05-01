'use client';

import { useEffect, useMemo, useState, useSyncExternalStore } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { ApiError, apiFetch } from '@/lib/api-client';
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

export default function LoginPage() {
  const router = useRouter();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const storedLocale = useSyncExternalStore<AppLocale>(emptySubscribe, getPreferredLocale, () => 'fr');
  const [localeOverride, setLocaleOverride] = useState<AppLocale | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const locale: AppLocale = localeOverride ?? storedLocale;
  const labels = useMemo(() => getCopy(locale), [locale]);

  useEffect(() => {
    applyDocumentLocale(locale);
  }, [locale]);

  const handleLocaleChange = (value: string) => {
    const nextLocale = normalizeLocale(value);
    setLocaleOverride(nextLocale);
    storePreferredLocale(nextLocale);
    applyDocumentLocale(nextLocale);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    setError(null);

    try {
      const loginResponse = await apiFetch('/auth/login', {
        method: 'POST',
        body: JSON.stringify({
          email,
          password,
          device_name: 'Web App',
        }),
      });

      const loginPayload = await loginResponse.json() as Record<string, unknown>;
      const rootToken = typeof loginPayload.token === 'string' ? loginPayload.token : null;
      const nestedData = loginPayload.data && typeof loginPayload.data === 'object'
        ? loginPayload.data as Record<string, unknown>
        : null;
      const nestedToken = nestedData && typeof nestedData.token === 'string' ? nestedData.token : null;
      const token = rootToken || nestedToken;

      if (!token) {
        throw new Error('Authentication token missing from login response.');
      }

      localStorage.setItem('auth_token', token);

      const meResponse = await apiFetch('/auth/me');
      const mePayload = await meResponse.json() as { data?: StoredAuthUser };
      const user = mePayload.data;

      if (!user) {
        throw new Error('Authenticated user missing from profile response.');
      }

      storeAuthSession(token, user);
      applyDocumentLocale(normalizeLocale(user.language), user.is_rtl);
      router.push('/dashboard');
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
      } else if (err instanceof Error) {
        setError(err.message);
      } else {
        setError('Une erreur est survenue.');
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-12 sm:px-6 lg:px-8">
      <div className="w-full max-w-md space-y-8 rounded-xl bg-white p-8 shadow-md">
        <div className="space-y-4">
          <div className="flex justify-end">
            <select
              aria-label="Language"
              className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
              value={locale}
              onChange={(e) => handleLocaleChange(e.target.value)}
            >
              <option value="fr">Francais</option>
              <option value="ar">العربية</option>
              <option value="tr">Turkce</option>
              <option value="en">English</option>
            </select>
          </div>
          <h2 className="mt-6 text-center text-3xl font-bold tracking-tight text-gray-900">
            {labels.login.title}
          </h2>
          <p className="mt-2 text-center text-sm text-gray-600">
            <Link href="/" className="font-medium text-primary hover:text-primary/90">
              {labels.login.back}
            </Link>
          </p>
        </div>
        <form className="mt-8 space-y-6" onSubmit={handleSubmit}>
          {error ? (
            <div className="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
              {error}
            </div>
          ) : null}
          <div className="-space-y-px rounded-md shadow-sm">
            <div>
              <label htmlFor="email-address" className="sr-only">
                {labels.login.email}
              </label>
              <input
                id="email-address"
                name="email"
                type="email"
                autoComplete="email"
                required
                className="relative block w-full rounded-t-md border-0 px-3 py-1.5 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:z-10 focus:ring-2 focus:ring-inset focus:ring-primary sm:text-sm sm:leading-6"
                placeholder={labels.login.email}
                value={email}
                onChange={(e) => setEmail(e.target.value)}
              />
            </div>
            <div>
              <label htmlFor="password" className="sr-only">
                {labels.login.password}
              </label>
              <input
                id="password"
                name="password"
                type="password"
                autoComplete="current-password"
                required
                className="relative block w-full rounded-b-md border-0 px-3 py-1.5 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:z-10 focus:ring-2 focus:ring-inset focus:ring-primary sm:text-sm sm:leading-6"
                placeholder={labels.login.password}
                value={password}
                onChange={(e) => setPassword(e.target.value)}
              />
            </div>
          </div>

          <div className="flex items-center justify-between">
            <div className="flex items-center">
              <input
                id="remember-me"
                name="remember-me"
                type="checkbox"
                className="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
              />
              <label htmlFor="remember-me" className="ml-2 block text-sm text-gray-900">
                {labels.login.remember}
              </label>
            </div>

            <div className="text-sm">
              <Link href="#" className="font-medium text-primary hover:text-primary/90">
                {labels.login.forgot}
              </Link>
            </div>
          </div>

          <div>
            <button
              type="submit"
              disabled={submitting}
              className="group relative flex w-full justify-center rounded-md bg-primary px-3 py-2 text-sm font-semibold text-white hover:bg-primary/90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary disabled:cursor-not-allowed disabled:opacity-70"
            >
              {submitting ? labels.login.loading : labels.login.submit}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
