'use client';

import { useCallback, useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import Link from 'next/link';
import {
  BookOpen,
  ListOrdered,
  Scale,
  FileBarChart2,
  CalendarRange,
  Link2,
  FileDown,
  ArrowRight,
  Landmark,
  Rocket,
  CheckCircle2,
  Circle,
} from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import { apiFetch } from '@/lib/api-client';
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
 * #5534 — Module Comptabilité : accueil du rôle comptable/principal.
 * Hub de navigation vers les 7 écrans du module (backend #5422 livré).
 *
 * #5626 — Carte d'activation affichée tant que le module n'est pas activé :
 * check-list compacte avec lien vers le wizard d'activation.
 */
export default function AccountingHomePage() {
  const locale = getPreferredLocale();
  const [activation, setActivation] = useState<ActivationStatus | null>(null);

  const loadActivation = useCallback(async () => {
    try {
      const res = await apiFetch('/accounting/activation');
      const body = await res.json();
      setActivation(body.data as ActivationStatus);
    } catch {
      // Silently ignore — le module peut ne pas être disponible sur ce plan.
    }
  }, []);

  useEffect(() => {
    void loadActivation();
  }, [loadActivation]);

  const tiles = [
    { href: '/accounting/chart', icon: BookOpen, label: t(locale, 'accountingModule.navChart'), accent: 'bg-sky-50 text-sky-600' },
    { href: '/accounting/ledger', icon: ListOrdered, label: t(locale, 'accountingModule.navLedger'), accent: 'bg-emerald-50 text-emerald-600' },
    { href: '/accounting/balance', icon: Scale, label: t(locale, 'accountingModule.navBalance'), accent: 'bg-amber-50 text-amber-600' },
    { href: '/accounting/statements', icon: FileBarChart2, label: t(locale, 'accountingModule.navStatements'), accent: 'bg-violet-50 text-violet-600' },
    { href: '/accounting/fiscal-years', icon: CalendarRange, label: t(locale, 'accountingModule.navFiscalYears'), accent: 'bg-rose-50 text-rose-600' },
    { href: '/accounting/lettering', icon: Link2, label: t(locale, 'accountingModule.navLettering'), accent: 'bg-cyan-50 text-cyan-600' },
    { href: '/accounting/reconciliation', icon: Landmark, label: t(locale, 'bankRecon.title'), accent: 'bg-teal-50 text-teal-600' },
    { href: '/accounting/fec', icon: FileDown, label: t(locale, 'accountingModule.navFec'), accent: 'bg-indigo-50 text-indigo-600' },
  ];

  // Étapes d'activation affichées dans la carte compacte.
  const activationSteps: Array<{ key: keyof ActivationStatus['steps']; label: string }> = [
    { key: 'settings', label: t(locale, 'accountingActivation.stepSettings') },
    { key: 'contact', label: t(locale, 'accountingActivation.stepContact') },
    { key: 'example_invoice', label: t(locale, 'accountingActivation.stepExampleInvoice') },
  ];

  const showActivationCard = activation !== null && !activation.completed;

  return (
    <ModulePageShell
      title={t(locale, 'accountingModule.homeTitle')}
      subtitle={t(locale, 'accountingModule.homeSubtitle')}
      accentClassName="bg-gradient-to-br from-amber-100 via-white to-white"
    >
      {/* #5626 — Carte d'activation compacte — masquée une fois le module activé */}
      {showActivationCard && (
        <motion.section
          initial={{ opacity: 0, y: -8 }}
          animate={{ opacity: 1, y: 0 }}
          className="rounded-2xl border border-amber-200 bg-amber-50 p-5"
          aria-label={t(locale, 'accountingActivation.title')}
        >
          <div className="flex items-start gap-4">
            <span className="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-600">
              <Rocket className="h-5 w-5" />
            </span>
            <div className="flex-1">
              <p className="text-sm font-bold text-amber-900">
                {t(locale, 'accountingActivation.title')}
              </p>
              <ul className="mt-2 space-y-1">
                {activationSteps.map((step) => {
                  const done = activation?.steps[step.key] ?? false;
                  return (
                    <li key={step.key} className="flex items-center gap-2 text-xs text-amber-800">
                      {done ? (
                        <CheckCircle2 className="h-3.5 w-3.5 shrink-0 text-emerald-600" />
                      ) : (
                        <Circle className="h-3.5 w-3.5 shrink-0 text-amber-400" />
                      )}
                      <span className={done ? 'line-through opacity-60' : ''}>{step.label}</span>
                    </li>
                  );
                })}
              </ul>
            </div>
            <Link
              href="/accounting/activation"
              className="shrink-0 inline-flex items-center gap-1.5 rounded-xl bg-amber-500 px-4 py-2 text-xs font-bold text-white transition hover:bg-amber-600"
            >
              {t(locale, 'accountingActivation.activateButton')}
              <ArrowRight className="h-3 w-3" />
            </Link>
          </div>
        </motion.section>
      )}

      <section className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {tiles.map((tile, i) => (
          <motion.div key={tile.href} initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: i * 0.05 }}>
            <Link
              href={tile.href}
              className="group flex items-center gap-4 rounded-2xl border border-app-border bg-white p-5 shadow-sm transition hover:border-amber-200 hover:shadow-md"
            >
              <span className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-xl ${tile.accent}`}>
                <tile.icon className="h-5 w-5" />
              </span>
              <span className="flex-1 text-sm font-bold text-slate-800">{tile.label}</span>
              <ArrowRight className="h-4 w-4 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-amber-500" />
            </Link>
          </motion.div>
        ))}
      </section>
    </ModulePageShell>
  );
}
