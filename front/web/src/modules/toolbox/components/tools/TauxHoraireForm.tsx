'use client';

import React, { useState } from 'react';
import { NumberField } from '../NumberField';
import { ResultPanel } from '../ResultPanel';
import { hourlyRate, monthlyGrossFromHourly } from '../../tools/taux-horaire/logic';
import { parseNumber } from '../../lib/validation';
import { formatNumber } from '../../lib/format';
import type { ToolCopy, ToolboxCopy, ToolboxLocale } from '../../data/copy';

interface TauxHoraireFormProps {
  copy: ToolCopy;
  common: ToolboxCopy['common'];
  locale: ToolboxLocale;
}

type Direction = 'monthly-to-hourly' | 'hourly-to-monthly';

/** Formulaire taux horaire — conversion brut mensuel ↔ taux horaire. */
export function TauxHoraireForm({ copy, common, locale }: TauxHoraireFormProps) {
  const [direction, setDirection] = useState<Direction>('monthly-to-hourly');
  const [monthlyGross, setMonthlyGross] = useState('2000');
  const [hourly, setHourly] = useState('13.19');
  const [hoursPerWeek, setHoursPerWeek] = useState('35');

  const hoursValue = parseNumber(hoursPerWeek);

  const result =
    hoursValue !== null
      ? direction === 'monthly-to-hourly'
        ? (() => {
            const gross = parseNumber(monthlyGross);
            return gross !== null ? hourlyRate(gross, hoursValue) : null;
          })()
        : (() => {
            const rate = parseNumber(hourly);
            return rate !== null ? monthlyGrossFromHourly(rate, hoursValue) : null;
          })()
      : null;

  return (
    <div className="space-y-5">
      <div className="w-full">
        <label
          htmlFor="th-direction"
          className="block text-sm font-semibold text-slate-900 dark:text-white mb-2"
        >
          {copy.fields.mode ?? copy.fields.monthlyGross}
        </label>
        <select
          id="th-direction"
          value={direction}
          onChange={(event) => setDirection(event.target.value as Direction)}
          className="w-full px-4 py-2.5 text-sm font-medium bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500"
        >
          <option value="monthly-to-hourly">{copy.options?.['monthly-to-hourly']}</option>
          <option value="hourly-to-monthly">{copy.options?.['hourly-to-monthly']}</option>
        </select>
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        {direction === 'monthly-to-hourly' ? (
          <NumberField
            id="th-gross"
            label={copy.fields.monthlyGross}
            value={monthlyGross}
            onChange={setMonthlyGross}
          />
        ) : (
          <NumberField id="th-hourly" label={copy.results.hourly} value={hourly} onChange={setHourly} />
        )}
        <NumberField
          id="th-hours"
          label={copy.fields.hoursPerWeek}
          value={hoursPerWeek}
          onChange={setHoursPerWeek}
        />
      </div>

      {result !== null ? (
        <ResultPanel
          title={common.result}
          rows={[
            direction === 'monthly-to-hourly'
              ? {
                  label: copy.results.hourly,
                  value: formatNumber(result, locale, 2),
                  emphasis: true,
                }
              : {
                  label: copy.results.monthly,
                  value: formatNumber(result, locale, 2),
                  emphasis: true,
                },
          ]}
          copyLabel={common.copy}
          copiedLabel={common.copied}
          copyText={formatNumber(result, locale, 2)}
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
