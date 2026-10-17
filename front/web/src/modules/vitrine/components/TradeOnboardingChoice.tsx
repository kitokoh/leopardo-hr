'use client';

/**
 * #8489 / #8490 (I-02, I-03) — choix d'entree « Trade ».
 *
 * Deux decisions, une seule page :
 *  1. le MODELE d'activite (International / Domestique) — la difference est
 *     portee par la configuration metier, jamais par deux systemes de vente
 *     separes (exigence de la mission) ;
 *  2. l'ACTION (creer un espace / rejoindre une entreprise) — « rejoindre »
 *     ne cree JAMAIS de tenant : un utilisateur qui appartient deja a une
 *     entreprise est renvoye vers la connexion existante, puis rattache a
 *     l'espace deja provisionne.
 *
 * Tout le texte visible vient du catalogue partage (`vitrine.trade.*`, x4
 * locales) : aucune chaine en dur (garde PA2-I18N-014 / #7664).
 */

import Link from 'next/link';
import { useState } from 'react';
import { motion } from 'framer-motion';
import { ArrowRight, Building2, Globe2, Store } from 'lucide-react';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';
import { t } from '@/lib/i18n/locale-catalog';
import {
  DEFAULT_TRADE_INTENT,
  TRADE_PROFILES_BY_MODE,
  tradeCreateHref,
  tradeJoinHref,
  type TradeAction,
  type TradeMode,
  type TradeProfile,
} from '@/modules/vitrine/lib/trade-intent';

type ModeCard = {
  mode: TradeMode;
  icon: React.ReactNode;
  titleKey: string;
  descKey: string;
  pointKeys: [string, string, string];
};

const iconClass = 'w-6 h-6';

const MODE_CARDS: ModeCard[] = [
  {
    mode: 'international',
    icon: <Globe2 className={iconClass} />,
    titleKey: 'internationalTitle',
    descKey: 'internationalDesc',
    pointKeys: ['internationalPoint1', 'internationalPoint2', 'internationalPoint3'],
  },
  {
    mode: 'domestic',
    icon: <Store className={iconClass} />,
    titleKey: 'domesticTitle',
    descKey: 'domesticDesc',
    pointKeys: ['domesticPoint1', 'domesticPoint2', 'domesticPoint3'],
  },
];

type ActionCopy = {
  action: TradeAction;
  icon: React.ReactNode;
  titleKey: string;
  descKey: string;
  ctaKey: string;
};

const ACTION_CARDS: ActionCopy[] = [
  {
    action: 'create',
    icon: <Building2 className={iconClass} />,
    titleKey: 'createTitle',
    descKey: 'createDesc',
    ctaKey: 'createCta',
  },
  {
    action: 'join',
    icon: <ArrowRight className={iconClass} />,
    titleKey: 'joinTitle',
    descKey: 'joinDesc',
    ctaKey: 'joinCta',
  },
];

export interface TradeOnboardingChoiceProps {
  /** Mode preselectionne (ex. `?mode=international`) — defaut fail-closed. */
  initialMode?: TradeMode;
}

