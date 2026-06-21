'use client';

import Link from 'next/link';
import { motion, AnimatePresence } from 'framer-motion';
import { ArrowRight } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { AppLocale } from '@/lib/i18n';

const copy: Record<AppLocale, { text: string; cta: string; sub: string }> = {
  fr: {
    text: 'Essai gratuit',
    cta: 'Démarrer maintenant',
    sub: 'Aucune CB requise · Opérationnel en 5 min',
  },
  en: {
    text: 'Free trial',
    cta: 'Get started now',
    sub: 'No credit card · Live in 5 minutes',
  },
  tr: {
    text: 'Ücretsiz deneme',
    cta: 'Hemen başla',
    sub: 'Kredi kartı gerekmez · 5 dakikada hazır',
  },
  ar: {
    text: 'تجربة مجانية',
    cta: 'ابدأ الآن',
    sub: 'بدون بطاقة ائتمان · جاهز في 5 دقائق',
  },
};

interface StickyMobileCTAProps {
  locale?: AppLocale;
}

/**
 * StickyMobileCTA
 * ─────────────────
 * Appears on mobile only after scrolling 400px.
 * Provides a persistent "Start free trial" CTA visible at all times.
 * Dismissible for the session (sessionStorage flag).
 */
export function StickyMobileCTA({ locale = 'fr' }: StickyMobileCTAProps) {
  const [visible, setVisible] = useState(false);
  const [dismissed, setDismissed] = useState(false);

  const t = copy[locale] ?? copy.fr;

  useEffect(() => {
    // Check if already dismissed this session
    if (typeof window !== 'undefined') {
      const wasDismissed = sessionStorage.getItem('sticky-cta-dismissed');
      if (wasDismissed) {
        setDismissed(true);
        return;
      }
    }

    const onScroll = () => {
      setVisible(window.scrollY > 400);
    };

    window.addEventListener('scroll', onScroll, { passive: true });
    return () => window.removeEventListener('scroll', onScroll);
  }, []);

  const handleDismiss = () => {
    setDismissed(true);
    sessionStorage.setItem('sticky-cta-dismissed', '1');
  };

  return (
    <AnimatePresence>
      {visible && !dismissed && (
        <motion.div
          initial={{ y: 100, opacity: 0 }}
          animate={{ y: 0, opacity: 1 }}
          exit={{ y: 100, opacity: 0 }}
          transition={{ type: 'spring', stiffness: 400, damping: 30 }}
          className="
            fixed bottom-0 left-0 right-0 z-50
            sm:hidden
            px-4 pb-safe-area-inset-bottom pb-4
          "
          role="complementary"
          aria-label="Sticky call to action"
        >
          <div className="relative bg-slate-900 dark:bg-slate-950 rounded-2xl shadow-2xl shadow-slate-900/50 border border-slate-800 overflow-hidden">
            {/* Gradient accent top border */}
            <div className="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-emerald-500 via-cyan-500 to-violet-500" />

            <div className="flex items-center gap-3 p-4">
              {/* Text */}
              <div className="flex-1 min-w-0">
                <p className="text-white font-bold text-sm truncate">{t.text}</p>
                <p className="text-slate-400 text-xs mt-0.5 truncate">{t.sub}</p>
              </div>

              {/* CTA Button */}
              <Link
                href="/signup"
                className="
                  flex-shrink-0 flex items-center gap-1.5
                  px-4 py-2.5 rounded-xl
                  bg-gradient-to-r from-emerald-500 to-emerald-600
                  text-white font-bold text-sm
                  hover:from-emerald-600 hover:to-emerald-700
                  active:scale-[0.97]
                  transition-all duration-200
                  shadow-lg shadow-emerald-500/25
                "
              >
                {t.cta}
                <ArrowRight className="w-4 h-4" />
              </Link>

              {/* Dismiss */}
              <button
                onClick={handleDismiss}
                aria-label="Fermer"
                className="flex-shrink-0 w-7 h-7 flex items-center justify-center rounded-lg text-slate-500 hover:text-slate-300 hover:bg-slate-800 transition-colors"
              >
                <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>
        </motion.div>
      )}
    </AnimatePresence>
  );
}
