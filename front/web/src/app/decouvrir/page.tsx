import type { Metadata } from 'next';
import { normalizeLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';
import { SITE_URL } from '@/lib/site-url';
import { searchPublicDiscovery } from '@/lib/public-discovery-api';
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

  return {
    title: t(locale, 'discovery.title'),
    description: t(locale, 'discovery.description'),
    alternates: { canonical: `${SITE_URL}/decouvrir` },
    robots: { index: true, follow: true },
    openGraph: {
      title: t(locale, 'discovery.title'),
      description: t(locale, 'discovery.description'),
      type: 'website',
    },
  };
}

export default async function DiscoveryPage({ searchParams }: DiscoveryPageProps) {
  const params = await searchParams;
  const locale = normalizeLocale(params.lang ?? '');

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
    <main className="min-h-screen bg-gradient-to-br from-slate-50 via-amber-50/40 to-orange-50/40 px-4 py-10">
      <div className="mx-auto max-w-5xl">
        <header className="mb-8">
          <h1 className="text-3xl font-black tracking-tight text-slate-950">
            {t(locale, 'discovery.title')}
          </h1>
          <p className="mt-1 text-sm text-slate-500">{t(locale, 'discovery.description')}</p>
        </header>

        <DiscoveryExplorer
          locale={locale}
          initialItems={result?.items ?? []}
          initialMeta={result?.meta ?? null}
          initialError={result === null}
          initialQuery={query}
        />
      </div>
    </main>
  );
}
