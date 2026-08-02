'use client';

import { motion } from 'framer-motion';
import { Factory, Landmark, Plane, Radio, ShoppingBag, Truck, Cpu, HardHat } from 'lucide-react';
import type { AppLocale } from '@/lib/i18n';
import type { ComponentType } from 'react';

/**
 * PA2-MKT-011: this section previously listed 22 real, named, well-known
 * companies (Arcelik, Sonatrach, SAP, Aramco, Turkish Airlines, Emirates,
 * ...) under an implicit "they trust us" badge with zero authorization or
 * proof of any customer relationship â€” a reputational and potential
 * trademark-misuse risk, not just a marketing credibility gap (see
 * docs/PLAN_ACTION2/13_PLAN_ACTION_EN_VIGUEUR_2026-07-20.md section 2.7 and
 * the PA2-MKT-011 backlog entry).
 *
 * Per the ticket's own definition of done, since no real, authorized
 * customer list exists yet, this is requalified into "sectors/markets
 * addressed" (generic industry categories, no company names or logos) so
 * it can no longer be read as implying an existing customer relationship
 * with any real company.
 */
type Sector = {
  key: string;
  Icon: ComponentType<{ className?: string }>;
};

const sectors: Sector[] = [
  { key: 'industry', Icon: Factory },
  { key: 'energy', Icon: HardHat },
  { key: 'finance', Icon: Landmark },
  { key: 'aviation', Icon: Plane },
  { key: 'telecom', Icon: Radio },
  { key: 'retail', Icon: ShoppingBag },
  { key: 'logistics', Icon: Truck },
  { key: 'tech', Icon: Cpu },
];

type Copy = {
  title: string;
  subtitle: string;
  sectorLabels: Record<string, string>;
};

const copyByLocale: Record<AppLocale, Copy> = {
  fr: {
    title: 'Concu pour vos secteurs',
    subtitle: 'Leopardo RH s\u2019adresse aux entreprises de Turquie, d\u2019Afrique du Nord, d\u2019Europe, d\u2019Afrique et du Monde Arabe qui ont besoin de paie multi-pays et de pointage terrain fiable.',
    sectorLabels: {
      industry: 'Industrie',
      energy: 'Energie & BTP',
      finance: 'Finance & Services',
      aviation: 'Aviation & Transport',
      telecom: 'Telecoms',
      retail: 'Retail & Distribution',
      logistics: 'Logistique',
      tech: 'Tech & IT',
    },
  },
  en: {
    title: 'Built for your sector',
    subtitle: 'Leopardo RH serves companies across Turkey, North Africa, Europe, Africa and the Arab World that need multi-country payroll and reliable field attendance.',
    sectorLabels: {
      industry: 'Manufacturing',
      energy: 'Energy & Construction',
      finance: 'Finance & Services',
      aviation: 'Aviation & Transport',
      telecom: 'Telecom',
      retail: 'Retail & Distribution',
      logistics: 'Logistics',
      tech: 'Tech & IT',
    },
  },
  tr: {
    title: 'Sektorunuz icin tasarlandi',
    subtitle: 'Leopardo RH, cok ulkeli bordro ve guvenilir saha devam takibine ihtiyaci olan Turkiye, Kuzey Afrika, Avrupa, Afrika ve Arap Dunyasindaki sirketlere hizmet verir.',
    sectorLabels: {
      industry: 'Uretim',
      energy: 'Enerji & Insaat',
      finance: 'Finans & Hizmetler',
      aviation: 'Havacilik & Ulasim',
      telecom: 'Telekom',
      retail: 'Perakende & Dagitim',
      logistics: 'Lojistik',
      tech: 'Teknoloji & BT',
    },
  },
  ar: {
    title: 'Ù…ØµÙ…Ù… Ù„Ù‚Ø·Ø§Ø¹Ùƒ',
    subtitle: 'ÙŠØ®Ø¯Ù… Leopardo RH Ø§Ù„Ø´Ø±ÙƒØ§Øª ÙÙŠ ØªØ±ÙƒÙŠØ§ ÙˆØ´Ù…Ø§Ù„ Ø£ÙØ±ÙŠÙ‚ÙŠØ§ ÙˆØ£ÙˆØ±ÙˆØ¨Ø§ ÙˆØ£ÙØ±ÙŠÙ‚ÙŠØ§ ÙˆØ§Ù„Ø¹Ø§Ù„Ù… Ø§Ù„Ø¹Ø±Ø¨ÙŠ Ø§Ù„ØªÙŠ ØªØ­ØªØ§Ø¬ Ø¥Ù„Ù‰ Ø±ÙˆØ§ØªØ¨ Ù…ØªØ¹Ø¯Ø¯Ø© Ø§Ù„Ø¨Ù„Ø¯Ø§Ù† ÙˆØ­Ø¶ÙˆØ± Ù…ÙŠØ¯Ø§Ù†ÙŠ Ù…ÙˆØ«ÙˆÙ‚.',
    sectorLabels: {
      industry: 'Ø§Ù„ØµÙ†Ø§Ø¹Ø©',
      energy: 'Ø§Ù„Ø·Ø§Ù‚Ø© ÙˆØ§Ù„Ø¨Ù†Ø§Ø¡',
      finance: 'Ø§Ù„Ù…Ø§Ù„ÙŠØ© ÙˆØ§Ù„Ø®Ø¯Ù…Ø§Øª',
      aviation: 'Ø§Ù„Ø·ÙŠØ±Ø§Ù† ÙˆØ§Ù„Ù†Ù‚Ù„',
      telecom: 'Ø§Ù„Ø§ØªØµØ§Ù„Ø§Øª',
      retail: 'Ø§Ù„ØªØ¬Ø²Ø¦Ø© ÙˆØ§Ù„ØªÙˆØ²ÙŠØ¹',
      logistics: 'Ø§Ù„Ù„ÙˆØ¬Ø³ØªÙŠØ§Øª',
      tech: 'Ø§Ù„ØªÙƒÙ†ÙˆÙ„ÙˆØ¬ÙŠØ§',
    },
  },
};

