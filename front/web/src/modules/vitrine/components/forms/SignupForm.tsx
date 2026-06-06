'use client';

import React, { useReducer } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { motion } from 'framer-motion';
import { AlertCircle, Building2, CheckCircle, Mail, Phone, Sparkles, Users } from 'lucide-react';
import { Input } from '@/modules/vitrine/components/common/Input';
import { Button } from '@/modules/vitrine/components/common/Button';
import { Card } from '@/modules/vitrine/components/common/Card';
import { signupFormSchema, SignupFormData } from '@/modules/vitrine/lib/validation';
import { submitSignupForm, createFormReducer, initialFormState } from '@/modules/vitrine/lib/forms';
import { useAnalyticsForm } from '@/modules/vitrine/hooks/useAnalytics';

interface SignupFormProps {
  page?: string;
  onSuccess?: (data: SignupFormData) => void;
  onError?: (error: string) => void;
  className?: string;
}

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

        dispatch({
          type: 'SUBMIT_SUCCESS',
          payload: { message: response.message },
        });

        reset();
        onSuccess?.(data);

        setTimeout(() => {
          dispatch({ type: 'RESET' });
        }, 8000);
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

  return (
    <Card className={`p-6 md:p-8 ${className}`}>
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.3 }}
      >
        <div className="mb-5 inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-bold uppercase tracking-wide text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">
          <Sparkles className="h-3.5 w-3.5" />
          Test guide par email
        </div>

        <h2 className="mb-2 text-2xl font-black tracking-tight text-slate-950 dark:text-white md:text-3xl">
          Tester Leopardo avec votre entreprise
        </h2>
        <p className="mb-6 text-sm leading-6 text-slate-600 dark:text-slate-400">
          Laissez votre email professionnel. Nous qualifions votre contexte et preparons l'acces
          d'essai le plus adapte sous 24h ouvrables.
        </p>

        {formState.isSuccess && (
          <motion.div
            initial={{ opacity: 0, y: -10 }}
            animate={{ opacity: 1, y: 0 }}
            className="mb-6 flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-800 dark:bg-emerald-900/20"
          >
            <CheckCircle className="mt-0.5 h-5 w-5 flex-shrink-0 text-emerald-600 dark:text-emerald-400" />
            <div>
              <p className="font-semibold text-emerald-900 dark:text-emerald-100">
                {formState.message}
              </p>
              <p className="mt-1 text-sm text-emerald-800 dark:text-emerald-200">
                Votre demande est exploitable par l'equipe commerciale. Si les webhooks CRM/email
                sont actifs, elle est aussi transmise automatiquement.
              </p>
            </div>
          </motion.div>
        )}

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
              <select className={selectClassName} {...register('role')}>
                <option value="">Choisir</option>
                <option value="founder">Fondateur / dirigeant</option>
                <option value="manager">Manager</option>
                <option value="hr">RH</option>
                <option value="operations">Operations terrain</option>
                <option value="other">Autre</option>
              </select>
              {errors.role && (
                <p className="mt-1 text-sm text-red-600 dark:text-red-400">
                  {errors.role.message}
                </p>
              )}
            </label>

            <label className="block">
              <span className="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-300">
                <Users className="h-4 w-4" />
                Taille equipe
              </span>
              <select className={selectClassName} {...register('employees')}>
                <option value="">Choisir</option>
                <option value="1-10">1-10</option>
                <option value="11-50">11-50</option>
                <option value="51-200">51-200</option>
                <option value="201-500">201-500</option>
                <option value="500+">500+</option>
              </select>
              {errors.employees && (
                <p className="mt-1 text-sm text-red-600 dark:text-red-400">
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
            <p className="text-sm text-red-600 dark:text-red-400">
              {errors.agreeToTerms.message}
            </p>
          )}

          <Button
            type="submit"
            variant="primary"
            size="lg"
            fullWidth
            loading={formState.isSubmitting}
            disabled={formState.isSubmitting || formState.isSuccess}
          >
            {formState.isSubmitting ? "Envoi de la demande..." : "Recevoir mon acces d'essai"}
          </Button>

          <p className="rounded-xl bg-slate-50 px-4 py-3 text-center text-xs leading-5 text-slate-500 dark:bg-slate-900/60 dark:text-slate-400">
            Aucun mot de passe n'est demande ici. Le compte d'essai est cree uniquement apres
            validation commerciale ou provisioning platform admin.
          </p>

          <p className="text-center text-sm text-slate-600 dark:text-slate-400">
            Vous avez deja un compte?{' '}
            <a href="/login" className="font-semibold text-emerald-600 hover:text-emerald-700">
              Se connecter
            </a>
          </p>
        </form>
      </motion.div>
    </Card>
  );
}
