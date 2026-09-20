'use client';

/**
 * Issue #7861 — Panneau 2FA partagé (statut, enrôlement QR, désactivation).
 *
 * Factorisation de l'ancienne page /settings/security/2fa (#5612) pour que la
 * gestion 2FA soit intégrée directement dans « Mon compte »
 * (/settings/account) tout en gardant la route dédiée fonctionnelle : les deux
 * écrans rendent ce même composant.
 *
 * Flux d'enrôlement :
 *   1. GET /auth/2fa/status → état actuel
 *   2. POST /auth/2fa/enroll → { secret, qr_code_url, qr_code_svg }
 *   3. Scanner le QR + saisir le premier code TOTP
 *   4. POST /auth/2fa/confirm { code } → { recovery_codes[] }
 *   5. Afficher et faire copier les codes de récupération
 *
 * Flux de désactivation : POST /auth/2fa/disable { code }
 *
 * Références backend : TwoFactorAuthController (#5436).
 * Tous les libellés viennent du catalogue i18n (settingsPage.*) — aucun
 * littéral utilisateur en dur (contrainte repo, 4 locales fr/en/ar/tr).
 */

import { useCallback, useEffect, useState } from 'react';
import {
  CheckCircle2,
  Copy,
  KeyRound,
  Loader2,
  QrCode,
  ShieldCheck,
  ShieldOff,
  X,
} from 'lucide-react';

import { apiFetch } from '@/lib/api-client';
import type { AppLocale } from '@/lib/i18n';
import { t as i18nT } from '@/lib/i18n/locale-catalog';

type TwoFaStatus = {
  enabled: boolean;
  enforced?: boolean;
};

type EnrollData = {
  secret: string;
  qr_code_url: string;
  qr_code_svg?: string;
};

