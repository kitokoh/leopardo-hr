'use client';

/**
 * COULOIR 16 — M3-bis · Vue de l'onglet « Activités ».
 *
 * ## Les états d'écran, tous explicites
 *
 * `loading` · `unauthenticated` · `error` · `empty` (par section) · `ok`.
 * Aucun n'est un écran muet : chacun dit ce qui se passe et propose l'action
 * qui a du sens (se connecter, réessayer, chercher).
 *
 * ## `unauthenticated` n'est pas une erreur
 *
 * Un visiteur sans compte n'a pas un système en panne : il n'a simplement pas
 * de données à voir. Afficher « Impossible de charger » serait un mensonge sur
 * l'état du système — et c'est précisément ce que la règle de vérité du
 * couloir interdit.
 *
 * ## Aucun état de succès déduit localement
 *
 * Le retrait d'un favori n'est retiré de la liste qu'après un `2xx` du serveur.
 * Un retrait optimiste masquerait un refus (favori d'un autre client, réseau
 * coupé) et laisserait l'écran mentir.
 */

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import {
  clearSearchHistory,
  fetchMyActivities,
  fetchMyFavorites,
  fetchMySearchHistory,
  removeFavorite,
  type PublicActivityReference,
  type PublicFavoriteEntry,
  type PublicSearchHistoryEntry,
} from '@/lib/public-account-api';
import {
  activityStatusLabel,
  getPublicAccountCopy,
} from '@/modules/public-portal/data/account-copy';

type LoadState = 'loading' | 'unauthenticated' | 'error' | 'ok';

interface ActivitiesViewProps {
  locale: string;
}

