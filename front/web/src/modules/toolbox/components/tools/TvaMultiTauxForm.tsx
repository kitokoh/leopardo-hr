'use client';

import React, { useCallback, useMemo, useState } from 'react';
import { Plus, Trash2 } from 'lucide-react';
import { NumberField } from '../NumberField';
import { ResultPanel, type ResultRow } from '../ResultPanel';
import { computeVatRecap } from '../../tools/tva-multi-taux/logic';
import {
  MAX_SHARED_LINES,
  INITIAL_LINES,
  LINE_KEY_PATTERN,
  addLine as addLineToList,
  canAddLine,
  linesToState,
  parseLines,
  pruneOrphanKeys,
  removeLine as removeLineFromList,
  stateToLines,
  type LineState,
} from '../../tools/tva-multi-taux/lines';
import { formatCurrency, formatNumber } from '../../lib/format';
import { round } from '../../lib/validation';
import { useToolState } from '../../lib/useToolState';
import type { ToolCopy, ToolboxCopy, ToolboxLocale } from '../../data/copy';

interface TvaMultiTauxFormProps {
  copy: ToolCopy;
  common: ToolboxCopy['common'];
  locale: ToolboxLocale;
}

/**
 * Formulaire TVA multi-taux — récapitulatif d'une facture à plusieurs lignes.
 *
 * La conversion lignes ↔ état partageable vit désormais dans
 * `tools/tva-multi-taux/lines.ts` (fonctions pures, testées). Elle y a été
 * extraite parce que le faire ici causait deux défauts réels (issue #9531) :
 *
 * - **Supprimer une ligne laissait des clés orphelines** (`amount1`, `rate1`…).
 *   La relecture ordonnée les faisait réapparaître en duplicata de la ligne
 *   suivante : le nombre de lignes ne diminuait pas et le total TTC était faux.
 *   → on supprime maintenant réellement ces clés via `removeValue`.
 * - **Le plafond de partage n'était pas appliqué à l'interface** : au-delà de
 *   `MAX_SHARED_LINES`, l'URL ne transportait qu'une partie des lignes et un
 *   lien partagé recalculait un autre total, sans avertissement.
 *   → l'ajout est borné et le refus est annoncé.
 */
