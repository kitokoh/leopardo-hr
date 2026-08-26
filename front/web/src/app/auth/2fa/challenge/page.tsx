'use client';

/**
 * Issue #5612 — Page de challenge TOTP / code de récupération.
 *
 * Flux :
 *   1. L'utilisateur a soumis son email/mot de passe sur /auth/login.
 *   2. Le backend (TwoFactorAuthController) a répondu avec
 *      { mfa_challenge: true, mfa_challenge_token: '...' }.
 *   3. login/page.tsx a redirigé vers /auth/2fa/challenge?token=<challenge_token>.
 *   4. Cette page envoie POST /auth/2fa/verify avec le challenge_token et le code.
 *   5. En cas de succès, le cookie httpOnly est posé et l'utilisateur est
 *      redirigé vers /dashboard (via /auth/me).
 *
 * Références backend :
 *   - TwoFactorAuthController::verify()
 *   - Endpoint : POST /api/v1/auth/2fa/verify
 *   - Payload  : { challenge_token, code, remember_device? }
 */

import { Suspense, useCallback, useState } from 'react';
import Link from 'next/link';
import { useRouter, useSearchParams } from 'next/navigation';
import {
  ArrowLeft,
  KeyRound,
  Loader2,
  ShieldCheck,
} from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import {
  applyDocumentLocale,
  getPreferredLocale,
  normalizeLocale,
  storeAuthSession,
  type StoredAuthUser,
} from '@/lib/i18n';
import { Button } from '@/components/ui/Button';

// ─── Composant interne (useSearchParams nécessite <Suspense>) ──────────────

