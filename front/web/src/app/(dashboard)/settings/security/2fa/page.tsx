'use client';

/**
 * Issue #5612 — Page de gestion 2FA (enrôlement, confirmation, désactivation).
 *
 * Accessible depuis /settings/security/2fa (managers authentifiés).
 *
 * Flux d'enrôlement :
 *   1. GET /auth/2fa/status → état actuel
 *   2. POST /auth/2fa/enroll → { secret, qr_code_url, qr_code_svg }
 *   3. Scanner le QR + saisir le premier code TOTP
 *   4. POST /auth/2fa/confirm { code } → { recovery_codes[] }
 *   5. Afficher et faire copier les 8 codes de récupération
 *
 * Flux de désactivation :
 *   POST /auth/2fa/disable { code }
 *
 * Références backend : TwoFactorAuthController (#5436)
 */

import { useCallback, useEffect, useState, useSyncExternalStore } from 'react';
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
import { ApiError, apiFetch } from '@/lib/api-client';
import { getPreferredLocale, type AppLocale } from '@/lib/i18n';
import { ModulePageShell } from '@/components/module-page-shell';

const emptySubscribe = () => () => {};

type TwoFaStatus = {
  enabled: boolean;
  enforced?: boolean;
};

type EnrollData = {
  secret: string;
  qr_code_url: string;
  qr_code_svg?: string;
};

