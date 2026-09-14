'use client';

import React, { useReducer, useState, useRef, useCallback, useEffect } from 'react';
import Link from 'next/link';
import { useForm } from 'react-hook-form';
import { useRouter } from 'next/navigation';
import { zodResolver } from '@hookform/resolvers/zod';
import { motion, AnimatePresence } from 'framer-motion';
import {
  AlertCircle,
  ArrowLeft,
  ArrowRight,
  BarChart3,
  Briefcase,
  Building2,
  CalendarClock,
  Calculator,
  Check,
  CheckCircle,
  Clock3,
  ClipboardCopy,
  Contact,
  Download,
  Fingerprint,
  Fuel,
  GraduationCap,
  Globe,
  KeyRound,
  LogIn,
  Mail,
  Megaphone,
  Phone,
  Rocket,
  ShieldCheck,
  Sparkles,
  Users,
  UtensilsCrossed,
  Wallet,
} from 'lucide-react';
import { Input } from '@/modules/vitrine/components/common/Input';
import { Button } from '@/modules/vitrine/components/common/Button';
import { Card } from '@/modules/vitrine/components/common/Card';
import { signupFormSchema, SignupFormData } from '@/modules/vitrine/lib/validation';
import { submitSignupForm, submitVerifyForm, fetchTrialStatus, submitTrialPassword, createFormReducer, initialFormState, getLeadSource } from '@/modules/vitrine/lib/forms';
import { useAnalyticsForm } from '@/modules/vitrine/hooks/useAnalytics';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';
import type { AppLocale } from '@/lib/i18n';
import { fetchSupportedCountries, type SupportedCountryOption } from '@/modules/vitrine/data/supported-countries';
import { t } from '@/lib/i18n/locale-catalog';
import {
  applyDocumentLocale,
  normalizeLocale,
  storeAuthSession,
  type StoredAuthUser,
} from '@/lib/i18n';
import { apiFetch } from '@/lib/api-client';

interface SignupFormProps {
  page?: string;
  onSuccess?: (data: SignupFormData) => void;
  onError?: (error: string) => void;
  className?: string;
}

// #7249 — parcours minimal demandé par le propriétaire : le choix du PROFIL
// (entreprise ou indépendant), puis les coordonnées, puis le code reçu par
// e-mail. L'étape « outils + métier » a été retirée (trop longue) : les outils
// se choisissent après la création, dans « Modules & plan ».
type Step = 'profile' | 'form' | 'otp' | 'pending' | 'tracking' | 'success';
type SignupProfile = 'company' | 'solo';

// #2469 : clé sessionStorage du token de provisioning (jamais dans l'URL).
const TRIAL_TOKEN_STORAGE_KEY = 'lp_trial_provisioning_token';
// Repli après ~60 s de polling (12 × 5 s).
const TRIAL_POLL_INTERVAL_MS = 5000;
const TRIAL_POLL_MAX_ATTEMPTS = 12;

/**
 * #7235 — Outils HORIZONTAUX proposés à l'inscription. Les clés sont celles
 * du catalogue client (`@/lib/client-features`) et de l'allowlist serveur
 * (`Company::HORIZONTAL_TOOLS`) : elles sont revalidées côté API (fail-closed).
 * `TEAM_TOOL_KEYS` est masqué pour un profil Indépendant.
 */
/** Clés du catalogue i18n `signup.*` utilisées par les cartes du parcours. */
type SignupFormCopyKey = (typeof signupFormKeys)[number];

const selectClassName =
  'w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 dark:border-slate-700 dark:bg-slate-900 dark:text-white';

type SignupFormCopy = Record<(typeof signupFormKeys)[number], string>;

// Clés du catalogue i18n partagé (shared/i18n/locales/*.json — source de
// vérité). Le record est construit via t() (garde PA2-I18N-014 : aucun
// littéral utilisateur ajouté dans le composant).
const signupFormKeys = ['badge', 'title', 'subtitle', 'profileTitle', 'profileSubtitle', 'profileCompanyTitle', 'profileCompanyDesc', 'profileCompanyBullet1', 'profileCompanyBullet2', 'profileCompanyBullet3', 'profileSoloTitle', 'profileSoloDesc', 'profileSoloBullet1', 'profileSoloBullet2', 'profileSoloBullet3', 'profileCompanyBadge', 'profileSoloBadge', 'toolsTitle', 'toolsSubtitle', 'toolsTeamGroup', 'toolsManagementGroup', 'toolsEmployees', 'toolsEmployeesDesc', 'toolsAttendance', 'toolsAttendanceDesc', 'toolsAbsences', 'toolsAbsencesDesc', 'toolsPayroll', 'toolsPayrollDesc', 'toolsAccounting', 'toolsAccountingDesc', 'toolsCrm', 'toolsCrmDesc', 'toolsReports', 'toolsReportsDesc', 'toolsMarketing', 'toolsMarketingDesc', 'toolsHint', 'verticalTitle', 'verticalSubtitle', 'verticalRestaurant', 'verticalRestaurantDesc', 'verticalFuel', 'verticalFuelDesc', 'verticalEdu', 'verticalEduDesc', 'verticalNone', 'verticalNoneDesc', 'continueLabel', 'stepProfileLabel', 'stepToolsLabel', 'stepIdentityLabel', 'soloNote', 'labelEmail', 'placeholderEmail', 'labelCompany', 'placeholderCompany', 'labelRole', 'rolePlaceholder', 'roleFounder', 'roleManager', 'roleHr', 'roleOperations', 'roleOther', 'labelTeamSize', 'teamPlaceholder', 'labelCountry', 'countryPlaceholder', 'labelPhone', 'placeholderPhone', 'operationsNote', 'agreePrefix', 'termsLink', 'privacyLink', 'agreeSuffix', 'submitLabel', 'submittingLabel', 'codeHint', 'haveAccount', 'loginCta', 'back', 'otpTitle', 'otpSentTo', 'otpInvalidLength', 'otpInvalidCode', 'otpVerifyError', 'verifyLabel', 'verifyingLabel', 'codeValidity', 'trackStatus', 'pendingTitle', 'pendingFallback', 'pendingNote', 'readyTitle', 'readySubtitle', 'accessCta', 'copyLink', 'linkCopied', 'linkEmailed', 'failedTitle', 'failedBody', 'timeoutTitle', 'timeoutBody', 'refreshStatus', 'preparingTitle', 'preparingBody', 'statusFor', 'statusEvery5s', 'successTitle', 'emailVerified', 'credsLabel', 'fieldEmail', 'fieldPassword', 'copyPasswordTitle', 'copied', 'credsSentByEmail', 'credsEmailed', 'trialNote', 'trialDaysUnit', 'trialNoteSuffix', 'downloadApp', 'changePasswordNote', 'setPasswordTitle', 'setPasswordSubtitle', 'setPasswordLabel', 'setPasswordConfirmLabel', 'setPasswordSubmit', 'setPasswordSubmitting', 'setPasswordSuccess', 'setPasswordTooWeak', 'setPasswordMismatch', 'setPasswordUnavailable', 'goToLogin', 'planSelected', 'planChange', 'countryDetectionFailed', 'defaultError'] as const;

