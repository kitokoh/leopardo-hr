'use client';

import React, { useReducer, useState, useRef, useCallback } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { motion, AnimatePresence } from 'framer-motion';
import {
  AlertCircle,
  ArrowLeft,
  Building2,
  CheckCircle,
  Clock3,
  ClipboardCopy,
  Download,
  LogIn,
  Mail,
  Phone,
  Rocket,
  ShieldCheck,
  Sparkles,
  Users,
} from 'lucide-react';
import { Input } from '@/modules/vitrine/components/common/Input';
import { Button } from '@/modules/vitrine/components/common/Button';
import { Card } from '@/modules/vitrine/components/common/Card';
import { signupFormSchema, SignupFormData } from '@/modules/vitrine/lib/validation';
import { submitSignupForm, submitVerifyForm, createFormReducer, initialFormState } from '@/modules/vitrine/lib/forms';
import { useAnalyticsForm } from '@/modules/vitrine/hooks/useAnalytics';

interface SignupFormProps {
  page?: string;
  onSuccess?: (data: SignupFormData) => void;
  onError?: (error: string) => void;
  className?: string;
}

type Step = 'form' | 'otp' | 'pending' | 'success';

const selectClassName =
  'w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 dark:border-slate-700 dark:bg-slate-900 dark:text-white';

