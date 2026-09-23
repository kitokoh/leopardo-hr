'use client';

import { motion } from 'framer-motion';
import Link from 'next/link';
import { ArrowRight, Fuel, GraduationCap, Plane, UtensilsCrossed } from 'lucide-react';
import type { AppLocale } from '@/lib/i18n';

/**
 * #8072 — les verticales (restaurants, voyages, écoles, stations-service)
 * étaient reléguées dans un ticker illisible de la section 3D. Les voici en
 * cartes cliquables avec un bénéfice concret chacune (pattern benchmark
 * Connecteam/Workpay). /restaurateur existait sans être liée nulle part :
 * elle reçoit désormais du trafic interne depuis la home.
 */

type VerticalCard = {
  icon: React.ReactNode;
  name: string;
  benefit: string;
  href: string;
  linkLabel: string;
};

type Copy = {
  badge: string;
  title: string;
  subtitle: string;
  cards: VerticalCard[];
};

const iconClass = 'w-6 h-6';

const copyByLocale: Record<AppLocale, Copy> = {
  fr: {
    badge: 'Verticales métier',
    title: 'Une plateforme, vos métiers',
    subtitle:
      'Chaque verticale se branche sur le socle commun — pointage, paie, RH, compta — avec ses modules dédiés.',
    cards: [
      {
        icon: <UtensilsCrossed className={iconClass} />,
        name: 'Restaurants',
        benefit: 'Pointage en salle comme en cuisine, planning et paie sans ressaisie.',
        href: '/restaurateur',
        linkLabel: 'Voir la page restaurateurs',
      },
      {
        icon: <Plane className={iconClass} />,
        name: 'Agences de voyage',
        benefit: 'Dossiers clients, commissions et paie des équipes au même endroit.',
        href: '/case-studies',
        linkLabel: 'Voir les cas par métier',
      },
      {
        icon: <GraduationCap className={iconClass} />,
        name: 'Écoles & formation',
        benefit: 'Présence du personnel, absences et paie alignées sur le calendrier scolaire.',
        href: '/case-studies',
        linkLabel: 'Voir les cas par métier',
      },
      {
        icon: <Fuel className={iconClass} />,
        name: 'Stations-service',
        benefit: 'Équipes tournantes pointées sur site, heures et majorations vers la paie.',
        href: '/case-studies',
        linkLabel: 'Voir les cas par métier',
      },
    ],
  },
  en: {
    badge: 'Industry verticals',
    title: 'One platform, your industries',
    subtitle:
      'Each vertical plugs into the shared foundation — attendance, payroll, HR, accounting — with its own dedicated modules.',
    cards: [
      {
        icon: <UtensilsCrossed className={iconClass} />,
        name: 'Restaurants',
        benefit: 'Clock-ins in the dining room and kitchen alike, scheduling and payroll without re-entry.',
        href: '/restaurateur',
        linkLabel: 'See the restaurants page',
      },
      {
        icon: <Plane className={iconClass} />,
        name: 'Travel agencies',
        benefit: 'Client files, commissions and team payroll in one place.',
        href: '/case-studies',
        linkLabel: 'See case studies by industry',
      },
      {
        icon: <GraduationCap className={iconClass} />,
        name: 'Schools & training',
        benefit: 'Staff attendance, leave and payroll aligned with the school calendar.',
        href: '/case-studies',
        linkLabel: 'See case studies by industry',
      },
      {
        icon: <Fuel className={iconClass} />,
        name: 'Fuel stations',
        benefit: 'Rotating crews clocked on site, hours and overtime straight to payroll.',
        href: '/case-studies',
        linkLabel: 'See case studies by industry',
      },
    ],
  },
  tr: {
    badge: 'Sektör çözümleri',
    title: 'Tek platform, sizin sektörleriniz',
    subtitle:
      'Her dikey çözüm ortak temele — yoklama, bordro, İK, muhasebe — kendi modülleriyle bağlanır.',
    cards: [
      {
        icon: <UtensilsCrossed className={iconClass} />,
        name: 'Restoranlar',
        benefit: 'Salonda da mutfakta da yoklama, planlama ve bordro yeniden veri girişi olmadan.',
        href: '/restaurateur',
        linkLabel: 'Restoran sayfasını görün',
      },
      {
        icon: <Plane className={iconClass} />,
        name: 'Seyahat acenteleri',
        benefit: 'Müşteri dosyaları, komisyonlar ve ekip bordrosu tek yerde.',
        href: '/case-studies',
        linkLabel: 'Sektöre göre vakaları görün',
      },
      {
        icon: <GraduationCap className={iconClass} />,
        name: 'Okullar & eğitim',
        benefit: 'Personel yoklaması, izinler ve bordro okul takvimiyle uyumlu.',
        href: '/case-studies',
        linkLabel: 'Sektöre göre vakaları görün',
      },
      {
        icon: <Fuel className={iconClass} />,
        name: 'Akaryakıt istasyonları',
        benefit: 'Vardiyalı ekipler sahada yoklanır, saatler ve fazla mesai bordroya akar.',
        href: '/case-studies',
        linkLabel: 'Sektöre göre vakaları görün',
      },
    ],
  },
  ar: {
    badge: 'حلول حسب القطاع',
    title: 'منصة واحدة، قطاعاتكم',
    subtitle:
      'كل حل قطاعي يتصل بالأساس المشترك — الحضور، الرواتب، الموارد البشرية، المحاسبة — مع وحداته الخاصة.',
    cards: [
      {
        icon: <UtensilsCrossed className={iconClass} />,
        name: 'المطاعم',
        benefit: 'تسجيل الحضور في القاعة كما في المطبخ، وجدولة ورواتب بلا إعادة إدخال.',
        href: '/restaurateur',
        linkLabel: 'شاهد صفحة المطاعم',
      },
      {
        icon: <Plane className={iconClass} />,
        name: 'وكالات السفر',
        benefit: 'ملفات العملاء والعمولات ورواتب الفرق في مكان واحد.',
        href: '/case-studies',
        linkLabel: 'شاهد الحالات حسب القطاع',
      },
      {
        icon: <GraduationCap className={iconClass} />,
        name: 'المدارس والتكوين',
        benefit: 'حضور الموظفين والغيابات والرواتب بما يناسب التقويم المدرسي.',
        href: '/case-studies',
        linkLabel: 'شاهد الحالات حسب القطاع',
      },
      {
        icon: <Fuel className={iconClass} />,
        name: 'محطات الوقود',
        benefit: 'فرق المناوبة تسجل حضورها في الموقع، والساعات والإضافي تذهب للرواتب.',
        href: '/case-studies',
        linkLabel: 'شاهد الحالات حسب القطاع',
      },
    ],
  },
};

