'use client';

/**
 * SolutionCard — la fiche interactive ouverte au clic sur une étoile.
 *
 * Le clic sur un module de la constellation n'ouvre plus directement une page
 * (comportement #8390) : il affiche cette fiche, qui présente la solution
 * (description, profils concernés, fonctionnalités incluses, bénéfices,
 * applications/outils) et porte le CTA vers la page complète ou l'onboarding.
 *
 * Données : 100 % issues de `getSolutionDetail` (source unique
 * `data/solution-catalog.ts` → `PACK_PAGES` / copie locale). Aucune donnée
 * métier dupliquée dans ce composant.
 *
 * Accessibilité : `role="dialog"` + `aria-modal`, Échap ferme, clic sur le
 * fond ferme, le focus est posé dans la fiche puis rendu à l'étoile d'origine,
 * le focus reste piégé dans la fiche (Tab/Shift+Tab cycliques), le fond est
 * neutralisé (`aria-hidden` + `inert` via les couches du hero). Mobile : la
 * fiche devient une feuille plein écran, scrollable, lisible en portrait.
 */

import { useCallback, useEffect, useId, useRef } from 'react';
import Link from 'next/link';
import { useSearchParams } from 'next/navigation';
import { motion } from 'framer-motion';
import { ArrowRight, Check, Sparkles, X } from 'lucide-react';
import type { AppLocale } from '@/lib/i18n';
import { withLocaleHref } from '../../lib/locale-href';
import {
  SOLUTION_CARD_COPY,
  getSolutionDetail,
  type SolutionId,
} from '../../data/solution-catalog';

const FOCUSABLE =
  'a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])';

export interface SolutionCardProps {
  solutionId: SolutionId;
  locale: AppLocale;
  onClose: () => void;
}