export function TwoFactorPanel({ locale }: { locale: AppLocale }) {
  const [status, setStatus] = useState<TwoFaStatus | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Enrôlement
  const [enrollData, setEnrollData] = useState<EnrollData | null>(null);
  const [enrolling, setEnrolling] = useState(false);
  const [confirmCode, setConfirmCode] = useState('');
  const [confirming, setConfirming] = useState(false);
  const [recoveryCodes, setRecoveryCodes] = useState<string[]>([]);
  const [copied, setCopied] = useState(false);

  // Désactivation
  const [disableCode, setDisableCode] = useState('');
  const [disabling, setDisabling] = useState(false);

  const loadStatus = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const res = await apiFetch('/auth/2fa/status');
      const payload = (await res.json()) as { data?: TwoFaStatus };
      setStatus(payload.data ?? { enabled: false });
    } catch (err) {
      setError(err instanceof Error ? err.message : i18nT(locale, 'settingsPage.twoFa.loadError'));
    } finally {
      setIsLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    void loadStatus();
  }, [loadStatus]);

  const handleEnroll = async () => {
    setEnrolling(true);
    setError(null);
    try {
      const res = await apiFetch('/auth/2fa/enroll', { method: 'POST' });
      const payload = (await res.json()) as { data?: EnrollData };
      setEnrollData(payload.data ?? null);
    } catch (err) {
      setError(err instanceof Error ? err.message : i18nT(locale, 'settingsPage.twoFa.enrollError'));
    } finally {
      setEnrolling(false);
    }
  };

  const handleConfirm = async (e: React.FormEvent) => {
    e.preventDefault();
    const trimmed = confirmCode.trim();
    if (!trimmed) return;
    setConfirming(true);
    setError(null);
    try {
      const res = await apiFetch('/auth/2fa/confirm', {
        method: 'POST',
        body: JSON.stringify({ code: trimmed }),
      });
      const payload = (await res.json()) as { data?: { recovery_codes?: string[] } };
      setRecoveryCodes(payload.data?.recovery_codes ?? []);
      setStatus({ enabled: true });
      setEnrollData(null);
      setConfirmCode('');
    } catch (err) {
      setError(err instanceof Error ? err.message : i18nT(locale, 'settingsPage.twoFa.codeInvalid'));
    } finally {
      setConfirming(false);
    }
  };

  const handleDisable = async (e: React.FormEvent) => {
    e.preventDefault();
    const trimmed = disableCode.trim();
    if (!trimmed) return;
    setDisabling(true);
    setError(null);
    try {
      await apiFetch('/auth/2fa/disable', {
        method: 'POST',
        body: JSON.stringify({ code: trimmed }),
      });
      setStatus({ enabled: false });
      setDisableCode('');
      setRecoveryCodes([]);
      setEnrollData(null);
    } catch (err) {
      setError(err instanceof Error ? err.message : i18nT(locale, 'settingsPage.twoFa.codeInvalid'));
    } finally {
      setDisabling(false);
    }
  };

  const handleCopyCodes = () => {
    void navigator.clipboard.writeText(recoveryCodes.join('\n'));
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  return (
    <div className="space-y-6" data-testid="two-factor-panel">
      {/* Statut */}
      <div className="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4 dark:border-slate-800 dark:bg-slate-900/40">
        <div className="flex items-center gap-3">
          {status?.enabled ? (
            <ShieldCheck className="h-5 w-5 text-emerald-500" aria-hidden="true" />
          ) : (
            <ShieldOff className="h-5 w-5 text-slate-400" aria-hidden="true" />
          )}
          <div>
            <p className="text-sm font-bold text-slate-900 dark:text-white">
              {i18nT(locale, 'settingsPage.twoFactorTitle')}
            </p>
            <p className="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
              {isLoading
                ? i18nT(locale, 'settingsPage.twoFa.loading')
                : status?.enabled
                  ? i18nT(locale, 'settingsPage.twoFa.enabledHint')
                  : i18nT(locale, 'settingsPage.twoFa.disabledHint')}
            </p>
          </div>
        </div>
        <span
          data-testid="two-factor-status-badge"
          className={[
            'shrink-0 rounded-lg border px-3 py-1 text-[11px] font-black uppercase tracking-widest',
            status?.enabled
              ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-400'
              : 'border-slate-200 bg-slate-100 text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400',
          ].join(' ')}
        >
          {status?.enabled
            ? i18nT(locale, 'settingsPage.enabled')
            : i18nT(locale, 'settingsPage.disabled')}
        </span>
      </div>

      {/* Erreur */}
      {error ? (
        <div
          role="alert"
          className="flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 dark:border-red-900/30 dark:bg-red-950/20"
        >
          <X className="mt-0.5 h-4 w-4 shrink-0 text-red-500" aria-hidden="true" />
          <p className="text-sm font-medium text-red-700 dark:text-red-400">{error}</p>
        </div>
      ) : null}

      {isLoading ? (
        <div className="flex h-24 items-center justify-center" role="status" aria-live="polite">
          <Loader2 className="h-6 w-6 animate-spin text-emerald-500" aria-hidden="true" />
          <span className="sr-only">{i18nT(locale, 'settingsPage.twoFa.loading')}</span>
        </div>
      ) : status?.enabled ? (
        /* ── 2FA activée : codes de récupération + désactivation ── */
        <div className="space-y-6">
          {recoveryCodes.length > 0 ? (
            <div className="rounded-2xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-900/30 dark:bg-amber-950/20">
              <div className="mb-3 flex items-center justify-between">
                <h3 className="text-sm font-bold text-amber-800 dark:text-amber-300">
                  {i18nT(locale, 'settingsPage.twoFa.recoveryTitle')}
                </h3>
                <button
                  type="button"
                  onClick={handleCopyCodes}
                  className="inline-flex items-center gap-1.5 text-xs font-bold text-amber-700 transition-colors hover:text-amber-900 dark:text-amber-400"
                >
                  {copied ? (
                    <CheckCircle2 className="h-3.5 w-3.5" aria-hidden="true" />
                  ) : (
                    <Copy className="h-3.5 w-3.5" aria-hidden="true" />
                  )}
                  {copied
                    ? i18nT(locale, 'settingsPage.twoFa.copied')
                    : i18nT(locale, 'settingsPage.twoFa.copyAll')}
                </button>
              </div>
              <p className="mb-3 text-xs text-amber-700 dark:text-amber-400">
                {i18nT(locale, 'settingsPage.twoFa.recoveryHint')}
              </p>
              <div className="grid grid-cols-2 gap-2">
                {recoveryCodes.map((code) => (
                  <code
                    key={code}
                    className="rounded-lg bg-amber-100 px-3 py-1.5 text-center font-mono text-xs text-amber-900 dark:bg-amber-900/30 dark:text-amber-200"
                  >
                    {code}
                  </code>
                ))}
              </div>
            </div>
          ) : null}

          {/* Désactivation */}
          <div className="rounded-2xl border border-slate-200 p-5 dark:border-slate-800">
            <h3 className="mb-1 text-sm font-bold text-slate-900 dark:text-white">
              {i18nT(locale, 'settingsPage.disable2fa')}
            </h3>
            <p className="mb-4 text-xs text-slate-500 dark:text-slate-400">
              {i18nT(locale, 'settingsPage.twoFa.disableHint')}
            </p>
            <form onSubmit={handleDisable} className="flex gap-3">
              <input
                type="text"
                inputMode="numeric"
                value={disableCode}
                onChange={(e) => setDisableCode(e.target.value)}
                maxLength={8}
                placeholder="123456"
                aria-label={i18nT(locale, 'settingsPage.disable2fa')}
                data-testid="two-factor-disable-code"
                className="flex-1 rounded-xl border border-slate-200 px-3 py-2 text-center font-mono text-sm tracking-widest outline-none transition focus:border-red-400 focus:ring-2 focus:ring-red-400/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
              />
              <button
                type="submit"
                disabled={disabling || !disableCode.trim()}
                data-testid="two-factor-disable-submit"
                className="inline-flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-sm font-bold text-red-700 transition hover:bg-red-100 disabled:opacity-50 dark:border-red-900/30 dark:bg-red-950/20 dark:text-red-400"
              >
                {disabling ? (
                  <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
                ) : (
                  <ShieldOff className="h-4 w-4" aria-hidden="true" />
                )}
                {i18nT(locale, 'settingsPage.disable2fa')}
              </button>
            </form>
          </div>
        </div>
      ) : enrollData ? (
        /* ── Enrôlement : scanner le QR puis confirmer ── */
        <div className="space-y-5">
          <div className="rounded-2xl border border-slate-200 p-5 dark:border-slate-800">
            <h3 className="mb-3 text-sm font-bold text-slate-900 dark:text-white">
              {i18nT(locale, 'settingsPage.twoFa.scanTitle')}
            </h3>
            {enrollData.qr_code_svg ? (
              <div
                className="mx-auto w-fit rounded-2xl bg-white p-3 shadow"
                dangerouslySetInnerHTML={{ __html: enrollData.qr_code_svg }}
              />
            ) : (
              <p className="break-all font-mono text-sm text-slate-500">{enrollData.qr_code_url}</p>
            )}
            <p className="mt-3 text-xs text-slate-500 dark:text-slate-400">
              {i18nT(locale, 'settingsPage.manualSecret')}
              <code className="ml-1 rounded-md bg-slate-100 px-2 py-0.5 font-mono text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                {enrollData.secret}
              </code>
            </p>
          </div>

          <div className="rounded-2xl border border-slate-200 p-5 dark:border-slate-800">
            <h3 className="mb-3 text-sm font-bold text-slate-900 dark:text-white">
              {i18nT(locale, 'settingsPage.twoFa.confirmTitle')}
            </h3>
            <form onSubmit={handleConfirm} className="space-y-4">
              <input
                type="text"
                inputMode="numeric"
                value={confirmCode}
                onChange={(e) => setConfirmCode(e.target.value)}
                maxLength={8}
                placeholder="123456"
                aria-label={i18nT(locale, 'settingsPage.twoFa.confirmTitle')}
                data-testid="two-factor-confirm-code"
                className="block w-full rounded-xl border border-slate-200 px-4 py-3 text-center font-mono text-lg tracking-[0.35em] outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
              />
              <div className="flex gap-3">
                <button
                  type="submit"
                  disabled={confirming || !confirmCode.trim()}
                  data-testid="two-factor-confirm-submit"
                  className="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-700 disabled:opacity-50"
                >
                  {confirming ? (
                    <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
                  ) : (
                    <CheckCircle2 className="h-4 w-4" aria-hidden="true" />
                  )}
                  {i18nT(locale, 'settingsPage.enable2fa')}
                </button>
                <button
                  type="button"
                  onClick={() => setEnrollData(null)}
                  className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-400"
                >
                  {i18nT(locale, 'settingsPage.cancel')}
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : (
        /* ── 2FA désactivée : bouton d'activation ── */
        <div className="rounded-2xl border border-slate-200 p-5 dark:border-slate-800">
          <div className="flex items-start gap-4">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
              <QrCode className="h-5 w-5" aria-hidden="true" />
            </div>
            <div className="flex-1">
              <h3 className="text-sm font-bold text-slate-900 dark:text-white">
                {i18nT(locale, 'settingsPage.enable2fa')}
              </h3>
              <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                {i18nT(locale, 'settingsPage.twoFa.enrollHint')}
              </p>
              <button
                type="button"
                onClick={handleEnroll}
                disabled={enrolling}
                data-testid="two-factor-enroll"
                className="mt-4 inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-700 disabled:opacity-50"
              >
                {enrolling ? (
                  <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
                ) : (
                  <KeyRound className="h-4 w-4" aria-hidden="true" />
                )}
                {i18nT(locale, 'settingsPage.twoFa.enrollStart')}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
