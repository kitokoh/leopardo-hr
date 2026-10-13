'use client';

import Link from 'next/link';
import { motion, useScroll, useTransform, useSpring } from 'framer-motion';
import { ArrowRight, Play, Sparkles } from 'lucide-react';
import { useRef } from 'react';
import { useSearchParams } from 'next/navigation';
import { withLocaleHref } from '../../lib/locale-href';
import { HeroScene3D } from '../hero/HeroScene3D';

export interface HeroSectionProps {
  headline: string;
  /** Sous-titre des layouts `centered`/`split` — absent en `stage` (#8437). */
  subheadline?: string;
  badge?: string | {
    icon?: React.ReactNode;
    text: string;
    label?: string;
  };
  ctaPrimary?: {
    text: string;
    href: string;
  };
  ctaSecondary?: {
    text: string;
    href: string;
    icon?: React.ReactNode;
  };
  visual?: React.ReactNode;
  stats?: Array<{
    value: number;
    suffix: string;
    label: string;
    icon?: React.ReactNode;
  }>;
  animated?: boolean;
  /**
   * Disposition du hero.
   *  · `centered` (défaut) — texte centré, visuel empilé dessous. Comportement
   *    historique : les pages qui n'optent pas explicitement restent
   *    strictement inchangées.
   *  · `split` — deux colonnes sur grand écran : le récit à gauche, le visuel
   *    à droite, à hauteur d'œil.
   *  · `stage` — scène immersive (#8437, vague 3) : le visuel prend TOUT le
   *    héro (plein cadre, nuit permanente) et la phrase vit en toile de fond
   *    derrière lui ; les CTA flottent au-dessus, ancrés en bas. Utilisé par
   *    la page d'accueil : premier écran = la phrase + la 3D + les CTA.
   */
  layout?: 'centered' | 'split' | 'stage';
  /** Optional inline quick-trial form rendered below CTAs (e.g. QuickTrialEmailForm) */
  quickTrialForm?: React.ReactNode;
  /** #8068 — ligne de réassurance sous les CTA (« Gratuit · Sans CB · … »). */
  ctaReassurance?: string;
  /**
   * #8389 — `false` retire la scène 3D plein cadre derrière le texte (page
   * d'accueil : la constellation vit dans la colonne visuelle à droite, via
   * `HeroScene3DPanel` — le texte reste 100 % lisible). Défaut `true` :
   * comportement historique inchangé pour les autres pages.
   */
  backgroundScene?: boolean;
}

/** Lien externe (GitHub, docs hors site) → nouvel onglet. */
const EXTERNAL_HREF_RE = /^https?:\/\//i;
function externalProps(href: string): { target?: string; rel?: string } {
  return EXTERNAL_HREF_RE.test(href)
    ? { target: '_blank', rel: 'noopener noreferrer' }
    : {};
}

