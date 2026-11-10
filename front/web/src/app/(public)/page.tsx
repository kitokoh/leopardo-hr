import type { Metadata } from 'next';
import Link from 'next/link';
import {
  ArrowRight,
  ArrowUpRight,
  Building2,
  Car,
  Check,
  Hotel,
  Search,
  Store,
  UtensilsCrossed,
} from 'lucide-react';
import { normalizeLocale } from '@/lib/i18n';
import { SITE_URL } from '@/lib/site-url';
import { getPublicPortalCopy } from '@/modules/public-portal/data/copy';
import { getPublicHomeCopy } from '@/modules/public-portal/data/home-copy';
import { HomeSearch } from '@/modules/public-portal/components/HomeSearch';

/**
 * Page d'accueil du PORTAIL GRAND PUBLIC (#9357).
 *
 * ## Ce que cette page remplace
 *
 * Jusqu'ici `/` servait la vitrine commerciale : un visiteur grand public
 * tombait sur un discours B2B (« Business OS », pricing, démo commerciale) et
 * le portail de découverte n'était atteignable qu'en connaissant `/decouvrir`.
 * La vitrine a été déplacée sous `/business` (#9358) ; `/` est désormais la
 * porte d'entrée du grand public.
 *
 * ## Pourquoi elle est rendue côté serveur
 *
 * Aucun appel API : la page est entièrement statique (copie i18n + liens).
 * C'est délibéré — la home doit répondre en quelques dizaines de millisecondes
 * et rester disponible même si l'API de découverte est en panne. La recherche
 * pousse simplement vers `/decouvrir`, qui, elle, interroge l'API.
 *
 * ## Design
 *
 * - Thème clair/sombre (le portail est la seule surface bi-thème du produit).
 * - Animations d'apparition en CSS pur, désactivées sous
 *   `prefers-reduced-motion: reduce` (voir `globals.css`).
 * - Aucun `<main>` : le layout racine en fournit un.
 */

interface HomePageProps {
  searchParams: Promise<{ lang?: string }>;
}

export async function generateMetadata({ searchParams }: HomePageProps): Promise<Metadata> {
  const { lang } = await searchParams;
  const locale = normalizeLocale(lang ?? '');
  const copy = getPublicHomeCopy(locale);

  return {
    title: copy.meta.title,
    description: copy.meta.description,
    alternates: {
      canonical: SITE_URL,
      languages: {
        fr: SITE_URL,
        en: `${SITE_URL}/?lang=en`,
        tr: `${SITE_URL}/?lang=tr`,
        ar: `${SITE_URL}/?lang=ar`,
        'x-default': SITE_URL,
      },
    },
    robots: { index: true, follow: true },
    openGraph: {
      title: copy.meta.title,
      description: copy.meta.description,
      type: 'website',
      url: SITE_URL,
    },
  };
}

/** Accès direct par verticale — miroir exact des annuaires réellement servis. */
const VERTICALS = [
  { href: '/restaurants', icon: UtensilsCrossed, key: 'restaurants' },
  { href: '/boutiques', icon: Store, key: 'shops' },
  { href: '/operateurs', icon: Car, key: 'operators' },
  { href: '/entreprises', icon: Building2, key: 'companies' },
  { href: '/stay', icon: Hotel, key: 'stays' },
] as const;

