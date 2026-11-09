import type { Metadata } from 'next';
import Link from 'next/link';
import { ArrowRight, Building2, Car, Hotel, Store, UtensilsCrossed } from 'lucide-react';
import { normalizeLocale } from '@/lib/i18n';
import { SITE_URL } from '@/lib/site-url';
import { searchPublicDiscovery } from '@/lib/public-discovery-api';
import { getPublicPortalCopy } from '@/modules/public-portal/data/copy';
import DiscoveryExplorer from './discovery-explorer';

/**
 * SUPERAPP-P0 (#8611) — passerelle publique « Leopardo Public » (`/decouvrir`).
 *
 * Point d'entrée NEUTRE cross-verticale : le client cherche un métier, un
 * service ou un nom SANS connaître la verticale. Le premier rendu (recherche,
 * ville, catégorie, pagination) est servi côté serveur via
 * `GET /api/v1/public/discover` (DTO public strict, sans auth) — page
 * INDEXABLE, comme `/restaurants`.
 *
 * La géolocalisation (« Autour de moi ») raffine ensuite côté client
 * uniquement : la position n'est jamais placée dans l'URL SSR, et elle n'est
 * demandée que sur action explicite de l'utilisateur.
 *
 * ## Ce que cette page ajoute au-delà de la recherche
 *
 * Avant, `/decouvrir` n'était qu'un formulaire suivi d'une liste : un visiteur
 * qui ne savait pas quoi chercher n'avait aucun point d'entrée. La page porte
 * désormais trois blocs statiques (aucun appel API supplémentaire) :
 *  - un accès direct par verticale (les annuaires `/restaurants`,
 *    `/boutiques`, `/operateurs`, `/entreprises`, `/stay`) ;
 *  - « Comment ça marche » en trois étapes ;
 *  - un CTA professionnel vers la vitrine.
 */

interface DiscoveryPageProps {
  searchParams: Promise<{
    q?: string;
    city?: string;
    type?: string;
    page?: string;
    lang?: string;
  }>;
}

export async function generateMetadata({ searchParams }: DiscoveryPageProps): Promise<Metadata> {
  const { lang } = await searchParams;
  const locale = normalizeLocale(lang ?? '');
  const copy = getPublicPortalCopy(locale);

  return {
    title: copy.home.title,
    description: copy.home.description,
    alternates: {
      canonical: `${SITE_URL}/decouvrir`,
      languages: {
        fr: `${SITE_URL}/decouvrir`,
        en: `${SITE_URL}/decouvrir?lang=en`,
        tr: `${SITE_URL}/decouvrir?lang=tr`,
        ar: `${SITE_URL}/decouvrir?lang=ar`,
        'x-default': `${SITE_URL}/decouvrir`,
      },
    },
    robots: { index: true, follow: true },
    openGraph: {
      title: copy.home.title,
      description: copy.home.description,
      type: 'website',
      url: `${SITE_URL}/decouvrir`,
    },
  };
}

/** Accès direct par verticale — miroir des annuaires réellement servis. */
const VERTICALS = [
  { href: '/restaurants', icon: UtensilsCrossed, key: 'restaurants' },
  { href: '/boutiques', icon: Store, key: 'shops' },
  { href: '/operateurs', icon: Car, key: 'operators' },
  { href: '/entreprises', icon: Building2, key: 'companies' },
  { href: '/stay', icon: Hotel, key: 'stays' },
] as const;

export default async function DiscoveryPage({ searchParams }: DiscoveryPageProps) {
  const params = await searchParams;
  const locale = normalizeLocale(params.lang ?? '');
  const copy = getPublicPortalCopy(locale);

  const page = Math.max(1, Number.parseInt(params.page ?? '1', 10) || 1);
  const query = {
    q: (params.q ?? '').slice(0, 120),
    city: (params.city ?? '').slice(0, 120),
    type: (params.type ?? '').slice(0, 60),
    page,
  };

  const result = await searchPublicDiscovery({
    q: query.q || undefined,
    city: query.city || undefined,
    type: query.type || undefined,
    page: query.page,
  });

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 via-amber-50/40 to-orange-50/40 px-4 py-10">
      <div className="mx-auto max-w-5xl">
        <header className="mb-8">
          <h1 className="text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">
            {copy.home.heroTitle}
          </h1>
          <p className="mt-2 max-w-2xl text-sm text-slate-600">{copy.home.heroSubtitle}</p>
        </header>

        <DiscoveryExplorer
          locale={locale}
          initialItems={result?.items ?? []}
          initialMeta={result?.meta ?? null}
          initialError={result === null}
          initialQuery={query}
        />

        {/* Accès direct par verticale : le visiteur qui ne sait pas quoi
            chercher entre par une catégorie au lieu de repartir. */}
        <section aria-labelledby="portal-categories" className="mt-14">
          <h2 id="portal-categories" className="text-xl font-black tracking-tight text-slate-950">
            {copy.home.categoriesTitle}
          </h2>
          <p className="mt-1 text-sm text-slate-500">{copy.home.categoriesSubtitle}</p>

          <ul className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {VERTICALS.map(({ href, icon: Icon, key }) => (
              <li key={href}>
                <Link
                  href={href}
                  className="flex min-h-[64px] items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm transition-colors hover:border-emerald-300 hover:bg-emerald-50/40"
                >
                  <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700">
                    <Icon className="h-5 w-5" aria-hidden />
                  </span>
                  <span className="text-sm font-bold text-slate-900">{copy.chrome[key]}</span>
                  <ArrowRight className="ml-auto h-4 w-4 text-slate-400" aria-hidden />
                </Link>
              </li>
            ))}
          </ul>
        </section>

        {/* Comment ça marche — rassure sur l'absence d'intermédiaire. */}
        <section aria-labelledby="portal-how" className="mt-14">
          <h2 id="portal-how" className="text-xl font-black tracking-tight text-slate-950">
            {copy.home.howTitle}
          </h2>
          <ol className="mt-5 grid gap-4 sm:grid-cols-3">
            {[
              { title: copy.home.howStep1Title, body: copy.home.howStep1Body },
              { title: copy.home.howStep2Title, body: copy.home.howStep2Body },
              { title: copy.home.howStep3Title, body: copy.home.howStep3Body },
            ].map((step, index) => (
              <li key={step.title} className="rounded-xl border border-slate-200 bg-white p-5">
                <span className="inline-flex h-7 w-7 items-center justify-center rounded-full bg-slate-900 text-xs font-bold text-white">
                  {index + 1}
                </span>
                <h3 className="mt-3 text-sm font-bold text-slate-900">{step.title}</h3>
                <p className="mt-1 text-sm text-slate-600">{step.body}</p>
              </li>
            ))}
          </ol>
        </section>

        {/* CTA professionnel : le portail est aussi une porte d'entrée commerciale. */}
        <section className="mt-14 rounded-2xl bg-slate-950 px-6 py-8 text-white sm:px-10">
          <h2 className="text-xl font-black tracking-tight">{copy.home.proTitle}</h2>
          <p className="mt-2 max-w-2xl text-sm text-slate-300">{copy.home.proBody}</p>
          <Link
            href="/"
            className="mt-5 inline-flex items-center gap-2 rounded-lg bg-emerald-500 px-4 py-2.5 text-sm font-bold text-slate-950 hover:bg-emerald-400"
          >
            {copy.home.proCta}
            <ArrowRight className="h-4 w-4" aria-hidden />
          </Link>
        </section>
      </div>
    </div>
  );
}
