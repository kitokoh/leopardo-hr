import type { Metadata } from 'next';
import { normalizeLocale } from '@/lib/i18n';
import { SITE_URL } from '@/lib/site-url';
import { searchPublicDiscovery } from '@/lib/public-discovery-api';
import { getPublicPortalCopy } from '../data/copy';
import PublicDirectoryExplorer from './PublicDirectoryExplorer';

/**
 * Fabrique de page d'annuaire public par verticale.
 *
 * ## Le problème qu'elle résout
 *
 * `/boutiques`, `/operateurs`, `/entreprises` et `/stay` n'avaient AUCUNE page
 * d'index : seules leurs fiches `/{verticale}/{slug}` existaient. Conséquences
 * mesurées :
 *  - un visiteur ne pouvait pas parcourir la verticale (aucun point d'entrée) ;
 *  - les fiches n'étaient liées par aucun index, donc orphelines pour le crawl ;
 *  - `/decouvrir` renvoyait vers ces fiches, mais rien ne renvoyait vers
 *    `/decouvrir` depuis elles (impasse de navigation).
 *
 * ## Ce que la fabrique garantit
 *
 * Chaque annuaire est une page SSR INDEXABLE (metadata + canonical + hreflang
 * `?lang=`), alimentée par le MÊME contrat public que `/decouvrir`
 * (`GET /api/v1/public/discover`, `type` figé). Aucune logique métier n'est
 * dupliquée : le périmètre de publication (opt-in, société active, feature
 * verticale) reste appliqué par le backend, fail-closed.
 *
 * Les métadonnées viennent de `directoryByType` : quatre annuaires aux
 * descriptions distinctes, jamais quatre copies de la même page.
 *
 * Une API indisponible ne casse jamais la page : `searchPublicDiscovery`
 * renvoie `null` et l'explorateur affiche un état d'erreur explicite avec
 * possibilité de réessayer.
 */

/** Types de verticale disposant d'un annuaire dédié. */
export type DirectoryType =
  | 'retail_shop'
  | 'vtc_operator'
  | 'trade_company'
  | 'hospitality_property';

export interface PublicDirectoryPageConfig {
  /** Type canonique de `/public/discover`. */
  type: DirectoryType;
  /** Chemin de l'annuaire (`/boutiques`). */
  basePath: string;
}

interface DirectorySearchParams {
  q?: string;
  city?: string;
  page?: string;
  lang?: string;
}

/** Construit `generateMetadata` pour un annuaire de verticale. */
export function buildDirectoryMetadata(config: PublicDirectoryPageConfig) {
  return async function generateMetadata({
    searchParams,
  }: {
    searchParams: Promise<DirectorySearchParams>;
  }): Promise<Metadata> {
    const { lang } = await searchParams;
    const locale = normalizeLocale(lang ?? '');
    const copy = getPublicPortalCopy(locale);
    const entry = copy.directoryByType[config.type];

    // Le layout racine applique déjà le template `%s | Leopardo` : ne PAS
    // répéter la marque ici, sinon le <title> devient « Boutiques — Leopardo |
    // Leopardo ».
    const title = entry.title;

    return {
      title,
      description: entry.description,
      alternates: {
        canonical: `${SITE_URL}${config.basePath}`,
        languages: {
          fr: `${SITE_URL}${config.basePath}`,
          en: `${SITE_URL}${config.basePath}?lang=en`,
          tr: `${SITE_URL}${config.basePath}?lang=tr`,
          ar: `${SITE_URL}${config.basePath}?lang=ar`,
          'x-default': `${SITE_URL}${config.basePath}`,
        },
      },
      robots: { index: true, follow: true },
      openGraph: {
        title,
        description: entry.description,
        type: 'website',
        url: `${SITE_URL}${config.basePath}`,
      },
    };
  };
}

/** Construit le composant de page d'un annuaire de verticale. */
export function buildDirectoryPage(config: PublicDirectoryPageConfig) {
  return async function PublicDirectoryPage({
    searchParams,
  }: {
    searchParams: Promise<DirectorySearchParams>;
  }) {
    const params = await searchParams;
    const locale = normalizeLocale(params.lang ?? '');

    const page = Math.max(1, Number.parseInt(params.page ?? '1', 10) || 1);
    const query = {
      q: (params.q ?? '').slice(0, 120),
      city: (params.city ?? '').slice(0, 120),
      page,
    };

    const result = await searchPublicDiscovery({
      q: query.q || undefined,
      city: query.city || undefined,
      type: config.type,
      page: query.page,
    });

    const copy = getPublicPortalCopy(locale);
    const entry = copy.directoryByType[config.type];

    return (
      <div className="min-h-screen bg-gradient-to-br from-slate-50 via-amber-50/40 to-orange-50/40 px-4 py-10">
        <div className="mx-auto max-w-5xl">
          <header className="mb-8">
            <h1 className="text-3xl font-black tracking-tight text-slate-950">{entry.title}</h1>
            <p className="mt-1 text-sm text-slate-500">{entry.description}</p>
          </header>

          <PublicDirectoryExplorer
            locale={locale}
            type={config.type}
            basePath={config.basePath}
            initialItems={result?.items ?? []}
            initialMeta={result?.meta ?? null}
            initialError={result === null}
            initialQuery={query}
          />
        </div>
      </div>
    );
  };
}
