'use client';

import { motion } from 'framer-motion';
import { MapPin, Users, ArrowRight } from 'lucide-react';
import type { AppLocale } from '@/lib/i18n';
import { getIllustrativeExampleLabel } from '@/modules/vitrine/lib/vitrine-locale';

/**
 * #3246 — preuve sociale honnête. Aucun client payant à ce jour
 * (PILOTAGE.md « Clients payants | 0 ») : ces cas ne décrivent PAS des
 * clients réels mais des profils types illustratifs. Chaque carte porte un
 * badge « Exemple illustratif » et les métriques chiffrées inventées
 * (-40 % temps admin, +60 % remplissage, -35 % absentéisme) ont été
 * retirées des résultats — seuls les scénarios d'usage restent décrits.
 */
type MiniCase = {
  company: string;
  country: string;
  employees: string;
  challenge: string;
  result: string;
  flag: string;
};

const casesByLocale: Record<AppLocale, { badge: string; title: string; titleHighlight: string; cases: MiniCase[] }> = {
  fr: {
    badge: 'Profils types',
    title: 'Des cas d\'usage',
    titleHighlight: 'typiques',
    cases: [
      {
        company: 'IT & Services — profil type',
        country: 'Algerie',
        employees: '350',
        challenge: 'Pointage papier sur 3 sites, paie manuelle multi-bureaux',
        result: 'Paie automatisee, pointage biometrique ZKTeco',
        flag: '🇩🇿',
      },
      {
        company: 'Marketing Digital — profil type',
        country: 'Maroc',
        employees: '120',
        challenge: 'Recrutement eparpille, pas de suivi integre',
        result: 'Pipeline kanban, onboarding automatise',
        flag: '🇲🇦',
      },
      {
        company: 'Transport & Logistique — profil type',
        country: 'Senegal',
        employees: '200',
        challenge: 'Equipes terrain sans visibilite, pointage impossible hors connexion',
        result: 'Mode offline mobile, synchro auto',
        flag: '🇸🇳',
      },
    ],
  },
  en: {
    badge: 'Use cases',
    title: 'Typical',
    titleHighlight: 'use cases',
    cases: [
      {
        company: 'IT & Services — example profile',
        country: 'Algeria',
        employees: '350',
        challenge: 'Paper-based attendance across 3 sites, manual multi-office payroll',
        result: 'Automated payroll, ZKTeco biometric attendance',
        flag: '🇩🇿',
      },
      {
        company: 'Digital Marketing — example profile',
        country: 'Morocco',
        employees: '120',
        challenge: 'Scattered recruitment, no integrated tracking',
        result: 'Kanban pipeline, automated onboarding',
        flag: '🇲🇦',
      },
      {
        company: 'Transport & Logistics — example profile',
        country: 'Senegal',
        employees: '200',
        challenge: 'Field teams with no visibility, impossible offline attendance',
        result: 'Offline mobile mode, auto sync',
        flag: '🇸🇳',
      },
    ],
  },
  tr: {
    badge: 'Kullanım senaryoları',
    title: 'Ornek',
    titleHighlight: 'kullanim senaryolari',
    cases: [
      {
        company: 'BT Hizmetleri — ornek profil',
        country: 'Cezayir',
        employees: '350',
        challenge: '3 sahada kagit devam takibi, manuel bordro',
        result: 'Otomatik bordro, ZKTeco biyometrik devam takibi',
        flag: '🇩🇿',
      },
      {
        company: 'Dijital Pazarlama — ornek profil',
        country: 'Fas',
        employees: '120',
        challenge: 'Daginis ise alim, entegre takip yok',
        result: 'Kanban boru hatti, otomatik ise alim',
        flag: '🇲🇦',
      },
      {
        company: 'Tasimacilik — ornek profil',
        country: 'Senegal',
        employees: '200',
        challenge: 'Saha ekipleri gorunurluk yok, cevrimdisi devam imkansiz',
        result: 'Cevrimdisi mobil mod, otomatik esitleme',
        flag: '🇸🇳',
      },
    ],
  },
  ar: {
    badge: 'سيناريوهات الاستخدام',
    title: 'حالات استخدام',
    titleHighlight: 'نموذجية',
    cases: [
      {
        company: 'تكنولوجيا المعلومات — نموذج توضيحي',
        country: 'الجزائر',
        employees: '350',
        challenge: 'حضور ورقي عبر 3 مواقع، رواتب يدوية',
        result: 'رواتب آلية، حضور بيومتري عبر ZKTeco',
        flag: '🇩🇿',
      },
      {
        company: 'التسويق الرقمي — نموذج توضيحي',
        country: 'المغرب',
        employees: '120',
        challenge: 'توظيف مبعثر، بدون تتبع متكامل',
        result: 'لوحة كانبان، تأهيل آلي',
        flag: '🇲🇦',
      },
      {
        company: 'النقل واللوجستيك — نموذج توضيحي',
        country: 'السنغال',
        employees: '200',
        challenge: 'فرق ميدانية بدون رؤية، حضور مستحيل بدون اتصال',
        result: 'وضع دون اتصال، مزامنة تلقائية',
        flag: '🇸🇳',
      },
    ],
  },
};

