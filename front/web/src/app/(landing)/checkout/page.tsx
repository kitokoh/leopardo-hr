'use client';

import { Suspense, useEffect, useState } from 'react';
import { useSearchParams, useRouter } from 'next/navigation';
import Link from 'next/link';
import { motion, AnimatePresence } from 'framer-motion';
import {
  ArrowLeft,
  ArrowRight,
  Building2,
  Check,
  CreditCard,
  Lock,
  Mail,
  Phone,
  Rocket,
  Shield,
  ShieldCheck,
  Sparkles,
  User,
  Users,
  Zap,
} from 'lucide-react';
import { useDarkMode } from '@/modules/vitrine/hooks/useDarkMode';
import { Navbar, Footer } from '@/modules/vitrine';
import { getCurrentLocale, useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';
import { getCheckoutCopy, type CheckoutPlanKey } from '@/modules/vitrine/data/checkout';
import { getApiBaseUrl } from '@/lib/backend-url';

/* ─────────────────────────────────────────────
   PLAN CONFIG
   Montants alignés sur PlanSeeder (api/database/seeders/PlanSeeder.php,
   schéma canonique #2977/#3919) :
   Free 0€/5 emp · Pilot 29€/mois (24,17€/mois en annuel = 290 €/an, 30 employés) ·
   Operations 79€/mois (65,83€/mois en annuel = 790 €/an, 200 employés) · Enterprise sur devis. (ADR-0014)
   Tarif annuel affiché au mois (24,17/65,83 €) — équivalent exact 290/790 €/an (PlanSeeder).
   Essai : 14 jours (décision D-E4-01).
───────────────────────────────────────────── */
// #4791 : prix annuel exact (290/790 €/an ÷ 12) — affiché avec le séparateur
// décimal de la locale (24,17 € en fr/tr/ar, 24.17 en en).
function formatPrice(value: number | null, locale: string): string {
  if (value === null) return '';
  const isWhole = Number.isInteger(value);
  const intl = locale === 'en' ? 'en-US' : locale === 'tr' ? 'tr-TR' : locale === 'ar' ? 'ar-EG' : 'fr-FR';
  return value.toLocaleString(intl, {
    minimumFractionDigits: isWhole ? 0 : 2,
    maximumFractionDigits: isWhole ? 0 : 2,
  });
}

const PLAN_CONFIG = {
  pilot: {
    icon: Rocket,
    color: 'blue',
    gradient: 'from-blue-500 to-indigo-600',
    priceMonthly: 29,
    priceAnnual: 24.17,
    savings: 58,
    trialDays: 14,
  },
  operations: {
    icon: Zap,
    color: 'emerald',
    gradient: 'from-emerald-500 to-cyan-600',
    // ADR-0014 : 79 €/mois, 66 €/mois annuel (790 €/an)
    priceMonthly: 79,
    priceAnnual: 65.83,
    savings: 158,
    trialDays: 14,
  },
  enterprise: {
    icon: Building2,
    color: 'violet',
    gradient: 'from-violet-500 to-fuchsia-600',
    priceMonthly: null,
    priceAnnual: null,
    savings: 0,
    trialDays: 14,
  },
} as const;

type PlanKey = keyof typeof PLAN_CONFIG;

// Anciens slugs de plans (pré #2907) : redirigés vers les clés canoniques
// pilot/operations/enterprise (#2977/#3919) — compat d'URLs, pas une vérité
// produit. Le plan Free (0 €/mois, 5 employés) EXISTE au backend depuis la
// recréation #2977 (PlanSeeder is_active) et est vendu sur la vitrine (#3883) ;
// « free » ne doit JAMAIS atterrir silencieusement sur le paywall Pilot :
// `/checkout?plan=free` rend un état « essai guidé » explicite (voir
// CheckoutInner) menant à /signup — jamais un formulaire de paiement.
const PLAN_ALIASES: Record<string, PlanKey> = {
  starter: 'pilot',
  business: 'operations',
  scale: 'enterprise',
  // ADR-0014 : Free est public mais gratuit → pas de checkout, redirection vers signup
  // (la page /checkout?plan=free redirige vers /signup?plan=free via getPlanHref)
  free: 'pilot',
};

/* ─────────────────────────────────────────────
   CHECKOUT MODE (sandbox = explicit opt-in via NEXT_PUBLIC_CHECKOUT_SANDBOX=true)

   #4950 : le mode sandbox (carte de test 4242) est STRUCTURELLEMENT interdit
   en production — NODE_ENV=production le désactive même si l'env var a été
   mal configurée sur Vercel (régression #2628/#2665 : UI test visible en
   prod). Le opt-in reste possible en dev/preview uniquement.
   In production the test card UI is never shown: payment must be real (#2628).
───────────────────────────────────────────── */
const CHECKOUT_SANDBOX = process.env.NODE_ENV !== 'production' && process.env.NEXT_PUBLIC_CHECKOUT_SANDBOX === 'true';

/* ─────────────────────────────────────────────
   SANDBOX TEST CARD
───────────────────────────────────────────── */
const SANDBOX_CARD = {
  number: '4242 4242 4242 4242',
  expiry: '12/29',
  cvc: '123',
  name: 'Test User',
};

/* ─────────────────────────────────────────────
   GOOGLE AUTH HREF
───────────────────────────────────────────── */
function googleAuthHref(): string {
  // Issue #2725 — même pattern que login (QA #2277) : passer par le proxy
  // Next.js (même origine) pour que le cookie de session soit posé sur la
  // vitrine, pas sur l'origine API directe.
  return '/api/v1/auth/google';
}

/* ─────────────────────────────────────────────
   STEP INDICATOR
───────────────────────────────────────────── */
function StepIndicator({
  step,
  total,
  stepLabels,
}: {
  step: number;
  total: number;
  stepLabels: string[];
}) {
  return (
    <div className="flex items-center justify-center gap-0 mb-10">
      {Array.from({ length: total }).map((_, i) => (
        <div key={i} className="flex items-center">
          <div
            className={`flex items-center justify-center w-9 h-9 rounded-full font-black text-sm transition-all duration-300 ${
              i < step
                ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-500/30'
                : i === step
                ? 'bg-slate-900 dark:bg-white text-white dark:text-slate-900 shadow-md'
                : 'bg-slate-100 dark:bg-slate-800 text-slate-400'
            }`}
          >
            {i < step ? <Check className="w-4 h-4" /> : i + 1}
          </div>
          <span
            className={`hidden sm:block ml-2 text-sm font-semibold transition-colors ${
              i === step ? 'text-slate-900 dark:text-white' : 'text-slate-400'
            }`}
          >
            {stepLabels[i]}
          </span>
          {i < total - 1 && (
            <div
              className={`hidden sm:block mx-4 h-px w-16 transition-colors duration-500 ${
                i < step ? 'bg-emerald-400' : 'bg-slate-200 dark:bg-slate-700'
              }`}
            />
          )}
        </div>
      ))}
    </div>
  );
}

/* ─────────────────────────────────────────────
   PLAN SUMMARY CARD
───────────────────────────────────────────── */
function PlanSummaryCard({
  plan,
  billing,
  onChangeBilling,
}: {
  plan: PlanKey;
  billing: 'monthly' | 'annual';
  onChangeBilling: (b: 'monthly' | 'annual') => void;
}) {
  const cfg = PLAN_CONFIG[plan];
  const Icon = cfg.icon;
  const { locale } = useVitrineLocale();
  const copy = getCheckoutCopy(locale);
  const planCopy = copy.plans[plan];
  const price = billing === 'annual' ? cfg.priceAnnual : cfg.priceMonthly;
  const priceLabel = price === null ? copy.quote : formatPrice(price, locale);

  // #4380 : rabais annuel calculé depuis le tarif (Pilot 29→24,17 = 17 %, Operations
  // 79→65,83 = 17 %) — badge statique remplacé par le calcul exact (#4791).
  const annualDiscountPct =
    billing === 'annual' && cfg.priceMonthly && cfg.priceAnnual
      ? Math.round((1 - cfg.priceAnnual / cfg.priceMonthly) * 100)
      : null;

  return (
    <div className="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden shadow-xl shadow-slate-100/50 dark:shadow-slate-950/50">
      {/* Header */}
      <div className={`bg-gradient-to-r ${cfg.gradient} p-6`}>
        <div className="flex items-center gap-3 mb-4">
          <div className="w-10 h-10 rounded-2xl bg-white/20 flex items-center justify-center">
            <Icon className="w-5 h-5 text-white" />
          </div>
          <div>
            <p className="text-white/80 text-xs font-semibold uppercase tracking-wider">{copy.planChosen}</p>
            <h3 className="text-white font-black text-xl">{planCopy.label}</h3>
          </div>
        </div>
        <div>
          <div className="flex items-baseline gap-1">
            {price !== null && <span className="text-white/70 text-sm">{copy.currencyLabel}</span>}
            <span className="text-white font-black text-5xl">{priceLabel}</span>
            {price !== null && <span className="text-white/70 text-sm">{copy.perMonth}</span>}
          </div>
          {billing === 'annual' && price !== null && (
            <p className="text-white/70 text-xs mt-1">
              {copy.billedAnnually.replace('{savings}', String(cfg.savings))}
            </p>
          )}
        </div>
      </div>

      {/* Billing toggle */}
      <div className="p-4 bg-transparent dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700">
        <div className="flex rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700">
            <button
              onClick={() => onChangeBilling('monthly')}
              className={`flex-1 py-2.5 text-sm font-bold transition-all duration-200 ${
                billing === 'monthly'
                  ? 'bg-slate-900 dark:bg-white text-white dark:text-slate-900'
                  : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
              }`}
            >
              {copy.monthly}
            </button>
            <button
              onClick={() => onChangeBilling('annual')}
              className={`flex-1 py-2.5 text-sm font-bold transition-all duration-200 ${
                billing === 'annual'
                  ? 'bg-emerald-500 text-white'
                  : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
              }`}
            >
              {copy.annual}
              {annualDiscountPct !== null && (
                <span className="ml-1.5 text-[10px] font-black">-{annualDiscountPct}%</span>
              )}
            </button>
          </div>
      </div>

      {/* Features */}
      <ul className="p-5 space-y-2.5">
        {planCopy.features.map((f) => (
          <li key={f} className="flex items-center gap-2.5 text-sm text-slate-700 dark:text-slate-300">
            <Check className="w-4 h-4 text-emerald-500 flex-shrink-0" />
            {f}
          </li>
        ))}
      </ul>

      {/* Badge */}
      <div className="px-5 pb-5">
        <div className="flex items-center gap-2 px-4 py-3 rounded-2xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-900/50">
          <Sparkles className="w-4 h-4 text-emerald-600 dark:text-emerald-400 flex-shrink-0" />
          <p className="text-sm font-semibold text-emerald-800 dark:text-emerald-300">
            {copy.trialBadge.replace('{days}', String(cfg.trialDays))}
          </p>
        </div>
      </div>
    </div>
  );
}

/* ─────────────────────────────────────────────
   TRUST BADGES
───────────────────────────────────────────── */
function TrustBadges() {
  const { locale } = useVitrineLocale();
  const copy = getCheckoutCopy(locale);
  return (
    <div className="mt-6 space-y-2">
      {[
        { icon: Lock, text: copy.trust.secure },
        { icon: ShieldCheck, text: copy.trust.rgpd },
        { icon: Shield, text: copy.trust.cancel },
      ].map(({ icon: Icon, text }) => (
        <div key={text} className="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
          <Icon className="w-3.5 h-3.5 text-emerald-500 flex-shrink-0" />
          {text}
        </div>
      ))}
    </div>
  );
}

/* ─────────────────────────────────────────────
   GOOGLE OAUTH BUTTON (reusable)
───────────────────────────────────────────── */
function GoogleButton({ label }: { label?: string }) {
  const { locale } = useVitrineLocale();
  const resolvedLabel = label ?? getCheckoutCopy(locale).continueWithGoogle;
  return (
    <a
      href={googleAuthHref()}
      className="flex items-center justify-center gap-3 w-full py-3.5 rounded-2xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-white font-bold text-sm hover:border-slate-300 hover:bg-transparent dark:hover:bg-slate-800 transition-all duration-200 shadow-sm"
    >
      <svg viewBox="0 0 24 24" className="w-5 h-5" xmlns="http://www.w3.org/2000/svg">
        <path
          d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
          fill="#4285F4"
        />
        <path
          d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
          fill="#34A853"
        />
        <path
          d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"
          fill="#FBBC05"
        />
        <path
          d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
          fill="#EA4335"
        />
      </svg>
      {resolvedLabel}
    </a>
  );
}

/* ─────────────────────────────────────────────
   STEP 0 — RECAP
───────────────────────────────────────────── */
function StepRecap({
  plan,
  billing,
  onChangeBilling,
  onNext,
}: {
  plan: PlanKey;
  billing: 'monthly' | 'annual';
  onChangeBilling: (b: 'monthly' | 'annual') => void;
  onNext: () => void;
}) {
  const cfg = PLAN_CONFIG[plan];
  const price = billing === 'annual' ? cfg.priceAnnual : cfg.priceMonthly;
  const { locale } = useVitrineLocale();
  const copy = getCheckoutCopy(locale);
  const priceLabel = price === null ? copy.quote : formatPrice(price, locale);

  return (
    <motion.div
      initial={{ opacity: 0, x: 30 }}
      animate={{ opacity: 1, x: 0 }}
      exit={{ opacity: 0, x: -30 }}
      transition={{ duration: 0.3 }}
    >
      <h2 className="text-2xl font-black text-slate-900 dark:text-white mb-2">
        {copy.recap.title}
      </h2>
      <p className="text-slate-500 dark:text-slate-400 mb-8">
        {copy.recap.subtitle}
      </p>

      <PlanSummaryCard plan={plan} billing={billing} onChangeBilling={onChangeBilling} />

      <div className="mt-6 p-4 rounded-2xl bg-blue-50 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-900/50 text-sm text-blue-800 dark:text-blue-300">
        <strong>{copy.recap.trialNote.replace('{days}', String(cfg.trialDays)).split('. ')[0]}.</strong>{' '}
        {copy.recap.trialNote.replace('{days}', String(cfg.trialDays)).split('. ').slice(1).join('. ')}
      </div>

      <div className="mt-6 text-center text-sm text-slate-500 dark:text-slate-400">
        <span>{copy.recap.wrongPlan} </span>
        <Link href="/pricing" className="font-semibold text-emerald-600 hover:text-emerald-700 underline underline-offset-2">
          {copy.recap.viewAllPlans}
        </Link>
      </div>

      <button
        onClick={onNext}
        className="mt-8 w-full flex items-center justify-center gap-2.5 py-4 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white font-black rounded-2xl hover:from-emerald-600 hover:to-cyan-600 transition-all duration-300 shadow-lg shadow-emerald-500/25 hover:shadow-emerald-500/40 hover:scale-[1.01] active:scale-[0.99] text-base"
      >
        <>
          {price === null
            ? copy.quote
            : copy.recap.continueCta.replace('{price}', formatPrice(price, locale))}{' '}
          <ArrowRight className="w-5 h-5" />
        </>
      </button>
    </motion.div>
  );
}

/* ─────────────────────────────────────────────
   ACCOUNT DATA TYPE
───────────────────────────────────────────── */
type AccountData = {
  firstName: string;
  lastName: string;
  email: string;
  company: string;
  phone: string;
  employees: string;
};

/* ─────────────────────────────────────────────
   STEP 1 — ACCOUNT (Paid plans)
───────────────────────────────────────────── */
function StepAccount({
  data,
  onChange,
  onNext,
  onBack,
}: {
  data: AccountData;
  onChange: (d: Partial<AccountData>) => void;
  onNext: () => void;
  onBack: () => void;
}) {
  const { locale } = useVitrineLocale();
  const copy = getCheckoutCopy(locale);
  const [errors, setErrors] = useState<Partial<AccountData>>({});

  function validate(): boolean {
    const e: Partial<AccountData> = {};
    if (!data.firstName.trim()) e.firstName = copy.account.errors.firstName;
    if (!data.lastName.trim()) e.lastName = copy.account.errors.lastName;
    if (!data.email.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email))
      e.email = copy.account.errors.email;
    if (!data.company.trim() || data.company.length < 2)
      e.company = copy.account.errors.company;
    setErrors(e);
    return Object.keys(e).length === 0;
  }

  function handleNext() {
    if (validate()) onNext();
  }

  const inputBase =
    'w-full px-4 py-3 rounded-xl border bg-white dark:bg-slate-900 text-sm font-medium text-slate-900 dark:text-white outline-none transition focus:ring-4 placeholder:text-slate-400';
  const inputOk = 'border-slate-200 dark:border-slate-700 focus:border-emerald-500 focus:ring-emerald-500/10';
  const inputErr = 'border-red-400 focus:border-red-400 focus:ring-red-500/10';

  return (
    <motion.div
      initial={{ opacity: 0, x: 30 }}
      animate={{ opacity: 1, x: 0 }}
      exit={{ opacity: 0, x: -30 }}
      transition={{ duration: 0.3 }}
    >
      <button
        onClick={onBack}
        className="flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 mb-6 transition-colors"
      >
        <ArrowLeft className="w-4 h-4" /> {copy.back}
      </button>

      <h2 className="text-2xl font-black text-slate-900 dark:text-white mb-2">
        {copy.account.title}
      </h2>
      <p className="text-slate-500 dark:text-slate-400 mb-6">
        {copy.account.subtitle}
      </p>

      {/* Google OAuth button */}
      <div className="mb-4">
        <GoogleButton />
      </div>

      <div className="flex items-center gap-3 mb-6">
        <div className="flex-1 h-px bg-slate-200 dark:bg-slate-700" />
        <span className="text-xs text-slate-400 font-medium">{copy.account.orEmail}</span>
        <div className="flex-1 h-px bg-slate-200 dark:bg-slate-700" />
      </div>

      <div className="space-y-4">
        {/* Name */}
        <div className="grid grid-cols-2 gap-3">
          <div>
            <label className="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
              {copy.account.firstName} <span className="text-red-500">*</span>
            </label>
            <div className="relative">
              <User className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
              <input
                type="text"
                value={data.firstName}
                onChange={(e) => onChange({ firstName: e.target.value })}
                placeholder={copy.account.placeholders.firstName}
                className={`${inputBase} pl-10 ${errors.firstName ? inputErr : inputOk}`}
              />
            </div>
            {errors.firstName && <p className="mt-1 text-xs text-red-500">{errors.firstName}</p>}
          </div>
          <div>
            <label className="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
              {copy.account.lastName} <span className="text-red-500">*</span>
            </label>
            <input
              type="text"
              value={data.lastName}
              onChange={(e) => onChange({ lastName: e.target.value })}
              placeholder={copy.account.placeholders.lastName}
              className={`${inputBase} ${errors.lastName ? inputErr : inputOk}`}
            />
            {errors.lastName && <p className="mt-1 text-xs text-red-500">{errors.lastName}</p>}
          </div>
        </div>

        {/* Email */}
        <div>
          <label className="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
            {copy.account.email} <span className="text-red-500">*</span>
          </label>
          <div className="relative">
            <Mail className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
            <input
              type="email"
              value={data.email}
              onChange={(e) => onChange({ email: e.target.value })}
              placeholder={copy.account.placeholders.email}
              className={`${inputBase} pl-10 ${errors.email ? inputErr : inputOk}`}
            />
          </div>
          {errors.email && <p className="mt-1 text-xs text-red-500">{errors.email}</p>}
        </div>

        {/* Company */}
        <div>
          <label className="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
            {copy.account.company} <span className="text-red-500">*</span>
          </label>
          <div className="relative">
            <Building2 className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
            <input
              type="text"
              value={data.company}
              onChange={(e) => onChange({ company: e.target.value })}
              placeholder={copy.account.placeholders.company}
              className={`${inputBase} pl-10 ${errors.company ? inputErr : inputOk}`}
            />
          </div>
          {errors.company && <p className="mt-1 text-xs text-red-500">{errors.company}</p>}
        </div>

        {/* Phone + Employees */}
        <div className="grid grid-cols-2 gap-3">
          <div>
            <label className="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
              {copy.account.phone}
            </label>
            <div className="relative">
              <Phone className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
              <input
                type="tel"
                value={data.phone}
                onChange={(e) => onChange({ phone: e.target.value })}
                placeholder={copy.account.placeholders.phone}
                className={`${inputBase} pl-10 ${inputOk}`}
              />
            </div>
          </div>
          <div>
            <label className="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
              <Users className="inline w-3.5 h-3.5 mr-1" />
              {copy.account.employees}
            </label>
            <select
              value={data.employees}
              onChange={(e) => onChange({ employees: e.target.value })}
              className={`${inputBase} ${inputOk}`}
            >
              <option value="">{copy.account.choose}</option>
              <option value="1-10">1-10</option>
              <option value="11-50">11-50</option>
              <option value="51-200">51-200</option>
              <option value="201-500">201-500</option>
              <option value="500+">500+</option>
            </select>
          </div>
        </div>
      </div>

      <button
        onClick={handleNext}
        className="mt-8 w-full flex items-center justify-center gap-2.5 py-4 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white font-black rounded-2xl hover:from-emerald-600 hover:to-cyan-600 transition-all duration-300 shadow-lg shadow-emerald-500/25 hover:scale-[1.01] active:scale-[0.99] text-base"
      >
        {copy.account.next}
        <ArrowRight className="w-5 h-5" />
      </button>
    </motion.div>
  );
}

