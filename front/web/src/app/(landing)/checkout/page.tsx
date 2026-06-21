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
import { Navbar, Footer } from '@/modules/vitrine';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';

/* ─────────────────────────────────────────────
   PLAN CONFIG
───────────────────────────────────────────── */
const PLAN_CONFIG = {
  pilot: {
    label: 'Pilot',
    icon: Rocket,
    color: 'blue',
    gradient: 'from-blue-500 to-indigo-600',
    priceMonthly: 29,
    priceAnnual: 24,
    savings: 60,
    features: [
      'Pointage web & mobile',
      'Absences & congés',
      'Dossiers employés',
      'Dashboard manager',
      'Apps Employee & Manager',
      'Support email 48h',
    ],
    trialDays: 30,
    employeeLimit: '1-30 employés',
  },
  starter: {
    label: 'Pilot',
    icon: Rocket,
    color: 'blue',
    gradient: 'from-blue-500 to-indigo-600',
    priceMonthly: 29,
    priceAnnual: 24,
    savings: 60,
    features: [
      'Pointage web & mobile',
      'Absences & congés',
      'Dossiers employés',
      'Dashboard manager',
      'Apps Employee & Manager',
      'Support email 48h',
    ],
    trialDays: 30,
    employeeLimit: '1-30 employés',
  },
  business: {
    label: 'Operations',
    icon: Zap,
    color: 'emerald',
    gradient: 'from-emerald-500 to-cyan-600',
    priceMonthly: 99,
    priceAnnual: 79,
    savings: 240,
    features: [
      'Tout Pilot inclus',
      'Paie automatisée',
      'Biométrie ZKTeco',
      'API & Webhooks',
      'Exports comptables',
      'Support prioritaire 24h',
    ],
    trialDays: 30,
    employeeLimit: '15-250 employés',
  },
  operations: {
    label: 'Operations',
    icon: Zap,
    color: 'emerald',
    gradient: 'from-emerald-500 to-cyan-600',
    priceMonthly: 99,
    priceAnnual: 79,
    savings: 240,
    features: [
      'Tout Pilot inclus',
      'Paie automatisée',
      'Biométrie ZKTeco',
      'API & Webhooks',
      'Exports comptables',
      'Support prioritaire 24h',
    ],
    trialDays: 30,
    employeeLimit: '15-250 employés',
  },
  enterprise: {
    label: 'Scale',
    icon: Building2,
    color: 'violet',
    gradient: 'from-violet-500 to-fuchsia-600',
    priceMonthly: 299,
    priceAnnual: 239,
    savings: 720,
    features: [
      'Tout Operations inclus',
      'Multi-pays & multi-devises',
      'SSO SAML/OIDC',
      'Audit trail immuable',
      'Schema PostgreSQL isolé',
      'Account manager dédié',
    ],
    trialDays: 30,
    employeeLimit: '250+ employés',
  },
  scale: {
    label: 'Scale',
    icon: Building2,
    color: 'violet',
    gradient: 'from-violet-500 to-fuchsia-600',
    priceMonthly: 299,
    priceAnnual: 239,
    savings: 720,
    features: [
      'Tout Operations inclus',
      'Multi-pays & multi-devises',
      'SSO SAML/OIDC',
      'Audit trail immuable',
      'Schema PostgreSQL isolé',
      'Account manager dédié',
    ],
    trialDays: 30,
    employeeLimit: '250+ employés',
  },
} as const;

type PlanKey = keyof typeof PLAN_CONFIG;

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
   STEP INDICATOR