export function SolutionCard({ solutionId, locale, onClose }: SolutionCardProps) {
  const copy = SOLUTION_CARD_COPY[locale] ?? SOLUTION_CARD_COPY.fr;
  const detail = getSolutionDetail(solutionId, locale);
  const panelRef = useRef<HTMLDivElement>(null);
  const titleId = useId();
  const searchParams = useSearchParams();
  const search = searchParams.toString();

  // Échap ferme ; le focus est piégé dans la fiche tant qu'elle est ouverte.
  const onKeyDown = useCallback(
    (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        onClose();
        return;
      }
      if (event.key !== 'Tab') return;
      const panel = panelRef.current;
      if (!panel) return;
      const nodes = Array.from(panel.querySelectorAll<HTMLElement>(FOCUSABLE)).filter(
        (node) => node.offsetParent !== null,
      );
      if (nodes.length === 0) return;
      const first = nodes[0];
      const last = nodes[nodes.length - 1];
      const active = document.activeElement;
      if (event.shiftKey && (active === first || !panel.contains(active))) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && active === last) {
        event.preventDefault();
        first.focus();
      }
    },
    [onClose],
  );

  useEffect(() => {
    const previouslyFocused = document.activeElement as HTMLElement | null;
    const timer = window.setTimeout(() => {
      const panel = panelRef.current;
      const target = panel?.querySelector<HTMLElement>(FOCUSABLE);
      (target ?? panel)?.focus();
    }, 20);
    document.addEventListener('keydown', onKeyDown);
    // Le fond ne doit pas défiler sous la fiche.
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => {
      window.clearTimeout(timer);
      document.removeEventListener('keydown', onKeyDown);
      document.body.style.overflow = previousOverflow;
      previouslyFocused?.focus?.();
    };
  }, [onKeyDown]);

  if (!detail) return null;

  return (
    <div className="fixed inset-0 z-[60] flex items-end justify-center p-0 sm:items-center sm:p-6">
      {/* Fond assombri : un clic ferme la fiche. */}
      <button
        type="button"
        aria-label={copy.close}
        tabIndex={-1}
        onClick={onClose}
        className="absolute inset-0 cursor-default bg-slate-950/70 backdrop-blur-sm"
      />

      <motion.div
        ref={panelRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        tabIndex={-1}
        initial={{ opacity: 0, y: 24, scale: 0.98 }}
        animate={{ opacity: 1, y: 0, scale: 1 }}
        transition={{ duration: 0.28, ease: [0.22, 1, 0.36, 1] }}
        className="relative z-10 flex max-h-[92dvh] w-full max-w-2xl flex-col overflow-hidden rounded-t-3xl border border-emerald-500/20 bg-slate-950/95 shadow-2xl shadow-emerald-500/10 outline-none backdrop-blur-xl sm:rounded-3xl"
      >
        {/* En-tête */}
        <div className="relative border-b border-white/10 px-6 pb-5 pt-6 sm:px-8">
          <div className="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-emerald-400/60 to-transparent" />
          <div className="flex items-start justify-between gap-4">
            <div>
              <span className="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/25 bg-emerald-500/10 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-emerald-300">
                <Sparkles className="h-3 w-3" aria-hidden="true" />
                {copy.badge}
              </span>
              <h2
                id={titleId}
                className="mt-3 text-2xl font-black tracking-tight text-white sm:text-3xl"
              >
                {detail.name}
              </h2>
              {detail.tagline && (
                <p className="mt-1.5 text-sm font-medium text-emerald-200/90">{detail.tagline}</p>
              )}
            </div>
            <button
              type="button"
              onClick={onClose}
              aria-label={copy.close}
              className="shrink-0 rounded-xl border border-white/10 bg-white/5 p-2 text-slate-300 transition-colors hover:bg-white/10 hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-400"
            >
              <X className="h-4 w-4" aria-hidden="true" />
            </button>
          </div>
        </div>

        {/* Corps scrollable */}
        <div className="min-h-0 flex-1 space-y-6 overflow-y-auto px-6 py-6 sm:px-8">
          <p className="text-sm leading-relaxed text-slate-300 sm:text-base">{detail.description}</p>

          {detail.users.length > 0 && (
            <div>
              <h3 className="mb-2.5 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                {copy.usersTitle}
              </h3>
              <ul className="flex flex-wrap gap-2">
                {detail.users.map((user) => (
                  <li
                    key={user}
                    className="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold text-slate-200"
                  >
                    {user}
                  </li>
                ))}
              </ul>
            </div>
          )}

          {detail.features.length > 0 && (
            <div>
              <h3 className="mb-2.5 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                {copy.featuresTitle}
              </h3>
              <ul className="space-y-2">
                {detail.features.map((feature) => (
                  <li key={feature} className="flex items-start gap-2.5 text-sm text-slate-300">
                    <span className="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-400">
                      <Check className="h-2.5 w-2.5" aria-hidden="true" />
                    </span>
                    {feature}
                  </li>
                ))}
              </ul>
            </div>
          )}
        </div>

        {/* Actions */}
        <div className="flex flex-col gap-2.5 border-t border-white/10 bg-slate-950/80 px-6 py-5 sm:flex-row sm:px-8">
          <Link
            href={withLocaleHref(detail.href, search)}
            className="group inline-flex flex-1 items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-emerald-600 to-cyan-600 px-6 py-3.5 text-sm font-bold text-white transition-all duration-300 hover:scale-[1.02] active:scale-[0.98] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-400"
          >
            {detail.cta}
            <ArrowRight
              className="h-4 w-4 transition-transform duration-300 group-hover:translate-x-1"
              aria-hidden="true"
            />
          </Link>
          <Link
            href={withLocaleHref('/signup', search)}
            prefetch={false}
            className="inline-flex items-center justify-center gap-2 rounded-2xl border border-white/15 bg-white/5 px-6 py-3.5 text-sm font-semibold text-white transition-colors hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-400"
          >
            {copy.start}
          </Link>
        </div>
      </motion.div>
    </div>
  );
}

export default SolutionCard;