/* ─────────────────────────────────────────────
   STEP 2 — PAYMENT (Sandbox)
───────────────────────────────────────────── */
function StepPayment({
  plan,
  billing,
  account,
  onBack,
}: {
  plan: PlanKey;
  billing: 'monthly' | 'annual';
  account: AccountData;
  onBack: () => void;
}) {
  const router = useRouter();
  const cfg = PLAN_CONFIG[plan];
  const price = billing === 'annual' ? cfg.priceAnnual : cfg.priceMonthly;
  const { locale } = useVitrineLocale();
  const copy = getCheckoutCopy(locale);

  const [cardNumber, setCardNumber] = useState(CHECKOUT_SANDBOX ? SANDBOX_CARD.number : '');
  const [expiry, setExpiry] = useState(CHECKOUT_SANDBOX ? SANDBOX_CARD.expiry : '');
  const [cvc, setCvc] = useState(CHECKOUT_SANDBOX ? SANDBOX_CARD.cvc : '');
  const [cardName, setCardName] = useState(
    account.firstName ? `${account.firstName} ${account.lastName}` : SANDBOX_CARD.name,
  );
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [checkoutUnavailable, setCheckoutUnavailable] = useState(false);
  const [sandboxFilled, setSandboxFilled] = useState(false);

  const isSandboxCard = cardNumber.replace(/\s/g, '') === '4242424242424242';

  function formatCardNumber(val: string) {
    return val
      .replace(/\D/g, '')
      .slice(0, 16)
      .replace(/(.{4})/g, '$1 ')
      .trim();
  }
  function formatExpiry(val: string) {
    const cleaned = val.replace(/\D/g, '').slice(0, 4);
    return cleaned.length >= 3 ? `${cleaned.slice(0, 2)}/${cleaned.slice(2)}` : cleaned;
  }

  function fillSandbox() {
    setCardNumber(SANDBOX_CARD.number);
    setExpiry(SANDBOX_CARD.expiry);
    setCvc(SANDBOX_CARD.cvc);
    setCardName(`${account.firstName || 'Test'} ${account.lastName || 'User'}`);
    setSandboxFilled(true);
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!cardNumber || !expiry || !cvc || !cardName) {
      setError(copy.payment.errors.fillAll);
      return;
    }

    setLoading(true);
    setError('');

    try {
      const origin = window.location.origin;
      const successUrl = `${origin}/checkout/success?lang=${locale}`;
      const cancelUrl = `${origin}/checkout?plan=${plan}&billing=${billing}&lang=${locale}`;

      const res = await fetch('/api/billing/checkout', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          plan,
          billing,
          email: account.email,
          company: account.company,
          first_name: account.firstName,
          last_name: account.lastName,
          phone: account.phone || undefined,
          employees: account.employees || undefined,
          locale: getCurrentLocale(),
          success_url: successUrl,
          cancel_url: cancelUrl,
        }),
      });

      const data = await res.json();

      if (data.success && data.checkout_url) {
        router.push(data.checkout_url);
      } else {
        if (data.error === 'CHECKOUT_UNAVAILABLE') {
          setCheckoutUnavailable(true);
        }
        setError(data.message || copy.payment.errors.generic);
      }
    } catch {
      setError(copy.payment.errors.network);
    } finally {
      setLoading(false);
    }
  }

  const inputBase =
    'w-full px-4 py-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm font-medium text-slate-900 dark:text-white outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 placeholder:text-slate-400';

  return (
    <motion.div
      initial={{ opacity: 0, x: 30 }}
      animate={{ opacity: 1, x: 0 }}
      exit={{ opacity: 0, x: -30 }}
      transition={{ duration: 0.3 }}
    >
      <button
        onClick={onBack}
        className="flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 mb-6 transition-colors"
      >
        <ArrowLeft className="w-4 h-4" /> {copy.back}
      </button>

      <h2 className="text-2xl font-black text-slate-900 dark:text-white mb-1">
        {copy.payment.title}
      </h2>

      {/* Sandbox notice (dev/staging only — never shown in production, #2628) */}
      {CHECKOUT_SANDBOX && (
      <div className="mt-3 mb-6 p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/50">
        <div className="flex items-start gap-3">
          <div className="w-8 h-8 rounded-xl bg-amber-400/20 flex items-center justify-center flex-shrink-0">
            <CreditCard className="w-4 h-4 text-amber-600 dark:text-amber-400" />
          </div>
          <div>
            <p className="text-sm font-bold text-amber-900 dark:text-amber-200 mb-1">
              {copy.payment.sandboxNoticeTitle}
            </p>
            <p className="text-xs text-amber-700 dark:text-amber-400 mb-2">
              {copy.payment.sandboxNoticeBody}
            </p>
            <button
              type="button"
              onClick={fillSandbox}
              className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-500 text-white text-xs font-black rounded-lg hover:bg-amber-600 transition-colors"
            >
              <Sparkles className="w-3 h-3" />
              {sandboxFilled ? copy.payment.filledTestCard : copy.payment.fillTestCard}
            </button>
            <div className="mt-2 font-mono text-xs text-amber-700 dark:text-amber-400 space-y-0.5">
              <p>{copy.payment.cardLabel} {SANDBOX_CARD.number}</p>
              <p>
                {copy.payment.expiryLabel} {SANDBOX_CARD.expiry} · {copy.payment.cvcLabel} : {SANDBOX_CARD.cvc}
              </p>
            </div>
          </div>
        </div>
      </div>
      )}

      <form onSubmit={handleSubmit} className="space-y-4">
        {/* Card number */}
        <div>
          <label className="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
            {copy.payment.cardLabel}
          </label>
          <div className="relative">
            <CreditCard className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
            <input
              type="text"
              value={cardNumber}
              onChange={(e) => setCardNumber(formatCardNumber(e.target.value))}
              placeholder={CHECKOUT_SANDBOX ? '4242 4242 4242 4242' : '1234 5678 9012 3456'}
              maxLength={19}
              className={`${inputBase} pl-10 font-mono ${isSandboxCard ? 'border-amber-400 ring-amber-500/10' : ''}`}
            />
            {isSandboxCard && (
              <span className="absolute right-3.5 top-1/2 -translate-y-1/2 text-[10px] font-black text-amber-600 bg-amber-100 px-1.5 py-0.5 rounded-full">
                {copy.payment.testBadge}
              </span>
            )}
          </div>
        </div>

        {/* Expiry + CVC */}
        <div className="grid grid-cols-2 gap-3">
          <div>
            <label className="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
              {copy.payment.expiryLabel}
            </label>
            <input
              type="text"
              value={expiry}
              onChange={(e) => setExpiry(formatExpiry(e.target.value))}
              placeholder="MM/AA"
              maxLength={5}
              className={`${inputBase} font-mono`}
            />
          </div>
          <div>
            <label className="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
              {copy.payment.cvcLabel}
            </label>
            <div className="relative">
              <Lock className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
              <input
                type="text"
                value={cvc}
                onChange={(e) => setCvc(e.target.value.replace(/\D/g, '').slice(0, 4))}
                placeholder="123"
                maxLength={4}
                className={`${inputBase} pl-10 font-mono`}
              />
            </div>
          </div>
        </div>

        {/* Card name */}
        <div>
          <label className="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
            {copy.payment.cardNameLabel}
          </label>
          <input
            type="text"
            value={cardName}
            onChange={(e) => setCardName(e.target.value)}
            placeholder={copy.payment.cardNamePlaceholder}
            className={inputBase}
          />
        </div>

        {/* Error */}
        {error && (
          <motion.p
            initial={{ opacity: 0, y: -8 }}
            animate={{ opacity: 1, y: 0 }}
            className="text-sm text-red-600 dark:text-red-400 font-medium bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-800/50 rounded-xl px-4 py-3"
          >
            {error}
          </motion.p>
        )}

        {/* Paiement indisponible : actions alternatives (#4952) */}
        {checkoutUnavailable && (
          <div className="rounded-2xl border border-amber-200 dark:border-amber-800/60 bg-amber-50 dark:bg-amber-950/30 px-4 py-4 text-sm">
            <p className="font-bold text-amber-800 dark:text-amber-300">{copy.checkoutUnavailableTitle}</p>
            <div className="mt-3 flex flex-wrap gap-2">
              <Link
                href="/demo"
                className="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white transition hover:bg-emerald-700"
              >
                {copy.checkoutUnavailableCtaTrial}
              </Link>
              <Link
                href="/contact"
                className="rounded-xl border border-amber-300 bg-white px-4 py-2 text-xs font-bold text-amber-800 transition hover:bg-amber-100 dark:bg-slate-900 dark:text-amber-300"
              >
                {copy.checkoutUnavailableCtaContact}
              </Link>
            </div>
          </div>
        )}

        {/* Summary */}
        <div className="p-4 rounded-2xl bg-transparent dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700">
          <div className="flex items-center justify-between text-sm">
            <span className="text-slate-600 dark:text-slate-400">{copy.payment.planRow.replace('{label}', copy.plans[plan].label)}</span>
            <span className="font-bold text-slate-900 dark:text-white">{copy.currencyLabel} {formatPrice(price, locale)}{copy.perMonth}</span>
          </div>
          <div className="flex items-center justify-between text-sm mt-1">
            <span className="text-slate-600 dark:text-slate-400">{copy.payment.freeTrialRow}</span>
            <span className="font-bold text-emerald-600">{cfg.trialDays} {copy.trialDaysUnit}</span>
          </div>
          <div className="border-t border-slate-200 dark:border-slate-700 mt-3 pt-3 flex items-center justify-between">
            <span className="font-bold text-slate-900 dark:text-white">{copy.payment.dueToday}</span>
            <span className="font-black text-lg text-emerald-600">{copy.success.zeroAmount}</span>
          </div>
        </div>

        {/* Submit */}
        <button
          type="submit"
          disabled={loading}
          className="w-full flex items-center justify-center gap-2.5 py-4 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white font-black rounded-2xl hover:from-emerald-600 hover:to-cyan-600 transition-all duration-300 shadow-lg shadow-emerald-500/25 hover:shadow-emerald-500/40 hover:scale-[1.01] active:scale-[0.99] text-base disabled:opacity-60 disabled:cursor-not-allowed disabled:hover:scale-100"
        >
          {loading ? (
            <>
              <motion.div
                animate={{ rotate: 360 }}
                transition={{ duration: 1, repeat: Infinity, ease: 'linear' }}
                className="w-5 h-5 border-2 border-white/30 border-t-white rounded-full"
              />
              {copy.payment.processing}
            </>
          ) : (
            <>
              <Lock className="w-4 h-4" />
              {copy.payment.submitCta}
              <ArrowRight className="w-5 h-5" />
            </>
          )}
        </button>

        <p className="text-center text-xs text-slate-400 dark:text-slate-500">
          {copy.payment.legal.prefix}{' '}
          <Link href="/terms" className="underline underline-offset-2 hover:text-slate-600">
            {copy.payment.legal.terms}
          </Link>{' '}
          {copy.payment.legal.and}{' '}
          <Link href="/privacy" className="underline underline-offset-2 hover:text-slate-600">
            {copy.payment.legal.privacy}
          </Link>
          {copy.payment.legal.suffix}
        </p>
      </form>
    </motion.div>
  );
}

