'use client';

import { useEffect, useState } from 'react';
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
} from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import { apiFetch } from '@/lib/api-client';
import { t } from '@/lib/i18n/locale-catalog';
import { getPreferredLocale } from '@/lib/i18n';
import { AccountingActivationBanner } from './AccountingActivationBanner';

/**
 * #5534 — Module Comptabilité : accueil du rôle comptable/principal.
 * Hub de navigation vers les 7 écrans du module (backend #5422 livré).
 * #5626 — Bandeau d'activation visible si le module n'est pas encore configuré.
 */
export default function AccountingHomePage() {
  const locale = getPreferredLocale();

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

  return (
    <ModulePageShell
      title={t(locale, 'accountingModule.homeTitle')}
      subtitle={t(locale, 'accountingModule.homeSubtitle')}
      accentClassName="bg-gradient-to-br from-amber-100 via-white to-white"
    >
      {/* #5626 : bandeau d'activation si le module n'est pas encore configuré */}
      <AccountingActivationBanner />
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

      <ActivationCta />
    </ModulePageShell>
  );
}

/**
 * #5626 — Carte « Démarrer la comptabilité » visible tant que le module
 * n'est pas activé (GET /accounting/activation). Disparaît quand
 * `completed=true` ; silencieuse si l'état est injoignable.
 */
function ActivationCta() {
  const locale = getPreferredLocale();
  const [notActivated, setNotActivated] = useState(false);

  useEffect(() => {
    let cancelled = false;
    apiFetch('/accounting/activation')
      .then(async (res) => {
        if (cancelled) return;
        const body = await res.json();
        setNotActivated(body?.data?.completed === false);
      })
      .catch(() => {
        if (!cancelled) setNotActivated(false);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  if (!notActivated) return null;

  return (
    <motion.aside
      initial={{ opacity: 0, y: 8 }}
      animate={{ opacity: 1, y: 0 }}
      className="mt-6 flex flex-col gap-4 rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 via-white to-white p-5 sm:flex-row sm:items-center"
    >
      <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-600">
        <Rocket className="h-5 w-5" />
      </div>
      <div className="min-w-0 flex-1">
        <h3 className="text-sm font-black text-slate-950">{t(locale, 'accountingActivation.hubCtaTitle')}</h3>
        <p className="mt-0.5 text-sm text-slate-500">{t(locale, 'accountingActivation.hubCtaBody')}</p>
      </div>
      <Link
        href="/accounting/activation"
        className="inline-flex shrink-0 items-center gap-2 rounded-xl bg-amber-500 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-amber-600"
      >
        {t(locale, 'accountingActivation.hubCtaButton')}
        <ArrowRight className="h-4 w-4" />
      </Link>
    </motion.aside>
  );
}
