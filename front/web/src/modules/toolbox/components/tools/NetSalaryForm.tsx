'use client';

import React, { useState } from 'react';
import { NumberField } from '../NumberField';
import { ResultPanel } from '../ResultPanel';
import { estimateNetSalary } from '../../tools/salaire-net/logic';
import { parseNumber } from '../../lib/validation';
import { formatCurrency } from '../../lib/format';
import { useToolState } from '../../lib/useToolState';
import type { ToolCopy, ToolboxCopy, ToolboxLocale } from '../../data/copy';

interface NetSalaryFormProps {
  copy: ToolCopy;
  common: ToolboxCopy['common'];
  locale: ToolboxLocale;
}

/** Formulaire salaire brut → net (estimation) — taux de cotisations paramétrable. */
export function NetSalaryForm({ copy, common, locale }: NetSalaryFormProps) {
  const [values, setValue] = useToolState({ gross: '3000', rate: '22' });
  const gross = values.gross;
  const rate = values.rate;

  const grossValue = parseNumber(gross);
  const rateValue = parseNumber(rate);
  const result =
    grossValue !== null && rateValue !== null
      ? estimateNetSalary({ grossMonthly: grossValue, contributionRatePercent: rateValue })
      : null;

  return (
    <div className="space-y-5">
      <div className="grid gap-4 sm:grid-cols-2">
        <NumberField
          id="net-gross"
          label={copy.fields.gross}
          value={gross}
          onChange={(value) => setValue('gross', value)}
          min={0}
        />
        <NumberField
          id="net-rate"
          label={copy.fields.rate}
          value={rate}
          onChange={(value) => setValue('rate', value)}
          unit="%"
          min={0}
          max={100}
        />
      </div>

      {result ? (
        <ResultPanel
          title={common.result}
          rows={[
            { label: copy.results.net, value: formatCurrency(result.net, locale), emphasis: true },
            { label: copy.results.deductions, value: formatCurrency(result.deductions, locale) },
          ]}
          copyLabel={common.copy}
          copiedLabel={common.copied}
          copyText={`${copy.results.net}: ${formatCurrency(result.net, locale)} — ${copy.results.deductions}: ${formatCurrency(result.deductions, locale)}`}
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
