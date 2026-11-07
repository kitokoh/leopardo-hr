'use client';

import React, { useState } from 'react';
import { NumberField } from '../NumberField';
import { ResultPanel } from '../ResultPanel';
import { calculatePercent, type PercentMode } from '../../tools/pourcentage/logic';
import { parseNumber } from '../../lib/validation';
import { formatNumber } from '../../lib/format';
import type { ToolCopy, ToolboxCopy, ToolboxLocale } from '../../data/copy';

interface PercentFormProps {
  copy: ToolCopy;
  common: ToolboxCopy['common'];
  locale: ToolboxLocale;
}

/** Formulaire pourcentages — 3 modes : % d'une valeur, évolution, remise. */
export function PercentForm({ copy, common, locale }: PercentFormProps) {
  const [mode, setMode] = useState<PercentMode>('percent-of');
  const [a, setA] = useState('250');
  const [b, setB] = useState('20');

  const aValue = parseNumber(a);
  const bValue = parseNumber(b);
  const result =
    aValue !== null && bValue !== null ? calculatePercent(mode, aValue, bValue) : null;

  const isPercentResult = mode === 'evolution';

  return (
    <div className="space-y-5">
      <div className="w-full">
        <label
          htmlFor="percent-mode"
          className="block text-sm font-semibold text-slate-900 dark:text-white mb-2"
        >
          {copy.fields.mode}
        </label>
        <select
          id="percent-mode"
          value={mode}
          onChange={(event) => setMode(event.target.value as PercentMode)}
          className="w-full px-4 py-2.5 text-sm font-medium bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500"
        >
          <option value="percent-of">{copy.options?.['percent-of']}</option>
          <option value="evolution">{copy.options?.evolution}</option>
          <option value="discount">{copy.options?.discount}</option>
        </select>
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <NumberField id="percent-a" label={copy.fields.a} value={a} onChange={setA} />
        <NumberField id="percent-b" label={copy.fields.b} value={b} onChange={setB} />
      </div>

      {result !== null ? (
        <ResultPanel
          title={common.result}
          rows={[
            {
              label: copy.results.value,
              value: isPercentResult
                ? `${formatNumber(result, locale, 2)} %`
                : formatNumber(result, locale, 2),
              emphasis: true,
            },
          ]}
          copyLabel={common.copy}
          copiedLabel={common.copied}
          copyText={
            isPercentResult
              ? `${formatNumber(result, locale, 2)} %`
              : formatNumber(result, locale, 2)
          }
        />
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
