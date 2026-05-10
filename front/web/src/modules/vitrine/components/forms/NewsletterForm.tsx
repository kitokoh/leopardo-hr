'use client';

import React, { useReducer } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { motion } from 'framer-motion';
import { Mail, CheckCircle, AlertCircle, ArrowRight } from 'lucide-react';
import { Input } from '@/modules/vitrine/components/common/Input';
import { Button } from '@/modules/vitrine/components/common/Button';
import { newsletterFormSchema, NewsletterFormData } from '@/modules/vitrine/lib/validation';
import { submitNewsletterForm, createFormReducer, initialFormState } from '@/modules/vitrine/lib/forms';
import { useAnalyticsForm } from '@/modules/vitrine/hooks/useAnalytics';

interface NewsletterFormProps {
  page?: string;
  onSuccess?: (data: NewsletterFormData) => void;
  onError?: (error: string) => void;
  className?: string;
  variant?: 'default' | 'compact' | 'inline';
  title?: string;
  description?: string;
}

export function NewsletterForm({
  page = '/newsletter',
  onSuccess,
  onError,
  className = '',
  variant = 'default',
  title = 'Restez informé',
  description = 'Recevez nos conseils et actualités directement dans votre boîte mail',
}: NewsletterFormProps) {
  const {
    register,
    handleSubmit,
    formState: { errors },
    reset,
  } = useForm<NewsletterFormData>({
    resolver: zodResolver(newsletterFormSchema),
    mode: 'onBlur',
  });

  const [formState, dispatch] = useReducer(createFormReducer(), initialFormState);
  const { trackNewsletterSignup } = useAnalyticsForm();

  const onSubmit = async (data: NewsletterFormData) => {
    dispatch({ type: 'SUBMIT_START' });

    try {
      const response = await submitNewsletterForm(data, page);

      if (response.success) {
        // Track newsletter signup
        trackNewsletterSignup(data.email, {
          source: 'newsletter_form',
          page,
          variant,
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

  // Compact variant - inline form
  if (variant === 'compact') {
    return (
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.3 }}
        className={className}
      >
        <form onSubmit={handleSubmit(onSubmit)} className="flex gap-2">
          <Input
            type="email"
            placeholder="votre@email.com"
            error={errors.email?.message}
            className="flex-1"
            {...register('email')}
          />
          <Button
            type="submit"
            variant="primary"
            size="md"
            loading={formState.isSubmitting}
            disabled={formState.isSubmitting || formState.isSuccess}
            icon={<ArrowRight className="w-4 h-4" />}
            iconPosition="right"
          >
            S'inscrire
          </Button>
        </form>
        {formState.isSuccess && (
          <motion.p
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            className="mt-2 text-sm text-emerald-600 dark:text-emerald-400"
          >
            ✓ Merci de votre inscription!
          </motion.p>
        )}
        {formState.isError && (
          <motion.p
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            className="mt-2 text-sm text-red-600 dark:text-red-400"
          >
            {formState.message}
          </motion.p>
        )}
      </motion.div>
    );
  }

  // Inline variant - horizontal layout
  if (variant === 'inline') {
    return (
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.3 }}
        className={className}
      >
        <div className="flex flex-col md:flex-row gap-4 items-start md:items-end">
          <div className="flex-1">
            <h3 className="text-lg font-bold text-slate-900 dark:text-white mb-1">{title}</h3>
            <p className="text-sm text-slate-600 dark:text-slate-400">{description}</p>
          </div>
          <form onSubmit={handleSubmit(onSubmit)} className="w-full md:w-auto flex gap-2">
            <Input
              type="email"
              placeholder="votre@email.com"
              error={errors.email?.message}
              className="flex-1 md:w-64"
              {...register('email')}
            />
            <Button
              type="submit"
              variant="primary"
              size="md"
              loading={formState.isSubmitting}
              disabled={formState.isSubmitting || formState.isSuccess}
            >
              S'inscrire
            </Button>
          </form>
        </div>
        {formState.isSuccess && (
          <motion.div
            initial={{ opacity: 0, y: -10 }}
            animate={{ opacity: 1, y: 0 }}
            className="mt-4 p-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-lg flex items-center gap-2"
          >
            <CheckCircle className="w-4 h-4 text-emerald-600 dark:text-emerald-400 flex-shrink-0" />
            <p className="text-sm text-emerald-900 dark:text-emerald-100">{formState.message}</p>
          </motion.div>
        )}
        {formState.isError && (
          <motion.div
            initial={{ opacity: 0, y: -10 }}
            animate={{ opacity: 1, y: 0 }}
            className="mt-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg flex items-center gap-2"
          >
            <AlertCircle className="w-4 h-4 text-red-600 dark:text-red-400 flex-shrink-0" />
            <p className="text-sm text-red-900 dark:text-red-100">{formState.message}</p>
          </motion.div>
        )}
      </motion.div>
    );
  }

  // Default variant - card layout
  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.3 }}
      className={`p-6 md:p-8 bg-gradient-to-br from-emerald-50 to-cyan-50 dark:from-emerald-900/20 dark:to-cyan-900/20 border border-emerald-200 dark:border-emerald-800 rounded-2xl ${className}`}
    >
      <h2 className="text-2xl md:text-3xl font-bold text-slate-900 dark:text-white mb-2">
        {title}
      </h2>
      <p className="text-slate-600 dark:text-slate-400 mb-6">{description}</p>

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
              Vérifiez votre email pour confirmer votre inscription.
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
            <p className="font-semibold text-red-900 dark:text-red-100">{formState.message}</p>
          </div>
        </motion.div>
      )}

      <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
        <Input
          label="Email"
          type="email"
          placeholder="vous@exemple.com"
          icon={<Mail className="w-4 h-4" />}
          error={errors.email?.message}
          required
          {...register('email')}
        />

        <Button
          type="submit"
          variant="primary"
          size="lg"
          fullWidth
          loading={formState.isSubmitting}
          disabled={formState.isSubmitting || formState.isSuccess}
        >
          {formState.isSubmitting ? 'Inscription en cours...' : 'S\'inscrire à la newsletter'}
        </Button>

        <p className="text-center text-xs text-slate-500 dark:text-slate-400">
          Nous respectons votre vie privée. Aucun spam, promis.
        </p>
      </form>
    </motion.div>
  );
}
