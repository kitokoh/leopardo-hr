'use client';

import React from 'react';
import { NumberField } from '../NumberField';
import { ResultPanel } from '../ResultPanel';
import { shiftDate, DATE_SHIFT_UNITS, type DateShiftUnit } from '../../tools/calcul-date-echeance/logic';
import { parseNumber } from '../../lib/validation';
import { formatNumber } from '../../lib/format';
import { useToolState } from '../../lib/useToolState';
import { WEEKDAY_LABELS } from '../../data/tool-options';
import type { ToolCopy, ToolboxCopy, ToolboxLocale } from '../../data/copy';

interface CalculDateEcheanceFormProps {
  copy: ToolCopy;
  common: ToolboxCopy['common'];
  locale: ToolboxLocale;
}

/**
 * Formulaire « calcul de date d'échéance » — projection d'une date.
 *
 * Complète `duree-entre-dates` : ici on ne mesure pas un écart, on obtient la
 * date d'arrivée (« dans 30 jours », « 3 mois », « 5 jours ouvrés »).
 * Calcul 100 % côté client, aucune date n'est envoyée.
 */
export function CalculDateEcheanceForm({ copy, common, locale }: CalculDateEcheanceFormProps) {
  const [values, setValue] = useToolState({
    startDate: '2026-11-14',
    amount: '30',
    unit: 'jours',
  });

  const startDate = values.startDate;
  const amountRaw = values.amount;
  const unit = (DATE_SHIFT_UNITS as string[]).includes(values.unit)
    ? (values.unit as DateShiftUnit)
    : 'jours';

  const amount = parseNumber(amountRaw);
  const result =
    amount !== null ? shiftDate({ startDate, amount, unit }) : null;

  const weekdayLabel = result ? (WEEKDAY_LABELS[locale][result.weekday] ?? '') : '';

  return (
    <div className="space-y-5">
      <div className="grid gap-4 sm:grid-cols-2">
        <div className="w-full">
          <label
            htmlFor="echeance-start"
            className="block text-sm font-semibold text-slate-900 dark:text-white mb-2"
          >
            {copy.fields.startDate}
          </label>
          <input
            id="echeance-start"
            type="date"
            value={startDate}
            onChange={(event) => setValue('startDate', event.target.value)}
            className="w-full px-4 py-2.5 text-sm font-medium bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500"
          />
        </div>

        <NumberField
          id="echeance-amount"
          label={copy.fields.amount}
          value={amountRaw}
          onChange={(value) => setValue('amount', value)}
          min={-10000}
          max={10000}
        />
      </div>

      <div className="w-full">
        <label
          htmlFor="echeance-unit"
          className="block text-sm font-semibold text-slate-900 dark:text-white mb-2"
        >
          {copy.fields.unit}
        </label>
        <select
          id="echeance-unit"
          value={unit}
          onChange={(event) => setValue('unit', event.target.value)}
          className="w-full px-4 py-2.5 text-sm font-medium bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500"
        >
          {DATE_SHIFT_UNITS.map((option) => (
            <option key={option} value={option}>
              {copy.options?.[option] ?? option}
            </option>
          ))}
        </select>
      </div>

      {result !== null ? (
        <ResultPanel
          title={common.result}
          rows={[
            { label: copy.results.date, value: result.date, emphasis: true },
            { label: copy.results.weekday, value: weekdayLabel },
            {
              label: copy.results.totalDays,
              value: formatNumber(result.totalDays, locale, 0),
            },
            {
              label: copy.results.businessDays,
              value: formatNumber(result.businessDays, locale, 0),
            },
          ]}
          copyLabel={common.copy}
          copiedLabel={common.copied}
          copyText={`${copy.results.date}: ${result.date} — ${copy.results.weekday}: ${weekdayLabel} (${result.totalDays} ${copy.results.totalDays}, ${result.businessDays} ${copy.results.businessDays})`}
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
