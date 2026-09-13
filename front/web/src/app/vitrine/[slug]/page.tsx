import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { normalizeLocale } from '@/lib/i18n';
import { SITE_URL } from '@/lib/site-url';
import {
  getPublicVitrine,
  vitrineCssVariables,
  type VitrinePublic,
  type VitrineSection,
} from '@/lib/showcase-public-api';

/**
 * BC-27 SHOWCASE (#6862) — rendu public du site vitrine d'un tenant.
 *
 * Page SSR, indexable, servie sur `/vitrine/{slug}` et alimentée par
 * `GET /api/v1/public/vitrine/{slug}` (DTO public dédié : jamais de donnée
 * interne RH). Un brouillon n'est visible qu'avec le jeton d'aperçu
 * (`?token=`, émis par la page de gestion `/showcase`).
 *
 * Public par nature : cette route NE fait PAS partie de PROTECTED_PREFIXES.
 */

interface VitrinePageProps {
  params: Promise<{ slug: string }>;
  searchParams: Promise<{ lang?: string; token?: string }>;
}

export async function generateMetadata({ params, searchParams }: VitrinePageProps): Promise<Metadata> {
  const { slug } = await params;
  const { lang, token } = await searchParams;
  const vitrine = await getPublicVitrine(slug, { lang: lang ? normalizeLocale(lang) : undefined, token });

  if (!vitrine) {
    return { title: 'Site introuvable' };
  }

  return {
    title: vitrine.meta.title,
    description: vitrine.meta.description ?? undefined,
    alternates: { canonical: `${SITE_URL}/vitrine/${vitrine.slug}` },
    robots: token ? { index: false, follow: false } : { index: true, follow: true },
    openGraph: {
      title: vitrine.meta.title,
      description: vitrine.meta.description ?? undefined,
      images: vitrine.meta.og_image ? [vitrine.meta.og_image] : undefined,
      type: 'website',
    },
  };
}

function str(value: unknown): string {
  return typeof value === 'string' ? value : '';
}

function items(value: unknown): Record<string, unknown>[] {
  return Array.isArray(value) ? value.filter((item): item is Record<string, unknown> => !!item && typeof item === 'object') : [];
}

