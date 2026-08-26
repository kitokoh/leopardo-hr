'use client';

import { useCallback, useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import { CheckCircle2, Circle, Loader2, Settings2, UserRound, FileText, Rocket, RefreshCw, ArrowRight } from 'lucide-react';
import Link from 'next/link';
import { apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { t } from '@/lib/i18n/locale-catalog';
import { getPreferredLocale } from '@/lib/i18n';

interface ActivationStatus {
  completed: boolean;
  steps: {
    settings: boolean;
    contact: boolean;
    example_invoice: boolean;
  };
}

/**
 * #5539 — Wizard d'activation Comptabilité (backend #5288 livré, aucune UI).
 *
 * Check-list GET /accounting/activation + bouton « Terminer » →
 * POST /accounting/activation/complete (payload optionnel : le backend
 * dérive les défauts du pays de l'entreprise). Redirection vers le module
 * Comptabilité (#5534) une fois activé.
 */
export default function AccountingActivationPage() {
  const locale = getPreferredLocale();
  const [status, setStatus] = useState<ActivationStatus | null>(null);
  const [loading, setLoading] = useState(true);
  const [activating, setActivating] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [completeError, setCompleteError] = useState<string | null>(null);

  const loadStatus = useCallback(async () => {
    setLoading(true);
    setLoadError(null);
    try {
      const res = await apiFetch('/accounting/activation');
      const body = await res.json();
      setStatus(body.data as ActivationStatus);
    } catch {
      setLoadError(t(locale, 'accountingActivation.loadError'));
    } finally {
      setLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    void loadStatus();
  }, [loadStatus]);

  const completeActivation = async () => {
    setActivating(true);
    setCompleteError(null);
    try {
      const res = await apiFetch('/accounting/activation/complete', { method: 'POST' });
      const body = await res.json();
      setStatus(body.data as ActivationStatus);
    } catch {
      setCompleteError(t(locale, 'accountingActivation.completeError'));
    } finally {
      setActivating(false);
    }
  };

  const steps: Array<{ key: 'settings' | 'contact' | 'example_invoice'; label: string; doneLabel: string; todoLabel: string; icon: typeof Settings2 }> = [
    {
      key: 'settings',
      label: t(locale, 'accountingActivation.stepSettings'),
      doneLabel: t(locale, 'accountingActivation.stepSettingsDone'),
      todoLabel: t(locale, 'accountingActivation.stepSettingsTodo'),
      icon: Settings2,
    },
    {
      key: 'contact',
      label: t(locale, 'accountingActivation.stepContact'),
      doneLabel: t(locale, 'accountingActivation.stepContactDone'),
      todoLabel: t(locale, 'accountingActivation.stepContactTodo'),
      icon: UserRound,
    },
    {
      key: 'example_invoice',
      label: t(locale, 'accountingActivation.stepExampleInvoice'),
      doneLabel: t(locale, 'accountingActivation.stepExampleInvoiceDone'),
      todoLabel: t(locale, 'accountingActivation.stepExampleInvoiceTodo'),
      icon: FileText,
    },
  ];

  return (
    <ModulePageShell
      title={t(locale, 'accountingActivation.title')}
      subtitle={t(locale, 'accountingActivation.subtitle')}
      accentClassName="bg-gradient-to-br from-amber-100 via-white to-white"
    >
      {loadError ? (
        <div className="rounded-2xl border border-red-200 bg-red-50 p-8 text-center" role="alert">
          <p className="text-sm font-medium text-red-700">{loadError}</p>
          <button
            onClick={() => void loadStatus()}
            className="mt-3 inline-flex items-center gap-1 rounded-lg bg-red-100 px-3 py-1.5 text-xs font-bold text-red-700 transition hover:bg-red-200"
          >
            <RefreshCw className="h-3 w-3" />
            {t(locale, 'accountingActivation.retry')}
          </button>
        </div>
      ) : loading ? (
        <div className="flex items-center justify-center gap-2 rounded-2xl border border-app-border bg-white p-10 text-sm text-slate-400">
          <Loader2 className="h-4 w-4 animate-spin" />
          {t(locale, 'accountingActivation.loading')}
        </div>
      ) : status?.completed ? (
        <motion.section
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          className="rounded-3xl border border-emerald-200 bg-white p-10 text-center shadow-sm"
        >
          <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-50">
            <CheckCircle2 className="h-8 w-8 text-emerald-600" />
          </div>
          <h2 className="text-xl font-black text-slate-950">{t(locale, 'accountingActivation.completedTitle')}</h2>
          <p className="mx-auto mt-2 max-w-md text-sm text-slate-600">{t(locale, 'accountingActivation.completedBody')}</p>
          <Link
            href="/accounting"
            className="mt-6 inline-flex items-center gap-2 rounded-xl bg-emerald-500 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-600"
          >
            {t(locale, 'accountingActivation.goToModule')}
            <ArrowRight className="h-4 w-4" />
          </Link>
        </motion.section>
      ) : (
        <>
          <section className="overflow-hidden rounded-3xl border border-app-border bg-white shadow-sm">
            <div className="border-b border-app-border px-6 py-4">
              <h2 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-slate-800">
                <Rocket className="h-4 w-4 text-amber-500" />
                {t(locale, 'accountingActivation.stepsTitle')}
              </h2>
            </div>
            <ul className="divide-y divide-app-border">
              {steps.map((step) => {
                const done = status?.steps[step.key] ?? false;
                const Icon = step.icon;
                return (
                  <li key={step.key} className="flex items-center gap-4 px-6 py-4">
                    <span
                      className={`flex h-10 w-10 items-center justify-center rounded-xl ${
                        done ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-400'
                      }`}
                    >
                      <Icon className="h-5 w-5" />
                    </span>
                    <span className="flex-1 text-sm font-medium text-slate-700">{step.label}</span>
                    <span
                      className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider ${
                        done ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'
                      }`}
                    >
                      {done ? <CheckCircle2 className="h-3 w-3" /> : <Circle className="h-3 w-3" />}
                      {done ? t(locale, 'accountingActivation.done') : t(locale, 'accountingActivation.todo')}
                    </span>
                  </li>
                );
              })}
            </ul>
          </section>

          {completeError && (
            <div className="rounded-2xl border border-red-200 bg-red-50 px-6 py-4 text-sm text-red-700" role="alert">
              {completeError}
            </div>
          )}

          <div className="flex justify-end">
            <button
              onClick={() => void completeActivation()}
              disabled={activating}
              className="inline-flex items-center gap-2 rounded-xl bg-amber-500 px-6 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-amber-600 disabled:opacity-60"
            >
              {activating ? <Loader2 className="h-4 w-4 animate-spin" /> : <Rocket className="h-4 w-4" />}
              {activating ? t(locale, 'accountingActivation.activating') : t(locale, 'accountingActivation.activateButton')}
            </button>
          </div>
        </>
      )}
    </ModulePageShell>
  );
}