function TwoFactorChallengeForm() {
  const router = useRouter();
  const params = useSearchParams();
  const challengeToken = params.get('token') ?? '';

  const [code, setCode] = useState('');
  const [rememberDevice, setRememberDevice] = useState(false);
  const [isRecovery, setIsRecovery] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault();
      const trimmedCode = code.trim();

      if (!trimmedCode) {
        setError('Veuillez saisir votre code.');
        return;
      }

      if (!challengeToken) {
        setError('Token de challenge manquant. Veuillez vous reconnecter.');
        return;
      }

      setSubmitting(true);
      setError(null);

      try {
        // POST /api/v1/auth/2fa/verify — champ `code` pour TOTP,
        // `recovery_code` pour les codes de récupération (#5436).
        await apiFetch('/auth/2fa/verify', {
          method: 'POST',
          body: JSON.stringify({
            challenge_token: challengeToken,
            ...(isRecovery ? { recovery_code: trimmedCode } : { code: trimmedCode }),
            remember_device: rememberDevice,
          }),
        });

        // La réponse 2FA/verify retourne un token Sanctum — le cookie httpOnly
        // est posé par le route handler /api/v1/auth/login via la réponse
        // POST /auth/2fa/verify du backend. Récupérer l'utilisateur pour finir
        // l'initialisation de session.
        const meRes = await apiFetch('/auth/me');
        const mePayload = (await meRes.json()) as { data?: StoredAuthUser };
        const user = mePayload.data;

        if (!user) {
          setError('Session invalide. Veuillez vous reconnecter.');
          return;
        }

        storeAuthSession(null, user);
        applyDocumentLocale(normalizeLocale(user.language), user.is_rtl);
        router.push('/dashboard');
      } catch (err) {
        setError(
          err instanceof Error
            ? err.message
            : 'Code invalide ou expiré. Veuillez réessayer.',
        );
      } finally {
        setSubmitting(false);
      }
    },
    [challengeToken, code, rememberDevice, router],
  );

  return (
    <main className="relative flex min-h-screen items-center justify-center overflow-hidden bg-slate-950 px-4 py-10 text-slate-950 dark:text-white sm:px-6 lg:px-8">
      {/* Background decorations */}
      <div className="auth-surface-dots absolute inset-0 z-0 opacity-10" />
      <div className="auth-surface-glow absolute inset-0 z-0 opacity-70" />

      <div className="relative z-10 w-full max-w-md">
        <div className="overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-slate-900">
          {/* Header */}
          <div className="bg-gradient-to-br from-brand-600 to-emerald-600 p-8 text-white">
            <div className="mb-4 inline-flex items-center gap-2 rounded-full bg-white/20 px-3 py-1 text-xs font-bold uppercase tracking-wider">
              <ShieldCheck className="h-3.5 w-3.5" aria-hidden="true" />
              Authentification à deux facteurs
            </div>
            <h1 className="text-2xl font-black">
              {isRecovery ? 'Code de récupération' : 'Code de vérification'}
            </h1>
            <p className="mt-2 text-sm text-emerald-100">
              {isRecovery
                ? 'Saisissez l\'un de vos codes de récupération à usage unique.'
                : 'Saisissez le code à 6 chiffres affiché par votre application authenticator.'}
            </p>
          </div>

          {/* Form */}
          <div className="p-8 space-y-6">
            <form onSubmit={handleSubmit} noValidate className="space-y-5">
              <div>
                <label
                  htmlFor="totp-code"
                  className="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5"
                >
                  {isRecovery ? 'Code de récupération' : 'Code TOTP'}
                </label>
                <input
                  id="totp-code"
                  type={isRecovery ? 'text' : 'text'}
                  inputMode={isRecovery ? 'text' : 'numeric'}
                  value={code}
                  onChange={(e) => setCode(e.target.value)}
                  maxLength={isRecovery ? 32 : 8}
                  autoComplete="one-time-code"
                  autoFocus
                  required
                  placeholder={isRecovery ? 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx' : '123456'}
                  className="block w-full rounded-2xl border border-slate-200 px-4 py-3 text-center font-mono tracking-[0.35em] text-lg text-slate-900 shadow-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                />
              </div>

              {/* Remember device */}
              {!isRecovery && (
                <label className="flex items-center gap-3 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={rememberDevice}
                    onChange={(e) => setRememberDevice(e.target.checked)}
                    className="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                  />
                  <span className="text-sm text-slate-600 dark:text-slate-400">
                    Se souvenir de cet appareil (30 jours)
                  </span>
                </label>
              )}

              {/* Error */}
              {error && (
                <div className="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm font-medium text-red-700 dark:bg-red-950/20 dark:border-red-900/30 dark:text-red-400">
                  {error}
                </div>
              )}

              <Button
                type="submit"
                disabled={submitting || !code.trim()}
                className="w-full"
              >
                {submitting ? (
                  <>
                    <Loader2 className="mr-2 h-4 w-4 animate-spin" aria-hidden="true" />
                    Vérification en cours…
                  </>
                ) : (
                  <>
                    <KeyRound className="mr-2 h-4 w-4" aria-hidden="true" />
                    Confirmer le code
                  </>
                )}
              </Button>
            </form>

            {/* Toggle recovery / TOTP */}
            <button
              type="button"
              onClick={() => {
                setIsRecovery((v) => !v);
                setCode('');
                setError(null);
              }}
              className="w-full text-center text-sm text-brand-600 hover:text-brand-700 dark:text-brand-400 font-medium transition-colors"
            >
              {isRecovery
                ? 'Utiliser mon application authenticator à la place'
                : 'Utiliser un code de récupération à la place'}
            </button>

            <div className="border-t border-slate-100 dark:border-slate-800 pt-4 text-center">
              <Link
                href="/auth/login"
                className="inline-flex items-center gap-2 text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 transition-colors"
              >
                <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                Retour à la connexion
              </Link>
            </div>
          </div>
        </div>
      </div>
    </main>
  );
}

// ─── Page exportée avec Suspense (useSearchParams) ──────────────────────────

export default function TwoFactorChallengePage() {
  return (
    <Suspense fallback={null}>
      <TwoFactorChallengeForm />
    </Suspense>
  );
}