───────────────────────────────────────────── */
function StepIndicator({ step, total }: { step: number; total: number }) {
  const labels = ['Récapitulatif', 'Compte', 'Paiement'];
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
            {labels[i]}
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
  const price = billing === 'annual' ? cfg.priceAnnual : cfg.priceMonthly;

  return (
    <div className="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden shadow-xl shadow-slate-100/50 dark:shadow-slate-950/50">
      {/* Header */}
      <div className={`bg-gradient-to-r ${cfg.gradient} p-6`}>
        <div className="flex items-center gap-3 mb-4">
          <div className="w-10 h-10 rounded-2xl bg-white/20 flex items-center justify-center">
            <Icon className="w-5 h-5 text-white" />
          </div>
          <div>
            <p className="text-white/80 text-xs font-semibold uppercase tracking-wider">Plan choisi</p>
            <h3 className="text-white font-black text-xl">{cfg.label}</h3>
          </div>
        </div>
        <div className="flex items-baseline gap-1">
          <span className="text-white/70 text-sm">EUR</span>
          <span className="text-white font-black text-5xl">{price}</span>
          <span className="text-white/70 text-sm">/mois</span>
        </div>
        {billing === 'annual' && (
          <p className="text-white/70 text-xs mt-1">Facturé annuellement — économisez EUR {cfg.savings}/an</p>
        )}
      </div>

      {/* Billing toggle */}
      <div className="p-4 bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700">
        <div className="flex rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700">
          <button
            onClick={() => onChangeBilling('monthly')}
            className={`flex-1 py-2.5 text-sm font-bold transition-all duration-200 ${
              billing === 'monthly'
                ? 'bg-slate-900 dark:bg-white text-white dark:text-slate-900'
                : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
            }`}
          >
            Mensuel
          </button>
          <button
            onClick={() => onChangeBilling('annual')}
            className={`flex-1 py-2.5 text-sm font-bold transition-all duration-200 ${
              billing === 'annual'
                ? 'bg-emerald-500 text-white'
                : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
            }`}
          >
            Annuel
            <span className="ml-1.5 text-[10px] font-black">-20%</span>
          </button>
        </div>
      </div>

      {/* Features */}
      <ul className="p-5 space-y-2.5">
        {cfg.features.map((f) => (
          <li key={f} className="flex items-center gap-2.5 text-sm text-slate-700 dark:text-slate-300">
            <Check className="w-4 h-4 text-emerald-500 flex-shrink-0" />
            {f}
          </li>
        ))}
      </ul>

      {/* Trial badge */}
      <div className="px-5 pb-5">
        <div className="flex items-center gap-2 px-4 py-3 rounded-2xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-900/50">
          <Sparkles className="w-4 h-4 text-emerald-600 dark:text-emerald-400 flex-shrink-0" />
          <p className="text-sm font-semibold text-emerald-800 dark:text-emerald-300">
            {cfg.trialDays} jours gratuits inclus · Aucune CB débitée avant la fin de l'essai
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
  return (
    <div className="mt-6 space-y-2">
      {[
        { icon: Lock, text: 'Paiement sécurisé TLS 1.3 + AES-256' },
        { icon: ShieldCheck, text: 'Données hébergées en Europe — conforme RGPD' },
        { icon: Shield, text: 'Sans engagement · Résiliation en 2 clics' },
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

  return (
    <motion.div
      initial={{ opacity: 0, x: 30 }}
      animate={{ opacity: 1, x: 0 }}
      exit={{ opacity: 0, x: -30 }}
      transition={{ duration: 0.3 }}
    >
      <h2 className="text-2xl font-black text-slate-900 dark:text-white mb-2">
        Votre plan sélectionné
      </h2>
      <p className="text-slate-500 dark:text-slate-400 mb-8">
        Vérifiez les détails avant de créer votre compte.
      </p>

      <PlanSummaryCard plan={plan} billing={billing} onChangeBilling={onChangeBilling} />

      <div className="mt-6 p-4 rounded-2xl bg-blue-50 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-900/50 text-sm text-blue-800 dark:text-blue-300">
        <strong>Essai gratuit de {cfg.trialDays} jours.</strong> Votre carte ne sera débitée qu'après la période d'essai. Annulez à tout moment depuis votre tableau de bord.
      </div>

      <div className="mt-6 text-center text-sm text-slate-500 dark:text-slate-400">
        <span>Mauvais plan ? </span>
        <Link href="/pricing" className="font-semibold text-emerald-600 hover:text-emerald-700 underline underline-offset-2">
          Voir tous les plans
        </Link>
      </div>

      <button
        onClick={onNext}
        className="mt-8 w-full flex items-center justify-center gap-2.5 py-4 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white font-black rounded-2xl hover:from-emerald-600 hover:to-cyan-600 transition-all duration-300 shadow-lg shadow-emerald-500/25 hover:shadow-emerald-500/40 hover:scale-[1.01] active:scale-[0.99] text-base"
      >
        Continuer — EUR {price}/mois
        <ArrowRight className="w-5 h-5" />
      </button>
    </motion.div>
  );
}

/* ─────────────────────────────────────────────
   STEP 1 — ACCOUNT
───────────────────────────────────────────── */
type AccountData = {
  firstName: string;
  lastName: string;
  email: string;
  company: string;
  phone: string;
  employees: string;
};

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
  const [errors, setErrors] = useState<Partial<AccountData>>({});

  function validate(): boolean {
    const e: Partial<AccountData> = {};
    if (!data.firstName.trim()) e.firstName = 'Prénom requis';
    if (!data.lastName.trim()) e.lastName = 'Nom requis';
    if (!data.email.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email))
      e.email = 'Email professionnel valide requis';
    if (!data.company.trim() || data.company.length < 2)
      e.company = 'Nom de société requis';
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
        <ArrowLeft className="w-4 h-4" /> Retour
      </button>

      <h2 className="text-2xl font-black text-slate-900 dark:text-white mb-2">
        Créez votre compte
      </h2>
      <p className="text-slate-500 dark:text-slate-400 mb-8">
        Votre espace Leopardo sera prêt en quelques secondes.
      </p>

      <div className="space-y-4">
        {/* Name */}
        <div className="grid grid-cols-2 gap-3">
          <div>
            <label className="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
              Prénom <span className="text-red-500">*</span>
            </label>
            <div className="relative">
              <User className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
              <input
                type="text"
                value={data.firstName}
                onChange={(e) => onChange({ firstName: e.target.value })}
                placeholder="Marie"
                className={`${inputBase} pl-10 ${errors.firstName ? inputErr : inputOk}`}
              />
            </div>
            {errors.firstName && <p className="mt-1 text-xs text-red-500">{errors.firstName}</p>}
          </div>
          <div>
            <label className="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
              Nom <span className="text-red-500">*</span>
            </label>
            <input
              type="text"
              value={data.lastName}
              onChange={(e) => onChange({ lastName: e.target.value })}
              placeholder="Dupont"
              className={`${inputBase} ${errors.lastName ? inputErr : inputOk}`}
            />
            {errors.lastName && <p className="mt-1 text-xs text-red-500">{errors.lastName}</p>}
          </div>
        </div>

        {/* Email */}
        <div>
          <label className="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
            Email professionnel <span className="text-red-500">*</span>
          </label>
          <div className="relative">
            <Mail className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
            <input
              type="email"
              value={data.email}
              onChange={(e) => onChange({ email: e.target.value })}
              placeholder="marie@societe.com"
              className={`${inputBase} pl-10 ${errors.email ? inputErr : inputOk}`}
            />
          </div>
          {errors.email && <p className="mt-1 text-xs text-red-500">{errors.email}</p>}
        </div>

        {/* Company */}
        <div>
          <label className="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
            Société <span className="text-red-500">*</span>
          </label>
          <div className="relative">
            <Building2 className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
            <input
              type="text"
              value={data.company}
              onChange={(e) => onChange({ company: e.target.value })}
              placeholder="Nom de votre entreprise"
              className={`${inputBase} pl-10 ${errors.company ? inputErr : inputOk}`}
            />
          </div>
          {errors.company && <p className="mt-1 text-xs text-red-500">{errors.company}</p>}
        </div>

        {/* Phone + Employees */}
        <div className="grid grid-cols-2 gap-3">
          <div>
            <label className="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
              Téléphone
            </label>
            <div className="relative">
              <Phone className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
              <input
                type="tel"
                value={data.phone}
                onChange={(e) => onChange({ phone: e.target.value })}
                placeholder="+33 6 00 00 00 00"
                className={`${inputBase} pl-10 ${inputOk}`}
              />
            </div>
          </div>
          <div>
            <label className="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
              <Users className="inline w-3.5 h-3.5 mr-1" />
              Effectif
            </label>
            <select
              value={data.employees}
              onChange={(e) => onChange({ employees: e.target.value })}
              className={`${inputBase} ${inputOk}`}
            >
              <option value="">Choisir</option>
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
        Passer au paiement
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

  const [cardNumber, setCardNumber] = useState(SANDBOX_CARD.number);
  const [expiry, setExpiry] = useState(SANDBOX_CARD.expiry);
  const [cvc, setCvc] = useState(SANDBOX_CARD.cvc);
  const [cardName, setCardName] = useState(account.firstName ? `${account.firstName} ${account.lastName}` : SANDBOX_CARD.name);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [sandboxFilled, setSandboxFilled] = useState(false);

  const isSandboxCard =
    cardNumber.replace(/\s/g, '') === '4242424242424242';

  function formatCardNumber(val: string) {
    return val.replace(/\D/g, '').slice(0, 16).replace(/(.{4})/g, '$1 ').trim();
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
      setError('Veuillez remplir tous les champs de paiement.');
      return;
    }

    setLoading(true);
    setError('');

    try {
      const origin = window.location.origin;
      const successUrl = `${origin}/checkout/success`;
      const cancelUrl = `${origin}/checkout?plan=${plan}&billing=${billing}`;

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
          locale: 'fr',
          success_url: successUrl,
          cancel_url: cancelUrl,
        }),
      });

      const data = await res.json();

      if (data.success && data.checkout_url) {
        router.push(data.checkout_url);
      } else {
        setError(data.message || 'Erreur lors du traitement du paiement.');
      }
    } catch {
      setError('Impossible de contacter le serveur. Vérifiez votre connexion.');
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
        <ArrowLeft className="w-4 h-4" /> Retour
      </button>

      <h2 className="text-2xl font-black text-slate-900 dark:text-white mb-1">
        Informations de paiement
      </h2>

      {/* Sandbox notice */}
      <div className="mt-3 mb-6 p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/50">
        <div className="flex items-start gap-3">
          <div className="w-8 h-8 rounded-xl bg-amber-400/20 flex items-center justify-center flex-shrink-0">
            <CreditCard className="w-4 h-4 text-amber-600 dark:text-amber-400" />
          </div>
          <div>
            <p className="text-sm font-bold text-amber-900 dark:text-amber-200 mb-1">
              Mode test activé — Aucune carte réelle débitée
            </p>
            <p className="text-xs text-amber-700 dark:text-amber-400 mb-2">
              Les Stripe Price IDs ne sont pas encore configurés. Utilisez la carte de test ci-dessous.
            </p>
            <button
              type="button"
              onClick={fillSandbox}
              className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-500 text-white text-xs font-black rounded-lg hover:bg-amber-600 transition-colors"
            >
              <Sparkles className="w-3 h-3" />
              {sandboxFilled ? '✓ Carte test remplie' : 'Remplir avec la carte test'}
            </button>
            <div className="mt-2 font-mono text-xs text-amber-700 dark:text-amber-400 space-y-0.5">
              <p>Carte : {SANDBOX_CARD.number}</p>
              <p>Expiry : {SANDBOX_CARD.expiry} · CVC : {SANDBOX_CARD.cvc}</p>
            </div>
          </div>
        </div>
      </div>

      <form onSubmit={handleSubmit} className="space-y-4">
        {/* Card number */}
        <div>
          <label className="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
            Numéro de carte
          </label>
          <div className="relative">
            <CreditCard className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
            <input
              type="text"
              value={cardNumber}
              onChange={(e) => setCardNumber(formatCardNumber(e.target.value))}
              placeholder="4242 4242 4242 4242"
              maxLength={19}
              className={`${inputBase} pl-10 font-mono ${isSandboxCard ? 'border-amber-400 ring-amber-500/10' : ''}`}
            />
            {isSandboxCard && (
              <span className="absolute right-3.5 top-1/2 -translate-y-1/2 text-[10px] font-black text-amber-600 bg-amber-100 px-1.5 py-0.5 rounded-full">
                TEST
              </span>
            )}
          </div>
        </div>

        {/* Expiry + CVC */}
        <div className="grid grid-cols-2 gap-3">
          <div>
            <label className="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
              Date d'expiration
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
              CVC
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
            Nom sur la carte
          </label>
          <input
            type="text"
            value={cardName}
            onChange={(e) => setCardName(e.target.value)}
            placeholder="Marie Dupont"
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

        {/* Summary */}
        <div className="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700">
          <div className="flex items-center justify-between text-sm">
            <span className="text-slate-600 dark:text-slate-400">Plan {cfg.label}</span>
            <span className="font-bold text-slate-900 dark:text-white">EUR {price}/mois</span>
          </div>
          <div className="flex items-center justify-between text-sm mt-1">
            <span className="text-slate-600 dark:text-slate-400">Essai gratuit</span>
            <span className="font-bold text-emerald-600">{cfg.trialDays} jours</span>
          </div>
          <div className="border-t border-slate-200 dark:border-slate-700 mt-3 pt-3 flex items-center justify-between">
            <span className="font-bold text-slate-900 dark:text-white">Dû aujourd'hui</span>
            <span className="font-black text-lg text-emerald-600">EUR 0,00</span>
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
              Traitement en cours...
            </>
          ) : (
            <>
              <Lock className="w-4 h-4" />
              Démarrer l'essai gratuit — EUR 0,00 dû maintenant
              <ArrowRight className="w-5 h-5" />
            </>
          )}
        </button>

        <p className="text-center text-xs text-slate-400 dark:text-slate-500">
          En confirmant, vous acceptez nos{' '}
          <Link href="/terms" className="underline underline-offset-2 hover:text-slate-600">
            conditions d'utilisation
          </Link>{' '}
          et notre{' '}
          <Link href="/privacy" className="underline underline-offset-2 hover:text-slate-600">
            politique de confidentialité
          </Link>.
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
  const rawPlan = (searchParams.get('plan') || 'business') as string;
  const plan: PlanKey = (rawPlan in PLAN_CONFIG ? rawPlan : 'business') as PlanKey;
  const rawBilling = searchParams.get('billing') as 'monthly' | 'annual' | null;
  const { direction } = useVitrineLocale();

  const [isDark, setIsDark] = useState(false);
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

  return (
    <div
      dir={direction}
      className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-slate-50'}`}
    >
      <Navbar isDark={isDark} onToggleDark={() => setIsDark(!isDark)} />

      <main className="py-16 px-4 sm:px-6 lg:px-8">
        <div className="max-w-5xl mx-auto">
          {/* Back to pricing */}
          <Link
            href="/pricing"
            className="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 mb-8 transition-colors"
          >
            <ArrowLeft className="w-4 h-4" />
            Retour aux tarifs
          </Link>

          <StepIndicator step={step} total={3} />

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
        <div className="min-h-screen flex items-center justify-center bg-slate-50">
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
