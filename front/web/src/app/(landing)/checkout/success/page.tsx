'use client';

import { Suspense, useEffect, useState } from 'react';
import { useDarkMode } from '@/modules/vitrine/hooks/useDarkMode';
import { useSearchParams } from 'next/navigation';
import Link from 'next/link';
import { motion } from 'framer-motion';
import {
  ArrowRight,
  CheckCircle,
  ClipboardCopy,
  Download,
  ExternalLink,
  LogIn,
  Mail,
  Rocket,
  Smartphone,
  Sparkles,
} from 'lucide-react';
import { Navbar, Footer } from '@/modules/vitrine';
import { DEFAULT_BACKEND_API_URL } from '@/lib/backend-url';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';
import { getCheckoutCopy } from '@/modules/vitrine/data/checkout';

/* ─────────────────────────────────────────────
   CONFETTI (lightweight CSS-based)
───────────────────────────────────────────── */
function Confetti() {
  // Issue #3925 : les valeurs aléatoires sont calculées UNE fois au premier
  // rendu client (useState initializer) — plus de Math.random() dans le
  // rendu, donc plus de mismatch SSR/hydration sur les styles inline.
  const [pieces] = useState(() =>
    Array.from({ length: 24 }, (_, i) => ({
      id: i,
      x: `${Math.random() * 100}vw`,
      scale: Math.random() * 0.8 + 0.4,
      rotate: Math.random() * 720 - 360,
      duration: Math.random() * 2 + 2,
      delay: Math.random() * 1.5,
    }))
  );
  const colors = ['bg-emerald-400', 'bg-cyan-400', 'bg-violet-400', 'bg-amber-400', 'bg-pink-400'];
  return (
    <div className="absolute inset-0 overflow-hidden pointer-events-none" aria-hidden>
      {pieces.map((p) => (
        <motion.div
          key={p.id}
          className={`absolute w-2 h-2 rounded-full ${colors[p.id % colors.length]}`}
          initial={{
            x: p.x,
            y: -20,
            opacity: 1,
            scale: p.scale,
            rotate: 0,
          }}
          animate={{
            y: '110vh',
            opacity: [1, 1, 0],
            rotate: p.rotate,
          }}
          transition={{
            duration: p.duration,
            delay: p.delay,
            ease: 'easeIn',
          }}
        />
      ))}
    </div>
  );
}

/* ─────────────────────────────────────────────
   NEXT STEPS (icônes/couleurs structurelles ; textes localisés
   via getCheckoutCopy(locale).success.nextSteps — issue #4185)
───────────────────────────────────────────── */
const NEXT_STEP_META = [
  {
    icon: LogIn,
    color: 'emerald',
    // Issue #2234 (T010) : l'URL API Laravel renvoyait JSON/404 —
    // la route web du portail client est /auth/login.
    href: '/auth/login',
    external: false,
  },
  {
    icon: Smartphone,
    color: 'blue',
    href: '/download',
    external: false,
  },
  {
    icon: Mail,
    color: 'violet',
    href: '/auth/login',
    external: false,
  },
];

