'use client';

import React, { useState } from 'react';
import { NumberField } from '../NumberField';
import { ResultPanel } from '../ResultPanel';
import { convertCurrency } from '../../tools/convertisseur-devises/logic';
import { CURRENCIES, REFERENCE_DATE, rateFor } from '../../tools/convertisseur-devises/rates';
import { parseNumber } from '../../lib/validation';
import { formatNumber } from '../../lib/format';
import type { ToolCopy, ToolboxCopy, ToolboxLocale } from '../../data/copy';

interface ConvertisseurDevisesFormProps {
  copy: ToolCopy;
  common: ToolboxCopy['common'];
  locale: ToolboxLocale;
}

/** Formulaire convertisseur de devises — taux de référence statiques, hors ligne. */
export function ConvertisseurDevisesForm({ copy, common, locale }: ConvertisseurDevisesFormProps) {
  const [amount, setAmount] = useState('100');
  const [from, setFrom] = useState('EUR');
  const [to, setTo] = useState('DZD');

  const amountValue = parseNumber(amount);
  const fromRate = rateFor(from);
  const toRate = rateFor(to);

  const result =
    amountValue !== null && fromRate !== null && toRate !== null
      ? convertCurrency(amountValue, fromRate, toRate)
      : null;

  const appliedRate =
    fromRate !== null && toRate !== null && toRate !== 0 ? fromRate / toRate : null;

  return (
    <div className="space-y-5">
      <NumberField id="cur-amount" label={copy.fields.amount} value={amount} onChange={setAmount} />

      <div className="grid gap-4 sm:grid-cols-2">
        <div className="w-full">
          <label
            htmlFor="cur-from"
            className="block text-sm font-semibold text-slate-900 dark:text-white mb-2"
          >
            {copy.fields.from}
          </label>
          <select
            id="cur-from"
            value={from}
            onChange={(event) => setFrom(event.target.value)}
            className="w-full px-4 py-2.5 text-sm font-medium bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500"
          >
            {CURRENCIES.map((currency) => (
              <option key={currency.code} value={currency.code}>
                {currency.code}
              </option>
            ))}
          </select>
        </div>

        <div className="w-full">
          <label
            htmlFor="cur-to"
            className="block text-sm font-semibold text-slate-900 dark:text-white mb-2"
          >
            {copy.fields.to}
          </label>
          <select
            id="cur-to"
            value={to}
            onChange={(event) => setTo(event.target.value)}
            className="w-full px-4 py-2.5 text-sm font-medium bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500"
          >
            {CURRENCIES.map((currency) => (
              <option key={currency.code} value={currency.code}>
                {currency.code}
              </option>
            ))}
          </select>
        </div>
      </div>

      {result !== null ? (
        <ResultPanel
          title={common.result}
          rows={[
            {
              label: `${copy.results.converted} (${to})`,
              value: formatNumber(result, locale, 2),
              emphasis: true,
            },
            ...(appliedRate !== null
              ? [
                  {
                    label: copy.results.rate,
                    value: `1 ${from} = ${formatNumber(appliedRate, locale, 6)} ${to}`,
                  },
                ]
              : []),
          ]}
          copyLabel={common.copy}
          copiedLabel={common.copied}
          copyText={`${formatNumber(result, locale, 2)} ${to}`}
        />
      ) : (
        <p className="text-sm text-slate-500 dark:text-slate-400">{common.invalidInput}</p>
      )}

      <p className="text-xs text-slate-500 dark:text-slate-400">
        {common.reference} : {REFERENCE_DATE}
      </p>

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