export default async function PublicHomePage({ searchParams }: HomePageProps) {
  const { lang } = await searchParams;
  const locale = normalizeLocale(lang ?? '');
  const copy = getPublicHomeCopy(locale);
  const chrome = getPublicPortalCopy(locale).chrome;

  return (
    <div className="relative overflow-hidden">
      {/* Halo décoratif — purement visuel, masqué aux lecteurs d'écran. */}
      <div
        aria-hidden
        className="pointer-events-none absolute inset-x-0 top-0 -z-10 h-[520px] bg-[radial-gradient(60%_60%_at_50%_0%,rgba(16,185,129,0.16),transparent_70%)] dark:bg-[radial-gradient(60%_60%_at_50%_0%,rgba(16,185,129,0.22),transparent_70%)]"
      />

      {/* ─────────────── HERO ─────────────── */}
      <section className="mx-auto max-w-6xl px-4 pb-16 pt-14 sm:px-6 sm:pt-20">
        <p className="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-bold uppercase tracking-wide text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300">
          <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" aria-hidden />
          {copy.hero.eyebrow}
        </p>

        <h1 className="mt-5 max-w-3xl text-4xl font-black leading-[1.05] tracking-tight text-slate-950 sm:text-5xl lg:text-6xl dark:text-white">
          {copy.hero.title}{' '}
          <span className="bg-gradient-to-r from-emerald-600 to-teal-500 bg-clip-text text-transparent dark:from-emerald-400 dark:to-teal-300">
            {copy.hero.titleAccent}
          </span>
        </h1>

        <p className="mt-5 max-w-2xl text-base leading-relaxed text-slate-600 sm:text-lg dark:text-slate-300">
          {copy.hero.subtitle}
        </p>

        <HomeSearch copy={copy.hero} />

        <div className="mt-6 flex flex-wrap items-center gap-3">
          <Link
            href="/decouvrir"
            className="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-800 transition-colors hover:border-slate-400 hover:bg-slate-50 dark:border-white/15 dark:bg-white/5 dark:text-white dark:hover:bg-white/10"
          >
            <Search className="h-4 w-4" aria-hidden />
            {copy.hero.exploreCta}
          </Link>

          <span className="text-sm text-slate-500 dark:text-slate-400">
            {copy.hero.businessHint}
          </span>
          <Link
            href="/business"
            className="inline-flex items-center gap-1.5 text-sm font-bold text-emerald-700 underline-offset-4 hover:underline dark:text-emerald-400"
          >
            {copy.hero.businessCta}
            <ArrowUpRight className="h-3.5 w-3.5" aria-hidden />
          </Link>
        </div>
      </section>

      {/* ─────────────── GARANTIES ─────────────── */}
      <section aria-labelledby="home-trust" className="border-y border-slate-200 bg-white dark:border-white/10 dark:bg-white/[0.02]">
        <div className="mx-auto max-w-6xl px-4 py-12 sm:px-6">
          <h2 id="home-trust" className="sr-only">
            {copy.trust.title}
          </h2>
          <ul className="grid gap-6 sm:grid-cols-3">
            {copy.trust.items.map((item) => (
              <li key={item.title} className="flex gap-3">
                <span className="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400">
                  <Check className="h-3.5 w-3.5" aria-hidden />
                </span>
                <div>
                  <p className="text-sm font-bold text-slate-900 dark:text-white">{item.title}</p>
                  <p className="mt-1 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                    {item.body}
                  </p>
                </div>
              </li>
            ))}
          </ul>
        </div>
      </section>

      {/* ─────────────── VERTICALES ─────────────── */}
      <section aria-labelledby="home-verticals" className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <h2 id="home-verticals" className="text-2xl font-black tracking-tight text-slate-950 sm:text-3xl dark:text-white">
          {copy.verticals.title}
        </h2>
        <p className="mt-2 text-sm text-slate-500 dark:text-slate-400">{copy.verticals.subtitle}</p>

        <ul className="mt-7 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {VERTICALS.map(({ href, icon: Icon, key }) => (
            <li key={href}>
              <Link
                href={href}
                className="group flex min-h-[76px] items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm transition-all hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md dark:border-white/10 dark:bg-white/[0.03] dark:hover:border-emerald-500/40"
              >
                <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400">
                  <Icon className="h-5 w-5" aria-hidden />
                </span>
                <span className="min-w-0">
                  <span className="block text-sm font-bold text-slate-900 dark:text-white">
                    {chrome[key]}
                  </span>
                  <span className="block text-xs text-slate-500 dark:text-slate-400">
                    {copy.verticals.cta}
                  </span>
                </span>
                <ArrowRight className="ml-auto h-4 w-4 shrink-0 text-slate-300 transition-transform group-hover:translate-x-0.5 group-hover:text-emerald-600 dark:text-slate-600" aria-hidden />
              </Link>
            </li>
          ))}
        </ul>
      </section>

      {/* ─────────────── COMMENT ÇA MARCHE ─────────────── */}
      <section aria-labelledby="home-how" className="border-t border-slate-200 bg-white dark:border-white/10 dark:bg-white/[0.02]">
        <div className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
          <h2 id="home-how" className="text-2xl font-black tracking-tight text-slate-950 sm:text-3xl dark:text-white">
            {copy.how.title}
          </h2>
          <p className="mt-2 text-sm text-slate-500 dark:text-slate-400">{copy.how.subtitle}</p>

          <ol className="mt-8 grid gap-5 sm:grid-cols-3">
            {copy.how.steps.map((step, index) => (
              <li
                key={step.title}
                className="rounded-2xl border border-slate-200 bg-slate-50/60 p-5 dark:border-white/10 dark:bg-white/[0.03]"
              >
                <span className="inline-flex h-8 w-8 items-center justify-center rounded-full bg-slate-900 text-xs font-black text-white dark:bg-white dark:text-slate-900">
                  {index + 1}
                </span>
                <h3 className="mt-3 text-sm font-bold text-slate-900 dark:text-white">{step.title}</h3>
                <p className="mt-1.5 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                  {step.body}
                </p>
              </li>
            ))}
          </ol>
        </div>
      </section>

      {/* ─────────────── CTA BUSINESS ─────────────── */}
      <section className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <div className="relative overflow-hidden rounded-3xl bg-slate-950 px-6 py-10 text-white sm:px-10 sm:py-12">
          <div
            aria-hidden
            className="pointer-events-none absolute -right-24 -top-24 h-64 w-64 rounded-full bg-emerald-500/20 blur-3xl"
          />
          <h2 className="max-w-xl text-2xl font-black tracking-tight sm:text-3xl">
            {copy.business.title}
          </h2>
          <p className="mt-3 max-w-2xl text-sm leading-relaxed text-slate-300">
            {copy.business.body}
          </p>
          <div className="mt-6 flex flex-wrap items-center gap-3">
            <Link
              href="/business"
              className="inline-flex items-center gap-2 rounded-xl bg-emerald-500 px-5 py-2.5 text-sm font-bold text-slate-950 transition-colors hover:bg-emerald-400"
            >
              {copy.business.cta}
              <ArrowRight className="h-4 w-4" aria-hidden />
            </Link>
            <Link
              href="/business/pricing"
              className="inline-flex items-center gap-2 rounded-xl border border-white/20 px-5 py-2.5 text-sm font-bold text-white transition-colors hover:bg-white/10"
            >
              {copy.business.secondaryCta}
            </Link>
          </div>
        </div>
      </section>
    </div>
  );
}