export interface MiniCaseStudiesProps {
  locale?: AppLocale;
}

export function MiniCaseStudies({ locale = 'fr' }: MiniCaseStudiesProps) {
  const data = casesByLocale[locale] ?? casesByLocale.fr;
  const exampleLabel = getIllustrativeExampleLabel(locale);

  return (
    <section className="relative py-24 overflow-hidden">
      <div className="absolute inset-0 bg-gradient-to-b from-slate-50 via-white to-slate-50 dark:from-slate-900 dark:via-slate-950 dark:to-slate-900" />

      <div className="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: 0.6 }}
          className="text-center mb-16"
        >
          <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-blue-500/[0.08] border border-blue-500/15 text-blue-700 dark:text-blue-400 text-sm font-semibold mb-6">
            <span className="w-1.5 h-1.5 rounded-full bg-blue-500 animate-pulse" />
            {data.badge}
          </div>
          <h2 className="text-3xl sm:text-4xl lg:text-5xl font-black text-slate-900 dark:text-white tracking-tight">
            {data.title}{' '}
            <span className="bg-gradient-to-r from-blue-500 to-indigo-500 bg-clip-text text-transparent">
              {data.titleHighlight}
            </span>
          </h2>
        </motion.div>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          {data.cases.map((miniCase, index) => (
            <motion.div
              key={miniCase.company}
              initial={{ opacity: 0, y: 30 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              transition={{ duration: 0.6, delay: index * 0.15 }}
              className="group relative bg-white dark:bg-slate-900/80 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 p-6 hover:shadow-lg hover:border-blue-200/50 dark:hover:border-blue-800/50 transition-all duration-300"
            >
              <span className="inline-flex items-center px-2.5 py-1 rounded-full bg-amber-100/70 dark:bg-amber-500/10 border border-amber-300/50 dark:border-amber-500/30 text-amber-700 dark:text-amber-400 text-[11px] font-semibold tracking-wide uppercase mb-3">
                {exampleLabel}
              </span>

              <div className="flex items-center gap-3 mb-4">
                <span className="text-2xl">{miniCase.flag}</span>
                <div>
                  <div className="text-base font-bold text-slate-900 dark:text-white">{miniCase.company}</div>
                </div>
              </div>

              <div className="flex items-center gap-4 mb-4 text-xs text-slate-500 dark:text-slate-400">
                <span className="inline-flex items-center gap-1">
                  <MapPin className="w-3 h-3" />
                  {miniCase.country}
                </span>
                <span className="inline-flex items-center gap-1">
                  <Users className="w-3 h-3" />
                  {miniCase.employees}
                </span>
              </div>

              <div className="mb-3">
                <div className="text-xs font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-1">Challenge</div>
                <p className="text-sm text-slate-600 dark:text-slate-300">{miniCase.challenge}</p>
              </div>

              <div className="flex items-start gap-2">
                <ArrowRight className="w-4 h-4 text-emerald-500 mt-0.5 shrink-0" />
                <p className="text-sm font-semibold text-emerald-700 dark:text-emerald-400">{miniCase.result}</p>
              </div>
            </motion.div>
          ))}
        </div>
      </div>
    </section>
  );
}
