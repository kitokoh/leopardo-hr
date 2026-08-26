'use client';

/**
 * Page de challenge 2FA (#5612).
 *
 * Affichée après /auth/login quand le backend retourne mfa_challenge: true.
 * Lit le challenge_token depuis sessionStorage (posé par la page login),
 * soumet le code TOTP ou le code de récupération, puis redirige vers le
 * dashboard si le backend valide.
 */

import { Suspense, useCallback, useEffect, useMemo, useState, useSyncExternalStore } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import Link from 'next/link';
import { ArrowRight, ShieldCheck, Loader2, KeyRound } from 'lucide-react';
import { ApiError, apiFetch } from '@/lib/api-client';
import { Button } from '@/components/ui/Button';
import {
  applyDocumentLocale,
  getCopy,
  getPreferredLocale,
  normalizeLocale,
  storeAuthSession,
  type AppLocale,
  type StoredAuthUser,
} from '@/lib/i18n';

const MFA_CHALLENGE_TOKEN_KEY = 'mfa_challenge_token';
const emptySubscribe = () => () => {};

function TwoFaChallengeInner() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const locale = useSyncExternalStore<AppLocale>(emptySubscribe, getPreferredLocale, () => 'fr');
  const labels = useMemo(() => getCopy(locale), [locale]);

  const [code, setCode] = useState('');
  const [recoveryCode, setRecoveryCode] = useState('');
  const [useRecovery, setUseRecovery] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [rememberDevice, setRememberDevice] = useState(false);
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    setMounted(true);
    applyDocumentLocale(locale);
  }, [locale]);

  // Récupère le challenge token depuis sessionStorage ou depuis le query param.
  const getChallengeToken = useCallback((): string | null => {
    if (typeof window === 'undefined') return null;
    const fromStorage = window.sessionStorage.getItem(MFA_CHALLENGE_TOKEN_KEY);
    if (fromStorage) return fromStorage;
    return searchParams.get('token');
  }, [searchParams]);

  const handleSubmit = useCallback(async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);

    const challengeToken = getChallengeToken();
    if (!challengeToken) {
      setError(
        locale === 'fr'
          ? 'Session expirée. Veuillez vous reconnecter.'
          : locale === 'ar'
            ? 'انتهت الجلسة. يرجى إعادة تسجيل الدخول.'
            : locale === 'tr'
              ? 'Oturum süresi doldu. Lütfen tekrar giriş yapın.'
              : 'Session expired. Please log in again.',
      );
      return;
    }

    const value = useRecovery ? recoveryCode.trim() : code.replace(/\s/g, '');

    if (!value) {
      setError(
        locale === 'fr'
          ? 'Veuillez saisir un code.'
          : locale === 'ar'
            ? 'يرجى إدخال الرمز.'
            : locale === 'tr'
              ? 'Lütfen kodu girin.'
              : 'Please enter a code.',
      );
      return;
    }

    setSubmitting(true);

    try {
      const body: Record<string, unknown> = {
        challenge_token: challengeToken,
        device_name: 'Web App',
        remember_device: rememberDevice,
      };

      if (useRecovery) {
        body.recovery_code = value;
      } else {
        body.code = value;
      }

      await apiFetch('/auth/2fa/verify', {
        method: 'POST',
        body: JSON.stringify(body),
      });

      // Nettoyage du token de challenge — consommé.
      if (typeof window !== 'undefined') {
        window.sessionStorage.removeItem(MFA_CHALLENGE_TOKEN_KEY);
      }

      // Récupération du profil utilisateur (le cookie httpOnly est posé par le proxy).
      const meResponse = await apiFetch('/auth/me');
      const mePayload = (await meResponse.json()) as { data?: StoredAuthUser };
      const user = mePayload.data;

      if (user) {
        storeAuthSession(null, user);
        applyDocumentLocale(normalizeLocale(user.language), user.is_rtl);
      }

      router.push('/dashboard');
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError(
          locale === 'fr'
            ? 'Code invalide ou expiré. Réessayez.'
            : locale === 'ar'
              ? 'الرمز غير صحيح أو منتهي الصلاحية. حاول مجدداً.'
              : locale === 'tr'
                ? 'Geçersiz veya süresi dolmuş kod. Tekrar deneyin.'
                : 'Invalid or expired code. Please try again.',
        );
      }
    } finally {
      setSubmitting(false);
    }
  }, [code, recoveryCode, useRecovery, rememberDevice, locale, getChallengeToken, router]);

  if (!mounted) return null;

  return (
    <main className="min-h-screen bg-transparent dark:bg-slate-950 px-4 py-6 text-slate-950 dark:text-white sm:px-6 lg:px-8 relative overflow-hidden flex items-center justify-center">
      {/* Background */}
      <div className="absolute inset-0 z-0">
        <div className="absolute top-[-10%] left-[-10%] w-[50%] h-[50%] bg-emerald-500/10 rounded-full blur-[120px] animate-pulse-slow" />
        <div className="absolute bottom-[-10%] right-[-10%] w-[50%] h-[50%] bg-emerald-500/10 rounded-full blur-[120px] animate-pulse-slow" style={{ animationDelay: '1.5s' }} />
      </div>

      <div className="relative z-10 w-full max-w-md animate-fade-in">
        <div className="rounded-[28px] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/50 backdrop-blur-xl shadow-2xl p-8 space-y-7">
          {/* Header */}
          <div className="flex flex-col items-center gap-3 text-center">
            <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-600 text-white shadow-lg">
              <ShieldCheck className="h-7 w-7" aria-hidden="true" />
            </div>
            <div>
              <h1 className="text-2xl font-black tracking-tight text-slate-950 dark:text-white">
                {locale === 'fr' ? 'Vérification 2FA'
                  : locale === 'ar' ? 'التحقق بخطوتين'
                    : locale === 'tr' ? 'İki Faktörlü Doğrulama'
                      : '2FA Verification'}
              </h1>
              <p className="mt-1 text-sm text-slate-500">
                {locale === 'fr'
                  ? 'Ouvrez votre application d\'authentification et saisissez le code à 6 chiffres.'
                  : locale === 'ar'
                    ? 'افتح تطبيق المصادقة وأدخل الرمز المكون من 6 أرقام.'
                    : locale === 'tr'
                      ? 'Kimlik doğrulama uygulamanızı açın ve 6 haneli kodu girin.'
                      : 'Open your authenticator app and enter the 6-digit code.'}
              </p>
            </div>
          </div>

          {/* Error */}
          {error && (
            <div role="alert" className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">
              {error}
            </div>
          )}

          <form onSubmit={(e) => void handleSubmit(e)} className="space-y-5">
            {!useRecovery ? (
              /* Code TOTP */
              <div>
                <label htmlFor="totp-code" className="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                  {locale === 'fr' ? 'Code de vérification'
                    : locale === 'ar' ? 'رمز التحقق'
                      : locale === 'tr' ? 'Doğrulama kodu'
                        : 'Verification code'}
                </label>
                <input
                  id="totp-code"
                  type="text"
                  inputMode="numeric"
                  autoComplete="one-time-code"
                  pattern="[0-9 ]{6,7}"
                  maxLength={7}
                  autoFocus
                  placeholder="000 000"
                  className="block h-14 w-full rounded-2xl border border-slate-200 dark:border-slate-700 bg-transparent/50 dark:bg-slate-800/50 px-4 text-center text-2xl font-mono font-bold tracking-[0.3em] text-slate-950 dark:text-white shadow-sm outline-none transition placeholder:text-slate-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
                  value={code}
                  onChange={(e) => { setCode(e.target.value); setError(null); }}
                />
              </div>
            ) : (
              /* Code de récupération */
              <div>
                <label htmlFor="recovery-code" className="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                  <KeyRound className="inline h-4 w-4 mr-1" aria-hidden="true" />
                  {locale === 'fr' ? 'Code de récupération'
                    : locale === 'ar' ? 'رمز الاسترداد'
                      : locale === 'tr' ? 'Kurtarma kodu'
                        : 'Recovery code'}
                </label>
                <input
                  id="recovery-code"
                  type="text"
                  autoComplete="off"
                  autoFocus
                  placeholder="xxxx-xxxx"
                  className="block h-12 w-full rounded-2xl border border-slate-200 dark:border-slate-700 bg-transparent/50 dark:bg-slate-800/50 px-4 font-mono text-sm text-slate-950 dark:text-white shadow-sm outline-none transition placeholder:text-slate-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
                  value={recoveryCode}
                  onChange={(e) => { setRecoveryCode(e.target.value); setError(null); }}
                />
              </div>
            )}

            {/* Remember device */}
            <label className="flex items-center gap-2.5 text-sm text-slate-700 dark:text-slate-300">
              <input
                type="checkbox"
                className="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                checked={rememberDevice}
                onChange={(e) => setRememberDevice(e.target.checked)}
              />
              {locale === 'fr' ? 'Se souvenir de cet appareil 30 jours'
                : locale === 'ar' ? 'تذكر هذا الجهاز لمدة 30 يومًا'
                  : locale === 'tr' ? 'Bu cihazı 30 gün hatırla'
                    : 'Remember this device for 30 days'}
            </label>

            <Button
              type="submit"
              loading={submitting}
              fullWidth
              className="h-12 rounded-2xl bg-emerald-600 px-4 text-xs font-black uppercase tracking-widest text-white shadow-lg hover:bg-emerald-500 focus:ring-emerald-500"
            >
              {submitting
                ? (locale === 'fr' ? 'Vérification...' : locale === 'ar' ? 'جارٍ التحقق...' : locale === 'tr' ? 'Doğrulanıyor...' : 'Verifying...')
                : (locale === 'fr' ? 'Vérifier' : locale === 'ar' ? 'تحقق' : locale === 'tr' ? 'Doğrula' : 'Verify')}
            </Button>
          </form>

          {/* Basculer code de récupération / TOTP */}
          <div className="text-center">
            <button
              type="button"
              className="text-sm text-teal-700 font-semibold hover:text-teal-900 transition"
              onClick={() => { setUseRecovery((v) => !v); setCode(''); setRecoveryCode(''); setError(null); }}
            >
              {useRecovery
                ? (locale === 'fr' ? '← Utiliser le code TOTP' : locale === 'ar' ? '← استخدام رمز TOTP' : locale === 'tr' ? '← TOTP kodu kullan' : '← Use TOTP code')
                : (locale === 'fr' ? 'Utiliser un code de récupération' : locale === 'ar' ? 'استخدام رمز الاسترداد' : locale === 'tr' ? 'Kurtarma kodu kullan' : 'Use a recovery code')}
            </button>
          </div>

          <div className="text-center">
            <Link href="/auth/login" className="inline-flex items-center gap-1 text-xs text-slate-500 hover:text-slate-800 transition">
              <ArrowRight className="h-3 w-3 rotate-180" aria-hidden="true" />
              {labels.login.back}
            </Link>
          </div>
        </div>
      </div>
    </main>
  );
}

export default function TwoFaChallengePage() {
  return (
    <Suspense fallback={null}>
      <TwoFaChallengeInner />
    </Suspense>
  );
}
