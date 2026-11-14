'use client';

import React, { useState } from 'react';
import { Check, Copy } from 'lucide-react';
import { ShareButton } from './ShareButton';
import { shareCopy } from '../lib/share-copy';
import type { ToolStateValues } from '../lib/share';
import type { ToolboxCopy } from '../data/copy';

export interface ResultRow {
  label: string;
  value: string;
  /** Ligne mise en avant (résultat principal). */
  emphasis?: boolean;
}

interface ResultPanelProps {
  title: string;
  rows: ResultRow[];
  copyLabel: string;
  copiedLabel: string;
  /** Texte brut copié dans le presse-papiers (résultats concaténés). */
  copyText: string;
  /**
   * Partage par lien — optionnel.
   *
   * `values` porte les ENTRÉES du formulaire (jamais le résultat) : le lien
   * rejoue le calcul avec la formule courante. Absent = pas de bouton de
   * partage (un outil sans entrée sérialisable n'en propose pas).
   */
  share?: {
    values: ToolStateValues;
    copy: ToolboxCopy['common'];
  };
}

/**
 * Panneau de résultats — affichage structuré + copie dans le presse-papiers.
 *
 * Aucune donnée n'est envoyée : la copie utilise l'API navigateur
 * `navigator.clipboard` (repli silencieux si indisponible).
 */
export function ResultPanel({ title, rows, copyLabel, copiedLabel, copyText, share }: ResultPanelProps) {
  const [copied, setCopied] = useState(false);

  async function handleCopy() {
    try {
      await navigator.clipboard.writeText(copyText);
      setCopied(true);
      window.setTimeout(() => setCopied(false), 2000);
    } catch {
      // Presse-papiers indisponible (contexte non sécurisé) : on n'échoue pas.
    }
  }

  return (
    <div className="rounded-2xl border border-emerald-200 dark:border-emerald-900 bg-emerald-50/50 dark:bg-emerald-950/20 p-5">
      <div className="flex items-center justify-between gap-3 mb-4">
        <h3 className="text-sm font-bold uppercase tracking-wide text-emerald-800 dark:text-emerald-300">
          {title}
        </h3>
        <button
          type="button"
          onClick={handleCopy}
          className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg border border-emerald-300 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 hover:bg-emerald-100 dark:hover:bg-emerald-900/40 transition-colors"
        >
          {copied ? <Check className="w-3.5 h-3.5" /> : <Copy className="w-3.5 h-3.5" />}
          {copied ? copiedLabel : copyLabel}
        </button>
      </div>

      {/*
        Région live : le résultat est recalculé à chaque frappe, mais un lecteur
        d'écran n'annonce rien sans `aria-live`. `polite` (et non `assertive`)
        pour ne pas interrompre la saisie en cours ; `aria-atomic` pour que la
        phrase annoncée soit le bloc complet et non le seul fragment modifié.
        Les boutons Copier/Partager restent HORS de la région : sinon chaque
        recalcul ré-annoncerait les libellés d'action.
      */}
      <dl aria-live="polite" aria-atomic="true" className="space-y-3">
        {rows.map((row) => (
          <div key={row.label} className="flex items-baseline justify-between gap-4">
            <dt className="text-sm text-slate-600 dark:text-slate-400">{row.label}</dt>
            <dd
              className={
                row.emphasis
                  ? 'text-2xl font-bold text-emerald-700 dark:text-emerald-300 tabular-nums'
                  : 'text-base font-semibold text-slate-900 dark:text-white tabular-nums'
              }
            >
              {row.value}
            </dd>
          </div>
        ))}
      </dl>

      {share && <ShareButton values={share.values} copy={shareCopy(share.copy)} />}
    </div>
  );
}
