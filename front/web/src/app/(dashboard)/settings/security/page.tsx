'use client';

/**
 * Paramètres de sécurité — Authentification à deux facteurs (2FA / TOTP).
 *
 * Issue #5612 — front/web ne disposait d'aucune interface pour enrôler ou
 * désactiver la 2FA. Un manager ayant activé la 2FA depuis l'admin-dashboard
 * se retrouvait bloqué à la connexion via front/web.
 *
 * Flux d'enrôlement :
 *   1. POST /auth/2fa/enroll  → { secret, qr_url }
 *   2. Utilisateur scanne le QR ou entre le secret dans son appli TOTP.
 *   3. POST /auth/2fa/confirm { code } → { recovery_codes: string[] }
 *   4. Afficher les 8 codes de récupération à copier.
 *
 * Désactivation :
 *   POST /auth/2fa/disable { code } (code TOTP courant requis)
 */

import { useCallback, useEffect, useState, useSyncExternalStore } from 'react';
import {
  ShieldCheck,
  ShieldOff,
  QrCode,
  Copy,
  Check,
  Loader2,
  AlertTriangle,
  CheckCircle2,
  Key,
} from 'lucide-react';
import QRCode from 'qrcode';
import { ApiError, apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { Button } from '@/components/ui/Button';
import { getPreferredLocale, type AppLocale } from '@/lib/i18n';
import { t as i18nT } from '@/lib/i18n/locale-catalog';

const emptySubscribe = () => () => {};

type TwoFaStatus = {
  enabled: boolean;
  mfa_required: boolean;
};

type EnrollData = {
  secret: string;
  qr_url: string;
};

type Step = 'idle' | 'enrolling' | 'confirming' | 'done' | 'disabling';

export default function SecuritySettingsPage() {
  const locale = useSyncExternalStore<AppLocale>(emptySubscribe, getPreferredLocale, () => 'fr');

  const [loading, setLoading] = useState(true);
  const [status, setStatus] = useState<TwoFaStatus | null>(null);
  const [step, setStep] = useState<Step>('idle');
  const [enrollData, setEnrollData] = useState<EnrollData | null>(null);
  const [qrDataUrl, setQrDataUrl] = useState<string | null>(null);
  const [recoveryCodes, setRecoveryCodes] = useState<string[]>([]);
  const [code, setCode] = useState('');
  const [disableCode, setDisableCode] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [successMsg, setSuccessMsg] = useState<string | null>(null);
  const [copied, setCopied] = useState(false);
  const [submitting, setSubmitting] = useState(false);

  // Chargement du statut 2FA
  const loadStatus = useCallback(() => {
    setLoading(true);
    apiFetch('/auth/2fa/status')
      .then((r) => r.json() as Promise<{ data?: TwoFaStatus }>)
      .then((payload) => {
        if (payload.data) setStatus(payload.data);
      })
      .catch(() => {
        setError(i18nT(locale, 'twoFa.loadError', 'Impossible de charger le statut 2FA.'));
      })
      .finally(() => setLoading(false));
  }, [locale]);

  useEffect(() => { loadStatus(); }, [loadStatus]);

  // Étape 1 : démarrer l'enrôlement
  const handleStartEnroll = useCallback(async () => {
    setError(null);
    setSubmitting(true);
    try {
      const r = await apiFetch('/auth/2fa/enroll', { method: 'POST' });
      const payload = (await r.json()) as { data?: EnrollData };
      if (!payload.data) throw new Error('Invalid response');
      setEnrollData(payload.data);
      // Génère le QR code (data URL PNG) depuis l'otpauth:// URI retourné par l'API.
      const dataUrl = await QRCode.toDataURL(payload.data.qr_url, { width: 200, margin: 1 });
      setQrDataUrl(dataUrl);
      setStep('enrolling');
    } catch (err) {
      setError(err instanceof ApiError ? err.message : i18nT(locale, 'twoFa.enrollError', 'Impossible de démarrer l\'enrôlement.'));
    } finally {
      setSubmitting(false);
    }
  }, [locale]);

  // Étape 2 : confirmer avec le premier code TOTP
  const handleConfirm = useCallback(async (e: React.FormEvent) => {
    e.preventDefault();
    if (!code) return;
    setError(null);
    setSubmitting(true);
    try {
      const r = await apiFetch('/auth/2fa/confirm', {
        method: 'POST',
        body: JSON.stringify({ code: code.replace(/\s/g, '') }),
      });
      const payload = (await r.json()) as { data?: { recovery_codes?: string[] } };
      const codes = payload.data?.recovery_codes ?? [];
      setRecoveryCodes(codes);
      setStep('done');
      setStatus((s) => s ? { ...s, enabled: true } : { enabled: true, mfa_required: false });
      setSuccessMsg(i18nT(locale, 'twoFa.activationSuccess', 'La 2FA est activée. Conservez vos codes de récupération en lieu sûr.'));
    } catch (err) {
      setError(err instanceof ApiError ? err.message : i18nT(locale, 'twoFa.confirmError', 'Code incorrect. Réessayez.'));
    } finally {
      setSubmitting(false);
    }
  }, [code, locale]);

  // Désactivation
  const handleDisable = useCallback(async (e: React.FormEvent) => {
    e.preventDefault();
    if (!disableCode) return;
    setError(null);
    setSubmitting(true);
    try {
      await apiFetch('/auth/2fa/disable', {
        method: 'POST',
        body: JSON.stringify({ code: disableCode.replace(/\s/g, '') }),
      });
      setStatus((s) => s ? { ...s, enabled: false } : { enabled: false, mfa_required: false });
      setStep('idle');
      setDisableCode('');
      setSuccessMsg(i18nT(locale, 'twoFa.disableSuccess', 'La 2FA a été désactivée.'));
    } catch (err) {
      setError(err instanceof ApiError ? err.message : i18nT(locale, 'twoFa.disableError', 'Code incorrect ou erreur serveur.'));
    } finally {
      setSubmitting(false);
    }
  }, [disableCode, locale]);

  const copyRecoveryCodes = useCallback(() => {
    void navigator.clipboard.writeText(recoveryCodes.join('\n')).then(() => {
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    });
  }, [recoveryCodes]);

  if (loading) {
    return (
      <ModulePageShell title="Sécurité" subtitle="2FA" accentClassName="border-emerald-200/50">
        <div className="flex items-center justify-center py-16">
          <Loader2 className="h-8 w-8 animate-spin text-emerald-500" aria-hidden="true" />
        </div>
      </ModulePageShell>
    );
  }

  return (
    <ModulePageShell
      title={i18nT(locale, 'twoFa.pageTitle', 'Sécurité')}
      subtitle={i18nT(locale, 'twoFa.pageSubtitle', 'Authentification à deux facteurs (TOTP) — protège votre compte en cas de mot de passe compromis.')}
      accentClassName="border-emerald-200/50"
    >
      <div className="max-w-xl space-y-6">
        {/* Messages */}
        {successMsg && (
          <div className="flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-800">
            <CheckCircle2 className="h-5 w-5 shrink-0 text-emerald-500" />
            {successMsg}
          </div>
        )}
        {error && (
          <div role="alert" className="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-medium text-red-800">
            {error}
          </div>
        )}

        {/* Statut */}
        <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
          <div className="flex items-center justify-between gap-4">
            <div className="flex items-center gap-3">
              <div className={`flex h-11 w-11 items-center justify-center rounded-xl text-white ${status?.enabled ? 'bg-emerald-600' : 'bg-slate-400'}`}>
                {status?.enabled ? <ShieldCheck className="h-5 w-5" /> : <ShieldOff className="h-5 w-5" />}
              </div>
              <div>
                <h2 className="text-base font-black text-slate-950">
                  {i18nT(locale, 'twoFa.title', 'Authentification à deux facteurs')}
                </h2>
                <p className={`text-xs font-bold ${status?.enabled ? 'text-emerald-600' : 'text-slate-500'}`}>
                  {status?.enabled
                    ? i18nT(locale, 'twoFa.statusEnabled', 'Activée')
                    : i18nT(locale, 'twoFa.statusDisabled', 'Désactivée')}
                  {status?.mfa_required && !status.enabled && (
                    <span className="ml-2 text-amber-600 flex items-center gap-1">
                      <AlertTriangle className="h-3.5 w-3.5 inline" />
                      {i18nT(locale, 'twoFa.required', 'Requise par votre organisation')}
                    </span>
                  )}
                </p>
              </div>
            </div>

            {!status?.enabled && step === 'idle' && (
              <Button
                type="button"
                loading={submitting}
                className="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-black uppercase tracking-widest text-white hover:bg-emerald-500"
                onClick={() => void handleStartEnroll()}
              >
                {i18nT(locale, 'twoFa.enable', 'Activer')}
              </Button>
            )}
          </div>
        </div>

        {/* Étape enrôlement : QR Code */}
        {step === 'enrolling' && enrollData && (
          <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm space-y-5">
            <div className="flex items-center gap-2">
              <QrCode className="h-5 w-5 text-slate-500" />
              <h3 className="text-base font-black text-slate-950">
                {i18nT(locale, 'twoFa.scanQr', 'Scannez le QR avec votre application TOTP')}
              </h3>
            </div>
            <p className="text-sm text-slate-600">
              {i18nT(locale, 'twoFa.scanHint', 'Utilisez Google Authenticator, Authy ou une application compatible TOTP (RFC 6238).')}
            </p>

            <div className="flex justify-center">
              <div className="rounded-2xl border border-slate-200 bg-white p-2 shadow-sm">
                {qrDataUrl ? (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img src={qrDataUrl} alt="QR Code 2FA" width={200} height={200} className="rounded-xl" />
                ) : (
                  <div className="flex h-[200px] w-[200px] items-center justify-center rounded-xl bg-slate-100">
                    <Loader2 className="h-8 w-8 animate-spin text-slate-400" />
                  </div>
                )}
              </div>
            </div>

            <details className="text-xs text-slate-500">
              <summary className="cursor-pointer hover:text-slate-700 font-semibold">
                {i18nT(locale, 'twoFa.manualEntry', 'Saisie manuelle du secret')}
              </summary>
              <code className="mt-2 block rounded-xl bg-slate-100 px-4 py-2 font-mono text-slate-800 break-all">{enrollData.secret}</code>
            </details>

            <form onSubmit={(e) => void handleConfirm(e)} className="space-y-4">
              <div>
                <label htmlFor="totp-confirm" className="block text-sm font-bold text-slate-700 mb-1.5">
                  {i18nT(locale, 'twoFa.enterFirst', 'Entrez le premier code généré pour confirmer')}
                </label>
                <input
                  id="totp-confirm"
                  type="text"
                  inputMode="numeric"
                  pattern="[0-9 ]{6,7}"
                  maxLength={7}
                  autoFocus
                  placeholder="000 000"
                  className="block h-14 w-full rounded-2xl border border-slate-200 bg-transparent/50 px-4 text-center text-2xl font-mono font-bold tracking-[0.3em] text-slate-950 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
                  value={code}
                  onChange={(e) => { setCode(e.target.value); setError(null); }}
                />
              </div>
              <Button
                type="submit"
                loading={submitting}
                disabled={!code || submitting}
                fullWidth
                className="h-11 rounded-2xl bg-emerald-600 text-xs font-black uppercase tracking-widest text-white hover:bg-emerald-500 disabled:opacity-50"
              >
                {i18nT(locale, 'twoFa.confirm', 'Confirmer l\'activation')}
              </Button>
            </form>
          </div>
        )}

        {/* Étape terminée : affichage des codes de récupération */}
        {step === 'done' && recoveryCodes.length > 0 && (
          <div className="rounded-3xl border border-amber-200 bg-amber-50 p-6 space-y-4">
            <div className="flex items-center gap-2">
              <Key className="h-5 w-5 text-amber-600" />
              <h3 className="text-base font-black text-amber-900">
                {i18nT(locale, 'twoFa.recoveryCodes', 'Codes de récupération — conservez-les précieusement')}
              </h3>
            </div>
            <p className="text-sm text-amber-800">
              {i18nT(locale, 'twoFa.recoveryCodesHint', 'En cas de perte de votre téléphone, utilisez un de ces codes à usage unique pour vous connecter. Chaque code ne peut être utilisé qu\'une seule fois.')}
            </p>
            <div className="grid grid-cols-2 gap-2">
              {recoveryCodes.map((c) => (
                <code key={c} className="rounded-xl bg-white border border-amber-200 px-3 py-2 text-center text-sm font-mono font-bold text-amber-900">{c}</code>
              ))}
            </div>
            <button
              type="button"
              onClick={copyRecoveryCodes}
              className="inline-flex items-center gap-2 rounded-xl border border-amber-300 bg-white px-4 py-2 text-sm font-bold text-amber-800 hover:bg-amber-100 transition"
            >
              {copied ? <Check className="h-4 w-4 text-emerald-600" /> : <Copy className="h-4 w-4" />}
              {copied
                ? i18nT(locale, 'twoFa.copied', 'Copié !')
                : i18nT(locale, 'twoFa.copy', 'Copier les codes')}
            </button>
          </div>
        )}

        {/* Désactivation */}
        {status?.enabled && step !== 'done' && (
          <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
            <h3 className="text-base font-black text-slate-950">
              {i18nT(locale, 'twoFa.disableTitle', 'Désactiver la 2FA')}
            </h3>
            <p className="text-sm text-slate-600">
              {i18nT(locale, 'twoFa.disableHint', 'Saisissez votre code TOTP actuel pour confirmer la désactivation.')}
            </p>
            <form onSubmit={(e) => void handleDisable(e)} className="flex flex-col sm:flex-row gap-3">
              <input
                type="text"
                inputMode="numeric"
                maxLength={7}
                placeholder="000 000"
                className="h-11 rounded-2xl border border-slate-200 bg-transparent/50 px-4 text-center font-mono font-bold tracking-[0.3em] text-slate-950 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-red-400 focus:ring-2 focus:ring-red-400/20 flex-1"
                value={disableCode}
                onChange={(e) => { setDisableCode(e.target.value); setError(null); }}
              />
              <Button
                type="submit"
                loading={submitting}
                disabled={!disableCode || submitting}
                className="h-11 rounded-2xl border border-red-300 bg-red-50 px-5 text-xs font-black uppercase tracking-widest text-red-700 hover:bg-red-100 disabled:opacity-50 shrink-0"
              >
                {i18nT(locale, 'twoFa.disable', 'Désactiver')}
              </Button>
            </form>
          </div>
        )}
      </div>
    </ModulePageShell>
  );
}
