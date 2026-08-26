'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { useRouter, useSearchParams } from 'next/navigation';
import { ArrowLeft, Eye, EyeOff, KeyRound, Loader2, ShieldCheck } from 'lucide-react';
import { ApiError, apiFetch } from '@/lib/api-client';
import { Button } from '@/components/ui/Button';
import {
  applyDocumentLocale,
  getCopy,
  normalizeLocale,
  storeAuthSession,
  type AppLocale,
  type StoredAuthUser,
} from '@/lib/i18n';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';

/**
 * #5612 — Page de challenge 2FA (TOTP ou code de récupération).
 *
 * Appelée par login/page.tsx quand le backend retourne
 * `{mfa_challenge: true, mfa_challenge_token: '...'}`.
 *
 * Flux :
 *   1. L'utilisateur saisit le code TOTP (6 chiffres) ou un code de récupération.
 *   2. POST /api/v1/auth/2fa/verify → proxy Next.js qui pose le cookie httpOnly.
 *   3. GET /auth/me → profil complet → storeAuthSession → redirect /dashboard.
 */
export default function TwoFactorChallengePage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const { locale } = useVitrineLocale();
  const appLocale: AppLocale = normalizeLocale(locale ?? 'fr');
  const labels = getCopy(appLocale);
  const t = labels.twoFactor;

  const challengeToken = searchParams.get('token') ?? '';

  const [code, setCode] = useState('');
  const [recoveryCode, setRecoveryCode] = useState('');
  const [useRecovery, setUseRecovery] = useState(false);
  const [showRecovery, setShowRecovery] = useState(false);
  const [rememberDevice, setRememberDevice] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Si pas de token dans l'URL, renvoyer vers login.
  useEffect(() => {
    if (!challengeToken) {
      router.replace('/auth/login');
    }
  }, [challengeToken, router]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setSubmitting(true);

    try {
      const body: Record<string, unknown> = {
        challenge_token: challengeToken,
        device_name: 'Web App',
        remember_device: rememberDevice,
      };

      if (useRecovery) {
        body.recovery_code = recoveryCode.trim();
      } else {
        body.code = code.replace(/\s/g, '');
      }

      await apiFetch('/auth/2fa/verify', { method: 'POST', body: JSON.stringify(body) });

      // Cookie posé — récupérer le profil complet et démarrer la session.
      const meResponse = await apiFetch('/auth/me');
      const mePayload = (await meResponse.json()) as { data?: StoredAuthUser };

      if (!mePayload.data) {
        throw new Error(t.genericError);
      }

      storeAuthSession(null, mePayload.data);
      applyDocumentLocale(normalizeLocale(mePayload.data.language), mePayload.data.is_rtl);
      router.replace('/dashboard');
    } catch (err) {
      if (err instanceof ApiError) {
        if (err.status === 422 || err.code === 'TWO_FACTOR_INVALID') {
          setError(t.invalidCode);
        } else if (err.status === 401 || err.code === 'TWO_FACTOR_CHALLENGE_EXPIRED') {
          setError(t.expiredChallenge);
        } else {
          setError(err.message || t.genericError);
        }
      } else {
        setError(err instanceof Error ? err.message : t.genericError);
      }
    } finally {
      setSubmitting(false);
    }
  };

  if (!challengeToken) return null;

  return (
    <main className="relative flex min-h-screen items-center justify-center overflow-hidden bg-slate-950 px-4 py-10 text-slate-950 dark:text-white sm:px-6 lg:px-8">
      <div className="auth-surface-dots absolute inset-0 z-0 opacity-10" />
      <div className="auth-surface-glow absolute inset-0 z-0 opacity-70" />

      <div className="relative z-10 w-full max-w-md">
        <div className="overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-slate-900">
          {/* Header */}
          <div className="bg-gradient-to-br from-emerald-500 to-teal-600 p-8 text-white">
            <div className="mb-4 inline-flex items-center gap-2 rounded-full bg-white/20 px-3 py-1 text-xs font-bold uppercase tracking-wider">
              <ShieldCheck className="h-3.5 w-3.5" aria-hidden="true" />
              Leopardo RH
            </div>
            <h1 className="text-2xl font-black">{t.challengeTitle}</h1>
            <p className="mt-2 text-sm text-emerald-50">{t.challengeSubtitle}</p>
          </div>

          {/* Form */}
          <div className="p-8">
            <form onSubmit={(e) => void handleSubmit(e)} className="space-y-5" noValidate>
              {!useRecovery ? (
                /* TOTP code */
                <div>
                  <label
                    htmlFor="totp-code"
                    className="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-200"
                  >
                    {t.codeLabel}
                  </label>
                  <input
                    id="totp-code"
                    name="code"
                    type="text"
                    inputMode="numeric"
                    autoComplete="one-time-code"
                    pattern="\d{6}"
                    maxLength={6}
                    required
                    value={code}
                    onChange={(e) => setCode(e.target.value.replace(/\D/g, ''))}
                    placeholder={t.codePlaceholder}
                    autoFocus
                    className="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-center text-2xl font-black tracking-[0.35em] text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                  />
                </div>
              ) : (
                /* Recovery code */
                <div>
                  <label
                    htmlFor="recovery-code"
                    className="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-200"
                  >
                    {t.recoveryCodeLabel}
                  </label>
                  <div className="relative">
                    <input
                      id="recovery-code"
                      name="recovery_code"
                      type={showRecovery ? 'text' : 'password'}
                      autoComplete="off"
                      required
                      value={recoveryCode}
                      onChange={(e) => setRecoveryCode(e.target.value)}
                      placeholder={t.recoveryCodePlaceholder}
                      autoFocus
                      className="h-12 w-full rounded-xl border border-slate-200 bg-white pl-4 pr-11 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                    />
                    <button
                      type="button"
                      onClick={() => setShowRecovery((v) => !v)}
                      aria-label={showRecovery ? 'Masquer' : 'Afficher'}
                      className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 transition hover:text-slate-600"
                    >
                      {showRecovery ? (
                        <EyeOff className="h-4 w-4" aria-hidden="true" />
                      ) : (
                        <Eye className="h-4 w-4" aria-hidden="true" />
                      )}
                    </button>
                  </div>
                </div>
              )}

              {/* Remember device */}
              <label className="flex cursor-pointer items-center gap-3">
                <input
                  type="checkbox"
                  checked={rememberDevice}
                  onChange={(e) => setRememberDevice(e.target.checked)}
                  className="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                />
                <span className="text-sm text-slate-600 dark:text-slate-300">{t.rememberDevice}</span>
              </label>

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
                {submitting ? (
                  <><Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />&nbsp;{t.submitting}</>
                ) : (
                  <><KeyRound className="h-4 w-4" aria-hidden="true" />&nbsp;{t.submit}</>
                )}
              </Button>

              {/* Toggle recovery / totp */}
              <button
                type="button"
                onClick={() => { setUseRecovery((v) => !v); setCode(''); setRecoveryCode(''); setError(null); }}
                className="w-full text-center text-xs font-semibold text-teal-700 transition hover:text-teal-900 dark:text-teal-400"
              >
                {useRecovery ? t.useTotpCode : t.useRecoveryCode}
              </button>

              <Link
                href="/auth/login"
                className="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 transition hover:text-slate-700 dark:text-slate-400"
              >
                <ArrowLeft className="h-3.5 w-3.5" aria-hidden="true" />
                {t.backToLogin}
              </Link>
            </form>
          </div>
        </div>
      </div>
    </main>
  );
}