export function TradeOnboardingChoice({ initialMode }: TradeOnboardingChoiceProps) {
  const { locale } = useVitrineLocale();
  const tr = (key: string) => t(locale, `vitrine.trade.${key}`);
  const mode = initialMode ?? DEFAULT_TRADE_INTENT.mode;

  // BOS-O-01 / #8625 — le profil professionnel est un CHOIX REPRESENTE, pas une
  // phrase marketing : la vitrine annoncait deja « importateur, exportateur ou
  // les deux » sans qu'aucune valeur ne puisse etre transportee. Un profil est
  // stocke par marche (changer de marche ne conserve pas un profil incoherent)
  // et reste OPTIONNEL : ne rien choisir n'empeche jamais de creer son espace.
  const [profiles, setProfiles] = useState<Partial<Record<TradeMode, TradeProfile>>>({});

  const selectProfile = (tradeMode: TradeMode, profile: TradeProfile) => {
    setProfiles((previous) => ({
      ...previous,
      [tradeMode]: previous[tradeMode] === profile ? undefined : profile,
    }));
  };

  return (
    <div className="mx-auto grid w-full max-w-5xl grid-cols-1 gap-6 lg:grid-cols-2">
      {MODE_CARDS.map((card, index) => (
        <motion.section
          key={card.mode}
          initial={{ y: 24, opacity: 0 }}
          animate={{ y: 0, opacity: 1 }}
          transition={{ duration: 0.45, delay: index * 0.08 }}
          aria-labelledby={`trade-mode-${card.mode}`}
          className="flex h-full flex-col rounded-2xl border border-slate-200/80 bg-slate-50/60 p-6 dark:border-slate-800/80 dark:bg-slate-900/60"
        >
          <div className="mb-4 inline-flex items-center justify-center w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
            {card.icon}
          </div>
          <h3
            id={`trade-mode-${card.mode}`}
            className="text-xl font-bold text-slate-900 dark:text-white"
          >
            {tr(card.titleKey)}
          </h3>
          <p className="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
            {tr(card.descKey)}
          </p>
          <ul className="mt-4 flex-1 space-y-2">
            {card.pointKeys.map((pointKey) => (
              <li
                key={pointKey}
                className="flex items-start gap-2 text-sm text-slate-700 dark:text-slate-300"
              >
                <span
                  aria-hidden="true"
                  className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-500"
                />
                {tr(pointKey)}
              </li>
            ))}
          </ul>

          {/* Choix du profil DANS ce marche (BOS-O-01). Rendu en boutons
              radio accessibles ; aucun texte en dur (garde i18n). */}
          <fieldset className="mt-4">
            <legend className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
              {tr('profileLabel')}
            </legend>
            <div className="flex flex-wrap gap-2" role="radiogroup" aria-label={tr('profileLabel')}>
              {TRADE_PROFILES_BY_MODE[card.mode].map((profile) => {
                const selected = profiles[card.mode] === profile;

                return (
                  <button
                    key={profile}
                    type="button"
                    role="radio"
                    aria-checked={selected}
                    onClick={() => selectProfile(card.mode, profile)}
                    className={`rounded-full border px-3 py-1.5 text-xs font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 ${selected ? 'border-brand-700 bg-brand-700 text-white' : 'border-slate-300 text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-900'}`}
                  >
                    {tr(`profile_${profile}`)}
                  </button>
                );
              })}
            </div>
          </fieldset>

          <div className="mt-6 flex flex-col gap-2">
            <Link
              href={tradeCreateHref(card.mode, undefined, profiles[card.mode] ?? null)}
              className="inline-flex items-center justify-center rounded-xl bg-brand-700 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
            >
              {tr('createCta')}
            </Link>
            <Link
              href={tradeJoinHref()}
              className="inline-flex items-center justify-center rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-900"
            >
              {tr('joinCta')}
            </Link>
          </div>
        </motion.section>
      ))}

      <div className="lg:col-span-2">
        <p className="text-center text-xs text-slate-500 dark:text-slate-400">{tr('joinHint')}</p>
      </div>

      {/* Rappel du choix deja fait quand un mode est preselectionne : la carte
          « creer » de ce mode porte le mode dans l'URL, aucun second ecran. */}
      <div className="lg:col-span-2 mt-2 grid grid-cols-1 gap-4 sm:grid-cols-2">
        {ACTION_CARDS.map((card) => (
          <div
            key={card.action}
            className="rounded-2xl border border-slate-200/80 px-5 py-4 dark:border-slate-800/80"
          >
            <h3 className="text-sm font-bold text-slate-900 dark:text-white">{tr(card.titleKey)}</h3>
            <p className="mt-1 text-xs leading-relaxed text-slate-600 dark:text-slate-400">
              {tr(card.descKey)}
            </p>
          </div>
        ))}
      </div>

      <p className="lg:col-span-2 text-center text-xs text-slate-500 dark:text-slate-400">
        {mode === 'international' ? tr('internationalPoint3') : tr('domesticPoint3')}
      </p>
    </div>
  );
}

export default TradeOnboardingChoice;
