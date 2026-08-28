'use client';

import { motion } from 'framer-motion';
import Link from 'next/link';
import {
  Building2,
  Users,
  Magnet,
  KanbanSquare,
  ArrowRight,
  ShieldCheck,
} from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

/**
 * #5715 — CRM Client : hub de navigation de l'espace client (tenant).
 *
 * Le CRM commercial Leopardo reste dans l'admin plateforme
 * (`/crm/pipeline` de admin-dashboard, PlatformCrmPipelineController) —
 * cet écran n'y fait JAMAIS appel (ADR-CRM-DUAL-CONTEXTS). Les données
 * viennent exclusivement de l'API tenant `/api/v1/crm/*` (#5712).
 */
export default function CrmHomePage() {
  const locale = getPreferredLocale();

  const tiles = [
    {
      href: '/crm/accounts',
      icon: Building2,
      title: t(locale, 'crm.accounts.title'),
      description: t(locale, 'crm.accounts.description'),
      accent: 'from-emerald-500 to-teal-600',
    },
    {
      href: '/crm/contacts',
      icon: Users,
      title: t(locale, 'crm.contacts.title'),
      description: t(locale, 'crm.contacts.description'),
      accent: 'from-cyan-500 to-blue-600',
    },
    {
      href: '/crm/leads',
      icon: Magnet,
      title: t(locale, 'crm.leads.title'),
      description: t(locale, 'crm.leads.description'),
      accent: 'from-amber-500 to-orange-600',
    },
    {
      href: '/crm/pipeline',
      icon: KanbanSquare,
      title: t(locale, 'crm.pipeline.title'),
      description: t(locale, 'crm.pipeline.description'),
      accent: 'from-violet-500 to-purple-600',
    },
  ];

  return (
    <ModulePageShell
      title={t(locale, 'crm.title')}
      subtitle={t(locale, 'crm.subtitle')}
      accentClassName="from-emerald-500/10 via-white/40 to-cyan-500/10"
    >
      <div className="grid gap-4 sm:grid-cols-2">
        {tiles.map((tile) => {
          const Icon = tile.icon;

          return (
            <Link
              key={tile.href}
              href={tile.href}
              className="group relative overflow-hidden rounded-3xl border border-white/20 bg-white/70 p-6 shadow-premium backdrop-blur-xl transition-transform hover:-translate-y-0.5"
            >
              <div className={`absolute inset-0 bg-gradient-to-br ${tile.accent} opacity-[0.04] transition-opacity group-hover:opacity-[0.08]`} />
              <div className="relative flex items-start justify-between">
                <div className={`flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br ${tile.accent} text-white shadow-lg`}>
                  <Icon className="h-6 w-6" />
                </div>
                <ArrowRight className="h-5 w-5 text-slate-300 transition-transform group-hover:translate-x-1 group-hover:text-slate-500" />
              </div>
              <div className="relative mt-4">
                <h2 className="text-lg font-black text-slate-900">{tile.title}</h2>
                <p className="mt-1 text-sm leading-relaxed text-slate-600">{tile.description}</p>
              </div>
            </Link>
          );
        })}
      </div>

      <motion.div
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        transition={{ delay: 0.3 }}
        className="flex items-center gap-3 rounded-2xl border border-emerald-100 bg-emerald-50/60 px-5 py-4 text-sm text-emerald-800"
      >
        <ShieldCheck className="h-5 w-5 shrink-0 text-emerald-600" />
        <p>{t(locale, 'crm.isolationNote')}</p>
      </motion.div>
    </ModulePageShell>
  );
}