export interface VerticalsSectionProps {
  locale?: AppLocale;
}

export function VerticalsSection({ locale = 'fr' }: VerticalsSectionProps) {
  const copy = copyByLocale[locale] ?? copyByLocale.fr;

  return (
    <section
      aria-labelledby="verticals-title"
      className="relative py-24 overflow-hidden bg-white dark:bg-slate-950"
    >
      <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <motion.div
          initial={{ y: 20 }}
          whileInView={{ y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: 0.6 }}
          className="text-center mb-14"
        >
          <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-emerald-500/[0.08] border border-emerald-500/15 text-emerald-700 dark:text-emerald-400 text-sm font-semibold mb-6">
            <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" />
            {copy.badge}
          </div>
          <h2
            id="verticals-title"
            className="text-3xl sm:text-4xl lg:text-5xl font-black text-slate-900 dark:text-white mb-4 tracking-tight"
          >
            {copy.title}
          </h2>
          <p className="text-lg text-slate-600 dark:text-slate-400 max-w-3xl mx-auto">
            {copy.subtitle}
          </p>
        </motion.div>

        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          {copy.cards.map((card, index) => (
            <motion.div
              key={card.name}
              initial={{ y: 30, opacity: 0 }}
              whileInView={{ y: 0, opacity: 1 }}
              viewport={{ once: true }}
              transition={{ duration: 0.5, delay: index * 0.08 }}
            >
              <Link
                href={card.href}
                aria-label={`${card.name} — ${card.linkLabel}`}
                className="group flex h-full flex-col rounded-2xl border border-slate-200/80 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-900/60 p-6 transition-all duration-300 hover:border-emerald-300 dark:hover:border-emerald-700 hover:shadow-lg"
              >
                <div className="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-emerald-500/10 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 mb-4">
                  {card.icon}
                </div>
                <h3 className="text-lg font-bold text-slate-900 dark:text-white mb-2">
                  {card.name}
                </h3>
                <p className="text-sm text-slate-600 dark:text-slate-400 mb-4 flex-1">
                  {card.benefit}
                </p>
                <span className="inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-600 dark:text-emerald-400">
                  {card.linkLabel}
                  <ArrowRight
                    className="w-4 h-4 transition-transform group-hover:translate-x-1"
                    aria-hidden="true"
                  />
                </span>
              </Link>
            </motion.div>
          ))}
        </div>
      </div>
    </section>
  );
}

export default VerticalsSection;
