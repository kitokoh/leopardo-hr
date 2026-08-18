'use client';

import { useState } from 'react';
import Link from 'next/link';
import { ArrowLeft, CheckCircle2, Loader2, LockKeyhole, Mail } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { getCopy, normalizeLocale, type AppLocale } from '@/lib/i18n';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export function ForgotPasswordForm() {
  const { locale } = useVitrineLocale();
  const appLocale: AppLocale = normalizeLocale(locale ?? 'fr');
  const labels = getCopy(appLocale).passwordReset;

  const [email, setEmail] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [sent, setSent] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    const trimmed = email.trim();
    if (!EMAIL_RE.test(trimmed)) {
      setError(labels.invalidEmail);
      return;
    }
    setError(null);
    setSubmitting(true);
    try {
      // Endpoint public du backend (anti-énumération) : la réponse est
      // volontairement générique, qu'un compte existe ou non.
      await apiFetch('/auth/forgot-password', {
        method: 'POST',
        body: JSON.stringify({ email: trimmed }),
      });
      setSent(true);
    } catch (e) {
      console.error(e);
      setError(e instanceof Error ? e.message : labels.genericError);
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <main className="relative flex min-h-screen items-center justify-center overflow-hidden bg-slate-950 px-4 py-10 text-slate-950 dark:text-white sm:px-6 lg:px-8">
      <div className="auth-surface-dots absolute inset-0 z-0 opacity-10" />
      <div className="auth-surface-glow absolute inset-0 z-0 opacity-70" />

      <div className="relative z-10 w-full max-w-md">
        <div className="overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-slate-900">
          <div className="bg-gradient-to-br from-emerald-500 to-teal-600 p-8 text-white">
            <div className="mb-4 inline-flex items-center gap-2 rounded-full bg-white/20 px-3 py-1 text-xs font-bold uppercase tracking-wider">
              <LockKeyhole className="h-3.5 w-3.5" aria-hidden="true" />
              Leopardo RH
            </div>
            <h1 className="text-2xl font-black">{labels.title}</h1>
            <p className="mt-2 text-sm text-emerald-50">{labels.subtitle}</p>
          </div>

          <div className="p-8">
            {sent ? (
              <div className="flex flex-col items-center gap-4 py-6 text-center">
                <div className="flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                  <CheckCircle2 className="h-8 w-8" aria-hidden="true" />
                </div>
                <h2 className="text-lg font-bold text-slate-900 dark:text-white">{labels.successTitle}</h2>
                <p className="text-sm text-slate-500">{labels.successBody}</p>
                <Link
                  href="/auth/login"
                  className="mt-2 inline-flex items-center gap-2 rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-slate-800"
                >
                  <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                  {labels.backToLogin}
                </Link>
              </div>
            ) : (
              <form onSubmit={handleSubmit} className="space-y-5" noValidate>
                <div>
                  <label htmlFor="forgot-email" className="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-200">
                    {labels.emailLabel}
                  </label>
                  <div className="relative">
                    <Mail className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                    <input
                      id="forgot-email"
                      name="email"
                      type="email"
                      autoComplete="email"
                      required
                      value={email}
                      onChange={(e) => setEmail(e.target.value)}
                      placeholder={labels.emailPlaceholder}
                      className="h-12 w-full rounded-xl border border-slate-200 bg-white pl-10 pr-4 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                    />
                  </div>
                </div>

                {error && (
                  <p role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-xs font-medium text-red-700 dark:bg-red-950/50 dark:text-red-300">
                    {error}
                  </p>
                )}

                <button
                  type="submit"
                  disabled={submitting}
                  className="inline-flex h-12 w-full items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-4 text-xs font-black uppercase tracking-widest text-white shadow-lg shadow-emerald-500/20 transition hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-70"
                >
                  {submitting ? (
                    <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
                  ) : (
                    <LockKeyhole className="h-4 w-4" aria-hidden="true" />
                  )}
                  {submitting ? labels.submitting : labels.submit}
                </button>

                <Link
                  href="/auth/login"
                  className="inline-flex items-center gap-1.5 text-xs font-semibold text-teal-700 transition hover:text-teal-900 dark:text-teal-400"
                >
                  <ArrowLeft className="h-3.5 w-3.5" aria-hidden="true" />
                  {labels.backToLogin}
                </Link>
              </form>
            )}
          </div>
        </div>
      </div>
    </main>
  );
}
