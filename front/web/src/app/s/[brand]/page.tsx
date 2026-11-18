import type { Metadata } from 'next';
import { notFound } from 'next/navigation';

import { normalizeLocale } from '@/lib/i18n';
import {
  PublicSurfaceShell,
  SurfaceBlocks,
  SurfaceCapabilityBar,
  SurfaceHero,
  loadSurfaceBySlug,
} from '@/modules/public-surface';

/**
 * #9580 (BC-41 BRANDING) — surface publique d'une enseigne (`/s/{brand}`).
 *
 * ## SSR indexable, un seul appel
 *
 * La page charge UN contrat (`GET /public/brands/{slug}`) et rend le profil de
 * la verticale. Elle ne connaît aucune verticale en particulier : c'est le
 * profil qui décide des blocs, du thème et des capacités. Ajouter une
 * verticale ne modifie pas cette page.
 *
 * C'est la réponse à « chaque verticale doit avoir un site spécialisé » sans
 * dupliquer douze fronts : la spécialisation est DONNÉE, pas codée.
 *
 * ## 404 propre
 *
 * `loadSurfaceBySlug` renvoie `null` pour un slug inconnu, une Brand non
 * publiée, une Brand suspendue ou un tenant suspendu — sans distinction.
 * `notFound()` produit alors un 404 indexable comme tel : un visiteur qui tape
 * une adresse inexistante ne doit pas recevoir une page d'erreur 500.
 */

interface BrandPageProps {
  params: Promise<{ brand: string }>;
  searchParams: Promise<{ lang?: string }>;
}

export async function generateMetadata({ params, searchParams }: BrandPageProps): Promise<Metadata> {
  const { brand: slug } = await params;
  const { lang } = await searchParams;

  const surface = await loadSurfaceBySlug(slug, { requestedLang: normalizeLocale(lang ?? '') });

  if (!surface) {
    return { title: 'Enseigne introuvable' };
  }

  const { brand } = surface;
  const name = brand.identity.display_name;
  const description = brand.identity.tagline ?? `Découvrez ${name}.`;
  const host = brand.surface.host;

  return {
    title: name,
    description,
    alternates: {
      canonical: host ? `https://${host}/` : `/s/${brand.slug}`,
      languages: Object.fromEntries(
        brand.surface.locales.map((locale) => [locale, `/s/${brand.slug}?lang=${locale}`]),
      ),
    },
    openGraph: {
      type: 'website',
      title: name,
      description,
      siteName: name,
      images: brand.identity.og_image_url ? [{ url: brand.identity.og_image_url }] : undefined,
      locale: surface.locale,
    },
    // Le favicon est propre à l'enseigne : deux Brands du même tenant ne
    // doivent pas partager l'icône de l'onglet.
    icons: brand.identity.favicon_url ? { icon: brand.identity.favicon_url } : undefined,
  };
}

export default async function BrandSurfacePage({ params, searchParams }: BrandPageProps) {
  const { brand: slug } = await params;
  const { lang } = await searchParams;

  const surface = await loadSurfaceBySlug(slug, { requestedLang: normalizeLocale(lang ?? '') });

  if (!surface) {
    notFound();
  }

  const { brand, capabilities } = surface;

  // JSON-LD : le type schema.org suit la verticale, ce qui donne un résultat
  // enrichi correct sans que la page ait à connaître les verticales.
  const schemaType = SCHEMA_TYPE_BY_VERTICAL[brand.vertical] ?? 'Organization';

  const jsonLd = {
    '@context': 'https://schema.org',
    '@type': schemaType,
    name: brand.identity.display_name,
    description: brand.identity.tagline ?? undefined,
    url: brand.surface.host ? `https://${brand.surface.host}/` : undefined,
    image: brand.identity.og_image_url ?? undefined,
    logo: brand.identity.logo_url ?? undefined,
    inLanguage: surface.locale,
  };

  return (
    <PublicSurfaceShell surface={surface}>
      <script
        type="application/ld+json"
        // Contenu entièrement construit ci-dessus à partir du DTO validé :
        // aucune entrée utilisateur brute n'est interpolée.
        dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }}
      />
      <SurfaceHero identity={brand.identity} verticalLabel={brand.profile.label} />
      <SurfaceCapabilityBar capabilities={capabilities} primaryCta={brand.profile.primary_cta} />
      <SurfaceBlocks brand={brand} />
    </PublicSurfaceShell>
  );
}

/**
 * Type schema.org par verticale.
 *
 * Table locale à la page : un type inconnu retombe sur `Organization`, qui est
 * valide et honnête. Mapper `restaurant` sur `Organization` priverait le tenant
 * de résultat enrichi ; inventer un type schema.org inexistant le rendrait
 * invalide.
 */
const SCHEMA_TYPE_BY_VERTICAL: Record<string, string> = {
  restaurant: 'Restaurant',
  retail: 'Store',
  pharmacy: 'Pharmacy',
  travel: 'TravelAgency',
  vtc: 'TaxiService',
  fuel: 'GasStation',
  hospitality: 'Hotel',
  health: 'MedicalBusiness',
  trade: 'WholesaleStore',
  ticketing: 'EventVenue',
};
