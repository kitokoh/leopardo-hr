'use client';

import { useRouter } from 'next/navigation';
import { useState } from 'react';
import { Search } from 'lucide-react';
import type { PublicHomeCopy } from '../data/home-copy';

/**
 * Recherche du hero de la page d'accueil.
 *
 * ## Pourquoi un formulaire GET et pas un appel API
 *
 * La recherche vit sur `/decouvrir`, qui est une page SSR indexable : le
 * formulaire pousse simplement `?q=&city=` et laisse le serveur rendre le
 * premier résultat. Conséquences voulues :
 *  - l'URL est partageable et indexable (un lien de recherche est un lien) ;
 *  - le bouton « retour » du navigateur fonctionne ;
 *  - aucun état client à hydrater, aucun spinner à gérer ici.
 *
 * ## Pourquoi `router.push` et pas `<form action>`
 *
 * Un `<form action="/decouvrir">` provoquerait un rechargement complet du
 * document. `router.push` garde la navigation côté client (transition Next)
 * tout en produisant exactement la même URL. Le `<form>` reste un vrai
 * formulaire (`onSubmit` + `role="search"`) : la touche Entrée fonctionne et
 * les lecteurs d'écran annoncent une zone de recherche.
 */
export function HomeSearch({ copy }: { copy: PublicHomeCopy['hero'] }) {
  const router = useRouter();
  const [q, setQ] = useState('');
  const [city, setCity] = useState('');

  const submit = (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const params = new URLSearchParams();
    const trimmedQ = q.trim();
    const trimmedCity = city.trim();
    if (trimmedQ) params.set('q', trimmedQ);
    if (trimmedCity) params.set('city', trimmedCity);
    const qs = params.toString();
    router.push(qs ? `/decouvrir?${qs}` : '/decouvrir');
  };

  return (
    <form
      role="search"
      onSubmit={submit}
      className="mt-8 w-full max-w-2xl rounded-2xl border border-slate-200/80 bg-white/80 p-2 shadow-lg shadow-slate-900/5 backdrop-blur-xl dark:border-white/10 dark:bg-white/5 dark:shadow-black/20"
    >
      <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
        <div className="flex min-w-0 flex-1 items-center gap-2 px-3">
          <Search className="h-4 w-4 shrink-0 text-slate-400" aria-hidden />
          <label htmlFor="home-search-q" className="sr-only">
            {copy.searchLabel}
          </label>
          <input
            id="home-search-q"
            name="q"
            type="search"
            value={q}
            onChange={(event) => setQ(event.target.value)}
            placeholder={copy.searchPlaceholder}
            maxLength={120}
            autoComplete="off"
            className="h-11 w-full min-w-0 bg-transparent text-sm text-slate-900 outline-none placeholder:text-slate-400 dark:text-white dark:placeholder:text-slate-500"
          />
        </div>

        <div className="hidden h-8 w-px shrink-0 bg-slate-200 sm:block dark:bg-white/10" aria-hidden />

        <div className="flex min-w-0 items-center px-3 sm:w-44">
          <label htmlFor="home-search-city" className="sr-only">
            {copy.cityLabel}
          </label>
          <input
            id="home-search-city"
            name="city"
            type="text"
            value={city}
            onChange={(event) => setCity(event.target.value)}
            placeholder={copy.cityPlaceholder}
            maxLength={120}
            autoComplete="address-level2"
            className="h-11 w-full min-w-0 bg-transparent text-sm text-slate-900 outline-none placeholder:text-slate-400 dark:text-white dark:placeholder:text-slate-500"
          />
        </div>

        <button
          type="submit"
          className="inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 text-sm font-bold text-white transition-colors hover:bg-emerald-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
        >
          <Search className="h-4 w-4 sm:hidden" aria-hidden />
          {copy.searchCta}
        </button>
      </div>
    </form>
  );
}
