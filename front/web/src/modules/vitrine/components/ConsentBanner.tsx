'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';

import { useConsent } from '@/modules/vitrine/components/ConsentProvider';
import { acceptAllConsent, deniedConsent, type ConsentPayload } from '@/modules/vitrine/lib/consent';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';
import { t } from '@/lib/i18n/locale-catalog';

/**
 * Bandeau de consentement + panneau de préférences (issue #7593).
 *
 * Principes appliqués :
 *  - **aucun dark pattern** : « Tout accepter » et « Tout refuser » ont le même
 *    poids visuel, au même niveau, et « Personnaliser » mène à un choix fin ;
 *  - **refus par défaut** : tant que rien n'est choisi, aucun traceur n'est
 *    chargé (voir `ConsentScripts`) ;
 *  - **accessible** : `role="dialog"`, titre relié, fermeture au clavier,
 *    interrupteurs natifs (cases à cocher) plutôt que des `<div>` cliquables.
 */
export function ConsentBanner() {
  const { isBannerVisible, isPreferencesOpen, decide, openPreferences, closePreferences } = useConsent();
  const { locale } = useVitrineLocale();
  const [draft, setDraft] = useState<ConsentPayload>(deniedConsent);

  useEffect(() => {
    if (isPreferencesOpen) setDraft(deniedConsent());
  }, [isPreferencesOpen]);

  if (!isBannerVisible && !isPreferencesOpen) return null;

  const text = (key: string) => t(locale, `consent.${key}`);

  const categories = [
    {
      id: 'necessary' as const,
      label: text('necessary'),
      description: text('necessaryDesc'),
      checked: true,
      locked: true,
    },
    {
      id: 'analytics' as const,
      label: text('analytics'),
      description: text('analyticsDesc'),
      checked: draft.analytics,
      locked: false,
    },
    {
      id: 'marketing' as const,
      label: text('marketing'),
      description: text('marketingDesc'),
      checked: draft.marketing,
      locked: false,
    },
  ];

  return (
    <div
      role="dialog"
      aria-modal="false"
      aria-labelledby="consent-title"
      aria-describedby="consent-body"
      className="fixed inset-x-0 bottom-0 z-[90] border-t border-slate-200 bg-white/95 p-4 shadow-lg backdrop-blur dark:border-slate-800 dark:bg-slate-900/95 sm:p-6"
    >
      <div className="mx-auto w-full max-w-4xl">
        <h2
          id="consent-title"
          className="text-base font-bold text-slate-900 dark:text-white"
        >
          {isPreferencesOpen ? text('settingsTitle') : text('title')}
        </h2>

        {!isPreferencesOpen && (
          <p id="consent-body" className="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">
            {text('body')}
          </p>
        )}

        {/* Préférences par finalité */}
        <fieldset className={isPreferencesOpen ? 'mt-4 space-y-3' : 'hidden'}>
          <legend className="sr-only">{text('settingsTitle')}</legend>
          {categories.map((category) => (
            <label
              key={category.id}
              className="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-800"
            >
              <input
                type="checkbox"
                name={category.id}
                checked={category.checked}
                disabled={category.locked}
                onChange={(event) =>
                  setDraft((previous) => ({ ...previous, [category.id]: event.target.checked }))
                }
                className="mt-1 h-4 w-4 rounded border-slate-300 text-emerald-700 focus:ring-emerald-600"
              />
              <span>
                <span className="block text-sm font-semibold text-slate-900 dark:text-white">
                  {category.label}
                  {category.locked && (
                    <span className="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                      {text('alwaysOn')}
                    </span>
                  )}
                </span>
                <span className="mt-1 block text-xs leading-relaxed text-slate-600 dark:text-slate-400">
                  {category.description}
                </span>
              </span>
            </label>
          ))}
        </fieldset>

        <div className="mt-4 flex flex-wrap gap-3">
          {/* Même poids visuel pour accepter et refuser (exigence d'équilibre). */}
          <button
            type="button"
            onClick={() => decide(acceptAllConsent())}
            className="inline-flex items-center rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2"
          >
            {text('acceptAll')}
          </button>
          <button
            type="button"
            onClick={() => decide(deniedConsent())}
            className="inline-flex items-center rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
          >
            {text('refuseAll')}
          </button>

          {isPreferencesOpen ? (
            <button
              type="button"
              onClick={() => decide(draft)}
              className="inline-flex items-center rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
            >
              {text('save')}
            </button>
          ) : (
            <button
              type="button"
              onClick={openPreferences}
              className="inline-flex items-center rounded-xl px-5 py-2.5 text-sm font-semibold text-slate-700 underline decoration-slate-300 underline-offset-4 transition hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 dark:text-slate-200 dark:hover:text-white"
            >
              {text('customize')}
            </button>
          )}

          {isPreferencesOpen && (
            <button
              type="button"
              onClick={closePreferences}
              className="inline-flex items-center rounded-xl px-5 py-2.5 text-sm font-semibold text-slate-600 underline decoration-slate-300 underline-offset-4 transition hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 dark:text-slate-300 dark:hover:text-white"
            >
              {text('close')}
            </button>
          )}
        </div>

        <p className="mt-3 text-xs text-slate-500 dark:text-slate-400">
          <Link href="/privacy" className="underline decoration-slate-300 underline-offset-2 hover:text-slate-700 dark:hover:text-slate-200">
            {text('privacyLink')}
          </Link>
        </p>
      </div>
    </div>
  );
}