/* ─────────────────────────────────────────────
   CHECKOUT INNER (uses useSearchParams)
───────────────────────────────────────────── */
function CheckoutInner() {
  const searchParams = useSearchParams();
  // Clés canoniques pilot/operations/enterprise (Closes #3247, #3919) ; les
  // anciens slugs (starter/business/scale/free) sont des alias doux pour la
  // compat des URLs (voir PLAN_ALIASES).
  const rawPlan = (searchParams.get('plan') || 'pilot') as string;
  const resolvedPlan = PLAN_ALIASES[rawPlan] ?? rawPlan;
  // #3326 : plan inconnu → fallback sur la clé canonique 'pilot' (#4209) —
  // 'starter' n'est pas une clé de PLAN_CONFIG et provoquait un TypeError.
  const plan: PlanKey = (resolvedPlan in PLAN_CONFIG ? resolvedPlan : 'pilot') as PlanKey;
  const rawBilling = searchParams.get('billing') as 'monthly' | 'annual' | null;
  const { direction } = useVitrineLocale();

  const cfg = PLAN_CONFIG[plan];
  const totalSteps = 3;
  const { locale } = useVitrineLocale();
  const copy = getCheckoutCopy(locale);
  const stepLabels = [copy.steps.recap, copy.steps.account, copy.steps.payment];

  const { isDark, toggleDarkMode } = useDarkMode();
  const [step, setStep] = useState(0);
  const [billing, setBilling] = useState<'monthly' | 'annual'>(rawBilling ?? 'annual');
  const [account, setAccount] = useState<AccountData>({
    firstName: '',
    lastName: '',
    email: searchParams.get('email') || '',
    company: searchParams.get('company') || '',
    phone: '',
    employees: '',
  });

  // Sync billing from URL
  useEffect(() => {
    if (rawBilling) setBilling(rawBilling);
  }, [rawBilling]);

  // #4195 : le plan Free existe au backend (#2977) et la vitrine le vend
  // (#3883) — une URL profonde ?plan=free ne doit pas présenter un paywall
  // Pilot silencieux. État « essai guidé » explicite vers /signup.
  if (rawPlan === 'free') {
    return (
      <div dir={direction} className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-transparent'}`}>
        <Navbar isDark={isDark} onToggleDark={toggleDarkMode} />
        <main className="py-24 px-4 sm:px-6 lg:px-8">
          <div className="max-w-xl mx-auto text-center">
            <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-emerald-500/[0.08] border border-emerald-500/15 text-emerald-700 dark:text-emerald-400 text-sm font-semibold mb-6">
              {copy.free.badge}
            </div>
            <h1 className="text-3xl sm:text-4xl font-black text-slate-900 dark:text-white mb-4">
              {copy.free.title}
            </h1>
            <p className="text-slate-500 dark:text-slate-400 mb-8">
              {copy.free.body}
            </p>
            <Link
              href="/signup?source=checkout_plan_free"
              className="inline-flex items-center gap-3 px-8 py-4 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white text-lg font-bold rounded-2xl hover:from-emerald-600 hover:to-emerald-700 transition-all shadow-xl shadow-emerald-500/20"
            >
              {copy.free.cta}
            </Link>
            <p className="mt-6 text-sm text-slate-500 dark:text-slate-400">
              <Link href="/pricing" className="text-emerald-600 dark:text-emerald-400 hover:underline">
                {copy.free.seePricing}
              </Link>
            </p>
          </div>
        </main>
        <Footer />
      </div>
    );
  }

  return (
    <div
      dir={direction}
      className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-transparent'}`}
    >
      <Navbar isDark={isDark} onToggleDark={toggleDarkMode} />

      <main className="py-16 px-4 sm:px-6 lg:px-8">
        <div className="max-w-5xl mx-auto">
          {/* Back to pricing */}
          <Link
            href="/pricing"
            className="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 mb-8 transition-colors"
          >
            <ArrowLeft className="w-4 h-4" />
            {copy.backToPricing}
          </Link>

          <StepIndicator step={step} total={totalSteps} stepLabels={stepLabels} />

          <div className="grid grid-cols-1 lg:grid-cols-[1fr_400px] gap-10 items-start">
            {/* Left — Wizard */}
            <div className="bg-white dark:bg-slate-900 rounded-3xl p-8 shadow-xl shadow-slate-100/50 dark:shadow-slate-950/50 border border-slate-200 dark:border-slate-800">
              <AnimatePresence mode="wait">
                {step === 0 && (
                  <StepRecap
                    key="recap"
                    plan={plan}
                    billing={billing}
                    onChangeBilling={setBilling}
                    onNext={() => setStep(1)}
                  />
                )}
                {step === 1 && (
                  <StepAccount
                    key="account"
                    data={account}
                    onChange={(d) => setAccount((prev) => ({ ...prev, ...d }))}
                    onNext={() => setStep(2)}
                    onBack={() => setStep(0)}
                  />
                )}
                {step === 2 && (
                  <StepPayment
                    key="payment"
                    plan={plan}
                    billing={billing}
                    account={account}
                    onBack={() => setStep(1)}
                  />
                )}
              </AnimatePresence>
            </div>

            {/* Right — Summary (desktop) */}
            <div className="hidden lg:block sticky top-24">
              <PlanSummaryCard plan={plan} billing={billing} onChangeBilling={setBilling} />
              <TrustBadges />
            </div>
          </div>
        </div>
      </main>

      <Footer />
    </div>
  );
}

/* ─────────────────────────────────────────────
   PAGE EXPORT
───────────────────────────────────────────── */
export default function CheckoutPage() {
  return (
    <Suspense
      fallback={
        <div className="min-h-screen flex items-center justify-center bg-transparent">
          <motion.div
            animate={{ rotate: 360 }}
            transition={{ duration: 1, repeat: Infinity, ease: 'linear' }}
            className="w-10 h-10 border-4 border-emerald-500/20 border-t-emerald-500 rounded-full"
          />
        </div>
      }
    >
      <CheckoutInner />
    </Suspense>
  );
}

