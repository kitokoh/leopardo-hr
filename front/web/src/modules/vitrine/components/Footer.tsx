'use client'

import Link from 'next/link'
import { useSearchParams } from 'next/navigation'
import { Gift, Globe, ShieldCheck, Lock, Activity } from 'lucide-react'
import { useVitrineLocale, type VitrineCopy } from '../lib/vitrine-locale'
import { t } from '@/lib/i18n/locale-catalog'

// Pas de lien social dans le footer : le compte X @leopardo_hr est inexistant
// (404 constaté 2026-08-15) et la page LinkedIn de #7192 aussi (404 vérifié
// 2026-09-10). Le lien GitHub, orienté développeurs, a été retiré lors du
// repositionnement grand public « Business OS » — la place est réinvestie en
// rappel de l'offre d'entrée : les packs métiers offerts.
// Ne réintroduire un réseau social qu'avec un compte officiel réellement actif.
import { CookieSettingsButton } from '@/modules/vitrine/components/CookieSettingsButton'
import { NewsletterForm } from './NewsletterForm'
import { getEnvConfig } from '../lib/env'
import { withLocaleHref } from '../lib/locale-href'

export function getFooterHref(sectionIndex: number, linkIndex: number): string | null {
  const key = `${sectionIndex}-${linkIndex}`
  const routes: Record<string, string> = {
    // PA2-MKT-013: the Footer renders on every landing page (26+ routes),
    // not just `/`. A bare `#fonctionnalites` hash only ever resolves
    // against the *current* page's DOM, so on any page other than the
    // homepage it pointed at a non-existent anchor. `/#fonctionnalites`
    // navigates to the homepage first, where the Features section now
    // carries a matching `id="fonctionnalites"` (see FeaturesSection).
    '0-0': '/#fonctionnalites',
    '0-1': '/pricing',
    '0-2': '/integrations',
    // `/integrations#api` also had no matching `id="api"` on that page;
    // the integrations page now exposes one on its main section.
    '0-3': '/integrations#api',
    '0-4': '/changelog',
    '0-5': '/download',
    // Audit expert 2026-08-15 (issue #2609) : pages orphelines liées
    // (étaient absentes de toute navigation, uniquement dans le sitemap).
    '0-6': '/about',
    '0-7': '/videos',
    // #8075 : pages modules construites mais jamais liées — 1 clic footer.
    '0-8': '/employes',
    '0-9': '/comptabilite',
    '0-10': '/marketing',
    // SUPERAPP-P0 (#8611) : passerelle publique « Leopardo Public » — point
    // d'entrée neutre cross-verticale (recherche métier/service/nom, proximité,
    // fiches publiques). Liée depuis le footer pour ne pas être orpheline (#8075).
    '0-11': '/decouvrir',
    '1-0': '/docs',
    '1-1': '/guides/rh-startup',
    '1-2': '/blog',
    '1-3': '/contact',
    '1-4': '/contact?topic=community',
    // #8075 : pages orphelines liées (case-studies et restaurateur méritent
    // le trafic, cf. issue) ; '1-8' sert désormais le hub /packs (offre grand public) ; /branding repositionné en '1-10' (il occupait
    // '1-5' alors qu'aucune locale n'a de 6e libellé Ressources).
    '1-5': '/case-studies',
    '1-6': '/testimonials',
    '1-7': '/alternatives',
    '1-8': '/packs',
    '1-9': '/careers',
    '1-10': '/branding',
    '2-0': '/download#mobile-apps',
    '2-1': '/mobile',
    '2-2': '/download#mobile-apps',
    '2-3': '/download#mobile-apps',
    '2-4': '/download#mobile-apps',
    // Le lien « Mentions légales » pointait vers /terms (la page CGU) : un lien
    // qui ment sur sa destination. Tant qu'une vraie page de mentions légales
    // n'existe pas (elle exige les données éditeur/hébergeur), la colonne ne
    // liste que ce qui existe, et la confidentialité porte explicitement le RGPD.
    '3-0': '/privacy',
    '3-1': '/terms',
    // La page existe désormais : le lien pointe vers les mentions légales
    // (et non plus vers les CGU, cf. correctif du lot vitrine).
    '3-2': '/mentions-legales',
    // #8440 — « Devenir actionnaire » : entrée volontairement discrète en
    // pied de page (pas de carte home ni de bandeau), vers la page de
    // manifestation d'intérêt pour prendre des parts de l'entreprise.
    '3-3': '/investir',
  }

  return routes[key] ?? null
}

