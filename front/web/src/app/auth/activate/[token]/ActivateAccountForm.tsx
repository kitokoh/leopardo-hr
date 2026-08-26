'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { ArrowLeft, CheckCircle2, Eye, EyeOff, Loader2, LockKeyhole } from 'lucide-react';
import { ApiError, apiFetch } from '@/lib/api-client';
import { Button } from '@/components/ui/Button';
import { PasswordStrengthBar } from '@/components/ui/PasswordStrengthBar';
import { getCopy, normalizeLocale, storeAuthSession, type AppLocale, type StoredAuthUser } from '@/lib/i18n';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';

const MIN_PASSWORD_LENGTH = 8;

export function ActivateAccountForm({ token }: { token: string }) {
  const { locale } = useVitrineLocale();
  const appLocale: AppLocale = normalizeLocale(locale ?? 'fr');
  const labels = getCopy(appLocale).accountActivation;
  const router = useRouter();

  const [password, setPassword] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [done, setDone] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const missingLink = !token;

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (password.length < MIN_PASSWORD_LENGTH) {
      setError(labels.invalidPassword);
      return;
    }
    if (password !== confirmation) {
      setError(labels.passwordMismatch);
      return;
    }
    setError(null);
    setSubmitting(true);
    try {
      // #R3 — Passer par le proxy dédié qui pose le cookie httpOnly leopardo_token
      // (même architecture que /api/v1/auth/login, cf. #1299).
      // Le proxy extrait data.token et le stocke en cookie avant de répondre.
      await apiFetch(`/onboarding/invitation/${encodeURIComponent(token)}/activate`, {
        method: 'POST',
        body: JSON.stringify({
          token,
          password,
          password_confirmation: confirmation,
        }),
      });

      // Cookie posé — récupérer le profil complet via /auth/me et démarrer la session.
      try {
        const meResponse = await apiFetch('/auth/me');
        const mePayload = (await meResponse.json()) as { data?: StoredAuthUser };
        if (mePayload.data) {
          storeAuthSession(null, mePayload.data);
          router.replace('/');
          return;
        }
      } catch {
        // /auth/me a échoué (env de test, cookie non posé, etc.) — fallback :
        // afficher l'écran de succès avec un lien vers la connexion.
      }

      setDone(true);
    } catch (e) {
      console.error(e);
      if (e instanceof ApiError) {
        if (e.status === 404) {
          setError(labels.missingTokenBody);
        } else if (e.status === 410 && e.code === 'INVITATION_EXPIRED') {
          setError(labels.expired);
        } else if (e.status === 410 && e.code === 'INVITATION_ALREADY_ACCEPTED') {
          setError(labels.alreadyAccepted);
        } else {
          setError(e.message || labels.genericError);
        }
      } else {
        setError(e instanceof Error ? e.message : labels.genericError);
      }
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
            <h1 className="text-2xl font-black">{missingLink ? labels.missingTokenTitle : labels.title}</h1>
            <p className="mt-2 text-sm text-emerald-50">
              {missingLink ? labels.missingTokenBody : labels.subtitle}
            </p>
          </div>

          <div className="p-8">
            {done ? (
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
            ) : missingLink ? (
              <div className="flex flex-col items-center gap-4 py-6 text-center">
                <p className="text-sm text-slate-500">{labels.missingTokenBody}</p>
                <Link
                  href="/auth/login"
                  className="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-slate-800"
                >
                  <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                  {labels.backToLogin}
                </Link>
              </div>
            ) : (
              <form onSubmit={handleSubmit} className="space-y-5" noValidate>
                <div>
                  <label htmlFor="activate-password" className="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-200">
                    {labels.passwordLabel}
                  </label>
                  <div className="relative">
                    <input
                      id="activate-password"
                      name="password"
                      type={showPassword ? 'text' : 'password'}
                      autoComplete="new-password"
                      required
                      minLength={MIN_PASSWORD_LENGTH}
                      value={password}
                      onChange={(e) => setPassword(e.target.value)}
                      placeholder={labels.passwordPlaceholder}
                      className="h-12 w-full rounded-xl border border-slate-200 bg-white pl-4 pr-11 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                    />
                    <button
                      type="button"
                      onClick={() => setShowPassword((v) => !v)}
                      aria-label={showPassword ? labels.hidePassword : labels.showPassword}
                      className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 transition hover:text-slate-600"
                    >
                      {showPassword ? <EyeOff className="h-4 w-4" aria-hidden="true" /> : <Eye className="h-4 w-4" aria-hidden="true" />}
                    </button>
                  </div>
                  <PasswordStrengthBar password={password} locale={appLocale} />
                </div>

                <div>
                  <label htmlFor="activate-password-confirm" className="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-200">
                    {labels.confirmPasswordLabel}
                  </label>
                  <input
                    id="activate-password-confirm"
                    name="password_confirmation"
                    type={showPassword ? 'text' : 'password'}
                    autoComplete="new-password"
                    required
                    minLength={MIN_PASSWORD_LENGTH}
                    value={confirmation}
                    onChange={(e) => setConfirmation(e.target.value)}
                    placeholder={labels.confirmPasswordPlaceholder}
                    className="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                  />
                </div>

                {error && (
                  <p role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-xs font-medium text-red-700 dark:bg-red-950/50 dark:text-red-300">
                    {error}
                  </p>
                )}

                <Button
                  type="submit"
                  loading={submitting}
                  fullWidth
                  className="h-12 rounded-2xl bg-emerald-600 px-4 text-xs font-black uppercase tracking-widest text-white shadow-lg shadow-emerald-500/20 hover:bg-emerald-500 focus:ring-emerald-500 focus:ring-offset-2"
                >
                  {submitting ? labels.submitting : labels.submit}
                </Button>

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