export default function TwoFactorSettingsPage() {
  const locale = useSyncExternalStore<AppLocale>(emptySubscribe, getPreferredLocale, () => 'fr');

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
      setError(err instanceof Error ? err.message : 'Erreur lors du chargement.');
    } finally {
      setIsLoading(false);
    }
  }, []);

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
      setError(err instanceof Error ? err.message : 'Erreur lors de l\'activation.');
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
      const codes = payload.data?.recovery_codes ?? [];
      setRecoveryCodes(codes);
      setStatus({ enabled: true });
      setEnrollData(null);
      setConfirmCode('');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Code invalide. Réessayez.');
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
      setError(err instanceof Error ? err.message : 'Code invalide. La 2FA est toujours active.');
    } finally {
      setDisabling(false);
    }
  };

  const handleCopyCodes = () => {
    navigator.clipboard.writeText(recoveryCodes.join('\n'));
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  // ─── Render ───────────────────────────────────────────────────────────────

  return (
    <ModulePageShell
      title="Authentification à deux facteurs"
      subtitle="Ajoutez une couche de sécurité supplémentaire à votre compte."
      accentClassName="from-brand-600 to-emerald-600"
    >
      <div className="max-w-xl space-y-6">
        {/* Status */}
        <div className="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4 dark:border-slate-800 dark:bg-slate-900/40">
          <div className="flex items-center gap-3">
            {status?.enabled ? (
              <ShieldCheck className="h-5 w-5 text-emerald-500" aria-hidden="true" />
            ) : (
              <ShieldOff className="h-5 w-5 text-slate-400" aria-hidden="true" />
            )}
            <div>
              <p className="font-bold text-slate-900 dark:text-white text-sm">
                Authentification à deux facteurs
              </p>
              <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                {isLoading
                  ? 'Chargement…'
                  : status?.enabled
                    ? 'Activée — votre compte est protégé.'
                    : 'Désactivée — votre compte est moins sécurisé.'}
              </p>
            </div>
          </div>
          <span
            className={[
              'px-3 py-1 text-[11px] font-black uppercase tracking-widest rounded-lg border shrink-0',
              status?.enabled
                ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-800'
                : 'bg-slate-100 text-slate-500 border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700',
            ].join(' ')}
          >
            {status?.enabled ? 'Activée' : 'Désactivée'}
          </span>
        </div>

        {/* Error */}
        {error && (
          <div className="flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 dark:border-red-900/30 dark:bg-red-950/20">
            <X className="mt-0.5 h-4 w-4 shrink-0 text-red-500" aria-hidden="true" />
            <p className="text-sm font-medium text-red-700 dark:text-red-400">{error}</p>
          </div>
        )}

        {/* Loading */}
        {isLoading ? (
          <div className="flex h-24 items-center justify-center">
            <Loader2 className="h-6 w-6 animate-spin text-brand-500" aria-hidden="true" />
          </div>
        ) : status?.enabled ? (
          /* ── 2FA activée : afficher les codes de récupération ou désactivation ── */
          <div className="space-y-6">
            {/* Codes de récupération (juste après activation) */}
            {recoveryCodes.length > 0 && (
              <div className="rounded-2xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-900/30 dark:bg-amber-950/20">
                <div className="flex items-center justify-between mb-3">
                  <h3 className="font-bold text-amber-800 dark:text-amber-300 text-sm">
                    Codes de récupération — conservez-les en lieu sûr
                  </h3>
                  <button
                    type="button"
                    onClick={handleCopyCodes}
                    className="inline-flex items-center gap-1.5 text-xs font-bold text-amber-700 hover:text-amber-900 dark:text-amber-400 transition-colors"
                  >
                    {copied ? (
                      <CheckCircle2 className="h-3.5 w-3.5" aria-hidden="true" />
                    ) : (
                      <Copy className="h-3.5 w-3.5" aria-hidden="true" />
                    )}
                    {copied ? 'Copié !' : 'Copier tout'}
                  </button>
                </div>
                <p className="text-xs text-amber-700 dark:text-amber-400 mb-3">
                  Chacun de ces codes ne peut être utilisé qu'une seule fois pour vous connecter
                  si vous perdez accès à votre application authenticator.
                </p>
                <div className="grid grid-cols-2 gap-2">
                  {recoveryCodes.map((code) => (
                    <code
                      key={code}
                      className="rounded-lg bg-amber-100 dark:bg-amber-900/30 px-3 py-1.5 text-xs font-mono text-amber-900 dark:text-amber-200 text-center"
                    >
                      {code}
                    </code>
                  ))}
                </div>
              </div>
            )}

            {/* Désactivation */}
            <div className="rounded-2xl border border-slate-200 dark:border-slate-800 p-5">
              <h3 className="font-bold text-slate-900 dark:text-white text-sm mb-1">
                Désactiver la 2FA
              </h3>
              <p className="text-xs text-slate-500 dark:text-slate-400 mb-4">
                Saisissez votre code TOTP actuel pour désactiver l'authentification à deux facteurs.
              </p>
              <form onSubmit={handleDisable} className="flex gap-3">
                <input
                  type="text"
                  inputMode="numeric"
                  value={disableCode}
                  onChange={(e) => setDisableCode(e.target.value)}
                  maxLength={8}
                  placeholder="123456"
                  className="flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono text-center tracking-widest outline-none transition focus:border-red-400 focus:ring-2 focus:ring-red-400/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                />
                <button
                  type="submit"
                  disabled={disabling || !disableCode.trim()}
                  className="inline-flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-sm font-bold text-red-700 transition hover:bg-red-100 disabled:opacity-50 dark:border-red-900/30 dark:bg-red-950/20 dark:text-red-400"
                >
                  {disabling ? (
                    <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
                  ) : (
                    <ShieldOff className="h-4 w-4" aria-hidden="true" />
                  )}
                  Désactiver
                </button>
              </form>
            </div>
          </div>
        ) : enrollData ? (
          /* ── Étape d'enrôlement : scanner le QR et confirmer ── */
          <div className="space-y-5">
            <div className="rounded-2xl border border-slate-200 dark:border-slate-800 p-5">
              <h3 className="font-bold text-slate-900 dark:text-white text-sm mb-3">
                Étape 1 — Scanner le QR code
              </h3>
              {enrollData.qr_code_svg ? (
                <div
                  className="mx-auto w-fit rounded-2xl bg-white p-3 shadow"
                  dangerouslySetInnerHTML={{ __html: enrollData.qr_code_svg }}
                />
              ) : (
                <p className="text-sm text-slate-500 break-all font-mono">{enrollData.qr_code_url}</p>
              )}
              <p className="mt-3 text-xs text-slate-500 dark:text-slate-400">
                Ou saisissez manuellement le code secret :
                <code className="ml-1 rounded-md bg-slate-100 px-2 py-0.5 font-mono text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                  {enrollData.secret}
                </code>
              </p>
            </div>

            <div className="rounded-2xl border border-slate-200 dark:border-slate-800 p-5">
              <h3 className="font-bold text-slate-900 dark:text-white text-sm mb-3">
                Étape 2 — Confirmer avec un code TOTP
              </h3>
              <form onSubmit={handleConfirm} className="space-y-4">
                <input
                  type="text"
                  inputMode="numeric"
                  value={confirmCode}
                  onChange={(e) => setConfirmCode(e.target.value)}
                  maxLength={8}
                  autoFocus
                  placeholder="123456"
                  className="block w-full rounded-xl border border-slate-200 px-4 py-3 text-center font-mono tracking-[0.35em] text-lg outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                />
                <div className="flex gap-3">
                  <button
                    type="submit"
                    disabled={confirming || !confirmCode.trim()}
                    className="flex-1 inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brand-700 disabled:opacity-50"
                  >
                    {confirming ? (
                      <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
                    ) : (
                      <CheckCircle2 className="h-4 w-4" aria-hidden="true" />
                    )}
                    Activer la 2FA
                  </button>
                  <button
                    type="button"
                    onClick={() => setEnrollData(null)}
                    className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-400"
                  >
                    Annuler
                  </button>
                </div>
              </form>
            </div>
          </div>
        ) : (
          /* ── 2FA désactivée : bouton d'activation ── */
          <div className="rounded-2xl border border-slate-200 dark:border-slate-800 p-5">
            <div className="flex items-start gap-4">
              <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-100 text-brand-700 dark:bg-brand-900/30 dark:text-brand-400">
                <QrCode className="h-5 w-5" aria-hidden="true" />
              </div>
              <div className="flex-1">
                <h3 className="font-bold text-slate-900 dark:text-white text-sm">
                  Activer l'authentification à deux facteurs
                </h3>
                <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                  Utilisez une application authenticator (Google Authenticator, Authy, 1Password…)
                  pour générer des codes TOTP à usage unique.
                </p>
                <button
                  type="button"
                  onClick={handleEnroll}
                  disabled={enrolling}
                  className="mt-4 inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brand-700 disabled:opacity-50"
                >
                  {enrolling ? (
                    <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
                  ) : (
                    <KeyRound className="h-4 w-4" aria-hidden="true" />
                  )}
                  Commencer l'enrôlement
                </button>
              </div>
            </div>
          </div>
        )}
      </div>
    </ModulePageShell>
  );
}
