import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { headers } from 'next/headers';
import { cache } from 'react';
import { normalizeLocale } from '@/lib/locale-core';
import { SITE_URL } from '@/lib/site-url';
import { getLibraryCopy } from '@/modules/library/data/copy';
import {
  getAllResourceSlugs,
  getRelatedResources,
  getResource,
} from '@/modules/library/lib/registry';
import { getAccessCapabilities } from '@/modules/library/lib/access';
import { ResourceShell } from '@/modules/library/components/ResourceShell';
import { ResourceActions } from '@/modules/library/components/ResourceActions';
import { ResourceCard } from '@/modules/library/components/ResourceCard';

/**
 * Fiche d'une ressource (`/library/[slug]`).
 *
 * Page PUBLIQUE et INDEXABLE, générée statiquement (`generateStaticParams`).
 * Un slug inconnu ou non publié renvoie un 404 propre (`notFound()`).
 *
 * Les capacités d'accès sont calculées CÔTÉ SERVEUR (contexte anonyme ici) et
 * passées au composant client : le bouton de téléchargement n'apparaît que si
 * la licence ET le modèle d'accès l'autorisent. La vérification réelle des
 * droits (achat, abonnement, tenant) reste faite par l'API.
 */

const getSsrLocale = cache(async () => {
  const headerList = await headers();
  const urlLang = headerList.get('x-vitrine-lang');
  if (urlLang) return normalizeLocale(urlLang);
  const base = (headerList.get('accept-language') ?? '').split(',')[0].trim().toLowerCase().slice(0, 2);
  return normalizeLocale(base);
});

export function generateStaticParams() {
  return getAllResourceSlugs().map((slug) => ({ slug }));
}

interface ResourcePageProps {
  params: Promise<{ slug: string }>;
}

export async function generateMetadata({ params }: ResourcePageProps): Promise<Metadata> {
  const { slug } = await params;
  const locale = await getSsrLocale();
  const copy = getLibraryCopy(locale);
  const resource = getResource(slug);
  if (!resource) return { title: copy.meta.title };

  return {
    title: `${slug} — ${copy.home.title}`,
    description: resource.excerpt ?? copy.meta.description,
    keywords: resource.keywords,
    alternates: {
      canonical: `${SITE_URL}/library/${slug}`,
      languages: {
        fr: `${SITE_URL}/library/${slug}`,
        en: `${SITE_URL}/library/${slug}?lang=en`,
        tr: `${SITE_URL}/library/${slug}?lang=tr`,
        ar: `${SITE_URL}/library/${slug}?lang=ar`,
        'x-default': `${SITE_URL}/library/${slug}`,
      },
    },
    robots: { index: true, follow: true },
    openGraph: {
      title: `${slug} — ${copy.home.title}`,
      description: resource.excerpt ?? copy.meta.description,
      type: 'article',
      url: `${SITE_URL}/library/${slug}`,
    },
  };
}

/** Découpe un contenu en pages (paragraphes regroupés). */
function toPages(content: string | undefined, excerpt: string | undefined): string[] {
  const source = content ?? excerpt ?? '';
  if (source.trim() === '') return [];
  const paragraphs = source.split(/\n{2,}/).map((part) => part.trim()).filter(Boolean);
  return paragraphs.length > 0 ? paragraphs : [source];
}

export default async function ResourcePage({ params }: ResourcePageProps) {
  const { slug } = await params;
  const resource = getResource(slug);
  if (!resource) notFound();

  const locale = await getSsrLocale();
  const copy = getLibraryCopy(locale);

  // Contexte anonyme : la fiche publique ne suppose aucun droit acquis.
  const capabilities = getAccessCapabilities(resource, { authenticated: false });
  const pages = toPages(resource.content, resource.excerpt);
  const related = getRelatedResources(slug);

  const metadata = [
    { label: copy.detail.author, value: resource.author },
    { label: copy.detail.publisher, value: resource.publisher ?? copy.detail.unknown },
    {
      label: copy.detail.publishedYear,
      value: resource.publishedYear !== undefined ? String(resource.publishedYear) : copy.detail.unknown,
    },
    { label: copy.detail.pages, value: resource.pages !== undefined ? String(resource.pages) : copy.detail.unknown },
    { label: copy.detail.language, value: resource.language.toUpperCase() },
    { label: copy.detail.category, value: copy.categories[resource.category] ?? resource.category },
    { label: copy.detail.documentType, value: copy.types[resource.documentType] },
    { label: copy.detail.access, value: copy.access[resource.accessModel] },
  ];

  return (
    <div>
      <ResourceShell
        title={slug}
        author={resource.author}
        categoryLabel={copy.categories[resource.category] ?? resource.category}
        accessLabel={copy.access[resource.accessModel]}
        accessTone={
          resource.accessModel === 'free' || resource.accessModel === 'read_only'
            ? 'free'
            : resource.accessModel === 'private'
              ? 'private'
              : 'paid'
        }
        description={resource.excerpt ?? copy.meta.description}
        metadata={metadata}
        licenseLabel={copy.detail.license}
        rightsHolder={`${copy.detail.rightsHolder} : ${resource.license.holder}`}
        sourceUrl={resource.sourceUrl}
        sourceNotice={copy.detail.sourceNotice}
        demoNotice={resource.demo ? copy.detail.demoNotice : undefined}
        backLabel={copy.detail.backToLibrary}
        disclaimer={copy.common.disclaimer}
      >
        <ResourceActions
          slug={resource.slug}
          title={slug}
          pages={pages}
          pdfUrl={resource.fileFormat === 'pdf' ? resource.fileUrl : undefined}
          capabilities={capabilities}
          copy={{
            readOnline: copy.detail.readOnline,
            download: copy.detail.download,
            downloadDisabled: copy.detail.downloadDisabled,
            buy: copy.detail.buy,
            subscribe: copy.detail.subscribe,
            privateAccess: copy.detail.privateAccess,
            addFavorite: copy.account.addFavorite,
            removeFavorite: copy.account.removeFavorite,
            notAvailable: copy.reader.notAvailable,
            reader: copy.reader,
          }}
        />
      </ResourceShell>

      {related.length > 0 && (
        <section className="mx-auto w-full max-w-3xl px-4 pb-14">
          <h2 className="text-lg font-bold text-slate-900 dark:text-white mb-4">{copy.detail.related}</h2>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {related.map((item) => (
              <ResourceCard
                key={item.slug}
                href={`/library/${item.slug}`}
                title={item.slug}
                author={item.author}
                categoryLabel={copy.categories[item.category] ?? item.category}
                accessLabel={copy.access[item.accessModel]}
                accessTone={
                  item.accessModel === 'free' || item.accessModel === 'read_only'
                    ? 'free'
                    : item.accessModel === 'private'
                      ? 'private'
                      : 'paid'
                }
                openLabel={copy.detail.readOnline}
              />
            ))}
          </div>
        </section>
      )}
    </div>
  );
}