function buildSignupFormCopy(locale: AppLocale): SignupFormCopy {
  const copy = {} as SignupFormCopy;
  for (const key of signupFormKeys) {
    copy[key] = t(locale, `signup.${key}`);
  }
  return copy;
}

export function SignupForm({
  page = '/signup',
  onSuccess,
  onError,
  className = '',
}: SignupFormProps) {
  const { locale } = useVitrineLocale();
  const c = buildSignupFormCopy(locale);

  const {
    register,
    handleSubmit,
    formState: { errors },
    reset,
    watch,
  } = useForm<SignupFormData>({
    resolver: zodResolver(signupFormSchema(locale)),
    mode: 'onBlur',
  });

  const [formState, dispatch] = useReducer(createFormReducer(), initialFormState);
  const { trackSignup } = useAnalyticsForm();
  const role = watch('role');
  const router = useRouter();

  // Repli pays : le pays n'est plus demandé (il est résolu côté serveur par
  // géolocalisation). Si le serveur ne peut PAS le détecter, il répond
  // `COUNTRY_REQUIRED` et on n'affiche le sélecteur QUE dans ce cas — le
  // formulaire reste minimal dans tous les autres.
  const [showCountryFallback, setShowCountryFallback] = useState(false);

  // Offre choisie sur /pricing (`?plan=<code>`), rappelée à l'utilisateur : le
  // tunnel démarre par le choix d'une offre, il doit rester lisible jusqu'au
  // bout. `?plan=` est obligatoire — `/signup` nu redirige vers /pricing.
  const [selectedPlan, setSelectedPlan] = useState('');
  useEffect(() => {
    if (typeof window === 'undefined') return;
    setSelectedPlan(new URLSearchParams(window.location.search).get('plan') ?? '');
  }, []);

  // #4476 — pays supportés pour l'essai guidé (registre public #4217, fallback
  // statique si le backend est injoignable). Sans pays, l'API trial/signup
  // répond 422 et le tunnel se dégradait en capture de lead silencieuse.
  const [countries, setCountries] = useState<SupportedCountryOption[]>([]);
  useEffect(() => {
    let cancelled = false;
    fetchSupportedCountries().then((list) => {
      if (!cancelled) setCountries(list);
    });
    return () => {
      cancelled = true;
    };
  }, []);

  // Multi-step state
  const [currentStep, setCurrentStep] = useState<Step>('profile');
  // #7249 — seul le profil est demandé avant les coordonnées (2 choix).
  const [profile, setProfile] = useState<SignupProfile | null>(null);

  const chooseProfile = (next: SignupProfile) => {
    setProfile(next);
    setCurrentStep('form');
  };

  const [pendingEmail, setPendingEmail] = useState('');
  const [otpValues, setOtpValues] = useState<string[]>(['', '', '', '', '', '']);
  const [otpError, setOtpError] = useState('');
  const [isVerifying, setIsVerifying] = useState(false);
  const otpRefs = useRef<(HTMLInputElement | null)[]>([]);

  const [provisionedData, setProvisionedData] = useState<{
    manager?: { email: string };
    trial?: { days: number; ends_at: string };
    company?: { name: string };
  } | null>(null);
  const [pendingMessage, setPendingMessage] = useState('');

  // #2469 — suivi du provisioning du guided trial
  const [copied, setCopied] = useState(false);
  const [trialToken, setTrialToken] = useState<string | null>(() => {
    if (typeof window === 'undefined') return null;
    try {
      return sessionStorage.getItem(TRIAL_TOKEN_STORAGE_KEY);
    } catch {
      return null;
    }
  });
  const [trialStatus, setTrialStatus] = useState<'pending' | 'ready' | 'failed' | 'unknown'>('pending');
  const [trialLoginUrl, setTrialLoginUrl] = useState('');
  // Onboarding sans mailer : le prospect définit son mot de passe avec le
  // provisioning_token qu'il détient déjà (l'email d'accès est best-effort).
  const [passwordSet, setPasswordSet] = useState(false);
  // #7298 — vrai uniquement si le backend confirme l'envoi du lien d'accès par
  // e-mail (`access_sent`). Évite d'annoncer un e-mail jamais parti.
  const [accessSent, setAccessSent] = useState(false);
  const [newPassword, setNewPassword] = useState('');
  const [settingPassword, setSettingPassword] = useState(false);
  const [passwordError, setPasswordError] = useState('');
  const [trialTimedOut, setTrialTimedOut] = useState(false);
  // #7264 — nonce de relance du polling : l'écran de timeout proposait
  // « Actualiser le statut » mais le bouton ne faisait que remettre l'état à
  // zéro ; l'`useEffect` de polling ayant pour dépendances `[currentStep,
  // trialToken]`, il ne se relançait jamais et l'utilisateur restait bloqué.
  const [pollNonce, setPollNonce] = useState(0);
  const [isTracking, setIsTracking] = useState(false);

  const persistTrialToken = (token: string | null | undefined) => {
    if (!token) return;
    setTrialToken(token);
    try {
      sessionStorage.setItem(TRIAL_TOKEN_STORAGE_KEY, token);
    } catch {
      // sessionStorage indisponible (SSR/sandboxé) — le suivi restera en mémoire
    }
  };

  // #2469 — polling du statut (pending → ready/failed) tant que l'écran de
  // suivi est affiché ; repli honnête après ~60 s.
  useEffect(() => {
    if (currentStep !== 'tracking' || !trialToken) return;

    let cancelled = false;
    let attempts = 0;
    let intervalId: ReturnType<typeof setInterval> | null = null;

    const poll = async () => {
      if (cancelled) return;
      const res = await fetchTrialStatus(trialToken);
      if (cancelled) return;
      if (res.success && res.data?.status) {
        const status = res.data.status;
        if (status === 'ready') {
          setTrialStatus('ready');
          setTrialLoginUrl(res.data.login_url || '');
          setPasswordSet(res.data.password_set === true);
          setAccessSent(res.data.access_sent === true);
          if (intervalId) clearInterval(intervalId);
          return;
        }
        if (status === 'failed') {
          setTrialStatus('failed');
          if (intervalId) clearInterval(intervalId);
          return;
        }
      }
      attempts += 1;
      if (attempts >= TRIAL_POLL_MAX_ATTEMPTS) {
        setTrialTimedOut(true);
        if (intervalId) clearInterval(intervalId);
      }
    };

    void poll();
    intervalId = setInterval(() => void poll(), TRIAL_POLL_INTERVAL_MS);

    return () => {
      cancelled = true;
      if (intervalId) clearInterval(intervalId);
    };
  }, [currentStep, trialToken, pollNonce]);

  const handleSetPassword = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!trialToken || settingPassword) return;

    setPasswordError('');

    if (newPassword.length < 8 || !/[0-9]/.test(newPassword)) {
      setPasswordError(c.setPasswordTooWeak);
      return;
    }

    setSettingPassword(true);
    const res = await submitTrialPassword(trialToken, newPassword);
    setSettingPassword(false);

    if (res.success) {
      setPasswordSet(true);
      const loginUrl = res.data?.login_url;
      if (typeof loginUrl === 'string' && loginUrl !== '') {
        setTrialLoginUrl(loginUrl);
      }
      return;
    }

    // Le mot de passe est déjà défini : ce n'est pas une erreur pour
    // l'utilisateur, on bascule simplement sur la connexion.
    if (res.error === 'TRIAL_PASSWORD_ALREADY_SET') {
      setPasswordSet(true);
      return;
    }

    setPasswordError(c.setPasswordUnavailable);
  };

  const startTracking = () => {
    if (!trialToken) return;
    setTrialStatus('pending');
    setTrialTimedOut(false);
    setIsTracking(true);
    setCurrentStep('tracking');
  };

  // ── Step 1: Submit signup form ──
  const onSubmit = async (data: SignupFormData) => {
    dispatch({ type: 'SUBMIT_START' });

    try {
      // #7249 — le profil (entreprise/indépendant) reste déclaré à
      // l'inscription ; les outils et le métier ne sont plus demandés dans le
      // tunnel (choisis ensuite depuis « Modules & plan »).
      // Le créateur du compte EST le fondateur : le rôle n'est plus demandé
      // dans le tunnel (il reste éditable ensuite depuis l'équipe).
      const payload: SignupFormData = {
        ...data,
        role: data.role ?? 'founder',
        company_type: profile ?? 'company',
      };
      const response = await submitSignupForm(payload, page);

      if (response.success) {
        trackSignup(data.email, {
          source: getLeadSource(),
          page,
          company: data.company,
          role: data.role,
          employees: data.employees,
        });

        setPendingEmail(data.email);
        dispatch({ type: 'RESET' });

        // #2469 : on conserve le token de provisioning (quand le backend en
        // renvoie un) pour permettre le suivi du statut sans email.
        const provisioningToken = response.data?.provisioning_token;
        persistTrialToken(provisioningToken);

        // #6959 : le flux « guided trial » (requestedWorkflow=guided_trial)
        // est SANS OTP — le backend provisionne le sandbox en asynchrone et
        // renvoie `status=provisioning_sandbox` + un provisioning_token. On ne
        // doit donc jamais afficher l'écran de vérification « Verify your
        // email » pour ce flux : on bascule directement sur le suivi du
        // provisioning (poll /trial/status). L'écran OTP reste réservé au flux
        // legacy self-service (`pending_verification`, OTP réellement envoyé).
        const guidedTrial = response.data?.status === 'provisioning_sandbox';

        if (guidedTrial) {
          if (provisioningToken) {
            // Démarre immédiatement le suivi (mêmes transitions que
            // startTracking, sans dépendre du re-render pour le token).
            setTrialStatus('pending');
            setTrialTimedOut(false);
            setIsTracking(true);
            setCurrentStep('tracking');
          } else {
            // Sans token (rare), on reste honnête : pas d'écran OTP.
            setPendingMessage(response.message || c.pendingFallback);
            setCurrentStep('pending');
          }
        } else if (response.provisioned === false) {
          // Backend could not send an OTP right now (e.g. cold-start timeout).
          // The lead was still captured, so tell the user honestly instead of
          // showing a vérification screen for a code that was never sent.
          setPendingMessage(
            response.message || c.pendingFallback
          );
          setCurrentStep('pending');
        } else {
          setCurrentStep('otp');
        }
      } else if (response.error === 'COUNTRY_REQUIRED') {
        // La géolocalisation n'a pas permis de déterminer le pays (dev local,
        // proxy, IP inconnue) : on ne demande le pays QUE dans ce cas précis,
        // au lieu de laisser l'utilisateur dans un cul-de-sac 422.
        setShowCountryFallback(true);
        dispatch({
          type: 'SUBMIT_ERROR',
          payload: { message: c.countryDetectionFailed },
        });
      } else {
        dispatch({
          type: 'SUBMIT_ERROR',
          payload: {
            message: response.error || response.message,
          },
        });
        onError?.(response.error || response.message);
      }
    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : c.defaultError;
      dispatch({
        type: 'SUBMIT_ERROR',
        payload: { message: errorMessage },
      });
      onError?.(errorMessage);
    }
  };

  // ── OTP input handlers ──
  const handleOtpChange = useCallback((index: number, value: string) => {
    if (!/^\d*$/.test(value)) return;

    const newValues = [...otpValues];
    newValues[index] = value.slice(-1);
    setOtpValues(newValues);
    setOtpError('');

    // Auto-focus next input
    if (value && index < 5) {
      otpRefs.current[index + 1]?.focus();
    }
  }, [otpValues]);

  const handleOtpKeyDown = useCallback((index: number, e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Backspace' && !otpValues[index] && index > 0) {
      otpRefs.current[index - 1]?.focus();
    }
  }, [otpValues]);

  const handleOtpPaste = useCallback((e: React.ClipboardEvent) => {
    e.preventDefault();
    const pasted = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6);
    if (pasted.length === 0) return;
    const newValues = [...otpValues];
    for (let i = 0; i < 6; i++) {
      newValues[i] = pasted[i] || '';
    }
    setOtpValues(newValues);
    const focusIdx = Math.min(pasted.length, 5);
    otpRefs.current[focusIdx]?.focus();
  }, [otpValues]);

  // ── Step 2: Verify OTP ──
  const handleVerify = async () => {
    const code = otpValues.join('');
    if (code.length !== 6) {
      setOtpError(c.otpInvalidLength);
      return;
    }

    setIsVerifying(true);
    setOtpError('');

    try {
      const response = await submitVerifyForm(pendingEmail, code);

      if (response.success) {
        setProvisionedData(response.data);
        reset();
        onSuccess?.({} as SignupFormData);

        // Auto-connexion : /api/forms/verify a posé le cookie de session
        // (l'utilisateur n'a jamais choisi de mot de passe). On entre
        // directement dans l'espace au lieu d'afficher un écran « e-mail
        // vérifié » suivi d'un bouton de connexion sans identifiants.
        //
        // ⚠️ Le cookie httpOnly n'est PAS lu par le client : il faut hydrater
        // la session locale (`auth_user`) avant de naviguer, sinon la garde du
        // layout dashboard (`getStoredUser()` → null) renvoie aussitôt vers
        // /auth/login — le prospect perdait l'accès qu'on venait de lui
        // provisionner. On lit donc le profil via /auth/me (le cookie est
        // désormais posé) puis on persiste la session.
        if (response.data?.sessionEstablished === true) {
          try {
            const meResponse = await apiFetch('/auth/me');
            if (meResponse.ok) {
              const mePayload = (await meResponse.json()) as { data?: StoredAuthUser };
              if (mePayload.data) {
                storeAuthSession(null, mePayload.data);
                applyDocumentLocale(
                  normalizeLocale(mePayload.data.language),
                  mePayload.data.is_rtl,
                );
              }
            }
          } catch {
            // Silencieux : si /auth/me échoue, l'utilisateur retombe sur
            // l'écran de connexion (avec son mot de passe temporaire reçu par
            // e-mail) plutôt que sur une page blanche.
          }

          router.replace('/dashboard');
          return;
        }

        setCurrentStep('success');
      } else {
        setOtpError(response.message || c.otpInvalidCode);
      }
    } catch (error) {
      setOtpError(c.otpVerifyError);
    } finally {
      setIsVerifying(false);
    }
  };

  // ── Render ──
  return (
    <Card className={`p-6 md:p-8 ${className}`}>
      {/* #7235 — parcours en 3 temps : profil → outils & métier → coordonnées. */}
      {(currentStep === 'profile' || currentStep === 'form') && (
        <ol className="mb-6 flex items-center gap-2 text-[11px] font-bold uppercase tracking-wide">
          {(
            [
              ['profile', c.stepProfileLabel],
              ['form', c.stepIdentityLabel],
            ] as const
          ).map(([key, label], index) => {
            const order = { profile: 0, form: 1 } as const;
            const current = order[currentStep as 'profile' | 'form'];
            const done = index < current;
            const active = index === current;
            return (
              <li key={key} className="flex flex-1 items-center gap-2">
                <span
                  className={`flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[11px] ${
                    done
                      ? 'bg-emerald-700 text-white'
                      : active
                        ? 'bg-emerald-100 text-emerald-700 ring-2 ring-emerald-500/30 dark:bg-emerald-950/60 dark:text-emerald-300'
                        : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'
                  }`}
                >
                  {done ? <Check className="h-3.5 w-3.5" /> : index + 1}
                </span>
                <span className={active ? 'text-slate-900 dark:text-white' : 'text-slate-500 dark:text-slate-400'}>
                  {label}
                </span>
                {index < 2 && (
                  <span className={`h-0.5 flex-1 rounded ${done ? 'bg-emerald-400' : 'bg-slate-200 dark:bg-slate-800'}`} />
                )}
              </li>
            );
          })}
        </ol>
      )}

      <AnimatePresence mode="wait">
        {/* ═══════════════════════════════════════ */}
        {/* STEP 0: Profil (entreprise / indép.)    */}
        {/* ═══════════════════════════════════════ */}
        {currentStep === 'profile' && (
          <motion.div
            key="step-profile"
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -20 }}
            transition={{ duration: 0.3 }}
          >
            <div className="mb-5 inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-bold uppercase tracking-wide text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">
              <Sparkles className="h-3.5 w-3.5" />
              {c.badge}
            </div>

            <h2 className="mb-2 text-2xl font-black tracking-tight text-slate-950 dark:text-white md:text-3xl">
              {c.profileTitle}
            </h2>
            <p className="mb-6 text-sm leading-6 text-slate-600 dark:text-slate-400">
              {c.profileSubtitle}
            </p>

            <div className="grid gap-4 sm:grid-cols-2">
              {(
                [
                  {
                    key: 'company' as const,
                    icon: Building2,
                    title: c.profileCompanyTitle,
                    desc: c.profileCompanyDesc,
                    bullets: [c.profileCompanyBullet1, c.profileCompanyBullet2, c.profileCompanyBullet3],
                    badge: c.profileCompanyBadge,
                  },
                  {
                    key: 'solo' as const,
                    icon: Briefcase,
                    title: c.profileSoloTitle,
                    desc: c.profileSoloDesc,
                    bullets: [c.profileSoloBullet1, c.profileSoloBullet2, c.profileSoloBullet3],
                    badge: c.profileSoloBadge,
                  },
                ]
              ).map((option) => {
                const Icon = option.icon;
                const active = profile === option.key;
                return (
                  <button
                    key={option.key}
                    type="button"
                    onClick={() => chooseProfile(option.key)}
                    aria-pressed={active}
                    data-testid={`signup-profile-${option.key}`}
                    className={`group relative flex h-full flex-col items-start rounded-2xl border-2 bg-white p-5 text-left transition hover:-translate-y-0.5 hover:shadow-xl hover:shadow-emerald-500/10 dark:bg-slate-900 ${
                      active
                        ? 'border-emerald-500 ring-2 ring-emerald-500/20'
                        : 'border-slate-200 hover:border-emerald-400 dark:border-slate-700'
                    }`}
                  >
                    <span className="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-700 to-cyan-700 text-white shadow-lg shadow-emerald-500/25">
                      <Icon className="h-6 w-6" aria-hidden="true" />
                    </span>
                    <span className="text-lg font-black tracking-tight text-slate-950 dark:text-white">
                      {option.title}
                    </span>
                    <span className="mt-1 text-sm leading-6 text-slate-600 dark:text-slate-400">
                      {option.desc}
                    </span>
                    <ul className="mt-4 space-y-1.5">
                      {option.bullets.map((bullet) => (
                        <li key={bullet} className="flex items-start gap-2 text-xs leading-5 text-slate-600 dark:text-slate-300">
                          <Check className="mt-0.5 h-3.5 w-3.5 shrink-0 text-emerald-500" aria-hidden="true" />
                          <span>{bullet}</span>
                        </li>
                      ))}
                    </ul>
                    <span className="mt-4 inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                      {option.badge}
                    </span>
                  </button>
                );
              })}
            </div>
          </motion.div>
        )}

        {/* ═══════════════════════════════════════ */}
        {/* STEP 1: Signup Form                     */}
        {/* ═══════════════════════════════════════ */}
        {currentStep === 'form' && (
          <motion.div
            key="step-form"
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -20 }}
            transition={{ duration: 0.3 }}
          >
            <button
              type="button"
              onClick={() => setCurrentStep('profile')}
              className="mb-4 inline-flex items-center gap-1 text-sm font-medium text-slate-500 transition hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200"
            >
              <ArrowLeft className="h-4 w-4" aria-hidden="true" />
              {c.back}
            </button>

            {profile === 'solo' && (
              <p className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs leading-5 text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-200">
                {c.soloNote}
              </p>
            )}

            <h2 className="mb-2 text-2xl font-black tracking-tight text-slate-950 dark:text-white md:text-3xl">
              {c.title}
            </h2>
            <p className="mb-6 text-sm leading-6 text-slate-600 dark:text-slate-400">
              {c.subtitle}
            </p>

            {formState.isError && (
              <motion.div
                initial={{ opacity: 0, y: -10 }}
                animate={{ opacity: 1, y: 0 }}
                className="mb-6 flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20"
              >
                <AlertCircle className="mt-0.5 h-5 w-5 flex-shrink-0 text-red-600 dark:text-red-400" />
                <div>
                  <p className="font-semibold text-red-900 dark:text-red-100">{formState.message}</p>
                </div>
              </motion.div>
            )}

            <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
              <Input
                label={c.labelEmail}
                type="email"
                placeholder={c.placeholderEmail}
                icon={<Mail className="h-4 w-4" />}
                error={errors.email?.message}
                required
                {...register('email')}
              />

              <Input
                label={c.labelCompany}
                type="text"
                placeholder={c.placeholderCompany}
                icon={<Building2 className="h-4 w-4" />}
                error={errors.company?.message}
                required
                {...register('company')}
              />

              {/* Le créateur du compte EST le fondateur — on ne lui demande
                  plus son rôle. La taille d'équipe, le pays (détecté) et le
                  téléphone (l'e-mail est vérifié) ne sont plus demandés non
                  plus : tout est éditable plus tard depuis les paramètres.
                  Objectif : réduire le tunnel au strict nécessaire. */}

              {selectedPlan !== '' && (
                <div className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm dark:border-emerald-900 dark:bg-emerald-950/30">
                  <span className="font-semibold text-emerald-800 dark:text-emerald-200">
                    {c.planSelected} : <span className="uppercase">{selectedPlan}</span>
                  </span>
                  <Link
                    href="/pricing"
                    className="font-semibold text-emerald-700 underline hover:text-emerald-800 dark:text-emerald-300"
                  >
                    {c.planChange}
                  </Link>
                </div>
              )}

              {/* Repli pays : rendu UNIQUEMENT si la géolocalisation serveur
                  n'a pas permis de déterminer le pays (sinon le champ reste
                  absent du formulaire minimal). */}
              {showCountryFallback && (
                <label className="block">
                  <span className="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-300">
                    <Globe className="h-4 w-4" />
                    {c.labelCountry}
                  </span>
                  <select
                    className={selectClassName}
                    aria-invalid={errors.country ? true : undefined}
                    {...register('country')}
                  >
                    <option value="">{c.countryPlaceholder}</option>
                    {countries.map((country) => (
                      <option key={country.code} value={country.code}>
                        {country.label}
                      </option>
                    ))}
                  </select>
                  {errors.country && (
                    <p role="alert" className="mt-1 text-sm text-red-600 dark:text-red-400">
                      {errors.country.message}
                    </p>
                  )}
                </label>
              )}

              <div className="flex items-start gap-3">
                <input
                  type="checkbox"
                  id="agreeToTerms"
                  className="mt-1 h-4 w-4 cursor-pointer rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                  aria-invalid={errors.agreeToTerms ? true : undefined}
                  aria-describedby={errors.agreeToTerms ? 'signup-agree-terms-error' : undefined}
                  {...register('agreeToTerms')}
                />
                <label htmlFor="agreeToTerms" className="text-sm text-slate-600 dark:text-slate-400">
                  {c.agreePrefix}{' '}
                  <Link href="/terms" className="font-semibold text-emerald-600 hover:text-emerald-700">
                    {c.termsLink}
                  </Link>{' '}
                  {c.agreeSuffix}{' '}
                  <Link href="/privacy" className="font-semibold text-emerald-600 hover:text-emerald-700">
                    {c.privacyLink}
                  </Link>
                </label>
              </div>
              {errors.agreeToTerms && (
                <p id="signup-agree-terms-error" role="alert" className="text-sm text-red-600 dark:text-red-400">
                  {errors.agreeToTerms.message}
                </p>
              )}

              <Button
                type="submit"
                variant="primary"
                size="lg"
                fullWidth
                loading={formState.isSubmitting}
                disabled={formState.isSubmitting}
              >
                {formState.isSubmitting ? c.submittingLabel : c.submitLabel}
              </Button>

              <p className="rounded-xl bg-transparent px-4 py-3 text-center text-xs leading-5 text-slate-500 dark:bg-slate-900/60 dark:text-slate-400">
                {c.codeHint}
              </p>

              <p className="text-center text-sm text-slate-600 dark:text-slate-400">
                {c.haveAccount}{' '}
                <Link href="/auth/login" className="font-semibold text-emerald-600 hover:text-emerald-700">
                  {c.loginCta}
                </Link>
              </p>
            </form>
          </motion.div>
        )}

        {/* ═══════════════════════════════════════ */}
        {/* STEP 2: OTP Vérification                */}
        {/* ═══════════════════════════════════════ */}
        {currentStep === 'otp' && (
          <motion.div
            key="step-otp"
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -20 }}
            transition={{ duration: 0.3 }}
            className="text-center"
          >
            <button
              type="button"
              onClick={() => {
                setCurrentStep('form');
                setOtpValues(['', '', '', '', '', '']);
                setOtpError('');
              }}
              className="mb-4 inline-flex items-center gap-1 text-sm font-medium text-slate-500 transition hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200"
            >
              <ArrowLeft className="h-4 w-4" />
              {c.back}
            </button>

            <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-100 dark:bg-emerald-900/40">
              <ShieldCheck className="h-8 w-8 text-emerald-700 dark:text-emerald-400" />
            </div>

            <h2 className="mb-2 text-2xl font-black tracking-tight text-slate-950 dark:text-white">
              {c.otpTitle}
            </h2>
            <p className="mb-1 text-sm leading-6 text-slate-600 dark:text-slate-400">
              {c.otpSentTo}
            </p>
            <p className="mb-6 text-sm font-bold text-emerald-700 dark:text-emerald-400">
              {pendingEmail}
            </p>

            {/* OTP Inputs */}
            <div className="mb-4 flex justify-center gap-2">
              {otpValues.map((val, i) => (
                <input
                  key={i}
                  ref={(el) => { otpRefs.current[i] = el; }}
                  type="text"
                  inputMode="numeric"
                  maxLength={1}
                  value={val}
                  onChange={(e) => handleOtpChange(i, e.target.value)}
                  onKeyDown={(e) => handleOtpKeyDown(i, e)}
                  onPaste={i === 0 ? handleOtpPaste : undefined}
                  aria-label={`${c.otpTitle} ${i + 1}`}
                  className={`h-14 w-12 rounded-xl border-2 text-center text-2xl font-bold outline-none transition-all
                    ${otpError
                      ? 'border-red-300 bg-red-50 text-red-900 dark:border-red-700 dark:bg-red-950/30 dark:text-red-200'
                      : 'border-slate-200 bg-white text-slate-900 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 dark:border-slate-700 dark:bg-slate-900 dark:text-white'
                    }`}
                  autoFocus={i === 0}
                />
              ))}
            </div>

            {otpError && (
              <motion.p
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                className="mb-4 text-sm font-medium text-red-600 dark:text-red-400"
              >
                {otpError}
              </motion.p>
            )}

            <Button
              type="button"
              variant="primary"
              size="lg"
              fullWidth
              loading={isVerifying}
              disabled={isVerifying || otpValues.join('').length !== 6}
              onClick={handleVerify}
            >
              {isVerifying ? c.verifyingLabel : c.verifyLabel}
            </Button>

            <p className="mt-4 text-xs text-slate-400">
              {c.codeValidity}
            </p>

            {trialToken && (
              <button
                type="button"
                onClick={startTracking}
                className="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-600 transition hover:text-emerald-700 dark:text-emerald-400"
              >
                <Rocket className="h-4 w-4" />
                {c.trackStatus}
              </button>
            )}
          </motion.div>
        )}

        {/* ═══════════════════════════════════════ */}
        {/* STEP 2b: Pending (cold-start fallback)   */}
        {/* ═══════════════════════════════════════ */}
        {currentStep === 'pending' && (
          <motion.div
            key="step-pending"
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -20 }}
            transition={{ duration: 0.3 }}
            className="text-center"
          >
            <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-amber-100 dark:bg-amber-900/40">
              <Clock3 className="h-8 w-8 text-amber-600 dark:text-amber-400" />
            </div>

            <h2 className="mb-2 text-2xl font-black tracking-tight text-slate-950 dark:text-white">
              {c.pendingTitle}
            </h2>
            <p className="mb-1 text-sm leading-6 text-slate-600 dark:text-slate-400">
              {pendingMessage}
            </p>
            <p className="mb-6 text-sm font-bold text-emerald-700 dark:text-emerald-400">
              {pendingEmail}
            </p>

            <div className="rounded-xl bg-transparent px-4 py-3 text-left text-xs leading-5 text-slate-500 dark:bg-slate-900/60 dark:text-slate-400">
              {c.pendingNote}
            </div>

            <p className="mt-4 text-center text-sm text-slate-600 dark:text-slate-400">
              {c.haveAccount}{' '}
              <Link href="/auth/login" className="font-semibold text-emerald-600 hover:text-emerald-700">
                {c.loginCta}
              </Link>
            </p>

            {trialToken && (
              <button
                type="button"
                onClick={startTracking}
                className="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-600 transition hover:text-emerald-700 dark:text-emerald-400"
              >
                <Rocket className="h-4 w-4" />
                {c.trackStatus}
              </button>
            )}
          </motion.div>
        )}

        {/* ═══════════════════════════════════════ */}
        {/* STEP 2c: Tracking (guided trial status) */}
        {/* ═══════════════════════════════════════ */}
        {currentStep === 'tracking' && (
          <motion.div
            key="step-tracking"
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -20 }}
            transition={{ duration: 0.3 }}
            className="text-center"
          >
            {trialStatus === 'ready' ? (
              <>
                <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-100 dark:bg-emerald-900/40">
                  <CheckCircle className="h-8 w-8 text-emerald-700 dark:text-emerald-400" />
                </div>
                <h2 className="mb-2 text-2xl font-black tracking-tight text-slate-950 dark:text-white">
                  {c.readyTitle}
                </h2>
                <p className="mb-6 text-sm leading-6 text-slate-600 dark:text-slate-400">
                  {c.readySubtitle}
                </p>
                {!passwordSet ? (
                  <form onSubmit={handleSetPassword} className="space-y-3 text-left">
                    <p className="text-sm leading-6 text-slate-600 dark:text-slate-400">
                      {c.setPasswordSubtitle}
                    </p>
                    <div>
                      <label
                        htmlFor="trial-new-password"
                        className="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500"
                      >
                        {c.setPasswordLabel}
                      </label>
                      <input
                        id="trial-new-password"
                        type="password"
                        autoComplete="new-password"
                        required
                        minLength={8}
                        value={newPassword}
                        onChange={(e) => setNewPassword(e.target.value)}
                        className="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                      />
                    </div>
                    {passwordError ? (
                      <p role="alert" className="text-xs font-semibold text-red-600 dark:text-red-400">
                        {passwordError}
                      </p>
                    ) : null}
                    <button
                      type="submit"
                      disabled={settingPassword}
                      className="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-700 px-4 py-3 text-sm font-bold text-white shadow-lg shadow-emerald-600/20 transition hover:bg-emerald-800 disabled:opacity-50"
                    >
                      <KeyRound className="h-4 w-4" />
                      {settingPassword ? c.setPasswordSubmitting : c.setPasswordSubmit}
                    </button>
                  </form>
                ) : null}
                {passwordSet && trialLoginUrl ? (
                  <div className="space-y-3">
                    <a
                      href={trialLoginUrl}
                      className="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-700 px-4 py-3 text-sm font-bold text-white shadow-lg shadow-emerald-600/20 transition hover:bg-emerald-800"
                    >
                      <LogIn className="h-4 w-4" />
                      {c.accessCta}
                    </a>
                    <button
                      type="button"
                      onClick={() => {
                        void navigator.clipboard
                          ?.writeText(trialLoginUrl)
                          .then(() => setCopied(true))
                          .catch(() => undefined);
                        setTimeout(() => setCopied(false), 2000);
                      }}
                      className="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 transition hover:text-slate-700 dark:text-slate-400"
                    >
                      <ClipboardCopy className="h-3.5 w-3.5" />
                      {copied ? c.linkCopied : c.copyLink}
                    </button>
                  </div>
                ) : null}
                {/* #7298 — n'annoncer un envoi par e-mail que s'il a RÉELLEMENT
                    eu lieu (`access_sent`, relayé par /api/forms/trial-status).
                    Dans le parcours guidé (repli sans mailer), le message
                    s'affichait à tort pendant la saisie du mot de passe. */}
                {accessSent ? (
                  <p className="mt-3 text-sm text-slate-500 dark:text-slate-400">
                    {c.linkEmailed}
                  </p>
                ) : null}
              </>
            ) : trialStatus === 'failed' ? (
              <>
                <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-red-100 dark:bg-red-900/40">
                  <AlertCircle className="h-8 w-8 text-red-600 dark:text-red-400" />
                </div>
                <h2 className="mb-2 text-2xl font-black tracking-tight text-slate-950 dark:text-white">
                  {c.failedTitle}
                </h2>
                <p className="mb-6 text-sm leading-6 text-slate-600 dark:text-slate-400">
                  {c.failedBody}
                   
                </p>
              </>
            ) : trialTimedOut ? (
              <>
                <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-amber-100 dark:bg-amber-900/40">
                  <Clock3 className="h-8 w-8 text-amber-600 dark:text-amber-400" />
                </div>
                <h2 className="mb-2 text-2xl font-black tracking-tight text-slate-950 dark:text-white">
                  {c.timeoutTitle}
                </h2>
                <p className="mb-6 text-sm leading-6 text-slate-600 dark:text-slate-400">
                  {c.timeoutBody}
                   
                </p>
                <Button
                  type="button"
                  variant="secondary"
                  size="lg"
                  fullWidth
                  onClick={() => {
                    setTrialTimedOut(false);
                    setTrialStatus('pending');
                    // #7264 — relance réellement un cycle de polling.
                    setPollNonce((value) => value + 1);
                  }}
                >
                  {c.refreshStatus}
                </Button>
              </>
            ) : (
              <>
                <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-100 dark:bg-emerald-900/40">
                  <div className="h-8 w-8 animate-spin rounded-full border-2 border-emerald-600 border-t-transparent" />
                </div>
                <h2 className="mb-2 text-2xl font-black tracking-tight text-slate-950 dark:text-white">
                  {c.preparingTitle}
                </h2>
                <p className="mb-6 text-sm leading-6 text-slate-600 dark:text-slate-400">
                  {c.preparingBody}
                   
                </p>
                <p className="text-xs text-slate-400">
                  {pendingEmail ? `${c.statusFor} ${pendingEmail}` : c.statusEvery5s}
                </p>
              </>
            )}
          </motion.div>
        )}

        {/* ═══════════════════════════════════════ */}
        {/* STEP 3: Success                         */}
        {/* ═══════════════════════════════════════ */}
        {currentStep === 'success' && (
          <motion.div
            key="step-success"
            initial={{ opacity: 0, scale: 0.95 }}
            animate={{ opacity: 1, scale: 1 }}
            transition={{ duration: 0.4, ease: 'easeOut' }}
          >
            <div className="mb-6 overflow-hidden rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50 via-white to-emerald-50/60 dark:border-emerald-800 dark:from-emerald-950/40 dark:via-slate-900 dark:to-emerald-950/20">
              <div className="flex items-center gap-3 bg-emerald-500/10 px-5 py-3 dark:bg-emerald-500/5">
                <Rocket className="h-5 w-5 text-emerald-700 dark:text-emerald-400" />
                <h3 className="text-lg font-black text-emerald-900 dark:text-emerald-100">
                  {c.successTitle}
                </h3>
              </div>

              <div className="space-y-4 p-5">
                <div className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-100 dark:bg-slate-800/60 dark:ring-slate-700">
                  <div className="flex items-center gap-2 text-center justify-center mb-3">
                    <CheckCircle className="h-4 w-4 text-emerald-700 dark:text-emerald-400" />
                    <p className="text-sm font-medium text-slate-700 dark:text-slate-300">
                      {c.emailVerified}
                    </p>
                  </div>
                  {provisionedData?.manager ? (
                    <>
                      <p className="mb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">
                        {c.credsLabel}
                      </p>
                      <div className="space-y-2">
                        <div className="flex items-center justify-between">
                          <span className="text-sm text-slate-600 dark:text-slate-300">{c.fieldEmail}</span>
                          <span className="font-mono text-sm font-bold text-slate-900 dark:text-white">
                            {provisionedData.manager.email}
                          </span>
                        </div>
                      </div>
                      {/* #2680 : le mot de passe temporaire ne transite plus
                          dans la réponse API — il est envoyé par email. */}
                      <p className="mt-3 text-xs text-slate-500 dark:text-slate-400">
                        {c.credsEmailed}
                      </p>
                    </>
                  ) : (
                    <p className="text-sm text-slate-600 dark:text-slate-400">
                      {c.credsEmailed}
                    </p>
                  )}
                </div>

                {provisionedData?.trial && (
                  <p className="text-center text-sm text-slate-500 dark:text-slate-400">
                    {c.trialNote}{' '}
                    <span className="font-bold text-emerald-600">
                      {provisionedData.trial.days} {c.trialDaysUnit}
                    </span>{' '}
                    — {c.trialNoteSuffix}
                  </p>
                )}

                <div className="flex flex-col gap-2 sm:flex-row">
                  <Link
                    href="/auth/login"
                    className="flex flex-1 items-center justify-center gap-2 rounded-xl bg-emerald-700 px-4 py-3 text-sm font-bold text-white shadow-lg shadow-emerald-500/25 transition hover:bg-emerald-800"
                  >
                    <LogIn className="h-4 w-4" />
                    {c.loginCta}
                  </Link>
                  <Link
                    href="/download"
                    className="flex flex-1 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-700 transition hover:bg-transparent dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                  >
                    <Download className="h-4 w-4" />
                    {c.downloadApp}
                  </Link>
                </div>

                <p className="text-center text-xs text-slate-400">
                  {c.changePasswordNote}
                </p>
              </div>
            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </Card>
  );
}

