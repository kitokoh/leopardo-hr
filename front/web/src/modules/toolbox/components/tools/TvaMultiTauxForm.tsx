'use client';

import React, { useState } from 'react';
import { Plus, Trash2 } from 'lucide-react';
import { NumberField } from '../NumberField';
import { ResultPanel, type ResultRow } from '../ResultPanel';
import { computeVatRecap, type VatLine } from '../../tools/tva-multi-taux/logic';
import { parseNumber } from '../../lib/validation';
import { formatCurrency, formatNumber } from '../../lib/format';
import { useToolState } from '../../lib/useToolState';
import type { ToolCopy, ToolboxCopy, ToolboxLocale } from '../../data/copy';

interface TvaMultiTauxFormProps {
  copy: ToolCopy;
  common: ToolboxCopy['common'];
  locale: ToolboxLocale;
}

interface LineState {
  amount: string;
  rate: string;
}

const INITIAL_LINES: LineState[] = [
  { amount: '1000', rate: '20' },
  { amount: '200', rate: '5.5' },
];

/**
 * Sérialise les lignes en état plat partageable (`amount0`, `rate0`, …).
 *
 * Le codec de partage ne connaît que des paires clé/valeur simples : on aplatit
 * donc la liste, avec un nombre de lignes borné pour garder une URL courte.
 */
const MAX_SHARED_LINES = 10;

function linesToState(lines: LineState[]): Record<string, string> {
  const state: Record<string, string> = {};
  lines.slice(0, MAX_SHARED_LINES).forEach((line, index) => {
    state[`amount${index}`] = line.amount;
    state[`rate${index}`] = line.rate;
  });
  return state;
}

/** Reconstruit les lignes depuis un état plat (au moins une ligne). */
function stateToLines(state: Record<string, string>): LineState[] {
  const lines: LineState[] = [];
  for (let index = 0; index < MAX_SHARED_LINES; index += 1) {
    const amount = state[`amount${index}`];
    const rate = state[`rate${index}`];
    if (amount === undefined || rate === undefined) break;
    lines.push({ amount, rate });
  }
  return lines.length > 0 ? lines : INITIAL_LINES;
}

/** Formulaire TVA multi-taux — récapitulatif d'une facture à plusieurs lignes. */
export function TvaMultiTauxForm({ copy, common, locale }: TvaMultiTauxFormProps) {
  const [state, setStateValue] = useToolState(linesToState(INITIAL_LINES));
  const lines = stateToLines(state);

  /** Réécrit l'état plat à partir des lignes courantes. */
  function commitLines(next: LineState[]) {
    const flat = linesToState(next);
    for (const key of Object.keys(flat)) setStateValue(key, flat[key]);
  }

  const parsed: VatLine[] | null = (() => {
    const result: VatLine[] = [];
    for (const line of lines) {
      const amountHt = parseNumber(line.amount);
      const ratePercent = parseNumber(line.rate);
      if (amountHt === null || ratePercent === null) return null;
      result.push({ amountHt, ratePercent });
    }
    return result;
  })();

  const recap = parsed ? computeVatRecap(parsed) : null;

  function setLine(index: number, field: keyof LineState, value: string) {
    commitLines(
      lines.map((line, position) =>
        position === index ? { ...line, [field]: value } : line,
      ),
    );
  }

  function addLine() {
    commitLines([...lines, { amount: '0', rate: '20' }]);
  }

  function removeLine(index: number) {
    commitLines(lines.filter((_, position) => position !== index));
  }

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
                onChange={(value) => setLine(index, 'amount', value)}
                min={0}
              />
            </div>
            <div className="w-32">
              <NumberField
                id={`vat-line-rate-${index}`}
                label={copy.fields.rate}
                value={line.rate}
                onChange={(value) => setLine(index, 'rate', value)}
                min={0}
              />
            </div>
            <button
              type="button"
              onClick={() => removeLine(index)}
              aria-label={copy.fields.removeLine}
              disabled={lines.length <= 1}
              className="mb-1 inline-flex items-center justify-center w-10 h-10 rounded-xl border border-slate-300 dark:border-slate-700 text-slate-500 dark:text-slate-400 hover:border-red-400 hover:text-red-600 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
            >
              <Trash2 className="w-4 h-4" />
            </button>
          </div>
        ))}
      </div>

      <button
        type="button"
        onClick={addLine}
        className="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-semibold rounded-xl border border-emerald-300 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 transition-colors"
      >
        <Plus className="w-4 h-4" />
        {copy.fields.addLine}
      </button>

      {recap ? (
        <div className="space-y-4">
          <ResultPanel
            title={common.result}
            rows={rows}
            copyLabel={common.copy}
            copiedLabel={common.copied}
            copyText={rows.map((row) => `${row.label}: ${row.value}`).join(' — ')}
            share={{ values: state, copy: common }}
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