function getLinkBadge(href: string, badges: VitrineCopy['footer']['badges']): { label: string; color: string } | null {
  if (href === '/decouvrir') {
    return { label: badges.public, color: 'bg-amber-500/10 text-amber-300 border-amber-500/30' }
  }
  if (href === '/packs') {
    return { label: badges.offered, color: 'bg-emerald-500/10 text-emerald-300 border-emerald-500/30' }
  }
  if (href === '/pricing') {
    return { label: badges.trial, color: 'bg-teal-500/10 text-teal-300 border-teal-500/30' }
  }
  if (href === '/investir') {
    return { label: badges.equity, color: 'bg-indigo-500/10 text-indigo-300 border-indigo-500/30' }
  }
  return null
}

export function Footer() {
  const { copy, locale, options } = useVitrineLocale()
  // #3806 : préserver ?lang= dans les liens internes du footer.
  const searchParams = useSearchParams()
  const search = searchParams.toString()
  const activeLocale = options.find((option) => option.value === locale)
  // The Blog link ('1-2' -> /blog) is hidden when NEXT_PUBLIC_ENABLE_BLOG is
  // disabled, since the route itself now 404s in that case (issue #1305).
  const { enableBlog } = getEnvConfig()

  return (
    <footer className="relative bg-slate-950 text-slate-300 border-t border-slate-800/80 overflow-hidden">
      {/* Ligne d'accent lumineuse supérieure */}
      <div className="absolute top-0 inset-x-0 h-px bg-gradient-to-r from-transparent via-emerald-500/40 via-amber-400/40 to-transparent pointer-events-none" />
      <div className="absolute top-0 left-1/4 -translate-y-1/2 w-96 h-36 bg-emerald-500/5 blur-3xl pointer-events-none rounded-full" />
      <div className="absolute top-0 right-1/4 -translate-y-1/2 w-96 h-36 bg-amber-500/5 blur-3xl pointer-events-none rounded-full" />

      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 relative z-10">
        <div className="grid grid-cols-2 md:grid-cols-6 gap-8 lg:gap-10 mb-12">
          {/* Colonne Marque, Mission & Réassurance */}
          <div className="col-span-2">
            <Link href="/" className="flex items-center gap-2.5 mb-4 group inline-flex">
              <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-400 via-emerald-500 to-teal-500 flex items-center justify-center shadow-lg shadow-amber-500/20 ring-1 ring-white/20 group-hover:scale-105 transition-transform duration-200">
                <span className="text-white font-black text-sm">L</span>
              </div>
              <div className="flex flex-col">
                <span className="font-black text-lg text-white leading-none tracking-tight">Leopardo</span>
                <span className="text-xs font-bold uppercase tracking-[0.2em] text-amber-400/90 mt-0.5">
                  {copy.nav.brandTagline}
                </span>
              </div>
            </Link>

            {/* Statut opérationnel live */}
            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-950/60 border border-emerald-500/30 text-xs font-semibold text-emerald-300 shadow-sm shadow-emerald-950/50 mb-4">
              <span className="relative flex h-2 w-2">
                <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75" />
                <span className="relative inline-flex rounded-full h-2 w-2 bg-emerald-500" />
              </span>
              <span>{copy.footer.systemStatus} &bull; 99.98%</span>
            </div>

            <p className="text-sm text-slate-400 leading-relaxed max-w-sm mb-5">
              {copy.footer.description}
            </p>

            {/* Cartouches de confiance & conformité */}
            <div className="grid grid-cols-2 gap-2 mb-6 p-3 rounded-xl bg-slate-900/60 border border-slate-800/80 text-xs text-slate-300 max-w-sm">
              <div className="flex items-center gap-2">
                <ShieldCheck className="w-4 h-4 text-emerald-400 flex-shrink-0" />
                <span>RGPD &amp; Cloud UE</span>
              </div>
              <div className="flex items-center gap-2">
                <Lock className="w-4 h-4 text-amber-400 flex-shrink-0" />
                <span>Chiffrement AES-256</span>
              </div>
              <div className="flex items-center gap-2">
                <Activity className="w-4 h-4 text-teal-400 flex-shrink-0" />
                <span>Haute Disponibilité</span>
              </div>
              <div className="flex items-center gap-2">
                <Globe className="w-4 h-4 text-blue-400 flex-shrink-0" />
                <span>Multi-devises &amp; Pays</span>
              </div>
            </div>

            <div className="flex items-center gap-4 mb-6">
              <Link
                href="/packs"
                title={t(locale, 'vitrine.nav.packs')}
                className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-emerald-950/80 to-teal-950/80 border border-emerald-500/30 px-3.5 py-2 text-xs font-bold text-emerald-300 hover:text-emerald-200 hover:border-emerald-400/50 shadow-sm transition-all duration-200 group"
              >
                <Gift className="w-3.5 h-3.5 text-amber-400 group-hover:scale-110 transition-transform" aria-hidden="true" />
                <span>{t(locale, 'vitrine.nav.packs')}</span>
              </Link>
            </div>

            {/* Newsletter sous la colonne de marque */}
            <div className="mt-6 max-w-sm">
              <NewsletterForm />
            </div>
          </div>

          {/* Colonnes de liens thématiques */}
          {copy.footer.sections.map((section, index) => (
            <div key={`${section.title}-${index}`} className="flex flex-col">
              <h4 className="text-xs font-black text-slate-100 mb-4 uppercase tracking-[0.16em] flex items-center gap-2">
                <span className="w-1.5 h-1.5 rounded-full bg-amber-400 shadow-[0_0_8px_rgba(251,191,36,0.8)] inline-block" />
                {section.title}
              </h4>
              <ul className="space-y-2">
                {section.links.map((link, linkIndex) => {
                  const href = getFooterHref(index, linkIndex)
                  if (!href || (href === '/blog' && !enableBlog)) return null

                  const badge = getLinkBadge(href, copy.footer.badges)

                  return (
                    <li key={`${section.title}-link-${linkIndex}`}>
                      <Link
                        href={withLocaleHref(href, search)}
                        className="text-sm text-slate-400 hover:text-emerald-300 dark:hover:text-amber-400 transition-all duration-200 hover:translate-x-1 inline-flex items-center gap-2 group py-0.5"
                      >
                        <span>{link}</span>
                        {badge && (
                          <span className={`text-xs font-bold px-1.5 py-0.5 rounded-full border leading-none ${badge.color}`}>
                            {badge.label}
                          </span>
                        )}
                      </Link>
                    </li>
                  )
                })}
                {/* #7593 — retirer son consentement : contrôle sur toutes les pages */}
                {index === copy.footer.sections.length - 1 && (
                  <li className="pt-2">
                    <CookieSettingsButton className="text-sm text-slate-400 hover:text-emerald-300 dark:hover:text-amber-400 transition-colors text-left inline-flex items-center gap-1.5" />
                  </li>
                )}
              </ul>
            </div>
          ))}
        </div>

        {/* Ligne inférieure de clôture & réassurance légale */}
        <div className="pt-8 border-t border-slate-800/80 flex flex-col md:flex-row items-center justify-between gap-4">
          <p className="text-sm text-slate-400">
            &copy; {new Date().getFullYear()} Leopardo. {copy.footer.rights}
          </p>
          <div className="text-xs text-slate-500 hidden lg:inline-block">
            Hébergement Cloud Sécurisé &bull; Données d&apos;entreprise isolées et chiffrées
          </div>
          <div className="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-900 border border-slate-800 text-xs text-slate-300">
            <Globe className="w-3.5 h-3.5 text-amber-400" />
            <span className="font-semibold">{activeLocale?.nativeLabel ?? locale}</span>
          </div>
        </div>
      </div>
    </footer>
  )
}