export default function ActivitiesView({ locale }: ActivitiesViewProps) {
  const copy = getPublicAccountCopy(locale);

  const [state, setState] = useState<LoadState>('loading');
  const [activities, setActivities] = useState<PublicActivityReference[]>([]);
  const [favorites, setFavorites] = useState<PublicFavoriteEntry[]>([]);
  const [history, setHistory] = useState<PublicSearchHistoryEntry[]>([]);
  const [busySlug, setBusySlug] = useState<string | null>(null);
  const [clearing, setClearing] = useState(false);

  const load = useCallback(async () => {
    setState('loading');

    const [activitiesResult, favoritesResult, historyResult] = await Promise.all([
      fetchMyActivities(),
      fetchMyFavorites(),
      fetchMySearchHistory(),
    ]);

    // Un seul 401 suffit : les trois routes partagent le même guard.
    if (
      activitiesResult.status === 'unauthenticated' ||
      favoritesResult.status === 'unauthenticated' ||
      historyResult.status === 'unauthenticated'
    ) {
      setState('unauthenticated');
      return;
    }

    if (
      activitiesResult.status !== 'ok' ||
      favoritesResult.status !== 'ok' ||
      historyResult.status !== 'ok'
    ) {
      setState('error');
      return;
    }

    setActivities(activitiesResult.data);
    setFavorites(favoritesResult.data);
    setHistory(historyResult.data);
    setState('ok');
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  async function handleRemoveFavorite(entry: PublicFavoriteEntry) {
    setBusySlug(entry.target_slug);

    const result = await removeFavorite(entry.target_type, entry.target_slug);

    if (result.status === 'ok') {
      // Retrait confirmé par le serveur seulement.
      setFavorites((current) => current.filter((item) => item.target_slug !== entry.target_slug));
    }

    setBusySlug(null);
  }

  async function handleClearHistory() {
    setClearing(true);

    const result = await clearSearchHistory();

    if (result.status === 'ok') {
      setHistory([]);
    }

    setClearing(false);
  }

  if (state === 'loading') {
    return (
      <main className="mx-auto w-full max-w-3xl px-4 py-8" aria-busy="true">
        <h1 className="text-2xl font-semibold">{copy.activities.title}</h1>
        <p className="mt-2 text-sm text-muted-foreground" role="status">
          {copy.activities.loading}
        </p>
        {/* Squelette à dimensions fixes : pas de saut de mise en page (CLS). */}
        <div className="mt-6 space-y-3">
          {[0, 1, 2].map((index) => (
            <div key={index} className="h-16 w-full animate-pulse rounded-lg bg-muted" />
          ))}
        </div>
      </main>
    );
  }

  if (state === 'unauthenticated') {
    return (
      <main className="mx-auto w-full max-w-3xl px-4 py-8">
        <h1 className="text-2xl font-semibold">{copy.activities.title}</h1>
        <p className="mt-2 text-sm text-muted-foreground">{copy.activities.unauthenticated}</p>
        <Link
          href="/login"
          className="mt-4 inline-flex min-h-11 items-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground"
        >
          {copy.activities.signIn}
        </Link>
      </main>
    );
  }

  if (state === 'error') {
    return (
      <main className="mx-auto w-full max-w-3xl px-4 py-8">
        <h1 className="text-2xl font-semibold">{copy.activities.title}</h1>
        <p className="mt-2 text-sm text-destructive" role="alert">
          {copy.activities.loadError}
        </p>
        <button
          type="button"
          onClick={() => void load()}
          className="mt-4 inline-flex min-h-11 items-center rounded-md border px-4 text-sm font-medium"
        >
          {copy.activities.retry}
        </button>
      </main>
    );
  }

  return (
    <main className="mx-auto w-full max-w-3xl px-4 py-8">
      <h1 className="text-2xl font-semibold">{copy.activities.title}</h1>
      <p className="mt-1 text-sm text-muted-foreground">{copy.activities.subtitle}</p>

      {/* ── Commandes et réservations ─────────────────────────────────────── */}
      <section className="mt-8" aria-labelledby="activities-section-activity">
        <h2 id="activities-section-activity" className="text-lg font-medium">
          {copy.activities.sections.activity}
        </h2>

        {activities.length === 0 ? (
          <p className="mt-2 text-sm text-muted-foreground">{copy.activities.emptyActivities}</p>
        ) : (
          <ul className="mt-3 divide-y rounded-lg border">
            {activities.map((entry) => (
              <li
                key={`${entry.vertical}-${entry.activity_type}-${entry.reference}`}
                className="flex flex-wrap items-center justify-between gap-2 px-4 py-3"
              >
                <div>
                  <p className="text-sm font-medium">{entry.reference}</p>
                  <p className="text-xs text-muted-foreground">
                    {entry.vertical} · {entry.activity_type}
                  </p>
                </div>
                <div className="text-end">
                  <span className="inline-flex rounded-full bg-muted px-2 py-1 text-xs font-medium">
                    {activityStatusLabel(locale, entry.status)}
                  </span>
                  {entry.occurred_at ? (
                    <p className="mt-1 text-xs text-muted-foreground">
                      {copy.activities.occurredAt}{' '}
                      <time dateTime={entry.occurred_at}>
                        {new Date(entry.occurred_at).toLocaleDateString(locale)}
                      </time>
                    </p>
                  ) : null}
                </div>
              </li>
            ))}
          </ul>
        )}
      </section>

      {/* ── Favoris ───────────────────────────────────────────────────────── */}
      <section className="mt-8" aria-labelledby="activities-section-favorites">
        <h2 id="activities-section-favorites" className="text-lg font-medium">
          {copy.activities.sections.favorites}
        </h2>

        {favorites.length === 0 ? (
          <p className="mt-2 text-sm text-muted-foreground">{copy.activities.emptyFavorites}</p>
        ) : (
          <ul className="mt-3 divide-y rounded-lg border">
            {favorites.map((entry) => {
              const label = entry.display_name ?? entry.target_slug;

              return (
                <li
                  key={`${entry.target_type}-${entry.target_slug}`}
                  className="flex flex-wrap items-center justify-between gap-2 px-4 py-3"
                >
                  <div>
                    <p className="text-sm font-medium">{label}</p>
                    {!entry.available ? (
                      <p className="text-xs text-muted-foreground">
                        {copy.activities.favoriteUnavailable}
                      </p>
                    ) : null}
                  </div>
                  <button
                    type="button"
                    onClick={() => void handleRemoveFavorite(entry)}
                    disabled={busySlug === entry.target_slug}
                    aria-label={copy.activities.removeFavoriteLabel.replace('{name}', label)}
                    className="inline-flex min-h-11 items-center rounded-md border px-3 text-sm disabled:opacity-50"
                  >
                    {busySlug === entry.target_slug
                      ? copy.activities.removing
                      : copy.activities.removeFavorite}
                  </button>
                </li>
              );
            })}
          </ul>
        )}
      </section>

      {/* ── Recherches récentes ───────────────────────────────────────────── */}
      <section className="mt-8" aria-labelledby="activities-section-history">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <h2 id="activities-section-history" className="text-lg font-medium">
            {copy.activities.sections.history}
          </h2>
          {history.length > 0 ? (
            <button
              type="button"
              onClick={() => void handleClearHistory()}
              disabled={clearing}
              className="inline-flex min-h-11 items-center rounded-md border px-3 text-sm disabled:opacity-50"
            >
              {clearing ? copy.activities.clearing : copy.activities.clearHistory}
            </button>
          ) : null}
        </div>

        {history.length === 0 ? (
          <p className="mt-2 text-sm text-muted-foreground">{copy.activities.emptyHistory}</p>
        ) : (
          <ul className="mt-3 divide-y rounded-lg border">
            {history.map((entry) => (
              <li
                key={`${entry.query}-${entry.city ?? ''}-${entry.searched_at ?? ''}`}
                className="flex flex-wrap items-center justify-between gap-2 px-4 py-3"
              >
                <p className="text-sm font-medium">{entry.query}</p>
                <p className="text-xs text-muted-foreground">
                  {entry.city ? `${entry.city} · ` : ''}
                  {entry.searched_at ? (
                    <time dateTime={entry.searched_at}>
                      {new Date(entry.searched_at).toLocaleDateString(locale)}
                    </time>
                  ) : null}
                </p>
              </li>
            ))}
          </ul>
        )}
      </section>
    </main>
  );
}
