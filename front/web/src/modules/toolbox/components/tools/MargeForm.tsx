'use client';

import React, { useState } from 'react';
import { NumberField } from '../NumberField';
import { ResultPanel, type ResultRow } from '../ResultPanel';
import {
  computeMargin,
  priceFromMarginRate,
  priceFromMarkupRate,
} from '../../tools/marge-prix-vente/logic';
import { parseNumber } from '../../lib/validation';
import { formatCurrency, formatNumber } from '../../lib/format';
import type { ToolCopy, ToolboxCopy, ToolboxLocale } from '../../data/copy';

interface MargeFormProps {
  copy: ToolCopy;
  common: ToolboxCopy['common'];
  locale: ToolboxLocale;
}

type MarginMode = 'from-cost-price' | 'from-margin' | 'from-markup';

/** Formulaire marge et prix de vente — trois modes de calcul. */
export function MargeForm({ copy, common, locale }: MargeFormProps) {
  const [mode, setMode] = useState<MarginMode>('from-cost-price');
  const [cost, setCost] = useState('100');
  const [price, setPrice] = useState('130');
  const [rate, setRate] = useState('30');

  const costValue = parseNumber(cost);
  const priceValue = parseNumber(price);
  const rateValue = parseNumber(rate);

  const rows: ResultRow[] = [];

  if (mode === 'from-cost-price') {
    const result =
      costValue !== null && priceValue !== null
        ? computeMargin({ cost: costValue, price: priceValue })
        : null;

    if (result) {
      rows.push({ label: copy.results.margin, value: formatCurrency(result.margin, locale) });
      rows.push({
        label: copy.results.marginRate,
        value:
          result.marginRate === null
            ? '—'
            : `${formatNumber(result.marginRate, locale, 2)} %`,
      });
      rows.push({
        label: copy.results.markupRate,
        value:
          result.markupRate === null
            ? '—'
            : `${formatNumber(result.markupRate, locale, 2)} %`,
      });
      rows.push({
        label: copy.results.coefficient,
        value:
          result.coefficient === null ? '—' : formatNumber(result.coefficient, locale, 4),
        emphasis: true,
      });
    }
  } else {
    const computed =
      costValue !== null && rateValue !== null
        ? mode === 'from-margin'
          ? priceFromMarginRate(costValue, rateValue)
          : priceFromMarkupRate(costValue, rateValue)
        : null;

    if (computed !== null) {
      rows.push({
        label: copy.results.price,
        value: formatCurrency(computed, locale),
        emphasis: true,
      });
    }
  }

  return (
    <div className="space-y-5">
      <div className="w-full">
        <label
          htmlFor="marge-mode"
          className="block text-sm font-semibold text-slate-900 dark:text-white mb-2"
        >
          {copy.fields.mode}
        </label>
        <select
          id="marge-mode"
          value={mode}
          onChange={(event) => setMode(event.target.value as MarginMode)}
          className="w-full px-4 py-2.5 text-sm font-medium bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500"
        >
          <option value="from-cost-price">{copy.options?.['from-cost-price']}</option>
          <option value="from-margin">{copy.options?.['from-margin']}</option>
          <option value="from-markup">{copy.options?.['from-markup']}</option>
        </select>
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <NumberField
          id="marge-cost"
          label={copy.fields.cost}
          value={cost}
          onChange={setCost}
          min={0}
        />
        {mode === 'from-cost-price' ? (
          <NumberField
            id="marge-price"
            label={copy.fields.price}
            value={price}
            onChange={setPrice}
            min={0}
          />
        ) : (
          <NumberField
            id="marge-rate"
            label={copy.fields.rate}
            value={rate}
            onChange={setRate}
          />
        )}
      </div>

      {rows.length > 0 ? (
        <ResultPanel
          title={common.result}
          rows={rows}
          copyLabel={common.copy}
          copiedLabel={common.copied}
          copyText={rows.map((row) => `${row.label}: ${row.value}`).join(' — ')}
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