export function HeroSection({
  headline,
  subheadline,
  badge,
  ctaPrimary,
  ctaSecondary,
  visual,
  stats,
  animated = true,
  layout = 'centered',
  quickTrialForm,
  ctaReassurance,
  backgroundScene = true,
}: HeroSectionProps) {
  const ref = useRef<HTMLElement>(null);
  const { scrollYProgress } = useScroll({ target: ref, offset: ['start start', 'end start'] });
  const y = useSpring(useTransform(scrollYProgress, [0, 1], [0, -200]), { stiffness: 80, damping: 30 });
  const opacity = useTransform(scrollYProgress, [0, 0.6], [1, 0]);
  const scale = useTransform(scrollYProgress, [0, 0.6], [1, 0.92]);
  const searchParams = useSearchParams();
  const search = searchParams.toString();
  const badgeConfig = typeof badge === 'string' ? { text: badge } : badge;

  /** Deux colonnes dès `lg` seulement si une visuel est fourni. */
  const isSplit = layout === 'split' && Boolean(visual);
  /** Scène immersive plein cadre (#8437) — nécessite un visuel. */
  const isStage = layout === 'stage' && Boolean(visual);
  const align = isSplit ? 'text-center lg:text-left' : 'text-center';

  // ── Layout `stage` (#8437 — retour propriétaire vague 3) ────────────────
  // La constellation 3D prend TOUTE la première page ; la phrase passe en
  // toile de fond (z-0) derrière la scène (z-10), les CTA flottent au-dessus
  // (z-20). Le héro est une NUIT PERMANENTE (#8438 : « en mode nuit la
  // constellation semble plus belle ») — beau en mode jour comme en mode
  // nuit ; une transition douce ramène vers le thème de la page en bas.
  // La phrase garde sa pleine opacité (garde #8063 : lisible sans JS,
  // opacité > 0.9) — « toile de fond » = ordre de plan, pas estompage.
  if (isStage) {
    return (
      <section ref={ref} className="relative min-h-[100dvh] overflow-hidden bg-slate-950">
        {/* Ciel nuit permanent : lueurs émeraude/cyan sur ardoise profonde */}
        <div className="absolute inset-0 bg-[radial-gradient(ellipse_90%_60%_at_50%_-10%,rgba(16,185,129,0.18),transparent)]" />
        <div className="absolute inset-0 bg-[radial-gradient(ellipse_70%_55%_at_85%_110%,rgba(6,182,212,0.14),transparent)]" />
        <div className="absolute inset-0 bg-gradient-to-b from-slate-950 via-slate-950/95 to-slate-900/95" />
        {/* Trame discrète (style en globals.css — la garde i18n ne scanne
            pas les .css, cf. note PA2-I18N-014 en tête de globals.css) */}
        <div className="hero-stage-grid absolute inset-0 opacity-[0.05]" />

        {/* Phrase en toile de fond (z-0) — jamais cliquable, jamais estompée.
            Le padding bas la hisse dans le tiers supérieur : la constellation
            (abaissée côté scène) ne la croise qu'en petits modules orbitaux. */}
        <div className="pointer-events-none absolute inset-0 z-0 flex items-center justify-center px-4 pb-[32vh] sm:px-6">
          <motion.h1
            initial={animated ? { y: 30 } : {}}
            animate={animated ? { y: 0 } : {}}
            transition={{ duration: 1, delay: 0.15, ease: [0.22, 1, 0.36, 1] }}
            className="max-w-5xl text-balance text-center font-black leading-[0.95] tracking-tight text-5xl sm:text-6xl lg:text-7xl"
          >
            <span className="block bg-gradient-to-b from-white via-slate-200 to-slate-400 bg-clip-text text-transparent">
              {headline}
            </span>
          </motion.h1>
        </div>

        {/* Constellation pleine scène (z-10) — modules cliquables (#8390) */}
        <div className="absolute inset-0 z-10">{visual}</div>

        {/* Actions (z-20) ancrées en bas. La classe `dark` LOCALE force les
            styles sombres des enfants (formulaire d'essai, boutons) : la
            scène est nuit permanente, même quand le thème global est clair. */}
        <motion.div
          style={animated ? { y, opacity, scale } : {}}
          className="dark relative z-20 flex min-h-[100dvh] flex-col items-center justify-end px-4 pb-24 sm:px-6"
        >
          <div className="flex w-full max-w-3xl flex-col items-center gap-4">
            {(ctaPrimary || ctaSecondary) && (
              <motion.div
                initial={animated ? { y: 20 } : {}}
                animate={animated ? { y: 0 } : {}}
                transition={{ duration: 0.8, delay: 0.5 }}
                className="flex flex-col items-center justify-center gap-4 sm:flex-row"
              >
                {ctaPrimary && (
                  <Link
                    href={withLocaleHref(ctaPrimary.href, search)}
                    {...externalProps(ctaPrimary.href)}
                    className="hero-cta-primary group relative overflow-hidden rounded-2xl bg-gradient-to-r from-emerald-700 to-emerald-800 px-8 py-4 font-bold text-white transition-all duration-300 hover:scale-[1.03] active:scale-[0.98]"
                  >
                    <span className="relative z-10 flex items-center gap-2.5 text-base">
                      {ctaPrimary.text}
                      <ArrowRight className="h-5 w-5 transition-transform duration-300 group-hover:translate-x-1" />
                    </span>
                    <div className="absolute inset-0 bg-gradient-to-r from-emerald-600 to-cyan-600 opacity-0 transition-opacity duration-500 group-hover:opacity-100" />
                  </Link>
                )}
                {ctaSecondary && (
                  <Link
                    href={withLocaleHref(ctaSecondary.href, search)}
                    {...externalProps(ctaSecondary.href)}
                    className="group flex items-center gap-3.5 rounded-2xl border border-slate-200 bg-white px-8 py-4 font-semibold text-slate-900 transition-all duration-300 hover:border-emerald-300 hover:shadow-xl dark:border-slate-800 dark:bg-slate-900 dark:text-white dark:hover:border-emerald-800"
                  >
                    {ctaSecondary.icon ? (
                      <div className="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-emerald-100 to-emerald-50 transition-transform duration-300 group-hover:scale-110 dark:from-emerald-900/40 dark:to-emerald-900/20">
                        {ctaSecondary.icon}
                      </div>
                    ) : (
                      <div className="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-emerald-100 to-emerald-50 transition-transform duration-300 group-hover:scale-110 dark:from-emerald-900/40 dark:to-emerald-900/20">
                        <Play className="ml-0.5 h-4 w-4 text-emerald-700 dark:text-emerald-400" />
                      </div>
                    )}
                    {ctaSecondary.text}
                  </Link>
                )}
              </motion.div>
            )}
            {ctaReassurance && (
              <p className="text-center text-sm font-medium text-slate-400">
                {ctaReassurance}
              </p>
            )}
            {quickTrialForm}
          </div>
        </motion.div>

        {/* Transition douce vers la section suivante : la nuit s'ouvre sur
            le thème courant de la page (blanc en jour, ardoise en nuit). */}
        <div className="pointer-events-none absolute inset-x-0 bottom-0 z-[15] h-28 bg-gradient-to-b from-transparent to-white dark:to-slate-950" />

        {/* Indicateur de scroll */}
        {animated && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            transition={{ delay: 1.5 }}
            className="absolute bottom-8 left-1/2 z-20 -translate-x-1/2"
          >
            <motion.div
              animate={{ y: [0, 8, 0] }}
              transition={{ duration: 2.5, repeat: Infinity, ease: 'easeInOut' }}
              className="flex w-6 items-start justify-center rounded-full border-2 border-slate-300 p-1.5 dark:border-slate-700"
            >
              <motion.div
                animate={{ opacity: [1, 0.3, 1], y: [0, 12, 0] }}
                transition={{ duration: 2.5, repeat: Infinity, ease: 'easeInOut' }}
                className="h-1.5 w-1.5 rounded-full bg-emerald-500"
              />
            </motion.div>
          </motion.div>
        )}
      </section>
    );
  }

  return (
    <section ref={ref} className="relative min-h-[100dvh] flex items-center justify-center overflow-hidden">
      {/* Background layers */}
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_80%_50%_at_50%_-20%,rgba(16,185,129,0.12),transparent)] dark:bg-[radial-gradient(ellipse_80%_50%_at_50%_-20%,rgba(16,185,129,0.08),transparent)]" />
      <div className="absolute inset-0 bg-gradient-to-b from-white via-white to-slate-50/80 dark:from-slate-950 dark:via-slate-950 dark:to-slate-900/80" />

      {/* Grid pattern */}
      <div
        className="absolute inset-0 opacity-[0.03] dark:opacity-[0.05]"
        style={{
          backgroundImage: 'linear-gradient(rgba(0,0,0,0.1) 1px, transparent 1px), linear-gradient(90deg, rgba(0,0,0,0.1) 1px, transparent 1px)',
          backgroundSize: '60px 60px',
        }}
      />

      {/* Fond vivant : constellation WebGL « Business OS » si possible,
          champ de particules 2D sinon (même rôle, deux niveaux d'exigence).
          Désactivable (#8389) quand la scène est placée en colonne visuelle.
          #8410 — TOUJOURS estompée en fond : le texte du héro reste lisible
          sur toutes les pages (clair et sombre), la 3D ne passe plus jamais
          « devant » le propos. */}
      {animated && backgroundScene && <HeroScene3D dimmed />}

      {/* Gradient orbs */}
      <div className="absolute top-1/4 -left-32 w-[500px] h-[500px] bg-emerald-400/15 rounded-full blur-[120px] animate-pulse" />
      <div className="absolute bottom-1/4 -right-32 w-[500px] h-[500px] bg-cyan-400/15 rounded-full blur-[120px] animate-pulse [animation-delay:2s]" />
      <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[900px] h-[900px] bg-gradient-to-r from-emerald-500/5 to-cyan-500/5 rounded-full blur-[100px]" />

      <motion.div
        style={animated ? { y, opacity, scale } : {}}
        className={`relative z-10 mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8 ${
          isSplit ? 'pt-32 pb-20 lg:pt-40' : 'pt-32 pb-24'
        }`}
      >
        <div
          className={
            isSplit
              ? // #8403 — la colonne visuelle (constellation 3D) s'élargit
                // (ratio 2:3 via grid-cols-5 — tokens standard, garde i18n)
                // et s'aligne en HAUT avec le récit : la 3D porte la première
                // page, au niveau du texte de gauche, pas centrée mollement.
                'grid grid-cols-1 items-center gap-14 lg:grid-cols-5 lg:items-start lg:gap-10 xl:gap-12'
              : 'mx-auto max-w-5xl'
          }
        >
          {/* ── Colonne « récit » ─────────────────────────────────────── */}
          <div className={isSplit ? `${align} lg:col-span-2` : align}>
            {badgeConfig && (
              <motion.div
                initial={animated ? { y: 20, filter: 'blur(10px)' } : {}}
                animate={animated ? { y: 0, filter: 'blur(0px)' } : {}}
                transition={{ duration: 0.8 }}
                className="mb-10 inline-flex items-center gap-2.5 rounded-full border border-emerald-500/20 bg-emerald-500/[0.08] px-4 py-2 text-sm font-medium text-emerald-700 backdrop-blur-sm dark:text-emerald-400"
              >
                {badgeConfig.icon && <span className="animate-pulse">{badgeConfig.icon}</span>}
                <span>{badgeConfig.text}</span>
                {badgeConfig.label && (
                  <span className="rounded-full bg-emerald-700 px-2 py-0.5 text-[10px] font-black uppercase tracking-wider text-white">
                    {badgeConfig.label}
                  </span>
                )}
              </motion.div>
            )}

            {/* Heading */}
            <motion.h1
              initial={animated ? { y: 30 } : {}}
              animate={animated ? { y: 0 } : {}}
              transition={{ duration: 1, delay: 0.15, ease: [0.22, 1, 0.36, 1] }}
              className={`mb-8 text-balance font-black leading-[0.95] tracking-tight ${
                isSplit
                  ? 'text-4xl sm:text-5xl lg:text-[3.6rem] xl:text-[4.1rem]'
                  : 'text-5xl sm:text-6xl lg:text-[5.5rem]'
              }`}
            >
              <span className="block bg-gradient-to-b from-slate-900 via-slate-800 to-slate-600 bg-clip-text text-transparent dark:from-white dark:via-slate-200 dark:to-slate-400">
                {headline}
              </span>
            </motion.h1>

            {/* Subtitle */}
            {subheadline && (
              <motion.p
                initial={animated ? { y: 20 } : {}}
                animate={animated ? { y: 0 } : {}}
                transition={{ duration: 0.8, delay: 0.35 }}
                className={`font-light leading-relaxed text-slate-500 dark:text-slate-400 ${
                  isSplit
                    ? 'mb-10 text-base sm:text-lg lg:max-w-xl lg:text-lg'
                    : 'mx-auto mb-14 max-w-3xl text-lg sm:text-xl lg:text-2xl'
                }`}
              >
                {subheadline}
              </motion.p>
            )}

            {/* CTAs */}
            {(ctaPrimary || ctaSecondary) && (
              <motion.div
                initial={animated ? { y: 20 } : {}}
                animate={animated ? { y: 0 } : {}}
                transition={{ duration: 0.8, delay: 0.5 }}
                className={`flex flex-col items-center gap-4 sm:flex-row ${
                  isSplit ? 'justify-center lg:justify-start' : 'justify-center'
                }`}
              >
                {ctaPrimary && (
                  <Link
                    href={withLocaleHref(ctaPrimary.href, search)}
                    {...externalProps(ctaPrimary.href)}
                    className="hero-cta-primary group relative overflow-hidden rounded-2xl bg-gradient-to-r from-emerald-700 to-emerald-800 px-8 py-4 font-bold text-white transition-all duration-300 hover:scale-[1.03] active:scale-[0.98]"
                  >
                    <span className="relative z-10 flex items-center gap-2.5 text-base">
                      {ctaPrimary.text}
                      <ArrowRight className="h-5 w-5 transition-transform duration-300 group-hover:translate-x-1" />
                    </span>
                    <div className="absolute inset-0 bg-gradient-to-r from-emerald-600 to-cyan-600 opacity-0 transition-opacity duration-500 group-hover:opacity-100" />
                  </Link>
                )}

                {ctaSecondary && (
                  <Link
                    href={withLocaleHref(ctaSecondary.href, search)}
                    {...externalProps(ctaSecondary.href)}
                    className="group flex items-center gap-3.5 rounded-2xl border border-slate-200 bg-white px-8 py-4 font-semibold text-slate-900 transition-all duration-300 hover:border-emerald-300 hover:shadow-xl dark:border-slate-800 dark:bg-slate-900 dark:text-white dark:hover:border-emerald-800"
                  >
                    {ctaSecondary.icon ? (
                      <div className="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-emerald-100 to-emerald-50 transition-transform duration-300 group-hover:scale-110 dark:from-emerald-900/40 dark:to-emerald-900/20">
                        {ctaSecondary.icon}
                      </div>
                    ) : (
                      <div className="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-emerald-100 to-emerald-50 transition-transform duration-300 group-hover:scale-110 dark:from-emerald-900/40 dark:to-emerald-900/20">
                        <Play className="ml-0.5 h-4 w-4 text-emerald-700 dark:text-emerald-400" />
                      </div>
                    )}
                    {ctaSecondary.text}
                  </Link>
                )}
              </motion.div>
            )}

            {/* Optional quick-trial inline form (e.g. QuickTrialEmailForm with source=hero_email_trial) */}
            {ctaReassurance && (
              <p
                className={`text-sm font-medium text-slate-500 dark:text-slate-400 ${
                  isSplit ? 'text-center lg:text-left' : 'text-center'
                }`}
              >
                {ctaReassurance}
              </p>
            )}
            {quickTrialForm}
          </div>

          {/* ── Visuel ────────────────────────────────────────────────── */}
          {visual && (
            isSplit ? (
              <motion.div
                initial={animated ? { x: 40, scale: 0.96 } : {}}
                animate={animated ? { x: 0, scale: 1 } : {}}
                transition={{ duration: 1, delay: 0.45, ease: [0.22, 1, 0.36, 1] }}
                // #8403 — léger retrait vers le haut : la constellation
                // « monte » au niveau du badge/titre de gauche.
                className="w-full lg:-mt-8 lg:col-span-3"
              >
                {visual}
              </motion.div>
            ) : (
              <motion.div
                initial={animated ? { y: 40, scale: 0.97 } : {}}
                animate={animated ? { y: 0, scale: 1 } : {}}
                transition={{ duration: 1, delay: 0.6, ease: [0.22, 1, 0.36, 1] }}
                className="mx-auto mt-16 max-w-4xl"
              >
                {visual}
              </motion.div>
            )
          )}
        </div>

        {/* Stats */}
        {stats && stats.length > 0 && (
          <motion.div
            initial={animated ? { y: 40 } : {}}
            animate={animated ? { y: 0 } : {}}
            transition={{ duration: 1, delay: 0.7 }}
            className="mx-auto mt-24 grid max-w-4xl grid-cols-2 gap-8 md:grid-cols-4"
          >
            {stats.map((stat, i) => (
              <div key={i} className="group text-center">
                <div className="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500/10 to-cyan-500/10 transition-transform duration-300 group-hover:scale-110">
                  {stat.icon}
                </div>
                <div className="bg-gradient-to-b from-slate-900 to-slate-600 bg-clip-text text-3xl font-black text-transparent sm:text-4xl dark:from-white dark:to-slate-400">
                  {stat.value}
                  {stat.suffix}
                </div>
                <div className="mt-1.5 text-sm font-medium text-slate-500 dark:text-slate-400">{stat.label}</div>
              </div>
            ))}
          </motion.div>
        )}
      </motion.div>

      {/* Scroll indicator */}
      {animated && (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ delay: 1.5 }}
          className="absolute bottom-8 left-1/2 -translate-x-1/2"
        >
          <motion.div
            animate={{ y: [0, 8, 0] }}
            transition={{ duration: 2.5, repeat: Infinity, ease: 'easeInOut' }}
            className="flex w-6 items-start justify-center rounded-full border-2 border-slate-300 p-1.5 dark:border-slate-700"
          >
            <motion.div
              animate={{ opacity: [1, 0.3, 1], y: [0, 12, 0] }}
              transition={{ duration: 2.5, repeat: Infinity, ease: 'easeInOut' }}
              className="h-1.5 w-1.5 rounded-full bg-emerald-500"
            />
          </motion.div>
        </motion.div>
      )}
    </section>
  );
}