export function SignupForm({
  page = '/signup',
  onSuccess,
  onError,
  className = '',
}: SignupFormProps) {
  const {
    register,
    handleSubmit,
    formState: { errors },
    reset,
    watch,
  } = useForm<SignupFormData>({
    resolver: zodResolver(signupFormSchema),
    mode: 'onBlur',
  });

  const [formState, dispatch] = useReducer(createFormReducer(), initialFormState);
  const { trackSignup } = useAnalyticsForm();
  const role = watch('role');

  // Multi-step state
  const [currentStep, setCurrentStep] = useState<Step>('form');
  const [pendingEmail, setPendingEmail] = useState('');
  const [otpValues, setOtpValues] = useState<string[]>(['', '', '', '', '', '']);
  const [otpError, setOtpError] = useState('');
  const [isVerifying, setIsVerifying] = useState(false);
  const otpRefs = useRef<(HTMLInputElement | null)[]>([]);

  const [provisionedData, setProvisionedData] = useState<{
    manager?: { email: string; temp_password: string };
    trial?: { days: number; ends_at: string };
    company?: { name: string };
  } | null>(null);
  const [pendingMessage, setPendingMessage] = useState('');
  const [copied, setCopied] = useState(false);

  const copyPassword = async (password: string) => {
    try {
      await navigator.clipboard.writeText(password);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch {
      const textarea = document.createElement('textarea');
      textarea.value = password;
      document.body.appendChild(textarea);
      textarea.select();
      document.execCommand('copy');
      document.body.removeChild(textarea);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    }
  };

  // ── Step 1: Submit signup form ──
  const onSubmit = async (data: SignupFormData) => {
    dispatch({ type: 'SUBMIT_START' });

    try {
      const response = await submitSignupForm(data, page);

      if (response.success) {
        trackSignup(data.email, {
          source: 'signup_form',
          page,
          company: data.company,
          role: data.role,
          employees: data.employees,
        });

        setPendingEmail(data.email);
        dispatch({ type: 'RESET' });

        if (response.provisioned === false) {
          // Backend could not send an OTP right now (e.g. cold-start timeout).
          // The lead was still captured, so tell the user honestly instead of
          // showing a verification screen for a code that was never sent.
          setPendingMessage(
            response.message ||
              "Demande d'essai recue. Notre equipe vous contacte sous 24h ouvrables."
          );
          setCurrentStep('pending');
        } else {
          setCurrentStep('otp');
        }
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
      const errorMessage = error instanceof Error ? error.message : 'Une erreur est survenue';
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
      setOtpError('Veuillez entrer les 6 chiffres du code.');
      return;
    }

    setIsVerifying(true);
    setOtpError('');

    try {
      const response = await submitVerifyForm(pendingEmail, code);

      if (response.success) {
        setProvisionedData(response.data);
        setCurrentStep('success');
        reset();
        onSuccess?.({} as SignupFormData);
      } else {
        setOtpError(response.message || 'Code invalide ou expire.');
      }
    } catch (error) {
      setOtpError('Erreur lors de la verification. Veuillez reessayer.');
    } finally {
      setIsVerifying(false);
    }
  };

  // ── Render ──
  return (
    <Card className={`p-6 md:p-8 ${className}`}>
      <AnimatePresence mode="wait">
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
            <div className="mb-5 inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-bold uppercase tracking-wide text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">
              <Sparkles className="h-3.5 w-3.5" />
              Essai gratuit 30 jours
            </div>

            <h2 className="mb-2 text-2xl font-black tracking-tight text-slate-950 dark:text-white md:text-3xl">
              Tester Leopardo avec votre entreprise
            </h2>
            <p className="mb-6 text-sm leading-6 text-slate-600 dark:text-slate-400">
              Creez votre espace d'essai en 2 minutes. Aucune carte bancaire requise.
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
                label="Email professionnel"
                type="email"
                placeholder="vous@entreprise.com"
                icon={<Mail className="h-4 w-4" />}
                error={errors.email?.message}
                required
                {...register('email')}
              />

              <Input
                label="Entreprise"
                type="text"
                placeholder="Nom de votre entreprise"
                icon={<Building2 className="h-4 w-4" />}
                error={errors.company?.message}
                required
                {...register('company')}
              />

              <div className="grid gap-4 sm:grid-cols-2">
                <label className="block">
                  <span className="mb-2 block text-sm font-semibold text-slate-700 dark:text-slate-300">
                    Votre role
                  </span>
                  <select
                    className={selectClassName}
                    aria-invalid={errors.role ? true : undefined}
                    aria-describedby={errors.role ? 'signup-role-error' : undefined}
                    {...register('role')}
                  >
                    <option value="">Choisir</option>
                    <option value="founder">Fondateur / dirigeant</option>
                    <option value="manager">Manager</option>
                    <option value="hr">RH</option>
                    <option value="operations">Operations terrain</option>
                    <option value="other">Autre</option>
                  </select>
                  {errors.role && (
                    <p id="signup-role-error" role="alert" className="mt-1 text-sm text-red-600 dark:text-red-400">
                      {errors.role.message}
                    </p>
                  )}
                </label>

                <label className="block">
                  <span className="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-300">
                    <Users className="h-4 w-4" />
                    Taille equipe
                  </span>
                  <select
                    className={selectClassName}
                    aria-invalid={errors.employees ? true : undefined}
                    aria-describedby={errors.employees ? 'signup-employees-error' : undefined}
                    {...register('employees')}
                  >
                    <option value="">Choisir</option>
                    <option value="1-10">1-10</option>
                    <option value="11-50">11-50</option>
                    <option value="51-200">51-200</option>
                    <option value="201-500">201-500</option>
                    <option value="500+">500+</option>
                  </select>
                  {errors.employees && (
                    <p id="signup-employees-error" role="alert" className="mt-1 text-sm text-red-600 dark:text-red-400">
                      {errors.employees.message}
                    </p>
                  )}
                </label>
              </div>

              <Input
                label="Telephone (optionnel)"
                type="tel"
                placeholder="+213 555 000 000"
                icon={<Phone className="h-4 w-4" />}
                error={errors.phone?.message}
                {...register('phone')}
              />

              {role === 'operations' && (
                <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100">
                  Nous preparerons un parcours axe terrain : pointage, taches, kiosk et suivi d'equipe.
                </div>
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
                  J'accepte les{' '}
                  <a href="/terms" className="font-semibold text-emerald-600 hover:text-emerald-700">
                    conditions d'utilisation
                  </a>{' '}
                  et la{' '}
                  <a href="/privacy" className="font-semibold text-emerald-600 hover:text-emerald-700">
                    politique de confidentialite
                  </a>
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
                {formState.isSubmitting ? 'Envoi du code...' : 'Recevoir mon code de verification'}
              </Button>

              <p className="rounded-xl bg-slate-50 px-4 py-3 text-center text-xs leading-5 text-slate-500 dark:bg-slate-900/60 dark:text-slate-400">
                Un code a 6 chiffres sera envoye a votre email pour confirmer votre identite.
              </p>

              <p className="text-center text-sm text-slate-600 dark:text-slate-400">
                Vous avez deja un compte?{' '}
                <a href="/auth/login" className="font-semibold text-emerald-600 hover:text-emerald-700">
                  Se connecter
                </a>
              </p>
            </form>
          </motion.div>
        )}

        {/* ═══════════════════════════════════════ */}
        {/* STEP 2: OTP Verification                */}
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
              Retour
            </button>

            <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-100 dark:bg-emerald-900/40">
              <ShieldCheck className="h-8 w-8 text-emerald-600 dark:text-emerald-400" />
            </div>

            <h2 className="mb-2 text-2xl font-black tracking-tight text-slate-950 dark:text-white">
              Verifiez votre email
            </h2>
            <p className="mb-1 text-sm leading-6 text-slate-600 dark:text-slate-400">
              Nous avons envoye un code de verification a 6 chiffres a :
            </p>
            <p className="mb-6 text-sm font-bold text-emerald-600 dark:text-emerald-400">
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
              {isVerifying ? 'Verification en cours...' : 'Verifier et creer mon espace'}
            </Button>

            <p className="mt-4 text-xs text-slate-400 dark:text-slate-500">
              Le code est valide pendant 30 minutes. Verifiez vos spams si vous ne le trouvez pas.
            </p>
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
              Demande d'essai recue
            </h2>
            <p className="mb-1 text-sm leading-6 text-slate-600 dark:text-slate-400">
              {pendingMessage}
            </p>
            <p className="mb-6 text-sm font-bold text-emerald-600 dark:text-emerald-400">
              {pendingEmail}
            </p>

            <div className="rounded-xl bg-slate-50 px-4 py-3 text-left text-xs leading-5 text-slate-500 dark:bg-slate-900/60 dark:text-slate-400">
              Notre systeme de creation d'espace instantane est momentanement
              indisponible (redemarrage serveur). Votre demande est bien
              enregistree : une personne de l'equipe Leopardo vous contactera
              par email sous 24h ouvrables avec un acces adapte a votre
              contexte.
            </div>

            <p className="mt-4 text-center text-sm text-slate-600 dark:text-slate-400">
              Vous avez deja un compte?{' '}
              <a href="/auth/login" className="font-semibold text-emerald-600 hover:text-emerald-700">
                Se connecter
              </a>
            </p>
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
                <Rocket className="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                <h3 className="text-lg font-black text-emerald-900 dark:text-emerald-100">
                  Votre espace est pret !
                </h3>
              </div>

              <div className="space-y-4 p-5">
                <div className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-100 dark:bg-slate-800/60 dark:ring-slate-700">
                  <div className="flex items-center gap-2 text-center justify-center mb-3">
                    <CheckCircle className="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                    <p className="text-sm font-medium text-slate-700 dark:text-slate-300">
                      Votre adresse email a bien été vérifiée.
                    </p>
                  </div>
                  {provisionedData?.manager ? (
                    <>
                      <p className="mb-1 text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                        Identifiants de connexion
                      </p>
                      <div className="space-y-2">
                        <div className="flex items-center justify-between">
                          <span className="text-sm text-slate-600 dark:text-slate-300">Email</span>
                          <span className="font-mono text-sm font-bold text-slate-900 dark:text-white">
                            {provisionedData.manager.email}
                          </span>
                        </div>
                        <div className="flex items-center justify-between gap-2">
                          <span className="text-sm text-slate-600 dark:text-slate-300">Mot de passe</span>
                          <div className="flex items-center gap-2">
                            <span className="rounded-lg bg-slate-100 px-3 py-1 font-mono text-sm font-bold text-slate-900 dark:bg-slate-700 dark:text-white">
                              {provisionedData.manager.temp_password}
                            </span>
                            <button
                              type="button"
                              onClick={() => copyPassword(provisionedData.manager!.temp_password)}
                              className="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700 dark:hover:text-slate-200"
                              title="Copier le mot de passe"
                            >
                              <ClipboardCopy className="h-4 w-4" />
                            </button>
                          </div>
                        </div>
                        {copied && (
                          <p className="text-right text-xs font-medium text-emerald-600">Copie !</p>
                        )}
                      </div>
                      <p className="mt-3 text-xs text-slate-500 dark:text-slate-400">
                        Ces identifiants ont aussi ete envoyes par email a {provisionedData.manager.email}.
                      </p>
                    </>
                  ) : (
                    <p className="text-sm text-slate-600 dark:text-slate-400">
                      Vos identifiants de connexion viennent de vous etre envoyes par email.
                    </p>
                  )}
                </div>

                {provisionedData?.trial && (
                  <p className="text-center text-sm text-slate-500 dark:text-slate-400">
                    Essai gratuit de{' '}
                    <span className="font-bold text-emerald-600">
                      {provisionedData.trial.days} jours
                    </span>{' '}
                    — aucune carte bancaire requise.
                  </p>
                )}

                <div className="flex flex-col gap-2 sm:flex-row">
                  <a
                    href="/auth/login"
                    className="flex flex-1 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-3 text-sm font-bold text-white shadow-lg shadow-emerald-500/25 transition hover:bg-emerald-700"
                  >
                    <LogIn className="h-4 w-4" />
                    Se connecter
                  </a>
                  <a
                    href="/download"
                    className="flex flex-1 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                  >
                    <Download className="h-4 w-4" />
                    Telecharger l'app
                  </a>
                </div>

                <p className="text-center text-xs text-slate-400 dark:text-slate-500">
                  Changez votre mot de passe des la premiere connexion.
                </p>
              </div>
            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </Card>
  );
}
