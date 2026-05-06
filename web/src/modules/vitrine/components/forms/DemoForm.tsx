'use client';

import React, { useReducer } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { motion } from 'framer-motion';
import { Mail, User, Building2, Phone, Calendar, CheckCircle, AlertCircle } from 'lucide-react';
import { Input } from '@/modules/vitrine/components/common/Input';
import { Select } from '@/modules/vitrine/components/common/Select';
import { Button } from '@/modules/vitrine/components/common/Button';
import { Card } from '@/modules/vitrine/components/common/Card';
import { demoFormSchema, DemoFormData } from '@/modules/vitrine/lib/validation';
import { submitDemoForm, createFormReducer, initialFormState } from '@/modules/vitrine/lib/forms';
import { useAnalyticsForm } from '@/modules/vitrine/hooks/useAnalytics';

interface DemoFormProps {
  page?: string;
  onSuccess?: (data: DemoFormData) => void;
  onError?: (error: string) => void;
  className?: string;
}

export function DemoForm({
  page = '/demo',
  onSuccess,
  onError,
  className = '',
}: DemoFormProps) {
  const {
    register,
    handleSubmit,
    formState: { errors },
    reset,
  } = useForm<DemoFormData>({
    resolver: zodResolver(demoFormSchema),
    mode: 'onBlur',
  });

  const [formState, dispatch] = useReducer(createFormReducer(), initialFormState);
  const { trackDemoRequest } = useAnalyticsForm();

  // Generate available dates (next 30 days, excluding weekends)
  const getAvailableDates = () => {
    const dates = [];
    const today = new Date();
    today.setDate(today.getDate() + 1); // Start from tomorrow

    for (let i = 0; i < 30; i++) {
      const date = new Date(today);
      date.setDate(date.getDate() + i);

      // Skip weekends
      if (date.getDay() !== 0 && date.getDay() !== 6) {
        dates.push(date);
      }
    }

    return dates;
  };

  const availableDates = getAvailableDates();

  const onSubmit = async (data: DemoFormData) => {
    dispatch({ type: 'SUBMIT_START' });

    try {
      const response = await submitDemoForm(data, page);

      if (response.success) {
        // Track demo request
        trackDemoRequest(data.email, data.company, {
          source: 'demo_form',
          page,
          name: data.name,
          phone: data.phone,
          employees: data.employees,
          preferredDate: data.preferredDate,
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
          Demander une démo
        </h2>
        <p className="text-slate-600 dark:text-slate-400 mb-6">
          Découvrez comment Leopardo peut transformer votre gestion RH
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
                Un expert vous contactera dans les 24 heures.
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

          {/* Company */}
          <Input
            label="Entreprise"
            type="text"
            placeholder="Votre entreprise"
            icon={<Building2 className="w-4 h-4" />}
            error={errors.company?.message}
            required
            {...register('company')}
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

          {/* Employees */}
          <Select
            label="Nombre d'employés"
            error={errors.employees?.message}
            options={[
              { value: '1-10', label: '1-10 employés' },
              { value: '11-50', label: '11-50 employés' },
              { value: '51-200', label: '51-200 employés' },
              { value: '201-500', label: '201-500 employés' },
              { value: '500+', label: '500+ employés' },
            ]}
            placeholder="Sélectionnez une plage"
            {...register('employees')}
          />

          {/* Preferred Date */}
          <div>
            <label className="block text-sm font-semibold text-slate-900 dark:text-white mb-2">
              Date préférée (optionnel)
            </label>
            <div className="relative">
              <Calendar className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 dark:text-slate-500 pointer-events-none" />
              <select
                className={`
                  w-full pl-10 pr-4 py-2.5 text-sm font-medium
                  bg-white dark:bg-slate-900
                  border border-slate-300 dark:border-slate-700
                  rounded-xl
                  transition-all duration-200
                  focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent
                  disabled:opacity-50 disabled:cursor-not-allowed
                  ${errors.preferredDate?.message ? 'border-red-500 focus:ring-red-500' : ''}
                `.trim()}
                {...register('preferredDate')}
              >
                <option value="">Choisir une date</option>
                {availableDates.map((date) => (
                  <option key={date.toISOString()} value={date.toISOString().split('T')[0]}>
                    {date.toLocaleDateString('fr-FR', {
                      weekday: 'long',
                      year: 'numeric',
                      month: 'long',
                      day: 'numeric',
                    })}
                  </option>
                ))}
              </select>
            </div>
            {errors.preferredDate && (
              <p className="mt-2 text-sm text-red-600 dark:text-red-400">
                {errors.preferredDate.message}
              </p>
            )}
          </div>

          {/* Submit Button */}
          <Button
            type="submit"
            variant="primary"
            size="lg"
            fullWidth
            loading={formState.isSubmitting}
            disabled={formState.isSubmitting || formState.isSuccess}
          >
            {formState.isSubmitting ? 'Envoi en cours...' : 'Demander une démo'}
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
