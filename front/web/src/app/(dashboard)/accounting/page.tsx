'use client';

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
} from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import { t } from '@/lib/i18n/locale-catalog';
import { getPreferredLocale } from '@/lib/i18n';

/**
 * #5534 — Module Comptabilité : accueil du rôle comptable/principal.
 * Hub de navigation vers les 7 écrans du module (backend #5422 livré).
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
