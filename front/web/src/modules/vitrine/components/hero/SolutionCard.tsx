'use client';

/**
 * SolutionCard — la fiche interactive ouverte au clic sur une étoile.
 *
 * Rendue dans un PORTAIL (`document.body`) : c'est le correctif du bug de
 * stacking context. Le héro est une pile de couches (titre z-0, scène z-10,
 * actions z-20) où chaque couche crée son propre contexte d'empilement
 * (`transform`, `filter`, `isolation`) ; une fiche enfant de la scène (z-10)
 * restait donc SOUS les boutons du héros et sous le canvas, d'où l'impression
 * « le modal s'ouvre trop bas et ne se ferme pas » (le bouton X était masqué
 * par les CTA du haut). Le portail sort la fiche de cet arbre : `z-[100]`,
 * au-dessus de la navbar (`z-50`), de la constellation et des cartes.
 *
 * Accessibilité : `role="dialog"` + `aria-modal`, Échap ferme, clic sur le
 * fond ferme, focus posé dans la fiche puis RESTITUÉ à l'élément d'origine
 * (l'étoile / la scène interactive), focus piégé (Tab/Shift+Tab cycliques).
 * Mobile : feuille plein écran scrollable, cibles tactiles ≥ 44 px.
 *
 * Données : 100 % issues de `getSolutionDetail` (source unique
 * `data/solution-catalog.ts` → `PACK_PAGES`). Aucune donnée dupliquée ici.
 */

import { useCallback, useEffect, useId, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import Link from 'next/link';
import { useSearchParams } from 'next/navigation';
import { motion } from 'framer-motion';
import { ArrowRight, Check, Sparkles, X } from 'lucide-react';
import type { AppLocale } from '@/lib/i18n';
import { withLocaleHref } from '../../lib/locale-href';
import { SOLUTION_CARD_COPY, getSolutionDetail, type SolutionId } from '../../data/solution-catalog';

const FOCUSABLE =
  'a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])';

export interface SolutionCardProps {
  solutionId: SolutionId;
  locale: AppLocale;
  onClose: () => void;
  /**
   * Élément à re-focaliser à la fermeture quand aucun élément n'avait le
   * focus à l'ouverture (cas normal : l'étoile est un pixel de canvas). Le
   * panneau passe la scène interactive.
   */
  returnFocusRef?: React.RefObject<HTMLElement | null>;
}

export function SolutionCard({ solutionId, locale, onClose, returnFocusRef }: SolutionCardProps) {
  const copy = SOLUTION_CARD_COPY[locale] ?? SOLUTION_CARD_COPY.fr;
  const detail = getSolutionDetail(solutionId, locale);
  const panelRef = useRef<HTMLDivElement>(null);
  const titleId = useId();
  const searchParams = useSearchParams();
  const search = searchParams.toString();
  // Le portail n'existe qu'après hydratation (aucun accès à `document` au SSR).
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    setMounted(true);
  }, []);

  // Échap ferme ; Tab tourne en boucle DANS la fiche.
  const onKeyDown = useCallback(
    (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        event.stopPropagation();
        onClose();
        return;
      }
      if (event.key !== 'Tab') return;
      const panel = panelRef.current;
      if (!panel) return;
      const nodes = Array.from(panel.querySelectorAll<HTMLElement>(FOCUSABLE)).filter((node) => {
        // `offsetParent` (le test habituel) vaut TOUJOURS null en jsdom et pour
        // un élément `position: fixed` : on interroge le style calculé, qui
        // distingue réellement un champ masqué d'un champ visible, dans le
        // navigateur comme dans les tests.
        const style = window.getComputedStyle(node);
        return style.display !== 'none' && style.visibility !== 'hidden';
      });
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
    // Capturé ici : au moment du nettoyage, `returnFocusRef.current` peut avoir
    // changé (démontage du panneau) — on veut le nœud réellement présent.
    const fallback = returnFocusRef?.current ?? null;
    const timer = window.setTimeout(() => {
      const panel = panelRef.current;
      const target = panel?.querySelector<HTMLElement>(FOCUSABLE);
      (target ?? panel)?.focus();
    }, 20);
    document.addEventListener('keydown', onKeyDown, true);
    // Le fond ne doit ni défiler ni « sauter » quand on masque la barre.
    const { overflow, paddingRight } = document.body.style;
    const scrollbar = window.innerWidth - document.documentElement.clientWidth;
    document.body.style.overflow = 'hidden';
    if (scrollbar > 0) document.body.style.paddingRight = `${scrollbar}px`;
    return () => {
      window.clearTimeout(timer);
      document.removeEventListener('keydown', onKeyDown, true);
      document.body.style.overflow = overflow;
      document.body.style.paddingRight = paddingRight;
      const fallbackNode = fallback;
      const target = previouslyFocused && previouslyFocused !== document.body ? previouslyFocused : fallbackNode;
      target?.focus?.();
    };
  }, [onKeyDown, returnFocusRef]);

  if (!detail || !mounted) return null;

  const overlay = (
    <div className="fixed inset-0 z-[100] flex items-end justify-center p-3 sm:items-center sm:p-6">
      {/* Fond assombri : un clic ferme la fiche (cible large, accessible). */}
      <button
        type="button"
        aria-label={copy.close}
        tabIndex={-1}
        onClick={onClose}
        className="absolute inset-0 h-full w-full cursor-default bg-slate-950/75 backdrop-blur-sm"
      />

      <motion.div
        ref={panelRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        data-testid="solution-card"
        tabIndex={-1}
        initial={{ opacity: 0, y: 32, scale: 0.98 }}
        animate={{ opacity: 1, y: 0, scale: 1 }}
        transition={{ duration: 0.28, ease: [0.22, 1, 0.36, 1] }}
        className="relative z-10 flex max-h-[88dvh] w-full max-w-2xl flex-col overflow-hidden rounded-3xl border border-emerald-500/20 bg-slate-950/95 shadow-2xl shadow-emerald-500/10 outline-none backdrop-blur-xl"
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
              className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-white/10 bg-white/5 text-slate-300 transition-colors hover:bg-white/10 hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-400"
            >
              <X className="h-5 w-5" aria-hidden="true" />
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
            className="group inline-flex min-h-12 flex-1 items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-emerald-600 to-cyan-600 px-6 py-3.5 text-sm font-bold text-white transition-all duration-300 hover:scale-[1.02] active:scale-[0.98] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-400"
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
            className="inline-flex min-h-12 items-center justify-center gap-2 rounded-2xl border border-white/15 bg-white/5 px-6 py-3.5 text-sm font-semibold text-white transition-colors hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-400"
          >
            {copy.start}
          </Link>
        </div>
      </motion.div>
    </div>
  );

  return createPortal(overlay, document.body);
}

export default SolutionCard;