export function TvaMultiTauxForm({ copy, common, locale }: TvaMultiTauxFormProps) {
  // `extraKeyPattern` : l'état porte 2 lignes par défaut mais jusqu'à
  // MAX_SHARED_LINES. Sans ce motif, un lien partagé à 3 lignes en perdait une
  // à l'ouverture (issue #9531). Le motif n'ouvre QUE les clés de ligne.
  const [state, setStateValue, removeValue] = useToolState(linesToState(INITIAL_LINES), {
    extraKeyPattern: LINE_KEY_PATTERN,
  });
  const [capReached, setCapReached] = useState(false);

  const lines = useMemo(() => stateToLines(state), [state]);

  /**
   * Écrit les lignes données ET supprime les clés devenues orphelines.
   *
   * L'ordre compte : on écrit d'abord les nouvelles valeurs, puis on supprime
   * les clés qui ne correspondent plus à aucune ligne. Sans cette seconde
   * étape, une ligne supprimée ressuscitait à la relecture (défaut #9531).
   */
  const commitLines = useCallback(
    (next: LineState[]) => {
      const flat = linesToState(next);
      const orphans = pruneOrphanKeys(state, next);

      for (const [key, value] of Object.entries(flat)) {
        if (state[key] !== value) setStateValue(key, value);
      }
      removeValue(orphans);
    },
    [state, setStateValue, removeValue],
  );

  function handleSetLine(index: number, field: keyof LineState, value: string) {
    commitLines(
      lines.map((line, position) => (position === index ? { ...line, [field]: value } : line)),
    );
  }

  function handleAddLine() {
    const next = addLineToList(lines);
    // `addLine` retourne le MÊME tableau quand le plafond est atteint : c'est
    // ainsi que le refus est détecté, sans recompter la constante ici.
    if (next === lines) {
      setCapReached(true);
      return;
    }
    setCapReached(false);
    commitLines(next);
  }

  function handleRemoveLine(index: number) {
    const next = removeLineFromList(lines, index);
    if (next === lines) return;
    setCapReached(false);
    commitLines(next);
  }

  const parsed = useMemo(() => parseLines(lines), [lines]);
  const recap = parsed ? computeVatRecap(parsed) : null;

  const rows: ResultRow[] = [];
  if (recap) {
    rows.push({ label: copy.results.totalHt, value: formatCurrency(recap.totalHt, locale) });
    rows.push({ label: copy.results.totalVat, value: formatCurrency(recap.totalVat, locale) });
    rows.push({
      label: copy.results.totalTtc,
      value: formatCurrency(recap.totalTtc, locale),
      emphasis: true,
    });
  }

  return (
    <div className="space-y-5">
      <div className="space-y-3">
        {lines.map((line, index) => (
          <div key={index} className="flex items-end gap-3">
            <div className="flex-1">
              <NumberField
                id={`vat-line-amount-${index}`}
                label={copy.fields.amount}
                value={line.amount}
                onChange={(value) => handleSetLine(index, 'amount', value)}
                min={0}
              />
            </div>
            <div className="w-32">
              <NumberField
                id={`vat-line-rate-${index}`}
                label={copy.fields.rate}
                value={line.rate}
                onChange={(value) => handleSetLine(index, 'rate', value)}
                min={0}
              />
            </div>
            <button
              type="button"
              onClick={() => handleRemoveLine(index)}
              aria-label={copy.fields.removeLine}
              disabled={lines.length <= 1}
              className="mb-1 inline-flex items-center justify-center w-10 h-10 rounded-xl border border-slate-300 dark:border-slate-700 text-slate-500 dark:text-slate-400 hover:border-red-400 hover:text-red-600 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
            >
              <Trash2 className="w-4 h-4" />
            </button>
          </div>
        ))}
      </div>

      <div className="space-y-2">
        {/* Volontairement NON désactivé au plafond : un bouton grisé n'explique
            rien. Le clic reste possible et affiche la raison (retour visuel
            explicite demandé par l'issue #9531). */}
        <button
          type="button"
          onClick={handleAddLine}
          aria-describedby={capReached ? 'vat-lines-cap' : undefined}
          className="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-semibold rounded-xl border border-emerald-300 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 transition-colors"
        >
          <Plus className="w-4 h-4" />
          {copy.fields.addLine}
        </button>

        {/* Le plafond est une limite de PARTAGE, pas de calcul : le dire évite
            de laisser croire que l'outil refuse une facture plus longue. */}
        {capReached && (
          <p id="vat-lines-cap" role="status" className="text-sm text-amber-700 dark:text-amber-400">
            {copy.fields.maxLinesReached.replace('{max}', String(MAX_SHARED_LINES))}
          </p>
        )}
      </div>

      {recap ? (
        <div className="space-y-4">
          <ResultPanel
            title={common.result}
            rows={rows}
            copyLabel={common.copy}
            copiedLabel={common.copied}
            copyText={rows.map((row) => `${row.label}: ${row.value}`).join(' — ')}
            share={{ values: state, copy: common }}
            /*
             * Résultat TABULAIRE : le récapitulatif par taux a une vraie forme
             * de tableau (une ligne par taux) et les totaux s'y rattachent.
             * C'est exactement ce qu'un tableur attend — d'où le bouton CSV,
             * absent des outils à résultat scalaire.
             *
             * Les cellules reprennent les valeurs DÉJÀ AFFICHÉES (donc déjà
             * localisées), et le montant numérique est écrit en clair (point
             * décimal) plutôt que formaté avec des séparateurs de milliers :
             * un tableur doit pouvoir le relire comme un nombre.
             */
            csv={{
              headers: [
                copy.results.byRate,
                copy.results.colBaseHt,
                copy.results.colVat,
              ],
              rows: [
                ...recap.groups.map((group) => [
                  `${formatNumber(group.ratePercent, locale, 2)} %`,
                  String(round(group.baseHt, 2)),
                  String(round(group.vat, 2)),
                ]),
                [copy.results.totalHt, String(round(recap.totalHt, 2)), ''],
                [copy.results.totalVat, '', String(round(recap.totalVat, 2))],
                [copy.results.totalTtc, String(round(recap.totalTtc, 2)), ''],
              ],
            }}
          />

          <div className="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5">
            <h3 className="text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-3">
              {copy.results.byRate}
            </h3>
            <dl className="space-y-2">
              {recap.groups.map((group) => (
                <div
                  key={group.ratePercent}
                  className="flex items-baseline justify-between gap-4 text-sm"
                >
                  <dt className="text-slate-600 dark:text-slate-400">
                    {`${formatNumber(group.ratePercent, locale, 2)} %`}
                  </dt>
                  <dd className="font-semibold text-slate-900 dark:text-white tabular-nums">
                    {`${formatCurrency(group.baseHt, locale)} + ${formatCurrency(group.vat, locale)}`}
                  </dd>
                </div>
              ))}
            </dl>
          </div>
        </div>
      ) : (
        <p className="text-sm text-slate-500 dark:text-slate-400">{common.invalidInput}</p>
      )}

      {copy.notes && copy.notes.length > 0 && (
        <ul className="space-y-1.5 text-xs text-slate-500 dark:text-slate-400">
          {copy.notes.map((note) => (
            <li key={note}>• {note}</li>
          ))}
        </ul>
      )}
    </div>
  );
}
