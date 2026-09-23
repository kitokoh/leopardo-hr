'use client';

import { useEffect, useMemo, useState } from 'react';
import Link from 'next/link';
import { ArrowLeft, CheckCircle2, Eye, EyeOff, Loader2, LockKeyhole } from 'lucide-react';
import { PASSWORD_MIN_LENGTH, isPasswordAcceptable } from '@/lib/password-policy';
import { normalizeLocale, getPreferredLocale, type AppLocale } from '@/lib/i18n';
import { t as i18nT } from '@/lib/i18n/locale-catalog';

/**
 * #7490 — définition du mot de passe via le lien magique de l'e-mail de
 * bienvenue (ou depuis l'écran de première connexion).
 *
 * Le secret est le `provisioning_token` (#2903) : porté par `?token=` dans le
 * lien e-mail, avec repli sur le token déjà détenu par le navigateur depuis
 * l'inscription (localStorage, même clé que la vitrine). Le POST passe par le
 * proxy same-origin `/api/forms/trial-password` (token en en-tête X-Token côté
 * serveur, jamais relayé en query vers l'API — #4931).
 *
 * Aucune session requise : c'est le critère 3 de l'issue (« définir le mot de
 * passe sans être connecté »).
 */

const TRIAL_TOKEN_STORAGE_KEY = 'lp_trial_provisioning_token';

/** Même forme que côté proxy : 64 caractères alphanumériques. */
function isValidToken(token: string): boolean {
  return /^[A-Za-z0-9]{64}$/.test(token);
}

function errorKeyFor(code: string): string {
  switch (code) {
    case 'PROVISIONING_TOKEN_INVALID':
      return 'setPassword.errorInvalidToken';
    case 'TRIAL_PASSWORD_LINK_EXPIRED':
      return 'setPassword.errorExpired';
    case 'TRIAL_PASSWORD_ALREADY_SET':
      return 'setPassword.errorAlreadySet';
    case 'PROVISIONING_NOT_READY':
      return 'setPassword.errorNotReady';
    case 'PASSWORD_TOO_WEAK':
      return 'setPassword.errorWeakPassword';
    case 'PASSWORD_MISMATCH':
      return 'setPassword.errorMismatch';
    default:
      return 'setPassword.errorGeneric';
  }
}

export function SetPasswordForm({ tokenFromUrl }: { tokenFromUrl: string }) {
  const [locale, setLocale] = useState<AppLocale>('fr');
  const [token, setToken] = useState<string>(tokenFromUrl);
  const [password, setPassword] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [done, setDone] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    setLocale(normalizeLocale(getPreferredLocale()));

    // Repli : token déjà détenu par le navigateur depuis l'inscription.
    if (!isValidToken(tokenFromUrl)) {
      try {
        const stored = window.localStorage.getItem(TRIAL_TOKEN_STORAGE_KEY) ?? '';
        if (isValidToken(stored)) {
          setToken(stored);
        }
      } catch {
        // localStorage indisponible (navigation privée stricte) : état « lien
        // manquant » assumé.
      }
    }
  }, [tokenFromUrl]);

  const t = useMemo(() => (key: string) => i18nT(locale, key), [locale]);

  const missingToken = !isValidToken(token);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!isPasswordAcceptable(password)) {
      setError(t('setPassword.errorWeakPassword'));
      return;
    }
    if (password !== confirmation) {
      setError(t('setPassword.errorMismatch'));
      return;
    }
    setError(null);
    setSubmitting(true);
    try {
      const response = await fetch('/api/forms/trial-password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          token,
          password,
          password_confirmation: confirmation,
        }),
      });
      const payload = (await response.json().catch(() => null)) as {
        success?: boolean;
        error?: string;
      } | null;

      if (response.ok && payload?.success === true) {
        setDone(true);
      } else {
        setError(t(errorKeyFor(payload?.error ?? '')));
      }
    } catch {
      setError(t('setPassword.errorGeneric'));
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
              Leopardo
            </div>
            <h1 className="text-2xl font-black">{t('setPassword.title')}</h1>
            <p className="mt-2 text-sm text-emerald-50">
              {missingToken ? t('setPassword.missingToken') : t('setPassword.subtitle')}
            </p>
          </div>

          <div className="p-8">
            {done ? (
              <div className="flex flex-col items-center gap-4 py-6 text-center">
                <div className="flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                  <CheckCircle2 className="h-8 w-8" aria-hidden="true" />
                </div>
                <p className="text-sm text-slate-600 dark:text-slate-300">{t('setPassword.success')}</p>
                <Link
                  href="/auth/login"
                  className="mt-2 inline-flex items-center gap-2 rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-slate-800"
                >
                  <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                  {t('setPassword.successCta')}
                </Link>
              </div>
            ) : missingToken ? (
              <div className="flex flex-col items-center gap-4 py-6 text-center">
                <Link
                  href="/auth/login"
                  className="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-slate-800"
                >
                  <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                  {t('setPassword.backToLogin')}
                </Link>
              </div>
            ) : (
              <form onSubmit={handleSubmit} className="space-y-5" noValidate>
                <div>
                  <label
                    htmlFor="set-password"
                    className="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-200"
                  >
                    {t('setPassword.passwordLabel')}
                  </label>
                  <div className="relative">
                    <input
                      id="set-password"
                      name="password"
                      type={showPassword ? 'text' : 'password'}
                      autoComplete="new-password"
                      minLength={PASSWORD_MIN_LENGTH}
                      required
                      value={password}
                      onChange={(e) => setPassword(e.target.value)}
                      className="w-full rounded-xl border border-slate-300 px-4 py-3 pr-11 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                    />
                    <button
                      type="button"
                      onClick={() => setShowPassword((v) => !v)}
                      className="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600"
                      aria-label={showPassword ? t('setPassword.hidePassword') : t('setPassword.showPassword')}
                    >
                      {showPassword ? (
                        <EyeOff className="h-4 w-4" aria-hidden="true" />
                      ) : (
                        <Eye className="h-4 w-4" aria-hidden="true" />
                      )}
                    </button>
                  </div>
                </div>

                <div>
                  <label
                    htmlFor="set-password-confirmation"
                    className="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-200"
                  >
                    {t('setPassword.confirmLabel')}
                  </label>
                  <input
                    id="set-password-confirmation"
                    name="password_confirmation"
                    type={showPassword ? 'text' : 'password'}
                    autoComplete="new-password"
                    minLength={PASSWORD_MIN_LENGTH}
                    required
                    value={confirmation}
                    onChange={(e) => setConfirmation(e.target.value)}
                    className="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                  />
                </div>

                {error && (
                  <p role="alert" className="rounded-xl bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                    {error}
                  </p>
                )}

                <button
                  type="submit"
                  disabled={submitting}
                  className="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-emerald-700 disabled:opacity-60"
                >
                  {submitting && <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />}
                  {submitting ? t('setPassword.submitting') : t('setPassword.submit')}
                </button>

                <p className="text-center">
                  <Link
                    href="/auth/login"
                    className="text-sm font-semibold text-slate-500 underline-offset-4 hover:underline"
                  >
                    {t('setPassword.backToLogin')}
                  </Link>
                </p>
              </form>
            )}
          </div>
        </div>
      </div>
    </main>
  );
}
