'use client';

import React, { useState } from 'react';
import { NumberField } from '../NumberField';
import { ResultPanel } from '../ResultPanel';
import { calculateVat, type VatDirection } from '../../tools/tva/logic';
import { parseNumber } from '../../lib/validation';
import { formatCurrency } from '../../lib/format';
import { useToolState } from '../../lib/useToolState';
import type { ToolCopy, ToolboxCopy, ToolboxLocale } from '../../data/copy';

interface VatFormProps {
  copy: ToolCopy;
  common: ToolboxCopy['common'];
  locale: ToolboxLocale;
}

const RATES = [20, 10, 5.5, 2.1];

/** Formulaire TVA — HT ↔ TTC, taux courants français + taux libre. */
export function VatForm({ copy, common, locale }: VatFormProps) {
  const [values, setValue] = useToolState({ amount: '100', rate: '20', direction: 'ht-to-ttc' });
  const amount = values.amount;
  const rate = values.rate;
  const direction = values.direction as VatDirection;

  const amountValue = parseNumber(amount);
  const rateValue = parseNumber(rate);
  const result =
    amountValue !== null && rateValue !== null
      ? calculateVat({ amount: amountValue, ratePercent: rateValue, direction })
      : null;

  return (
    <div className="space-y-5">
      <div className="grid gap-4 sm:grid-cols-2">
        <NumberField
          id="vat-amount"
          label={copy.fields.amount}
          value={amount}
          onChange={(value) => setValue('amount', value)}
          min={0}
        />
        <div className="w-full">
          <label
            htmlFor="vat-rate"
            className="block text-sm font-semibold text-slate-900 dark:text-white mb-2"
          >
            {copy.fields.rate}
          </label>
          <input
            id="vat-rate"
            type="text"
            inputMode="decimal"
            value={rate}
            onChange={(event) => setValue('rate', event.target.value)}
            className="w-full px-4 py-2.5 text-sm font-medium bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
          />
          <div className="flex flex-wrap gap-2 mt-2">
            {RATES.map((preset) => (
              <button
                key={preset}
                type="button"
                onClick={() => setValue('rate', String(preset))}
                className={`px-2.5 py-1 text-xs font-semibold rounded-lg border transition-colors ${
                  rateValue === preset
                    ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300'
                    : 'border-slate-300 dark:border-slate-700 text-slate-600 dark:text-slate-400 hover:border-emerald-400'
                }`}
              >
                {preset} %
              </button>
            ))}
          </div>
        </div>
      </div>

      <div className="w-full">
        <label
          htmlFor="vat-direction"
          className="block text-sm font-semibold text-slate-900 dark:text-white mb-2"
        >
          {copy.fields.direction}
        </label>
        <select
          id="vat-direction"
          value={direction}
          onChange={(event) => setValue('direction', event.target.value)}
          className="w-full px-4 py-2.5 text-sm font-medium bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500"
        >
          <option value="ht-to-ttc">{copy.options?.['ht-to-ttc']}</option>
          <option value="ttc-to-ht">{copy.options?.['ttc-to-ht']}</option>
        </select>
      </div>

      {result ? (
        <ResultPanel
          title={common.result}
          rows={[
            { label: copy.results.ht, value: formatCurrency(result.ht, locale) },
            { label: copy.results.vat, value: formatCurrency(result.vat, locale) },
            { label: copy.results.ttc, value: formatCurrency(result.ttc, locale), emphasis: true },
          ]}
          copyLabel={common.copy}
          copiedLabel={common.copied}
          copyText={`${copy.results.ht}: ${formatCurrency(result.ht, locale)} — ${copy.results.vat}: ${formatCurrency(result.vat, locale)} — ${copy.results.ttc}: ${formatCurrency(result.ttc, locale)}`}
          share={{ values, copy: common }}
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
