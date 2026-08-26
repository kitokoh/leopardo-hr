'use client';

import { useCallback, useEffect, useState, useSyncExternalStore } from 'react';
import {
  CheckCircle2,
  Copy,
  Loader2,
  QrCode,
  RefreshCw,
  ShieldCheck,
  ShieldOff,
} from 'lucide-react';
import { ApiError, apiFetch } from '@/lib/api-client';
import { getCopy, getPreferredLocale, type AppLocale } from '@/lib/i18n';

type TwoFactorStatus = {
  enabled: boolean;
  mfa_required?: boolean;
};

type EnrollPayload = {
  secret: string;
  qr_url: string;
};

type ConfirmPayload = {
  recovery_codes: string[];
};

/**
 * Issue #5612 — page d'enrôlement 2FA (TOTP) pour le portail web client.
 * Flux : GET /auth/2fa/status → (inactif) POST /auth/2fa/enroll (QR + secret)
 * → POST /auth/2fa/confirm (1er code) → affichage des codes de récupération.
 * Actif : régénération des codes (POST /auth/2fa/recovery-codes) et
 * désactivation (POST /auth/2fa/disable avec code TOTP).
 */
export default function TwoFactorSettingsPage() {
  const locale = useSyncExternalStore<AppLocale>(() => () => {}, getPreferredLocale, () => 'fr');
  const labels = getCopy(locale).twoFactor;

  const [loading, setLoading] = useState(true);
  const [status, setStatus] = useState<TwoFactorStatus | null>(null);
  const [error, setError] = useState<string | null>(null);

  // Enrôlement
  const [enrollData, setEnrollData] = useState<EnrollPayload | null>(null);
  const [enrolling, setEnrolling] = useState(false);
  const [confirmCode, setConfirmCode] = useState('');
  const [confirming, setConfirming] = useState(false);
  const [recoveryCodes, setRecoveryCodes] = useState<string[] | null>(null);
  const [copied, setCopied] = useState(false);

  // Désactivation / régénération
  const [disableCode, setDisableCode] = useState('');
  const [disabling, setDisabling] = useState(false);
  const [regenerating, setRegenerating] = useState(false);

  const loadStatus = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const response = await apiFetch('/auth/2fa/status');
      const payload = (await response.json()) as { data?: TwoFactorStatus };
      setStatus(payload.data ?? null);
    } catch (err) {
      setError(err instanceof Error ? err.message : labels.genericError);
    } finally {
      setLoading(false);
    }
  }, [labels.genericError]);

  useEffect(() => {
    void loadStatus();
  }, [loadStatus]);

  const startEnroll = useCallback(async () => {
    setEnrolling(true);
    setError(null);
    try {
      const response = await apiFetch('/auth/2fa/enroll', { method: 'POST' });
      const payload = (await response.json()) as { data?: EnrollPayload };
      if (!payload.data) {
        throw new Error(labels.genericError);
      }
      setEnrollData(payload.data);
    } catch (err) {
      setError(err instanceof Error ? err.message : labels.genericError);
    } finally {
      setEnrolling(false);
    }
  }, [labels.genericError]);

  const confirmEnrollment = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault();
      setConfirming(true);
      setError(null);
      try {
        const response = await apiFetch('/auth/2fa/confirm', {
          method: 'POST',
          body: JSON.stringify({ code: confirmCode.trim() }),
        });
        const payload = (await response.json()) as { data?: ConfirmPayload };
        setRecoveryCodes(payload.data?.recovery_codes ?? []);
        setEnrollData(null);
        setConfirmCode('');
        await loadStatus();
      } catch (err) {
        if (err instanceof ApiError) {
          setError(err.message);
        } else {
          setError(err instanceof Error ? err.message : labels.genericError);
        }
        setConfirmCode('');
      } finally {
        setConfirming(false);
      }
    },
    [confirmCode, labels.genericError, loadStatus],
  );

  const copyRecoveryCodes = useCallback(async () => {
    if (!recoveryCodes) return;
    try {
      await navigator.clipboard.writeText(recoveryCodes.join('\n'));
      setCopied(true);
      setTimeout(() => setCopied(false), 2500);
    } catch {
      setCopied(false);
    }
  }, [recoveryCodes]);

  const regenerateCodes = useCallback(async () => {
    setRegenerating(true);
    setError(null);
    try {
      const response = await apiFetch('/auth/2fa/recovery-codes', { method: 'POST' });
      const payload = (await response.json()) as { data?: { recovery_codes?: string[] } };
      setRecoveryCodes(payload.data?.recovery_codes ?? []);
    } catch (err) {
      setError(err instanceof Error ? err.message : labels.genericError);
    } finally {
      setRegenerating(false);
    }
  }, [labels.genericError]);

  const disableTwoFactor = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault();
      setDisabling(true);
      setError(null);
      try {
        await apiFetch('/auth/2fa/disable', {
          method: 'POST',
          body: JSON.stringify({ code: disableCode.trim() }),
        });
        setDisableCode('');
        setRecoveryCodes(null);
        await loadStatus();
      } catch (err) {
        if (err instanceof ApiError) {
          setError(err.message);
        } else {
          setError(err instanceof Error ? err.message : labels.genericError);
        }
        setDisableCode('');
      } finally {
        setDisabling(false);
      }
    },
    [disableCode, labels.genericError, loadStatus],
  );

  if (loading) {
    return (
      <div className="flex items-center justify-center py-20">
        <Loader2 className="h-8 w-8 animate-spin text-emerald-600" aria-hidden="true" />
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-2xl px-4 py-10">
      <div className="mb-8">
        <h1 className="text-2xl font-black text-slate-900 dark:text-white">{labels.settingsTitle}</h1>
        <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">{labels.settingsSubtitle}</p>
      </div>

      {error && (
        <div className="mb-6 rounded-xl bg-red-50 px-4 py-3 text-sm font-medium text-red-700 dark:bg-red-950/40 dark:text-red-300">
          {error}
        </div>
      )}

      {/* Codes de récupération (affichés une fois après activation / régénération) */}
      {recoveryCodes && (
        <div className="mb-8 rounded-2xl border border-emerald-200 bg-emerald-50 p-6 dark:border-emerald-800 dark:bg-emerald-950/30">
          <h2 className="flex items-center gap-2 text-lg font-bold text-emerald-900 dark:text-emerald-200">
            <ShieldCheck className="h-5 w-5" aria-hidden="true" />
            {labels.recoveryCodesTitle}
          </h2>
          <p className="mt-1 text-sm text-emerald-800 dark:text-emerald-300">{labels.recoveryCodesBody}</p>
          <ul className="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
            {recoveryCodes.map((rc) => (
              <li
                key={rc}
                className="rounded-lg bg-white px-3 py-2 font-mono text-sm font-semibold tracking-wider text-slate-800 shadow-sm dark:bg-slate-800 dark:text-slate-100"
              >
                {rc}
              </li>
            ))}
          </ul>
          <div className="mt-4 flex flex-wrap gap-3">
            <button
              type="button"
              onClick={() => void copyRecoveryCodes()}
              className="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-emerald-700"
            >
              <Copy className="h-4 w-4" aria-hidden="true" />
              {copied ? labels.recoveryCodesCopied : labels.recoveryCodesCopy}
            </button>
            <button
              type="button"
              onClick={() => setRecoveryCodes(null)}
              className="inline-flex items-center gap-2 rounded-lg border border-emerald-300 px-4 py-2 text-sm font-bold text-emerald-800 transition hover:bg-emerald-100 dark:border-emerald-700 dark:text-emerald-200 dark:hover:bg-emerald-900"
            >
              {labels.recoveryCodesDone}
            </button>
          </div>
        </div>
      )}

      {status?.enabled ? (
        <div className="rounded-2xl border border-emerald-200 bg-white p-6 shadow-sm dark:border-emerald-800 dark:bg-slate-900">
          <div className="flex items-center gap-2 text-emerald-700 dark:text-emerald-300">
            <CheckCircle2 className="h-5 w-5" aria-hidden="true" />
            <h2 className="text-lg font-bold">{labels.statusActive}</h2>
          </div>

          <button
            type="button"
            onClick={() => void regenerateCodes()}
            disabled={regenerating}
            className="mt-6 inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-100 disabled:opacity-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800"
          >
            {regenerating ? (
              <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
            ) : (
              <RefreshCw className="h-4 w-4" aria-hidden="true" />
            )}
            {labels.regenerateCodes}
          </button>

          <form className="mt-8 border-t border-slate-200 pt-6 dark:border-slate-700" onSubmit={disableTwoFactor}>
            <h3 className="flex items-center gap-2 text-base font-bold text-slate-800 dark:text-slate-100">
              <ShieldOff className="h-4 w-4" aria-hidden="true" />
              {labels.disableTitle}
            </h3>
            <div className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
              <label className="flex-1">
                <span className="mb-1.5 block text-sm font-bold text-slate-700 dark:text-slate-300">
                  {labels.disableCodeLabel}
                </span>
                <input
                  type="text"
                  inputMode="numeric"
                  autoComplete="one-time-code"
                  maxLength={16}
                  value={disableCode}
                  onChange={(e) => setDisableCode(e.target.value.replace(/[^0-9]/g, ''))}
                  placeholder={labels.disableCodePlaceholder}
                  className="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-center tracking-[0.3em] text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                  required
                  disabled={disabling}
                />
              </label>
              <button
                type="submit"
                disabled={disabling || disableCode.trim() === ''}
                className="inline-flex items-center justify-center gap-2 rounded-xl bg-red-600 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-red-700 disabled:opacity-50"
              >
                {disabling && <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />}
                {labels.disable}
              </button>
            </div>
          </form>
        </div>
      ) : (
        <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
          <p className="text-sm font-medium text-slate-500 dark:text-slate-400">{labels.statusInactive}</p>

          {!enrollData ? (
            <button
              type="button"
              onClick={() => void startEnroll()}
              disabled={enrolling}
              className="mt-6 inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-700 disabled:opacity-50"
            >
              {enrolling ? (
                <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
              ) : (
                <ShieldCheck className="h-4 w-4" aria-hidden="true" />
              )}
              {labels.enable}
            </button>
          ) : (
            <div className="mt-6">
              <p className="text-sm text-slate-600 dark:text-slate-300">{labels.enableStepQr}</p>
              {/* eslint-disable-next-line @next/next/no-img-element -- URL QR générée par le backend */}
              <img
                src={enrollData.qr_url}
                alt={labels.enableStepQr}
                width={200}
                height={200}
                className="mx-auto mt-4 rounded-xl border border-slate-200 dark:border-slate-700"
              />
              <p className="mt-4 text-xs text-slate-500 dark:text-slate-400">
                {labels.settingsSubtitle} — {enrollData.secret}
              </p>

              <p className="mt-6 text-sm text-slate-600 dark:text-slate-300">{labels.enableStepCode}</p>
              <form className="mt-3 flex flex-col gap-3 sm:flex-row sm:items-end" onSubmit={confirmEnrollment}>
                <label className="flex-1">
                  <span className="mb-1.5 block text-sm font-bold text-slate-700 dark:text-slate-300">
                    {labels.confirmCodeLabel}
                  </span>
                  <input
                    type="text"
                    inputMode="numeric"
                    autoComplete="one-time-code"
                    maxLength={16}
                    value={confirmCode}
                    onChange={(e) => setConfirmCode(e.target.value.replace(/[^0-9]/g, ''))}
                    placeholder={labels.confirmCodePlaceholder}
                    className="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-center tracking-[0.3em] text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                    required
                    disabled={confirming}
                  />
                </label>
                <button
                  type="submit"
                  disabled={confirming || confirmCode.trim() === ''}
                  className="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-700 disabled:opacity-50"
                >
                  {confirming && <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />}
                  {labels.confirm}
                </button>
              </form>
            </div>
          )}
        </div>
      )}

      <div className="mt-8 flex items-center gap-2 text-xs text-slate-400 dark:text-slate-500">
        <QrCode className="h-4 w-4" aria-hidden="true" />
        TOTP (RFC 6238)
      </div>
    </div>
  );
}