function SectorCard({ sector, label }: { sector: Sector; label: string }) {
  const { Icon } = sector;
  return (
    <div className="flex-shrink-0 mx-3">
      <div className="flex items-center gap-3 px-5 py-3 rounded-xl border bg-gradient-to-br from-emerald-500/10 to-cyan-500/5 border-emerald-500/20 backdrop-blur-sm hover:scale-105 transition-transform duration-300">
        <div className="w-10 h-10 rounded-lg bg-white/80 dark:bg-white/10 flex items-center justify-center shadow-sm">
          <Icon className="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
        </div>
        <span className="text-sm font-semibold text-slate-800 dark:text-slate-200 whitespace-nowrap">
          {label}
        </span>
      </div>
    </div>
  );
}

export interface TrustedBrandsProps {
  locale?: AppLocale;
}

export function TrustedBrands({ locale = 'fr' }: TrustedBrandsProps) {
  const copy = copyByLocale[locale] ?? copyByLocale.fr;
  const duplicated = [...sectors, ...sectors];

  return (
    <section className="relative py-16 overflow-hidden bg-transparent dark:bg-slate-900/50">
      <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 mb-10">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: 0.6 }}
          className="text-center"
        >
          <h2 className="text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white mb-3">
            {copy.title}
          </h2>
          <p className="text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">
            {copy.subtitle}
          </p>
        </motion.div>
      </div>

      <div className="relative">
        <div className="absolute left-0 top-0 bottom-0 w-24 bg-gradient-to-r from-slate-50 dark:from-slate-900/50 to-transparent z-10 pointer-events-none" />
        <div className="absolute right-0 top-0 bottom-0 w-24 bg-gradient-to-l from-slate-50 dark:from-slate-900/50 to-transparent z-10 pointer-events-none" />

        <div className="flex marquee-scroll">
          {duplicated.map((sector, idx) => (
            <SectorCard key={`${sector.key}-${idx}`} sector={sector} label={copy.sectorLabels[sector.key] ?? sector.key} />
          ))}
        </div>
      </div>

      <style jsx>{`
        .marquee-scroll {
          display: flex;
          width: max-content;
          animation: marquee-rtl 60s linear infinite;
        }
        @keyframes marquee-rtl {
          0% {
            transform: translateX(0);
          }
          100% {
            transform: translateX(-50%);
          }
        }
        .marquee-scroll:hover {
          animation-play-state: paused;
        }
      `}</style>
    </section>
  );
}

