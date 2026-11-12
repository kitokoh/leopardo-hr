'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { ArrowRight, History } from 'lucide-react';
import { readLastSearch, type CachedDiscovery } from '@/lib/offline/public-discovery-cache';
import type { PublicHomeCopy } from '../data/home-copy';

/**
 * Couloir 16 — M1 (#9372, point F) : « Reprendre où vous étiez ».
 *
 * ## Règle de vérité
 *
 * La section n'est rendue QUE si un historique local RÉEL existe, c'est-à-dire
 * si `readLastSearch()` renvoie une entrée valide. Aucun contenu de
 * démonstration, aucune recherche inventée : sans cache, le composant rend
 * `null` et la section est simplement absente de la page.
 *
 * ## Pourquoi côté client
 *
 * Le cache vit dans `localStorage` : il n'existe pas au rendu serveur. Rendre
 * la section en SSR produirait un décalage d'hydratation (serveur : rien,
 * client : un bloc). On lit donc après montage, et l'état initial est
 * explicitement « pas encore lu » — pas « vide ».
 *
 * ## Ce qui est repris
 *
 * Uniquement la requête (`q`, `city`, `type`, `page`) : jamais `near`. La
 * position n'est pas persistée par `saveLastSearch()` (voir
 * `public-discovery-cache.ts`), donc elle ne peut pas réapparaître ici.
 *
 * ## Accessibilité
 *
 * Le lien porte un libellé complet (« Reprendre : boulangerie à Lyon ») et non
 * un simple « Reprendre » : un lecteur d'écran doit savoir ce qu'il reprend.
 */

interface PublicResumeSearchProps {
  copy: PublicHomeCopy['resume'];
}

/** Construit l'URL de reprise à partir de la requête mise en cache. */
export function buildResumeHref(query: CachedDiscovery['query']): string {
  const params = new URLSearchParams();
  if (query.q) params.set('q', query.q);
  if (query.city) params.set('city', query.city);
  if (query.type) params.set('type', query.type);
  if (query.page > 1) params.set('page', String(query.page));

  const qs = params.toString();
  return qs ? `/decouvrir?${qs}` : '/decouvrir';
}

/** Résumé humain de la requête, pour le libellé accessible. */
export function describeResumeQuery(query: CachedDiscovery['query']): string {
  const parts = [query.q, query.city, query.type].filter((value) => value && value.trim() !== '');
  return parts.join(' · ');
}

export function PublicResumeSearch({ copy }: PublicResumeSearchProps) {
  // `undefined` = pas encore lu (montage en cours) ; `null` = lu, rien en cache.
  const [cached, setCached] = useState<CachedDiscovery | null | undefined>(undefined);

  useEffect(() => {
    setCached(readLastSearch());
  }, []);

  if (!cached) {
    return null;
  }

  const href = buildResumeHref(cached.query);
  const summary = describeResumeQuery(cached.query);

  return (
    <section aria-labelledby="home-resume" className="mx-auto max-w-6xl px-4 pt-10 sm:px-6">
      <div className="flex flex-wrap items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/[0.03]">
        <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400">
          <History className="h-5 w-5" aria-hidden />
        </span>

        <div className="min-w-0 flex-1">
          <h2 id="home-resume" className="text-sm font-bold text-slate-900 dark:text-white">
            {copy.title}
          </h2>
          <p className="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">
            {summary || copy.subtitle}
          </p>
        </div>

        <Link
          href={href}
          aria-label={summary ? `${copy.cta} : ${summary}` : copy.cta}
          className="inline-flex min-h-[44px] shrink-0 items-center gap-1.5 rounded-lg bg-emerald-600 px-4 text-sm font-bold text-white transition-colors hover:bg-emerald-500"
        >
          {copy.cta}
          <ArrowRight className="h-4 w-4 rtl:rotate-180" aria-hidden />
        </Link>
      </div>
    </section>
  );
}