/* ─────────────────────────────────────────────
   SUCCESS INNER
───────────────────────────────────────────── */
function SuccessInner() {
  const searchParams = useSearchParams();
  const { direction, locale } = useVitrineLocale();
  const copy = getCheckoutCopy(locale);
  const nextSteps = copy.success.nextSteps.map((step, i) => ({ ...NEXT_STEP_META[i], ...step }));

  const isSandbox = searchParams.get('sandbox') === '1';
  const sessionId = searchParams.get('session_id') || '';
  // #3919 : affiche le nom canonique du plan (Pilot/Operations/Enterprise)
  // quel que soit le slug (pilot/operations/enterprise ou alias legacy).
  const PLAN_DISPLAY: Record<string, string> = {
    free: 'Free',
    pilot: 'Pilot',
    starter: 'Pilot',
    operations: 'Operations',
    business: 'Operations',
    enterprise: 'Enterprise',
    scale: 'Enterprise',
  };
  const plan = PLAN_DISPLAY[searchParams.get('plan') || ''] || '';
  const billing = searchParams.get('billing') || 'monthly';
  const email = searchParams.get('email') || '';
  const company = searchParams.get('company') || '';
  const amount = searchParams.get('amount');
  const price = amount ? (parseInt(amount, 10) / 100).toFixed(2) : null;

  const { isDark, toggleDarkMode } = useDarkMode();
  const [copied, setCopied] = useState(false);
  const [showConfetti, setShowConfetti] = useState(true);

  useEffect(() => {
    const t = setTimeout(() => setShowConfetti(false), 4500);
    return () => clearTimeout(t);
  }, []);

  async function copySessionId() {
    try {
      await navigator.clipboard.writeText(sessionId);
    } catch {
      const ta = document.createElement('textarea');
      ta.value = sessionId;
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
    }
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  }

  return (
    <div
      dir={direction}
      className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}
    >
      {showConfetti && <Confetti />}
      <Navbar isDark={isDark} onToggleDark={toggleDarkMode} />

      <main className="relative py-24 px-4 sm:px-6 lg:px-8 overflow-hidden">
        {/* Background glow */}
        <div className="absolute inset-0 pointer-events-none">
          <div className="absolute top-0 left-1/2 -translate-x-1/2 w-[800px] h-[400px] bg-emerald-400/10 rounded-full blur-[120px]" />
        </div>

        <div className="relative max-w-3xl mx-auto text-center">
          {/* Success icon */}
          <motion.div
            initial={{ scale: 0, opacity: 0 }}
            animate={{ scale: 1, opacity: 1 }}
            transition={{ type: 'spring', stiffness: 300, damping: 20, delay: 0.1 }}
            className="inline-flex items-center justify-center w-24 h-24 rounded-full bg-gradient-to-br from-emerald-400 to-emerald-600 shadow-2xl shadow-emerald-500/40 mb-8"
          >
            <CheckCircle className="w-12 h-12 text-white" />
          </motion.div>

          {/* Title */}
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.3 }}
          >
            <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-700 dark:text-emerald-400 text-sm font-semibold mb-6">
              <Sparkles className="w-3.5 h-3.5" />
              {isSandbox ? copy.success.badgeSandbox : copy.success.badgePaid}
            </div>

            <h1 className="text-4xl sm:text-5xl font-black text-slate-900 dark:text-white mb-4 tracking-tight">
              {copy.success.title}{' '}
              <span className="bg-gradient-to-r from-emerald-500 to-cyan-500 bg-clip-text text-transparent">
                {copy.success.titleAccent}
              </span>
            </h1>

            <p className="text-xl text-slate-500 dark:text-slate-400 mb-10 max-w-xl mx-auto leading-relaxed">
              {copy.success.subtitle
                .replace('{company}', company)
                .replace('{plan}', plan)
                .replace('{period}', billing === 'annual' ? copy.success.periodAnnual : copy.success.periodMonthly)}
            </p>
          </motion.div>

          {/* Confirmation card */}
          <motion.div
            initial={{ opacity: 0, y: 30 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.5 }}
            className="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-xl shadow-slate-100/50 dark:shadow-slate-950/50 p-6 mb-10 text-left"
          >
            <div className="flex items-center gap-3 mb-5">
              <div className="w-10 h-10 rounded-2xl bg-emerald-500/10 flex items-center justify-center">
                <Rocket className="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
              </div>
              <div>
                <h2 className="font-black text-slate-900 dark:text-white">{copy.success.cardTitle}</h2>
                <p className="text-sm text-slate-500 dark:text-slate-400">{copy.success.cardSubtitle}</p>
              </div>
            </div>

            <div className="space-y-3">
              {email && (
                <div className="flex items-center justify-between py-3 border-b border-slate-100 dark:border-slate-800">
                  <span className="text-sm text-slate-500 dark:text-slate-400">{copy.success.emailRow}</span>
                  <span className="text-sm font-bold text-slate-900 dark:text-white">{email}</span>
                </div>
              )}
              <div className="flex items-center justify-between py-3 border-b border-slate-100 dark:border-slate-800">
                <span className="text-sm text-slate-500 dark:text-slate-400">{copy.success.planRow}</span>
                <span className="text-sm font-bold text-slate-900 dark:text-white">{plan}</span>
              </div>
              <div className="flex items-center justify-between py-3 border-b border-slate-100 dark:border-slate-800">
                <span className="text-sm text-slate-500 dark:text-slate-400">{copy.success.trialPeriodRow}</span>
                <span className="text-sm font-bold text-emerald-600">{copy.success.trialValue}</span>
              </div>
              <div className="flex items-center justify-between py-3 border-b border-slate-100 dark:border-slate-800">
                <span className="text-sm text-slate-500 dark:text-slate-400">{copy.success.chargedTodayRow}</span>
                <span className="text-sm font-black text-emerald-600">{copy.success.zeroAmount}</span>
              </div>
              {isSandbox && (
                <div className="flex items-center justify-between py-3 border-b border-slate-100 dark:border-slate-800">
                  <span className="text-sm text-slate-500 dark:text-slate-400">{copy.success.modeRow}</span>
                  <span className="text-xs font-black text-amber-600 bg-amber-100 dark:bg-amber-900/30 px-2 py-1 rounded-full">
                    {copy.success.sandboxBadge}
                  </span>
                </div>
              )}
              {sessionId && (
                <div className="flex items-center justify-between py-3">
                  <span className="text-sm text-slate-500 dark:text-slate-400">{copy.success.sessionRow}</span>
                  <div className="flex items-center gap-2">
                    <span className="text-xs font-mono text-slate-600 dark:text-slate-400 truncate max-w-[160px]">
                      {sessionId.slice(0, 24)}…
                    </span>
                    <button
                      onClick={copySessionId}
                      className="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                      title={copy.success.copyTitle}
                    >
                      <ClipboardCopy className="w-3.5 h-3.5" />
                    </button>
                    {copied && <span className="text-xs text-emerald-600 font-semibold">{copy.success.copied}</span>}
                  </div>
                </div>
              )}
            </div>
          </motion.div>

          {/* Email notice */}
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.65 }}
            className="flex items-center gap-3 p-4 rounded-2xl bg-blue-50 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-800/50 text-left mb-10"
          >
            <Mail className="w-5 h-5 text-blue-600 dark:text-blue-400 flex-shrink-0" />
            <p className="text-sm text-blue-800 dark:text-blue-300">
              {copy.success.emailNotice.replace('{email}', email || copy.success.emailFallback)}
            </p>
          </motion.div>

          {/* Next steps */}
          <motion.div
            initial={{ opacity: 0, y: 30 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.7, delay: 0.8 }}
          >
            <h2 className="text-2xl font-black text-slate-900 dark:text-white mb-6 text-left">
              {copy.success.nextStepsTitle}
            </h2>
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-10">
              {nextSteps.map((step, i) => {
                const Icon = step.icon;
                const colorMap: Record<string, string> = {
                  emerald: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
                  blue: 'bg-blue-500/10 text-blue-600 dark:text-blue-400',
                  violet: 'bg-violet-500/10 text-violet-600 dark:text-violet-400',
                };
                return (
                  <motion.div
                    key={step.title}
                    initial={{ opacity: 0, y: 20 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.5, delay: 0.9 + i * 0.1 }}
                    className="group bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5 text-left hover:shadow-lg hover:shadow-slate-100/50 dark:hover:shadow-slate-950/50 hover:-translate-y-1 transition-all duration-300"
                  >
                    <div className={`inline-flex items-center justify-center w-10 h-10 rounded-xl mb-4 ${colorMap[step.color]}`}>
                      <Icon className="w-5 h-5" />
                    </div>
                    <h3 className="font-bold text-slate-900 dark:text-white text-sm mb-2">{step.title}</h3>
                    <p className="text-xs text-slate-500 dark:text-slate-400 mb-4 leading-relaxed">{step.desc}</p>
                    {step.external ? (
                      <a
                        href={step.href}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 transition-colors"
                      >
                        {step.cta}
                        <ExternalLink className="w-3 h-3" />
                      </a>
                    ) : (
                      <Link
                        href={step.href}
                        className="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 transition-colors"
                      >
                        {step.cta}
                        <ArrowRight className="w-3 h-3" />
                      </Link>
                    )}
                  </motion.div>
                );
              })}
            </div>
          </motion.div>

          {/* Primary CTA */}
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 1.2 }}
            className="flex flex-col sm:flex-row gap-4 justify-center"
          >
            <Link
              href="/auth/login"
              className="group flex items-center justify-center gap-2.5 px-8 py-4 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white font-black rounded-2xl hover:from-emerald-600 hover:to-cyan-600 transition-all duration-300 shadow-lg shadow-emerald-500/25 hover:shadow-emerald-500/40 hover:scale-[1.02] active:scale-[0.98] text-base"
            >
              <LogIn className="w-5 h-5" />
              {copy.success.primaryCta}
            </Link>
            <Link
              href="/download"
              className="group flex items-center justify-center gap-2.5 px-8 py-4 bg-white dark:bg-slate-900 text-slate-900 dark:text-white font-bold rounded-2xl border border-slate-200 dark:border-slate-800 hover:border-emerald-300 dark:hover:border-emerald-800 transition-all duration-300 hover:shadow-lg text-base"
            >
              <Download className="w-5 h-5" />
              {copy.success.secondaryCta}
            </Link>
          </motion.div>

          {/* Footer note */}
          <motion.p
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            transition={{ duration: 0.6, delay: 1.4 }}
            className="mt-10 text-sm text-slate-400 dark:text-slate-500"
          >
            {copy.success.helpPrefix}{' '}
            <Link href="/contact" className="font-semibold text-emerald-600 hover:text-emerald-700 underline underline-offset-2">
              {copy.success.helpLink}
            </Link>{' '}
            {copy.success.helpSuffix}
          </motion.p>
        </div>
      </main>

      <Footer />
    </div>
  );
}

/* ─────────────────────────────────────────────
   PAGE EXPORT
───────────────────────────────────────────── */
export default function CheckoutSuccessPage() {
  return (
    <Suspense
      fallback={
        <div className="min-h-screen flex items-center justify-center bg-white dark:bg-slate-950">
          <motion.div
            animate={{ rotate: 360 }}
            transition={{ duration: 1, repeat: Infinity, ease: 'linear' }}
            className="w-10 h-10 border-4 border-emerald-500/20 border-t-emerald-500 rounded-full"
          />
        </div>
      }
    >
      <SuccessInner />
    </Suspense>
  );
}
