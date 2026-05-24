'use client';

import { motion } from 'framer-motion';
import type { AppLocale } from '@/lib/i18n';

type Brand = {
  name: string;
  logo: string;
  region: string;
};

const brands: Brand[] = [
  // Turkey
  { name: 'Arcelik', logo: 'Arcelik', region: 'Turquie' },
  { name: 'Vestel', logo: 'Vestel', region: 'Turquie' },
  { name: 'Beko', logo: 'Beko', region: 'Turquie' },
  { name: 'Turkish Airlines', logo: 'THY', region: 'Turquie' },
  { name: 'Koton', logo: 'Koton', region: 'Turquie' },
  // North Africa
  { name: 'Cevital', logo: 'Cevital', region: 'Algerie' },
  { name: 'Sonatrach', logo: 'Sonatrach', region: 'Algerie' },
  { name: 'Condor', logo: 'Condor', region: 'Algerie' },
  { name: 'Marjane', logo: 'Marjane', region: 'Maroc' },
  { name: 'Inwi', logo: 'Inwi', region: 'Maroc' },
  { name: 'Poulina', logo: 'Poulina', region: 'Tunisie' },
  // Europe
  { name: 'SAP', logo: 'SAP', region: 'Europe' },
  { name: 'Sage', logo: 'Sage', region: 'Europe' },
  { name: 'OVHcloud', logo: 'OVHcloud', region: 'Europe' },
  { name: 'Dassault', logo: 'Dassault', region: 'Europe' },
  // Africa
  { name: 'Dangote', logo: 'Dangote', region: 'Afrique' },
  { name: 'MTN', logo: 'MTN', region: 'Afrique' },
  { name: 'Ecobank', logo: 'Ecobank', region: 'Afrique' },
  // Arab World
  { name: 'Aramco', logo: 'Aramco', region: 'Monde Arabe' },
  { name: 'Emirates', logo: 'Emirates', region: 'Monde Arabe' },
  { name: 'Etisalat', logo: 'Etisalat', region: 'Monde Arabe' },
  { name: 'STC', logo: 'STC', region: 'Monde Arabe' },
];

const headingByLocale: Record<AppLocale, { title: string; subtitle: string }> = {
  fr: {
    title: 'Ils nous font confiance',
    subtitle: 'Des entreprises leaders dans leurs domaines en Turquie, Afrique du Nord, Europe, Afrique et Monde Arabe.',
  },
  en: {
    title: 'They trust us',
    subtitle: 'Leading companies across Turkey, North Africa, Europe, Africa and the Arab World.',
  },
  tr: {
    title: 'Bize guveniyorlar',
    subtitle: 'Turkiye, Kuzey Afrika, Avrupa, Afrika ve Arap Dunyasinda lider sirketler.',
  },
  ar: {
    title: 'يثقون بنا',
    subtitle: 'شركات رائدة في تركيا وشمال أفريقيا وأوروبا وأفريقيا والعالم العربي.',
  },
};

function BrandLogo({ brand }: { brand: Brand }) {
  const colors: Record<string, string> = {
    Turquie: 'from-red-500/20 to-red-600/10 border-red-500/20',
    Algerie: 'from-emerald-500/20 to-emerald-600/10 border-emerald-500/20',
    Maroc: 'from-red-500/20 to-amber-500/10 border-red-500/20',
    Tunisie: 'from-red-500/20 to-red-600/10 border-red-500/20',
    Europe: 'from-blue-500/20 to-blue-600/10 border-blue-500/20',
    Afrique: 'from-amber-500/20 to-amber-600/10 border-amber-500/20',
    'Monde Arabe': 'from-emerald-500/20 to-emerald-600/10 border-emerald-500/20',
  };

  const textColors: Record<string, string> = {
    Turquie: 'text-red-600 dark:text-red-400',
    Algerie: 'text-emerald-600 dark:text-emerald-400',
    Maroc: 'text-red-600 dark:text-red-400',
    Tunisie: 'text-red-600 dark:text-red-400',
    Europe: 'text-blue-600 dark:text-blue-400',
    Afrique: 'text-amber-600 dark:text-amber-400',
    'Monde Arabe': 'text-emerald-600 dark:text-emerald-400',
  };

  return (
    <div className="flex-shrink-0 mx-3">
      <div
        className={`flex items-center gap-3 px-5 py-3 rounded-xl border bg-gradient-to-br ${colors[brand.region] ?? 'from-gray-500/20 to-gray-600/10 border-gray-500/20'} backdrop-blur-sm hover:scale-105 transition-transform duration-300`}
      >
        <div className="w-10 h-10 rounded-lg bg-white/80 dark:bg-white/10 flex items-center justify-center shadow-sm">
          <span className={`text-sm font-bold ${textColors[brand.region] ?? 'text-gray-600 dark:text-gray-400'}`}>
            {brand.logo.slice(0, 2).toUpperCase()}
          </span>
        </div>
        <div className="flex flex-col">
          <span className="text-sm font-semibold text-slate-800 dark:text-slate-200 whitespace-nowrap">
            {brand.name}
          </span>
          <span className="text-[10px] text-slate-500 dark:text-slate-400 whitespace-nowrap">
            {brand.region}
          </span>
        </div>
      </div>
    </div>
  );
}

export interface TrustedBrandsProps {
  locale?: AppLocale;
}

export function TrustedBrands({ locale = 'fr' }: TrustedBrandsProps) {
  const heading = headingByLocale[locale] ?? headingByLocale.fr;
  const duplicated = [...brands, ...brands];

  return (
    <section className="relative py-16 overflow-hidden bg-slate-50 dark:bg-slate-900/50">
      <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 mb-10">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: 0.6 }}
          className="text-center"
        >
          <h2 className="text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white mb-3">
            {heading.title}
          </h2>
          <p className="text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">
            {heading.subtitle}
          </p>
        </motion.div>
      </div>

      <div className="relative">
        <div className="absolute left-0 top-0 bottom-0 w-24 bg-gradient-to-r from-slate-50 dark:from-slate-900/50 to-transparent z-10 pointer-events-none" />
        <div className="absolute right-0 top-0 bottom-0 w-24 bg-gradient-to-l from-slate-50 dark:from-slate-900/50 to-transparent z-10 pointer-events-none" />

        <div className="flex marquee-scroll">
          {duplicated.map((brand, idx) => (
            <BrandLogo key={`${brand.name}-${idx}`} brand={brand} />
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
