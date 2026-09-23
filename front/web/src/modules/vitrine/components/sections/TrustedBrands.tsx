'use client';

import { motion } from 'framer-motion';
import type { AppLocale } from '@/lib/i18n';
import type { ComponentType } from 'react';

/**
 * Relecture propriétaire (2026-09-21) : « la preuve n'est pas assez vivante ;
 * mettre avion et autres icônes littérales risque de confondre le lecteur —
 * mets plutôt des trucs symboliques ».
 *
 * Les icônes LITTÉRALES par métier (avion, usine, camion, panier, carte…)
 * laissent place à des **glyphes symboliques** : géométrie abstraite (orbe,
 * treillis, arcs, nœuds, flux, hexagone, paliers). Aucune image d'objet ne peut
 * donc plus être lue comme un client ou un secteur « déjà servi » — le risque
 * de confusion qui motivait PA2-MKT-011. Le défilement est conservé (vivant) et
 * complété par une respiration douce, coupée si `prefers-reduced-motion`.
 */
function IconLattice({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" className={className} aria-hidden="true">
      <rect x="4" y="4" width="16" height="16" rx="2" />
      <path d="M4 12h16M12 4v16" />
      <circle cx="12" cy="12" r="2" fill="currentColor" stroke="none" />
    </svg>
  );
}

function IconHex({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" className={className} aria-hidden="true">
      <path d="M12 3l7.5 4.3v9L12 20.6 4.5 16.3v-9L12 3z" />
      <path d="M12 8.2l4.2 2.4v4.8L12 17.8 7.8 15.4v-4.8L12 8.2z" opacity="0.7" />
    </svg>
  );
}

function IconTiers({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" className={className} aria-hidden="true">
      <path d="M12 3l8 4.5-8 4.5-8-4.5L12 3z" />
      <path d="M4 12l8 4.5 8-4.5" opacity="0.75" />
      <path d="M4 16.5L12 21l8-4.5" opacity="0.45" />
    </svg>
  );
}

function IconOrbit({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" className={className} aria-hidden="true">
      <circle cx="12" cy="12" r="3.2" />
      <ellipse cx="12" cy="12" rx="9" ry="4.2" />
      <circle cx="21" cy="12" r="1.4" fill="currentColor" stroke="none" />
    </svg>
  );
}

function IconPulse({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" className={className} aria-hidden="true">
      <path d="M2 12h4l2.5-6 3 12 2.5-6h8" />
    </svg>
  );
}

function IconNetwork({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" className={className} aria-hidden="true">
      <circle cx="6" cy="7" r="2.2" />
      <circle cx="18" cy="7" r="2.2" />
      <circle cx="12" cy="18" r="2.2" />
      <path d="M7.8 8.4l3.4 7.6M16.2 8.4L12.8 16M8 7h8" opacity="0.8" />
    </svg>
  );
}

function IconFlow({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" className={className} aria-hidden="true">
      <path d="M3 16c4 0 4-8 8-8s4 8 8 8" />
      <circle cx="3" cy="16" r="1.4" fill="currentColor" stroke="none" />
      <circle cx="11" cy="8" r="1.4" fill="currentColor" stroke="none" />
      <circle cx="19" cy="16" r="1.4" fill="currentColor" stroke="none" />
    </svg>
  );
}

function IconWaves({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" className={className} aria-hidden="true">
      <path d="M12 20a8 8 0 0 0 0-16" />
      <path d="M12 16a4 4 0 0 0 0-8" />
      <circle cx="12" cy="12" r="1.5" fill="currentColor" stroke="none" />
      <path d="M12 2v2M12 20v2" opacity="0.6" />
    </svg>
  );
}

/**
 * PA2-MKT-011: this section previously listed 22 real, named, well-known
 * companies (Arcelik, Sonatrach, SAP, Aramco, Turkish Airlines, Emirates,
 * ...) under an implicit "they trust us" badge with zero authorization or
 * proof of any customer relationship — a reputational and potential
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

/** Secteurs / marchés ADRESSÉS — jamais des références clients (PA2-MKT-011). */

const sectors: Sector[] = [
  { key: 'industry', Icon: IconLattice },
  { key: 'energy', Icon: IconHex },
  { key: 'finance', Icon: IconTiers },
  { key: 'aviation', Icon: IconOrbit },
  { key: 'telecom', Icon: IconPulse },
  { key: 'retail', Icon: IconNetwork },
  { key: 'logistics', Icon: IconFlow },
  { key: 'tech', Icon: IconWaves },
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
    title: 'مصمم لقطاعك',
    subtitle: 'يخدم Leopardo RH الشركات في تركيا وشمال أفريقيا وأوروبا وأفريقيا والعالم العربي التي تحتاج إلى رواتب متعددة البلدان وحضور ميداني موثوق.',
    sectorLabels: {
      industry: 'الصناعة',
      energy: 'الطاقة والبناء',
      finance: 'المالية والخدمات',
      aviation: 'الطيران والنقل',
      telecom: 'الاتصالات',
      retail: 'التجزئة والتوزيع',
      logistics: 'اللوجستيات',
      tech: 'التكنولوجيا',
    },
  },
};

function SectorCard({ sector, label }: { sector: Sector; label: string }) {
  const { Icon } = sector;
  return (
    <div className="flex-shrink-0 mx-3">
      <div className="flex items-center gap-3 px-5 py-3 rounded-xl border bg-gradient-to-br from-emerald-500/10 to-cyan-500/5 border-emerald-500/20 backdrop-blur-sm hover:scale-105 transition-transform duration-300">
        <div className="w-10 h-10 rounded-lg bg-white/80 dark:bg-white/10 flex items-center justify-center shadow-sm">
          <Icon className="w-5 h-5 text-emerald-700 dark:text-emerald-400 symbol-breathe" />
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
          initial={{ y: 20 }}
          whileInView={{ y: 0 }}
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
        .symbol-breathe {
          animation: symbol-breathe 4.5s ease-in-out infinite;
        }
        @keyframes symbol-breathe {
          0%,
          100% {
            transform: scale(1) rotate(0deg);
            opacity: 0.9;
          }
          50% {
            transform: scale(1.08) rotate(3deg);
            opacity: 1;
          }
        }
        @media (prefers-reduced-motion: reduce) {
          .marquee-scroll,
          .symbol-breathe {
            animation: none;
          }
        }
      `}</style>
    </section>
  );
}

