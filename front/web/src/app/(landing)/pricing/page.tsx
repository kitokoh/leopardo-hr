'use client';
import { getPricingFaq } from '@/modules/vitrine/data/pricing-faq';

import { Fragment, useState } from 'react';
import { useDarkMode } from '@/modules/vitrine/hooks/useDarkMode';
import Link from 'next/link';
import { motion, AnimatePresence } from 'framer-motion';
import {
  Navbar,
  Footer,
  useScrollReveal,
} from '@/modules/vitrine';
import { getPricingPlans, showsCurrency } from '@/modules/vitrine/data/pricing';
import { CURRENCY_OPTIONS, DEFAULT_CURRENCY_OPTION, convertEurPrice, type CurrencyOption } from '@/modules/vitrine/data/currency';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';
import type { AppLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';
import {
  Check,
  X,
  Zap,
  ArrowRight,
  ShieldCheck,
  Users,
  Star,
  ChevronDown,
  MessageCircle,
  Building2,
  Rocket,
  Crown,
  Gift,
} from 'lucide-react';

/* ─────────────────────────────────────────────
   TYPES
───────────────────────────────────────────── */
type ComparisonFeature = {
  name: string;
  free: boolean | string;
  starter: boolean | string;
  business: boolean | string;
  enterprise: boolean | string;
};

type ComparisonCategory = {
  category: string;
  features: ComparisonFeature[];
};

type FaqItem = {
  id: string;
  question: string;
  answer: string;
  category: string;
};

type PricingPageCopy = {
  hero: { headline: string; subheadline: string; primary: string; secondary: string; badge: string };
  plans: { title: string; subtitle: string; badge: string; monthly: string; annual: string; customPrice: string; periodMonthly: string; periodAnnual: string; trialNote: string };
  currency: { label: string; approx: string };
  trust: { items: string[] };
  comparison: { badge: string; title: string; subtitle: string; featureColumn: string; categories: ComparisonCategory[] };
  faq: { title: string; subtitle: string; badge: string; all: string; categories: string[]; items: FaqItem[] };
  cta: { badge: string; headline: string; subheadline: string; primary: string; secondary: string };
  badges: { popular: string; free: string; freePrice: string; freeNote: string; freeTag: string };
};

/* ─────────────────────────────────────────────
   COPY (fr / en / tr / ar)
───────────────────────────────────────────── */
/**
 * Copie de la page /pricing — générée depuis le catalogue i18n partagé
 * (namespace `pricing.page.*`, #2755 lot 2). Les ids de FAQ restent ici.
 */
/**
 * Copie de la page /pricing — générée depuis le catalogue i18n partagé
 * (namespace `pricing.page.*`, #2755 lot 2). Les ids de FAQ restent ici.
 */
const FAQ_ITEM_IDS = ["starter-plan","change-plan","per-employee","free-trial","trial-to-paid","support","data-location","gdpr","api"];

function getPricingPageCopy(locale: AppLocale): PricingPageCopy {
  const hero: PricingPageCopy["hero"] = {
    badge: t(locale, 'pricing.page.hero.badge'),
    headline: t(locale, 'pricing.page.hero.headline'),
    subheadline: t(locale, 'pricing.page.hero.subheadline'),
    primary: t(locale, 'pricing.page.hero.primary'),
    secondary: t(locale, 'pricing.page.hero.secondary'),
  };
  const plans: PricingPageCopy["plans"] = {
    badge: t(locale, 'pricing.page.plans.badge'),
    title: t(locale, 'pricing.page.plans.title'),
    subtitle: t(locale, 'pricing.page.plans.subtitle'),
    monthly: t(locale, 'pricing.page.plans.monthly'),
    annual: t(locale, 'pricing.page.plans.annual'),
    customPrice: t(locale, 'pricing.page.plans.customPrice'),
    periodMonthly: t(locale, 'pricing.page.plans.periodMonthly'),
    periodAnnual: t(locale, 'pricing.page.plans.periodAnnual'),
    trialNote: t(locale, 'pricing.page.plans.trialNote'),
  };
  const currency: PricingPageCopy["currency"] = {
    label: t(locale, 'pricing.page.currency.label'),
    approx: t(locale, 'pricing.page.currency.approx'),
  };
  const trust: PricingPageCopy["trust"] = {
    items: [
      t(locale, 'pricing.page.trust.items.0'),
      t(locale, 'pricing.page.trust.items.1'),
      t(locale, 'pricing.page.trust.items.2'),
      t(locale, 'pricing.page.trust.items.3'),
    ],
  };
  const comparison: PricingPageCopy["comparison"] = {
    badge: t(locale, 'pricing.page.comparison.badge'),
    title: t(locale, 'pricing.page.comparison.title'),
    subtitle: t(locale, 'pricing.page.comparison.subtitle'),
    featureColumn: t(locale, 'pricing.page.comparison.featureColumn'),
    categories: [
      {
        category: t(locale, 'pricing.page.comparison.categories.0.name'),
        features: [
          { name: t(locale, 'pricing.page.comparison.categories.0.features.0.name'), free: t(locale, 'pricing.page.comparison.categories.0.features.0.free'), starter: true, business: true, enterprise: true },
          { name: t(locale, 'pricing.page.comparison.categories.0.features.1.name'), free: true, starter: true, business: true, enterprise: true },
          { name: t(locale, 'pricing.page.comparison.categories.0.features.2.name'), free: false, starter: true, business: true, enterprise: true },
          { name: t(locale, 'pricing.page.comparison.categories.0.features.3.name'), free: false, starter: true, business: true, enterprise: true },
          { name: t(locale, 'pricing.page.comparison.categories.0.features.4.name'), free: false, starter: false, business: true, enterprise: true },
          { name: t(locale, 'pricing.page.comparison.categories.0.features.5.name'), free: false, starter: false, business: true, enterprise: true },
        ],
      },
      {
        category: t(locale, 'pricing.page.comparison.categories.1.name'),
        features: [
          { name: t(locale, 'pricing.page.comparison.categories.1.features.0.name'), free: false, starter: false, business: true, enterprise: true },
          { name: t(locale, 'pricing.page.comparison.categories.1.features.1.name'), free: true, starter: true, business: true, enterprise: true },
          { name: t(locale, 'pricing.page.comparison.categories.1.features.2.name'), free: false, starter: false, business: true, enterprise: true },
          { name: t(locale, 'pricing.page.comparison.categories.1.features.3.name'), free: false, starter: false, business: true, enterprise: true },
          { name: t(locale, 'pricing.page.comparison.categories.1.features.4.name'), free: false, starter: false, business: true, enterprise: true },
          { name: t(locale, 'pricing.page.comparison.categories.1.features.5.name'), free: false, starter: false, business: false, enterprise: true },
        ],
      },
      {
        category: t(locale, 'pricing.page.comparison.categories.2.name'),
        features: [
          { name: t(locale, 'pricing.page.comparison.categories.2.features.0.name'), free: true, starter: true, business: true, enterprise: true },
          { name: t(locale, 'pricing.page.comparison.categories.2.features.1.name'), free: false, starter: true, business: true, enterprise: true },
          { name: t(locale, 'pricing.page.comparison.categories.2.features.2.name'), free: false, starter: true, business: true, enterprise: true },
          { name: t(locale, 'pricing.page.comparison.categories.2.features.3.name'), free: false, starter: false, business: true, enterprise: true },
          { name: t(locale, 'pricing.page.comparison.categories.2.features.4.name'), free: false, starter: false, business: true, enterprise: true },
          { name: t(locale, 'pricing.page.comparison.categories.2.features.5.name'), free: false, starter: false, business: true, enterprise: true },
        ],
      },
      {
        category: t(locale, 'pricing.page.comparison.categories.3.name'),
        features: [
          { name: t(locale, 'pricing.page.comparison.categories.3.features.0.name'), free: false, starter: false, business: true, enterprise: true },
          { name: t(locale, 'pricing.page.comparison.categories.3.features.1.name'), free: false, starter: false, business: true, enterprise: true },
          { name: t(locale, 'pricing.page.comparison.categories.3.features.2.name'), free: false, starter: false, business: false, enterprise: true },
          { name: t(locale, 'pricing.page.comparison.categories.3.features.3.name'), free: false, starter: false, business: false, enterprise: true },
          { name: t(locale, 'pricing.page.comparison.categories.3.features.4.name'), free: false, starter: false, business: false, enterprise: true },
          { name: t(locale, 'pricing.page.comparison.categories.3.features.5.name'), free: false, starter: false, business: false, enterprise: true },
        ],
      },
    ],
  };
  const faq: PricingPageCopy["faq"] = {
    badge: t(locale, 'pricing.page.faq.badge'),
    title: t(locale, 'pricing.page.faq.title'),
    subtitle: t(locale, 'pricing.page.faq.subtitle'),
    all: t(locale, 'pricing.page.faq.all'),
    categories: [
      t(locale, 'pricing.page.faq.categories.0'),
      t(locale, 'pricing.page.faq.categories.1'),
      t(locale, 'pricing.page.faq.categories.2'),
      t(locale, 'pricing.page.faq.categories.3'),
      t(locale, 'pricing.page.faq.categories.4'),
    ],
    items: [
      {
        id: FAQ_ITEM_IDS[0],
        question: t(locale, 'pricing.page.faq.items.0.question'),
        answer: t(locale, 'pricing.page.faq.items.0.answer'),
        category: t(locale, 'pricing.page.faq.items.0.category'),
      },
      {
        id: FAQ_ITEM_IDS[1],
        question: t(locale, 'pricing.page.faq.items.1.question'),
        answer: t(locale, 'pricing.page.faq.items.1.answer'),
        category: t(locale, 'pricing.page.faq.items.1.category'),
      },
      {
        id: FAQ_ITEM_IDS[2],
        question: t(locale, 'pricing.page.faq.items.2.question'),
        answer: t(locale, 'pricing.page.faq.items.2.answer'),
        category: t(locale, 'pricing.page.faq.items.2.category'),
      },
      {
        id: FAQ_ITEM_IDS[3],
        question: t(locale, 'pricing.page.faq.items.3.question'),
        answer: t(locale, 'pricing.page.faq.items.3.answer'),
        category: t(locale, 'pricing.page.faq.items.3.category'),
      },
      {
        id: FAQ_ITEM_IDS[4],
        question: t(locale, 'pricing.page.faq.items.4.question'),
        answer: t(locale, 'pricing.page.faq.items.4.answer'),
        category: t(locale, 'pricing.page.faq.items.4.category'),
      },
      {
        id: FAQ_ITEM_IDS[5],
        question: t(locale, 'pricing.page.faq.items.5.question'),
        answer: t(locale, 'pricing.page.faq.items.5.answer'),
        category: t(locale, 'pricing.page.faq.items.5.category'),
      },
      {
        id: FAQ_ITEM_IDS[6],
        question: t(locale, 'pricing.page.faq.items.6.question'),
        answer: t(locale, 'pricing.page.faq.items.6.answer'),
        category: t(locale, 'pricing.page.faq.items.6.category'),
      },
      {
        id: FAQ_ITEM_IDS[7],
        question: t(locale, 'pricing.page.faq.items.7.question'),
        answer: t(locale, 'pricing.page.faq.items.7.answer'),
        category: t(locale, 'pricing.page.faq.items.7.category'),
      },
      {
        id: FAQ_ITEM_IDS[8],
        question: t(locale, 'pricing.page.faq.items.8.question'),
        answer: t(locale, 'pricing.page.faq.items.8.answer'),
        category: t(locale, 'pricing.page.faq.items.8.category'),
      },
    ],
  };
  const cta: PricingPageCopy["cta"] = {
    badge: t(locale, 'pricing.page.cta.badge'),
    headline: t(locale, 'pricing.page.cta.headline'),
    subheadline: t(locale, 'pricing.page.cta.subheadline'),
    primary: t(locale, 'pricing.page.cta.primary'),
    secondary: t(locale, 'pricing.page.cta.secondary'),
  };
  const badges: PricingPageCopy["badges"] = {
    popular: t(locale, 'pricing.page.badges.popular'),
    free: t(locale, 'pricing.page.badges.free'),
    freePrice: t(locale, 'pricing.page.badges.freePrice'),
    freeNote: t(locale, 'pricing.page.badges.freeNote'),
    freeTag: t(locale, 'pricing.page.badges.freeTag'),
  };
  return { hero, plans, currency, trust, comparison, faq, cta, badges };
}

/* ─────────────────────────────────────────────
   PLAN ICONS
───────────────────────────────────────────── */
const planIcons = [Gift, Rocket, Crown, Building2] as const;
const planIconColors = [
  'text-slate-500',
  'text-blue-500',
  'text-emerald-500',
  'text-violet-500',
] as const;

/* ─────────────────────────────────────────────
   AVAILABILITY MARK
───────────────────────────────────────────── */
function AvailabilityMark({
  value,
  popular,
}: {
  value: boolean | string;
  popular: boolean;
}) {
  if (value === true) {
    return (
      <span
        className={`inline-flex items-center justify-center w-7 h-7 rounded-full ${
          popular
            ? 'bg-emerald-500/10'
            : 'bg-slate-100 dark:bg-slate-800'
        }`}
      >
        <Check
          className={`w-4 h-4 ${
            popular ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-500 dark:text-slate-400'
          }`}
        />
      </span>
    );
  }
  if (typeof value === 'string') {
    return (
      <span className="text-xs text-slate-500 dark:text-slate-400 font-medium">
        {value}
      </span>
    );
  }
  return (
    <span className="inline-flex items-center justify-center w-7 h-7">
      <X className="w-4 h-4 text-slate-300 dark:text-slate-700" />
    </span>
  );
}

/* ─────────────────────────────────────────────
   FAQ ITEM
───────────────────────────────────────────── */
function FaqAccordionItem({ item, isOpen, onToggle }: {
  item: FaqItem;
  isOpen: boolean;
  onToggle: () => void;
}) {
  return (
    <div className="border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden">
      <button
        onClick={onToggle}
        className="w-full flex items-center justify-between gap-4 p-6 text-left hover:bg-transparent dark:hover:bg-slate-900/50 transition-colors"
        aria-expanded={isOpen}
      >
        <span className="font-semibold text-slate-900 dark:text-white text-base">
          {item.question}
        </span>
        <motion.span
          animate={{ rotate: isOpen ? 180 : 0 }}
          transition={{ duration: 0.2 }}
          className="flex-shrink-0 w-6 h-6 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center"
        >
          <ChevronDown className="w-4 h-4 text-slate-500 dark:text-slate-400" />
        </motion.span>
      </button>
      <AnimatePresence initial={false}>
        {isOpen && (
          <motion.div
            initial={{ height: 0, opacity: 0 }}
            animate={{ height: 'auto', opacity: 1 }}
            exit={{ height: 0, opacity: 0 }}
            transition={{ duration: 0.25, ease: 'easeInOut' }}
          >
            <div className="px-6 pb-6 text-slate-600 dark:text-slate-400 leading-relaxed border-t border-slate-100 dark:border-slate-800 pt-4">
              {item.answer}
            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
}

/* ─────────────────────────────────────────────
   HELPER: get comparison feature value by plan name
───────────────────────────────────────────── */
function getFeatureValue(feature: ComparisonFeature, planName: string): boolean | string {
  const lower = planName.toLowerCase();
  if (lower === 'free') return feature.free;
  if (lower === 'pilot' || lower === 'starter') return feature.starter;
  if (lower === 'operations' || lower === 'business') return feature.business;
  if (lower === 'enterprise' || lower === 'scale') return feature.enterprise;
  return false;
}

/* ─────────────────────────────────────────────
   PAGE
───────────────────────────────────────────── */
export default function PricingPage() {
  const { isDark, toggleDarkMode } = useDarkMode();
  const [isAnnual, setIsAnnual] = useState(true);
  const [openFaqId, setOpenFaqId] = useState<string | null>('starter-plan');
  const [faqCategory, setFaqCategory] = useState<string | null>(null);
  // PA2-MKT-003: let PME prospects in DZ/MA/TN/TR/CA/US see an approximate
  // price in their own currency instead of only EUR. The contractual price
  // stays EUR (see currency.ts docblock); this is a display convenience.
  const [currencyOption, setCurrencyOption] = useState<CurrencyOption>(DEFAULT_CURRENCY_OPTION);

  const vitrine = useVitrineLocale();
  const { locale, direction } = vitrine;
  const copy = getPricingPageCopy(locale);
  const annualSavingsLabel = vitrine.copy.pricing.annualSavings;
  const toggleBillingLabel = vitrine.copy.pricing.toggleBilling;
  const plans = getPricingPlans(locale);
  useScrollReveal();

  const isEurSelected = currencyOption.currency === 'EUR';
  const convertedPrice = (eurAmount: string) => convertEurPrice(eurAmount, currencyOption);

  function getPlanHref(plan: ReturnType<typeof getPricingPlans>[number]) {
    // ADR-0014 : Free est public et gratuit → inscription directe, pas de checkout
    if (plan.planCode === 'free') return '/signup?plan=free&source=pricing_free';
    // Enterprise → contact commercial
    if (!showsCurrency(plan.price)) return '/contact?topic=enterprise';
    // #4952 : tunnel de paiement indisponible en prod (CHECKOUT_UNAVAILABLE —
    // Stripe non branché, fail-closed #2628/#2665) : les plans payants mènent
    // au parcours d'essai sans carte au lieu d'un checkout mort. Réactiver
    // `/checkout?plan=operations` quand Stripe est live (#4630).
    if (plan.planCode === 'operations') return '/signup?plan=operations&source=pricing_operations';
    // Pilot → essai guidé 14 jours (pas de CB à l'inscription — #2649)
    return `/signup?plan=${plan.planCode}&source=pricing_pilot`;
  }

  const filteredFaq = faqCategory
    ? copy.faq.items.filter((i) => i.category === faqCategory)
    : copy.faq.items;

  return (
    <div
      dir={direction}
      className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}
    >
      <Navbar isDark={isDark} onToggleDark={toggleDarkMode} />

      {/* ── HERO ───────────────────────────────── */}
      <section className="relative min-h-[60vh] flex items-center justify-center overflow-hidden pt-24 pb-20">
        <div className="absolute inset-0 bg-gradient-to-br from-slate-950 via-indigo-950 to-slate-900 dark:from-slate-950 dark:via-indigo-950 dark:to-slate-900" />
        <div className="absolute inset-0 bg-[radial-gradient(ellipse_80%_50%_at_50%_-10%,rgba(99,102,241,0.15),transparent)]" />
        <div className="absolute top-1/4 left-1/4 w-[600px] h-[600px] bg-violet-500/10 rounded-full blur-[140px] animate-pulse" />
        <div className="absolute bottom-1/4 right-1/4 w-[400px] h-[400px] bg-emerald-500/10 rounded-full blur-[100px] animate-pulse [animation-delay:2s]" />
        <div className="absolute inset-0 opacity-[0.04]" style={{ backgroundImage: 'linear-gradient(rgba(255,255,255,0.15) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.15) 1px, transparent 1px)', backgroundSize: '60px 60px' }} />

        <div className="relative z-10 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6 }}
            className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-violet-500/10 border border-violet-500/20 text-violet-300 text-sm font-semibold mb-8"
          >
            <Zap className="w-3.5 h-3.5" />
            {copy.hero.badge}
          </motion.div>

          <motion.h1
            initial={{ opacity: 0, y: 30 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.8, delay: 0.1 }}
            className="text-4xl sm:text-5xl lg:text-6xl font-black tracking-tight text-white mb-6 leading-[1.1]"
          >
            {copy.hero.headline}
          </motion.h1>

          <motion.p
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.8, delay: 0.25 }}
            className="text-lg sm:text-xl text-slate-300 mb-10 max-w-2xl mx-auto leading-relaxed"
          >
            {copy.hero.subheadline}
          </motion.p>

          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.8, delay: 0.4 }}
            className="flex flex-col sm:flex-row items-center justify-center gap-4"
          >
            <Link
              href="/checkout?plan=free"
              className="group relative px-8 py-4 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white font-bold rounded-2xl overflow-hidden transition-all duration-300 hover:shadow-[0_20px_60px_-15px_rgba(16,185,129,0.4)] hover:scale-[1.03] active:scale-[0.98]"
            >
              <span className="relative z-10 flex items-center gap-2.5">
                {copy.hero.primary}
                <ArrowRight className="w-5 h-5 group-hover:translate-x-1 transition-transform" />
              </span>
              <div className="absolute inset-0 bg-gradient-to-r from-emerald-600 to-cyan-600 opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
            </Link>
            <Link
              href="/contact?topic=enterprise"
              className="group flex items-center gap-2.5 px-8 py-4 bg-white/10 text-white font-semibold rounded-2xl border border-white/20 hover:bg-white/20 transition-all duration-300 backdrop-blur-sm"
            >
              <MessageCircle className="w-5 h-5" />
              {copy.hero.secondary}
            </Link>
          </motion.div>

          {/* Trust pills */}
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.8, delay: 0.6 }}
            className="flex flex-wrap items-center justify-center gap-3 mt-10"
          >
            {copy.trust.items.map((item) => (
              <div
                key={item}
                className="flex items-center gap-1.5 px-3 py-1.5 bg-white/5 border border-white/10 rounded-full text-slate-300 text-xs font-medium"
              >
                <ShieldCheck className="w-3 h-3 text-emerald-400" />
                {item}
              </div>
            ))}
          </motion.div>
        </div>
      </section>

      {/* ── PRICING CARDS ──────────────────────── */}
      <section className="relative py-24 overflow-hidden">
        <div className="absolute inset-0 bg-gradient-to-b from-white via-slate-50/50 to-white dark:from-slate-950 dark:via-slate-900/50 dark:to-slate-950" />

        <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          {/* Section header */}
          <div className="text-center mb-12">
            <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-violet-500/[0.08] border border-violet-500/15 text-violet-700 dark:text-violet-400 text-sm font-semibold mb-6">
              <span className="w-1.5 h-1.5 rounded-full bg-violet-500 animate-pulse" />
              {copy.plans.badge}
            </div>
            <h2 className="text-3xl sm:text-4xl lg:text-5xl font-black text-slate-900 dark:text-white mb-4 tracking-tight">
              {copy.plans.title}
            </h2>
            <p className="text-lg text-slate-500 dark:text-slate-400 max-w-xl mx-auto">
              {copy.plans.subtitle}
            </p>
          </div>

          {/* PA2-MKT-003: currency/country selector for approximate local pricing */}
          <div className="flex items-center justify-center gap-2 mb-6">
            <label className="flex items-center gap-2 rounded-xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/80 px-3 py-2 text-sm text-slate-600 dark:text-slate-300">
              <span className="font-medium">{copy.currency.label}</span>
              <select
                value={currencyOption.country}
                onChange={(event) => {
                  const next = CURRENCY_OPTIONS.find((o) => o.country === event.target.value);
                  if (next) setCurrencyOption(next);
                }}
                className="bg-transparent outline-none font-semibold"
                aria-label={copy.currency.label}
              >
                {CURRENCY_OPTIONS.map((option) => (
                  <option key={option.country} value={option.country}>
                    {option.label[locale] ?? option.label.fr}
                  </option>
                ))}
              </select>
            </label>
          </div>
          {!isEurSelected && (
            <p className="text-center text-xs text-slate-400 dark:text-slate-500 mb-8 max-w-md mx-auto">
              {copy.currency.approx}
            </p>
          )}

          {/* Billing toggle */}
          <div className="flex items-center justify-center gap-4 mb-14">
            <span className={`text-sm font-semibold transition-colors ${!isAnnual ? 'text-slate-900 dark:text-white' : 'text-slate-400 dark:text-slate-500'}`}>
              {copy.plans.monthly}
            </span>
            <button
              onClick={() => setIsAnnual(!isAnnual)}
              aria-label={toggleBillingLabel}
              className="relative w-16 h-8 rounded-full bg-emerald-500 shadow-inner shadow-emerald-700/30 transition-colors"
            >
              <motion.div
                className="absolute top-1 w-6 h-6 rounded-full bg-white shadow-md"
                animate={{ left: isAnnual ? '2.25rem' : '0.25rem' }}
                transition={{ type: 'spring', stiffness: 500, damping: 35 }}
              />
            </button>
            <span className={`text-sm font-semibold transition-colors ${isAnnual ? 'text-slate-900 dark:text-white' : 'text-slate-400 dark:text-slate-500'}`}>
              {copy.plans.annual}
            </span>
            <AnimatePresence>
              {isAnnual && (
                <motion.span
                  initial={{ opacity: 0, scale: 0.8, x: -8 }}
                  animate={{ opacity: 1, scale: 1, x: 0 }}
                  exit={{ opacity: 0, scale: 0.8, x: -8 }}
                  className="px-3 py-1 text-xs font-black text-emerald-700 bg-emerald-100 dark:bg-emerald-900/30 dark:text-emerald-400 rounded-full"
                >
                  {annualSavingsLabel}
                </motion.span>
              )}
            </AnimatePresence>
          </div>

          {/* Cards — 4 plans in a responsive grid */}
          <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6 max-w-7xl mx-auto">
            {plans.map((plan, index) => {
              const Icon = planIcons[index % planIcons.length];
              const iconColor = planIconColors[index % planIconColors.length];
              const displayPrice = isAnnual ? plan.annualPrice : plan.price;
              // #3023 — le vrai period du plan (avec surcoût par employé actif
              // pour Pilot/Operations), sinon le libellé générique localisé.
              const displayPeriod = (isAnnual ? plan.annualPeriod : plan.period)
                || (isAnnual ? copy.plans.periodAnnual : copy.plans.periodMonthly);
              const isFree = plan.price === '0';
              const hasNumericPrice = showsCurrency(displayPrice);
              const ctaHref = getPlanHref(plan);

              return (
                <motion.div
                  key={plan.name}
                  initial={{ opacity: 0, y: 40 }}
                  whileInView={{ opacity: 1, y: 0 }}
                  viewport={{ once: true }}
                  transition={{ duration: 0.6, delay: index * 0.1 }}
                  whileHover={{ y: -6, transition: { duration: 0.2 } }}
                  className={`relative rounded-3xl ${
                    plan.popular
                      ? 'bg-gradient-to-b from-emerald-400 via-emerald-500 to-cyan-600 p-px shadow-2xl shadow-emerald-500/25'
                      : isFree
                        ? 'bg-gradient-to-b from-slate-300 to-slate-400 dark:from-slate-600 dark:to-slate-700 p-px'
                        : 'bg-slate-200/70 dark:bg-slate-800/70 p-px'
                  }`}
                >
                  <div className="relative h-full rounded-[23px] bg-white dark:bg-slate-950 flex flex-col p-8">
                    {/* Plan badge */}
                    {plan.popular && (
                      <div className="absolute -top-4 left-1/2 -translate-x-1/2 z-10">
                        <div className="flex items-center gap-1.5 px-4 py-1.5 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white text-[11px] font-black uppercase tracking-widest rounded-full shadow-lg shadow-emerald-500/30">
                          <Star className="w-3 h-3 fill-white" />
                          {copy.badges.popular}
                        </div>
                      </div>
                    )}
                    {isFree && (
                      <div className="absolute -top-4 left-1/2 -translate-x-1/2 z-10">
                        <div className="flex items-center gap-1.5 px-4 py-1.5 bg-gradient-to-r from-slate-600 to-slate-700 text-white text-[11px] font-black uppercase tracking-widest rounded-full shadow-lg">
                          <Gift className="w-3 h-3" />
                          {copy.badges.free}
                        </div>
                      </div>
                    )}

                    {/* Plan header */}
                    <div className="mb-8">
                      <div className="inline-flex items-center justify-center w-12 h-12 rounded-2xl mb-4 bg-slate-100 dark:bg-slate-800/80">
                        <Icon className={`w-6 h-6 ${iconColor}`} />
                      </div>
                      <h3 className="text-xl font-black text-slate-900 dark:text-white mb-1">{plan.name}</h3>
                      <p className="text-sm text-slate-500 dark:text-slate-400">{plan.description}</p>
                    </div>

                    {/* Price */}
                    <div className="mb-6">
                      <div className="flex items-baseline gap-1.5">
                        {isFree ? (
                          <span className="text-5xl font-black bg-gradient-to-b from-slate-900 to-slate-600 dark:from-white dark:to-slate-400 bg-clip-text text-transparent">
                            {copy.badges.freePrice}
                          </span>
                        ) : hasNumericPrice ? (
                          <>
                            <span className="text-lg font-bold text-slate-500 dark:text-slate-400">
                              {isEurSelected ? 'EUR' : currencyOption.currency}
                            </span>
                            <span className="text-5xl font-black bg-gradient-to-b from-slate-900 to-slate-600 dark:from-white dark:to-slate-400 bg-clip-text text-transparent">
                              {isEurSelected ? displayPrice : (convertedPrice(displayPrice) ?? displayPrice)}
                            </span>
                          </>
                        ) : (
                          <span className="text-4xl font-black bg-gradient-to-b from-slate-900 to-slate-600 dark:from-white dark:to-slate-400 bg-clip-text text-transparent">
                            {displayPrice}
                          </span>
                        )}
                      </div>
                      {isFree ? (
                        <p className="mt-1 text-sm text-emerald-600 dark:text-emerald-400 font-semibold">
                          {copy.badges.freeNote}
                        </p>
                      ) : hasNumericPrice ? (
                        <div className="mt-1 space-y-0.5">
                          <p className="text-sm text-slate-500">
                            {displayPeriod}
                          </p>
                          {isAnnual && (
                            <p className="text-xs text-slate-400 dark:text-slate-600">
                              <span className="line-through">
                                {isEurSelected ? 'EUR' : currencyOption.currency} {isEurSelected ? plan.price : (convertedPrice(plan.price) ?? plan.price)}
                              </span>
                              {' '}
                              <span className="text-emerald-600 dark:text-emerald-400 font-semibold">{annualSavingsLabel}</span>
                            </p>
                          )}
                          {!isEurSelected && (
                            <p className="text-xs text-slate-400 dark:text-slate-600">≈ EUR {displayPrice}</p>
                          )}
                        </div>
                      ) : null}
                      {plan.priceNote && (
                        <p className="mt-2 text-xs text-slate-500 dark:text-slate-400 font-medium">
                          {plan.priceNote}
                        </p>
                      )}
                      <div className="inline-flex items-center gap-1.5 mt-3 px-2.5 py-1 rounded-full bg-slate-100 dark:bg-slate-800 text-xs text-slate-500 dark:text-slate-400">
                        <Users className="w-3 h-3" />
                        {plan.employeeLimit}
                      </div>
                    </div>

                    {/* Features */}
                    <ul className="flex-1 space-y-3 mb-8">
                      {plan.features.map((feature, fi) => (
                        <li key={fi} className="flex items-start gap-3">
                          <Check className={`w-4 h-4 flex-shrink-0 mt-0.5 ${plan.popular ? 'text-emerald-500' : isFree ? 'text-slate-500' : 'text-slate-400 dark:text-slate-500'}`} />
                          <span className="text-sm text-slate-700 dark:text-slate-300 leading-snug">{feature}</span>
                        </li>
                      ))}
                    </ul>

                    {/* CTA */}
                    <Link
                      href={ctaHref}
                      className={`flex items-center justify-center gap-2 w-full py-4 rounded-2xl font-bold text-sm transition-all duration-300 ${
                        plan.popular
                          ? 'bg-gradient-to-r from-emerald-500 to-emerald-600 text-white hover:from-emerald-600 hover:to-cyan-600 shadow-lg shadow-emerald-500/25 hover:shadow-emerald-500/40 hover:scale-[1.02] active:scale-[0.98]'
                          : isFree
                            ? 'bg-gradient-to-r from-slate-700 to-slate-900 text-white hover:from-slate-800 hover:to-black hover:scale-[1.01] active:scale-[0.98] shadow-md'
                            : hasNumericPrice
                              ? 'bg-slate-900 dark:bg-white text-white dark:text-slate-900 hover:bg-slate-800 dark:hover:bg-slate-100 hover:scale-[1.01] active:scale-[0.98]'
                              : 'bg-gradient-to-r from-violet-500 to-fuchsia-600 text-white hover:from-violet-600 hover:to-fuchsia-700 shadow-lg hover:scale-[1.02] active:scale-[0.98]'
                      }`}
                    >
                      {plan.cta}
                      <ArrowRight className="w-4 h-4" />
                    </Link>
                  </div>
                </motion.div>
              );
            })}
          </div>

          {/* Trial note */}
          <motion.p
            initial={{ opacity: 0 }}
            whileInView={{ opacity: 1 }}
            viewport={{ once: true }}
            transition={{ duration: 0.6, delay: 0.4 }}
            className="text-center text-sm text-slate-500 dark:text-slate-400 mt-8 flex items-center justify-center gap-2"
          >
            <ShieldCheck className="w-4 h-4 text-emerald-500" />
            {copy.plans.trialNote}
          </motion.p>
        </div>
      </section>

      {/* ── COMPARISON TABLE ────────────────────── */}
      <section className="relative py-24 overflow-hidden">
        <div className="absolute inset-0 bg-gradient-to-b from-slate-50/50 to-white dark:from-slate-900/50 dark:to-slate-950" />

        <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-16">
            <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-emerald-500/[0.08] border border-emerald-500/15 text-emerald-700 dark:text-emerald-400 text-sm font-semibold mb-6">
              <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" />
              {copy.comparison.badge}
            </div>
            <h2 className="text-3xl sm:text-4xl lg:text-5xl font-black text-slate-900 dark:text-white tracking-tight">
              {copy.comparison.title}{' '}
              <span className="bg-gradient-to-r from-emerald-500 to-cyan-500 bg-clip-text text-transparent">
                {copy.comparison.subtitle}
              </span>
            </h2>
          </div>

          <div className="overflow-x-auto rounded-3xl border border-slate-200 dark:border-slate-800 shadow-xl shadow-slate-100/50 dark:shadow-slate-950/50">
            <table className="w-full min-w-[720px]">
              <thead>
                <tr className="bg-transparent dark:bg-slate-900">
                  <th className="text-left py-5 px-6 font-bold text-slate-900 dark:text-white text-sm w-[32%]">
                    {copy.comparison.featureColumn}
                  </th>
                  {plans.map((plan, i) => {
                    const Icon = planIcons[i % planIcons.length];
                    const isFree = plan.price === '0';
                    return (
                      <th
                        key={plan.name}
                        className={`text-center py-5 px-4 font-black text-sm ${
                          plan.popular
                            ? 'text-emerald-600 dark:text-emerald-400'
                            : 'text-slate-700 dark:text-slate-300'
                        }`}
                      >
                        <div className="flex flex-col items-center gap-1.5">
                          <Icon className={`w-5 h-5 ${planIconColors[i % planIconColors.length]}`} />
                          {plan.name}
                          {plan.popular && (
                            <span className="text-[9px] px-2 py-0.5 bg-emerald-500 text-white rounded-full font-black uppercase tracking-wider">
                              ★ top
                            </span>
                          )}
                          {isFree && (
                            <span className="text-[9px] px-2 py-0.5 bg-slate-600 text-white rounded-full font-black uppercase tracking-wider">
                              {copy.badges.freeTag}
                            </span>
                          )}
                        </div>
                      </th>
                    );
                  })}
                </tr>
              </thead>
              <tbody>
                {copy.comparison.categories.map((cat, catIdx) => (
                  <Fragment key={cat.category}>
                    {/* Category row */}
                    <tr className={catIdx % 2 === 0 ? 'bg-transparent/70 dark:bg-slate-900/30' : 'bg-emerald-50/30 dark:bg-emerald-950/10'}>
                      <td colSpan={plans.length + 1} className="py-3 px-6">
                        <span className="text-xs font-black uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">
                          {cat.category}
                        </span>
                      </td>
                    </tr>
                    {/* Feature rows */}
                    {cat.features.map((feature, fIdx) => (
                      <motion.tr
                        key={feature.name}
                        initial={{ opacity: 0 }}
                        whileInView={{ opacity: 1 }}
                        viewport={{ once: true, margin: '-40px' }}
                        transition={{ duration: 0.3, delay: fIdx * 0.05 }}
                        className="border-t border-slate-100 dark:border-slate-800/50 hover:bg-transparent/80 dark:hover:bg-slate-900/30 transition-colors"
                      >
                        <td className="py-4 px-6 text-sm text-slate-700 dark:text-slate-300 font-medium">
                          {feature.name}
                        </td>
                        {plans.map((plan) => (
                          <td
                            key={plan.name}
                            className={`py-4 px-4 text-center ${plan.popular ? 'bg-emerald-50/40 dark:bg-emerald-950/10' : ''}`}
                          >
                            <AvailabilityMark
                              value={getFeatureValue(feature, plan.name)}
                              popular={plan.popular}
                            />
                          </td>
                        ))}
                      </motion.tr>
                    ))}
                  </Fragment>
                ))}
                {/* CTA row */}
                <tr className="border-t border-slate-200 dark:border-slate-800 bg-transparent dark:bg-slate-900">
                  <td className="py-6 px-6" />
                  {plans.map((plan) => {
                    const isFree = plan.price === '0';
                    const hasNumericPrice = showsCurrency(plan.price);
                    return (
                      <td key={plan.name} className={`py-6 px-4 text-center ${plan.popular ? 'bg-emerald-50/40 dark:bg-emerald-950/10' : ''}`}>
                        <Link
                          href={getPlanHref(plan)}
                          className={`inline-flex items-center gap-1.5 px-5 py-2.5 rounded-xl text-sm font-bold transition-all duration-200 hover:scale-[1.03] active:scale-[0.97] ${
                            plan.popular
                              ? 'bg-gradient-to-r from-emerald-500 to-emerald-600 text-white shadow-lg shadow-emerald-500/20'
                              : isFree
                                ? 'bg-slate-700 text-white hover:bg-slate-800'
                                : hasNumericPrice
                                  ? 'bg-slate-900 dark:bg-white text-white dark:text-slate-900 hover:bg-slate-800 dark:hover:bg-slate-100'
                                  : 'bg-gradient-to-r from-violet-500 to-fuchsia-600 text-white hover:from-violet-600 hover:to-fuchsia-700'
                          }`}
                        >
                          {plan.cta}
                          <ArrowRight className="w-3.5 h-3.5" />
                        </Link>
                      </td>
                    );
                  })}
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      {/* ── FAQ ─────────────────────────────────── */}
      <section className="relative py-24 overflow-hidden">
        <div className="absolute inset-0 bg-gradient-to-b from-white to-slate-50/50 dark:from-slate-950 dark:to-slate-900/50" />

        <div className="relative max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-12">
            <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-violet-500/[0.08] border border-violet-500/15 text-violet-700 dark:text-violet-400 text-sm font-semibold mb-6">
              <span className="w-1.5 h-1.5 rounded-full bg-violet-500 animate-pulse" />
              {copy.faq.badge}
            </div>
            <h2 className="text-3xl sm:text-4xl lg:text-5xl font-black text-slate-900 dark:text-white mb-4 tracking-tight">
              {copy.faq.title}
            </h2>
            <p className="text-lg text-slate-500 dark:text-slate-400">{copy.faq.subtitle}</p>
          </div>

          {/* Category filter */}
          <div className="flex flex-wrap gap-2 justify-center mb-8">
            <button
              onClick={() => setFaqCategory(null)}
              className={`px-4 py-1.5 rounded-full text-sm font-semibold transition-all duration-200 ${
                !faqCategory
                  ? 'bg-slate-900 dark:bg-white text-white dark:text-slate-900'
                  : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700'
              }`}
            >
              {copy.faq.all}
            </button>
            {copy.faq.categories.map((cat) => (
              <button
                key={cat}
                onClick={() => setFaqCategory(faqCategory === cat ? null : cat)}
                className={`px-4 py-1.5 rounded-full text-sm font-semibold transition-all duration-200 ${
                  faqCategory === cat
                    ? 'bg-emerald-500 text-white'
                    : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700'
                }`}
              >
                {cat}
              </button>
            ))}
          </div>

          {/* Accordion */}
          <div className="space-y-3">
            <AnimatePresence mode="wait">
              {filteredFaq.map((item) => (
                <motion.div
                  key={item.id}
                  initial={{ opacity: 0, y: 10 }}
                  animate={{ opacity: 1, y: 0 }}
                  exit={{ opacity: 0, y: -10 }}
                  transition={{ duration: 0.2 }}
                >
                  <FaqAccordionItem
                    item={item}
                    isOpen={openFaqId === item.id}
                    onToggle={() => setOpenFaqId(openFaqId === item.id ? null : item.id)}
                  />
                </motion.div>
              ))}
            </AnimatePresence>
          </div>

          {/* Still have questions */}
          <div className="mt-10 text-center p-6 rounded-2xl bg-transparent dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
            <p className="text-slate-700 dark:text-slate-300 font-semibold mb-3">
              {t(locale, 'pricing.page.faq.moreTitle')}
            </p>
            <Link
              href="/contact"
              className="inline-flex items-center gap-2 px-6 py-3 bg-slate-900 dark:bg-white text-white dark:text-slate-900 font-bold rounded-xl hover:scale-[1.02] transition-transform duration-200 text-sm"
            >
              <MessageCircle className="w-4 h-4" />
              {t(locale, 'pricing.page.faq.contactSupport')}
              <ArrowRight className="w-4 h-4" />
            </Link>
          </div>
        </div>
      </section>

      {/* ── CTA FINAL ───────────────────────────── */}
      <section className="relative py-32 overflow-hidden">
        <div className="absolute inset-0 bg-gradient-to-r from-emerald-500 via-emerald-600 to-cyan-600" />
        <div className="absolute top-1/4 -left-32 w-[500px] h-[500px] bg-white/10 rounded-full blur-[120px]" />
        <div className="absolute bottom-1/4 -right-32 w-[500px] h-[500px] bg-white/10 rounded-full blur-[120px]" />
        <div className="absolute inset-0 opacity-10" style={{ backgroundImage: 'radial-gradient(circle, rgba(255,255,255,0.3) 1px, transparent 1px)', backgroundSize: '30px 30px' }} />

        <div className="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.6 }}
            className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-white/10 border border-white/20 text-white text-sm font-semibold mb-8"
          >
            <Zap className="w-3.5 h-3.5" />
            {copy.cta.badge}
          </motion.div>

          <motion.h2
            initial={{ opacity: 0, y: 30 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.8, delay: 0.1 }}
            className="text-4xl sm:text-5xl lg:text-6xl font-black text-white mb-6 tracking-tight leading-[1.1]"
          >
            {copy.cta.headline}
          </motion.h2>

          <motion.p
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.8, delay: 0.2 }}
            className="text-xl text-white/80 mb-12 max-w-2xl mx-auto leading-relaxed"
          >
            {copy.cta.subheadline}
          </motion.p>

          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.8, delay: 0.3 }}
            className="flex flex-col sm:flex-row items-center justify-center gap-4"
          >
            <Link
              href="/checkout?plan=free"
              className="group relative px-10 py-4 bg-white text-emerald-600 font-black rounded-2xl overflow-hidden transition-all duration-300 hover:shadow-2xl hover:scale-[1.03] active:scale-[0.98] text-base"
            >
              <span className="relative z-10 flex items-center gap-2.5">
                {copy.cta.primary}
                <ArrowRight className="w-5 h-5 group-hover:translate-x-1 transition-transform" />
              </span>
            </Link>
            <Link
              href="/contact?topic=enterprise"
              className="group flex items-center gap-2.5 px-10 py-4 bg-white/10 text-white font-bold rounded-2xl border border-white/20 hover:bg-white/20 transition-all duration-300 backdrop-blur-sm text-base"
            >
              <Building2 className="w-5 h-5" />
              {copy.cta.secondary}
            </Link>
          </motion.div>
        </div>
      </section>

      <Footer />
    </div>
  );
}


