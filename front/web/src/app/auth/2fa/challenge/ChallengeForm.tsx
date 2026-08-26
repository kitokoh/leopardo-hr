'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { ArrowLeft, KeyRound, Loader2, ShieldCheck } from 'lucide-react';
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

const CHALLENGE_TOKEN_KEY = 'mfa_challenge_token';

function resolvePostLoginTarget(user: StoredAuthUser): string {
  if (user.role === 'super_admin') {
    return process.env.NEXT_PUBLIC_ADMIN_URL || '/dashboard';
  }

  return '/dashboard';
}

/**
 * Issue #5612 — formulaire de challenge 2FA (code TOTP ou code de
 * récupération). Le challenge_token à usage unique est lu dans
 * sessionStorage (posé par /auth/login quand le backend répond
 * mfa_challenge:true), puis POST /api/v1/auth/2fa/verify — le route handler
 * stocke le token de session dans le cookie httpOnly. On hydrate ensuite
 * /auth/me (même parcours que login) avant de rediriger vers le dashboard.
 */
export function ChallengeForm() {
  const { locale } = useVitrineLocale();
  const appLocale: AppLocale = normalizeLocale(locale ?? 'fr');
  const labels = getCopy(appLocale).twoFactor;
  const router = useRouter();

  const [code, setCode] = useState('');
  const [recoveryCode, setRecoveryCode] = useState('');
  const [useRecovery, setUseRecovery] = useState(false);
  const [rememberDevice, setRememberDevice] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [challengeMissing, setChallengeMissing] = useState(false);

  useEffect(() => {
    if (typeof window === 'undefined') return;
    if (!window.sessionStorage.getItem(CHALLENGE_TOKEN_KEY)) {
      setChallengeMissing(true);
    }
  }, []);

  const handleSubmit = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault();
      const challengeToken =
        typeof window !== 'undefined'
          ? window.sessionStorage.getItem(CHALLENGE_TOKEN_KEY)
          : null;

      if (!challengeToken) {
        setChallengeMissing(true);
        return;
      }

      setError(null);
      setSubmitting(true);

      try {
        const payload = useRecovery
          ? { recovery_code: recoveryCode.trim() }
          : { code: code.trim() };

        const response = await apiFetch('/auth/2fa/verify', {
          method: 'POST',
          body: JSON.stringify({
            ...payload,
            challenge_token: challengeToken,
            device_name: typeof navigator !== 'undefined' ? navigator.userAgent.slice(0, 200) : undefined,
            remember_device: rememberDevice,
          }),
        });

        if (!response.ok) {
          const data = await response.json().catch(() => null);
          const message =
            data && typeof data === 'object' && 'message' in data
              ? String((data as Record<string, unknown>).message)
              : labels.invalidCode;
          throw new Error(message);
        }

        // Le cookie httpOnly est posé — hydrater le profil utilisateur.
        const meResponse = await apiFetch('/auth/me');
        const mePayload = (await meResponse.json()) as { data?: StoredAuthUser };
        const user = mePayload.data;

        if (!user) {
          throw new Error(labels.genericError);
        }

        storeAuthSession(null, user);
        applyDocumentLocale(normalizeLocale(user.language), user.is_rtl);
        if (typeof window !== 'undefined') {
          window.sessionStorage.removeItem(CHALLENGE_TOKEN_KEY);
          window.sessionStorage.removeItem('mfa_challenge_expires_in');
        }

        const target = resolvePostLoginTarget(user);
        if (/^https?:\/\//.test(target)) {
          window.location.assign(target);
        } else {
          router.push(target);
        }
      } catch (err) {
        if (err instanceof ApiError) {
          setError(err.message);
        } else if (err instanceof Error) {
          setError(err.message);
        } else {
          setError(labels.genericError);
        }
        setCode('');
        setRecoveryCode('');
      } finally {
        setSubmitting(false);
      }
    },
    [code, recoveryCode, useRecovery, rememberDevice, labels, router],
  );

  if (challengeMissing) {
    return (
      <div className="rounded-2xl bg-red-50 p-6 text-center dark:bg-red-950/40">
        <p className="text-sm font-medium text-red-700 dark:text-red-300">{labels.missingToken}</p>
        <Link
          href="/auth/login"
          className="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-emerald-600 hover:text-emerald-700 dark:text-emerald-400"
        >
          <ArrowLeft className="h-4 w-4" aria-hidden="true" />
          {labels.backToLogin}
        </Link>
      </div>
    );
  }

  return (
    <div className="overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-slate-900">
      <div className="bg-gradient-to-br from-emerald-500 to-teal-600 p-8 text-white">
        <div className="mb-4 inline-flex items-center gap-2 rounded-full bg-white/20 px-3 py-1 text-xs font-bold uppercase tracking-wider">
          <ShieldCheck className="h-3.5 w-3.5" aria-hidden="true" />
          Leopardo RH
        </div>
        <h1 className="text-2xl font-black">{labels.challengeTitle}</h1>
        <p className="mt-2 text-sm text-emerald-50">{labels.challengeSubtitle}</p>
      </div>

      <form className="space-y-6 p-8" onSubmit={handleSubmit}>
        {!useRecovery ? (
          <div>
            <label
              htmlFor="mfa-code"
              className="mb-1.5 block text-sm font-bold text-slate-700 dark:text-slate-300"
            >
              {labels.codeLabel}
            </label>
            <input
              id="mfa-code"
              type="text"
              inputMode="numeric"
              autoComplete="one-time-code"
              maxLength={16}
              value={code}
              onChange={(e) => setCode(e.target.value.replace(/[^0-9]/g, ''))}
              placeholder={labels.codePlaceholder}
              className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-center text-lg tracking-[0.4em] text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
              required
              disabled={submitting}
            />
          </div>
        ) : (
          <div>
            <label
              htmlFor="mfa-recovery"
              className="mb-1.5 block text-sm font-bold text-slate-700 dark:text-slate-300"
            >
              {labels.recoveryCodeLabel}
            </label>
            <input
              id="mfa-recovery"
              type="text"
              autoComplete="one-time-code"
              maxLength={32}
              value={recoveryCode}
              onChange={(e) => setRecoveryCode(e.target.value)}
              placeholder={labels.recoveryCodePlaceholder}
              className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-center text-base uppercase tracking-wider text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
              required
              disabled={submitting}
            />
          </div>
        )}

        <button
          type="button"
          onClick={() => setUseRecovery((v) => !v)}
          className="text-sm font-semibold text-emerald-600 hover:text-emerald-700 dark:text-emerald-400"
          disabled={submitting}
        >
          {useRecovery ? labels.codeLabel : labels.recoveryToggle}
        </button>

        <label className="flex items-start gap-3 text-sm text-slate-600 dark:text-slate-300">
          <input
            type="checkbox"
            checked={rememberDevice}
            onChange={(e) => setRememberDevice(e.target.checked)}
            className="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
            disabled={submitting}
          />
          <span>{labels.rememberDevice}</span>
        </label>

        {error && (
          <div className="rounded-xl bg-red-50 px-4 py-3 text-sm font-medium text-red-700 dark:bg-red-950/40 dark:text-red-300">
            {error}
          </div>
        )}

        <Button
          type="submit"
          disabled={submitting || (useRecovery ? recoveryCode.trim() === '' : code.trim() === '')}
          className="w-full"
        >
          {submitting ? (
            <>
              <Loader2 className="mr-2 h-4 w-4 animate-spin" aria-hidden="true" />
              {labels.submitting}
            </>
          ) : (
            <>
              <KeyRound className="mr-2 h-4 w-4" aria-hidden="true" />
              {labels.submit}
            </>
          )}
        </Button>

        <div className="text-center">
          <Link
            href="/auth/login"
            className="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200"
          >
            <ArrowLeft className="h-4 w-4" aria-hidden="true" />
            {labels.backToLogin}
          </Link>
        </div>
      </form>
    </div>
  );
}
