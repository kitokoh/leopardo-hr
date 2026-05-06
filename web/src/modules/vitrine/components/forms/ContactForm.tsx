'use client';

import React, { useReducer } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { motion } from 'framer-motion';
import { Mail, User, MessageSquare, Phone, CheckCircle, AlertCircle } from 'lucide-react';
import { Input } from '@/modules/vitrine/components/common/Input';
import { Textarea } from '@/modules/vitrine/components/common/Textarea';
import { Button } from '@/modules/vitrine/components/common/Button';
import { Card } from '@/modules/vitrine/components/common/Card';
import { contactFormSchema, ContactFormData } from '@/modules/vitrine/lib/validation';
import { submitContactForm, createFormReducer, initialFormState } from '@/modules/vitrine/lib/forms';
import { useAnalyticsForm } from '@/modules/vitrine/hooks/useAnalytics';

interface ContactFormProps {
  page?: string;
  onSuccess?: (data: ContactFormData) => void;
  onError?: (error: string) => void;
  className?: string;
}

export function ContactForm({
  page = '/contact',
  onSuccess,
  onError,
  className = '',
}: ContactFormProps) {
  const {
    register,
    handleSubmit,
    formState: { errors },
    reset,
  } = useForm<ContactFormData>({
    resolver: zodResolver(contactFormSchema),
    mode: 'onBlur',
  });

  const [formState, dispatch] = useReducer(createFormReducer(), initialFormState);
  const { trackContact } = useAnalyticsForm();

  const onSubmit = async (data: ContactFormData) => {
    dispatch({ type: 'SUBMIT_START' });

    try {
      const response = await submitContactForm(data, page);

      if (response.success) {
        // Track contact
        trackContact(data.email, data.subject, {
          source: 'contact_form',
          page,
          name: data.name,
          phone: data.phone,
          message: data.message,
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
          Nous contacter
        </h2>
        <p className="text-slate-600 dark:text-slate-400 mb-6">
          Une question? Nous sommes là pour vous aider
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
                Nous vous répondrons dans les 24 heures.
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
          {/* Name */}
          <Input
            label="Nom complet"
            type="text"
            placeholder="Jean Dupont"
            icon={<User className="w-4 h-4" />}
            error={errors.name?.message}
            required
            {...register('name')}
          />

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

          {/* Phone */}
          <Input
            label="Téléphone (optionnel)"
            type="tel"
            placeholder="+33 1 23 45 67 89"
            icon={<Phone className="w-4 h-4" />}
            error={errors.phone?.message}
            {...register('phone')}
          />

          {/* Subject */}
          <Input
            label="Sujet"
            type="text"
            placeholder="Comment puis-je vous aider?"
            icon={<MessageSquare className="w-4 h-4" />}
            error={errors.subject?.message}
            required
            {...register('subject')}
          />

          {/* Message */}
          <Textarea
            label="Message"
            placeholder="Décrivez votre question ou votre besoin..."
            error={errors.message?.message}
            required
            {...register('message')}
          />

          {/* Submit Button */}
          <Button
            type="submit"
            variant="primary"
            size="lg"
            fullWidth
            loading={formState.isSubmitting}
            disabled={formState.isSubmitting || formState.isSuccess}
          >
            {formState.isSubmitting ? 'Envoi en cours...' : 'Envoyer le message'}
          </Button>

          {/* Info */}
          <p className="text-center text-xs text-slate-500 dark:text-slate-400">
            Nous respectons votre vie privée. Aucun spam, promis.
          </p>
        </form>
      </motion.div>
    </Card>
  );
}
