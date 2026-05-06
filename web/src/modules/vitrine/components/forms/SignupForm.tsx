'use client';

import React, { useReducer, useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { motion } from 'framer-motion';
import { Mail, Lock, CheckCircle, AlertCircle } from 'lucide-react';
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
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);
  const { trackSignup } = useAnalyticsForm();

  const password = watch('password');

  const onSubmit = async (data: SignupFormData) => {
    dispatch({ type: 'SUBMIT_START' });

    try {
      // Verify passwords match
      if (data.password !== data.confirmPassword) {
        dispatch({
          type: 'SUBMIT_ERROR',
          payload: {
            message: 'Les mots de passe ne correspondent pas',
            errors: { confirmPassword: 'Les mots de passe ne correspondent pas' },
          },
        });
        return;
      }

      // Submit form
      const response = await submitSignupForm(data, page);

      if (response.success) {
        // Track signup
        trackSignup(data.email, {
          source: 'signup_form',
          page,
        });

        dispatch({
          type: 'SUBMIT_SUCCESS',
          payload: { message: response.message },
        });

        reset();

        if (onSuccess) {
          onSuccess(data);
        }

        // Reset success message after 5 seconds
        setTimeout(() => {
          dispatch({ type: 'RESET' });
        }, 5000);
      } else {
        dispatch({
          type: 'SUBMIT_ERROR',
          payload: {
            message: response.error || response.message,
          },
        });

        if (onError) {
          onError(response.error || response.message);
        }
      }
    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : 'Une erreur est survenue';
      dispatch({
        type: 'SUBMIT_ERROR',
        payload: { message: errorMessage },
      });

      if (onError) {
        onError(errorMessage);
      }
    }
  };

  return (
    <Card className={`p-6 md:p-8 ${className}`}>
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.3 }}
      >
        <h2 className="text-2xl md:text-3xl font-bold text-slate-900 dark:text-white mb-2">
          Créer un compte
        </h2>
        <p className="text-slate-600 dark:text-slate-400 mb-6">
          Commencez votre essai gratuit de 14 jours
        </p>

        {formState.isSuccess && (
          <motion.div
            initial={{ opacity: 0, y: -10 }}
            animate={{ opacity: 1, y: 0 }}
            className="mb-6 p-4 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-lg flex items-start gap-3"
          >
            <CheckCircle className="w-5 h-5 text-emerald-600 dark:text-emerald-400 flex-shrink-0 mt-0.5" />
            <div>
              <p className="font-semibold text-emerald-900 dark:text-emerald-100">
                {formState.message}
              </p>
              <p className="text-sm text-emerald-800 dark:text-emerald-200 mt-1">
                Vérifiez votre email pour confirmer votre compte.
              </p>
            </div>
          </motion.div>
        )}

        {formState.isError && (
          <motion.div
            initial={{ opacity: 0, y: -10 }}
            animate={{ opacity: 1, y: 0 }}
            className="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg flex items-start gap-3"
          >
            <AlertCircle className="w-5 h-5 text-red-600 dark:text-red-400 flex-shrink-0 mt-0.5" />
            <div>
              <p className="font-semibold text-red-900 dark:text-red-100">
                {formState.message}
              </p>
            </div>
          </motion.div>
        )}

        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
          {/* Email */}
          <Input
            label="Email"
            type="email"
            placeholder="vous@exemple.com"
            icon={<Mail className="w-4 h-4" />}
            error={errors.email?.message}
            required
            {...register('email')}
          />

          {/* Password */}
          <div>
            <Input
              label="Mot de passe"
              type={showPassword ? 'text' : 'password'}
              placeholder="••••••••"
              icon={
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300"
                >
                  <Lock className="w-4 h-4" />
                </button>
              }
              iconPosition="right"
              error={errors.password?.message}
              helperText="Au moins 8 caractères, 1 majuscule, 1 chiffre, 1 caractère spécial"
              required
              {...register('password')}
            />
          </div>

          {/* Confirm Password */}
          <div>
            <Input
              label="Confirmer le mot de passe"
              type={showConfirmPassword ? 'text' : 'password'}
              placeholder="••••••••"
              icon={
                <button
                  type="button"
                  onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                  className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300"
                >
                  <Lock className="w-4 h-4" />
                </button>
              }
              iconPosition="right"
              error={
                errors.confirmPassword?.message ||
                (password && watch('confirmPassword') && password !== watch('confirmPassword')
                  ? 'Les mots de passe ne correspondent pas'
                  : undefined)
              }
              required
              {...register('confirmPassword')}
            />
          </div>

          {/* Terms */}
          <div className="flex items-start gap-3">
            <input
              type="checkbox"
              id="agreeToTerms"
              className="mt-1 w-4 h-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer"
              {...register('agreeToTerms')}
            />
            <label htmlFor="agreeToTerms" className="text-sm text-slate-600 dark:text-slate-400">
              J'accepte les{' '}
              <a href="/terms" className="text-emerald-600 hover:text-emerald-700 font-semibold">
                conditions d'utilisation
              </a>{' '}
              et la{' '}
              <a href="/privacy" className="text-emerald-600 hover:text-emerald-700 font-semibold">
                politique de confidentialité
              </a>
            </label>
          </div>
          {errors.agreeToTerms && (
            <p className="text-sm text-red-600 dark:text-red-400">{errors.agreeToTerms.message}</p>
          )}

          {/* Submit Button */}
          <Button
            type="submit"
            variant="primary"
            size="lg"
            fullWidth
            loading={formState.isSubmitting}
            disabled={formState.isSubmitting || formState.isSuccess}
          >
            {formState.isSubmitting ? 'Création en cours...' : 'Créer mon compte'}
          </Button>

          {/* Login Link */}
          <p className="text-center text-sm text-slate-600 dark:text-slate-400">
            Vous avez déjà un compte?{' '}
            <a href="/login" className="text-emerald-600 hover:text-emerald-700 font-semibold">
              Se connecter
            </a>
          </p>
        </form>
      </motion.div>
    </Card>
  );
}