function Section({ section }: { section: VitrineSection }) {
  const content = section.content ?? {};

  switch (section.type) {
    case 'hero':
      return (
        <header className="bg-[var(--vitrine-primary)] px-6 py-20 text-[var(--vitrine-on-primary)]">
          <div className="mx-auto max-w-4xl space-y-4 text-center">
            <h1 className="text-4xl font-black tracking-tight sm:text-5xl">{str(content.heading)}</h1>
            {str(content.subheading) !== '' && <p className="text-lg opacity-90">{str(content.subheading)}</p>}
            {str(content.cta_label) !== '' && (
              <a
                href={str(content.cta_url) || '#contact'}
                className="mt-4 inline-flex rounded-[var(--vitrine-radius)] bg-[var(--vitrine-accent)] px-6 py-3 font-bold text-[var(--vitrine-on-primary)]"
              >
                {str(content.cta_label)}
              </a>
            )}
          </div>
        </header>
      );

    case 'features':
      return (
        <section className="mx-auto max-w-5xl px-6 py-14">
          {str(content.title) !== '' && (
            <h2 className="mb-8 text-2xl font-black tracking-tight text-slate-900">{str(content.title)}</h2>
          )}
          <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            {items(content.items).map((item, index) => (
              <article key={index} className="rounded-[var(--vitrine-radius)] border border-slate-200 p-5">
                <h3 className="font-bold text-slate-900">{str(item.title)}</h3>
                {str(item.description) !== '' && <p className="mt-2 text-sm text-slate-600">{str(item.description)}</p>}
              </article>
            ))}
          </div>
        </section>
      );

    case 'gallery':
      return (
        <section className="mx-auto max-w-5xl px-6 py-14">
          {str(content.title) !== '' && (
            <h2 className="mb-8 text-2xl font-black tracking-tight text-slate-900">{str(content.title)}</h2>
          )}
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {items(content.items).map((item, index) => (
              <figure key={index} className="overflow-hidden rounded-[var(--vitrine-radius)] border border-slate-200">
                {/* eslint-disable-next-line @next/next/no-img-element -- URL publique fournie par l'API, dimensions inconnues */}
                <img src={str(item.image_url)} alt={str(item.caption)} className="h-56 w-full object-cover" />
                {str(item.caption) !== '' && (
                  <figcaption className="p-3 text-xs text-slate-500">{str(item.caption)}</figcaption>
                )}
              </figure>
            ))}
          </div>
        </section>
      );

    case 'testimonials':
      return (
        <section className="mx-auto max-w-4xl px-6 py-14">
          {str(content.title) !== '' && (
            <h2 className="mb-8 text-2xl font-black tracking-tight text-slate-900">{str(content.title)}</h2>
          )}
          <div className="grid gap-6 sm:grid-cols-2">
            {items(content.items).map((item, index) => (
              <blockquote key={index} className="rounded-[var(--vitrine-radius)] bg-slate-50 p-6">
                <p className="text-slate-700">“{str(item.quote)}”</p>
                <footer className="mt-3 text-sm font-semibold text-slate-500">
                  {str(item.author)}
                  {str(item.role) !== '' ? ` — ${str(item.role)}` : ''}
                </footer>
              </blockquote>
            ))}
          </div>
        </section>
      );

    case 'products':
      return (
        <section className="mx-auto max-w-5xl px-6 py-14">
          {str(content.title) !== '' && (
            <h2 className="mb-8 text-2xl font-black tracking-tight text-slate-900">{str(content.title)}</h2>
          )}
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {items(content.items).map((item, index) => (
              <article key={index} className="rounded-[var(--vitrine-radius)] border border-slate-200 p-5">
                <h3 className="font-bold text-slate-900">{str(item.name)}</h3>
                {str(item.description) !== '' && <p className="mt-2 text-sm text-slate-600">{str(item.description)}</p>}
              </article>
            ))}
          </div>
        </section>
      );

    case 'contact':
      return (
        <section id="contact" className="mx-auto max-w-4xl px-6 py-14">
          <h2 className="mb-6 text-2xl font-black tracking-tight text-slate-900">
            {str(content.title) || 'Contact'}
          </h2>
          <div className="space-y-2 text-slate-700">
            {str(content.email) !== '' && (
              <p>
                <a className="underline" href={`mailto:${str(content.email)}`}>
                  {str(content.email)}
                </a>
              </p>
            )}
            {str(content.phone) !== '' && <p>{str(content.phone)}</p>}
            {str(content.address) !== '' && <p>{str(content.address)}</p>}
          </div>
        </section>
      );

    case 'footer':
      return (
        <footer className="border-t border-slate-200 px-6 py-8">
          <div className="mx-auto max-w-5xl space-y-3 text-sm text-slate-500">
            <p>{str(content.text)}</p>
            {items(content.links).length > 0 && (
              <ul className="flex flex-wrap gap-4">
                {items(content.links).map((link, index) => (
                  <li key={index}>
                    <a className="underline" href={str(link.url)}>
                      {str(link.label)}
                    </a>
                  </li>
                ))}
              </ul>
            )}
          </div>
        </footer>
      );

    default:
      return null;
  }
}

export default async function VitrinePage({ params, searchParams }: VitrinePageProps) {
  const { slug } = await params;
  const { lang, token } = await searchParams;

  const vitrine: VitrinePublic | null = await getPublicVitrine(slug, {
    lang: lang ? normalizeLocale(lang) : undefined,
    token,
  });

  if (!vitrine) {
    notFound();
  }

  const cssVars = vitrineCssVariables(vitrine);
  const style = Object.fromEntries(Object.entries(cssVars)) as React.CSSProperties;

  return (
    <div lang={vitrine.lang} dir={vitrine.lang === 'ar' ? 'rtl' : 'ltr'} className="min-h-screen bg-[var(--vitrine-surface)]" style={style}>
      {vitrine.sections.map((section, index) => (
        <Section key={`${section.type}-${index}`} section={section} />
      ))}
    </div>
  );
}
