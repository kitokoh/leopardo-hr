'use client';

import React, { useState } from 'react';
import { ResultPanel } from '../ResultPanel';
import { ageAt } from '../../tools/age/logic';
import { formatNumber } from '../../lib/format';
import { useToolState } from '../../lib/useToolState';
import type { ToolCopy, ToolboxCopy, ToolboxLocale } from '../../data/copy';

interface AgeFormProps {
  copy: ToolCopy;
  common: ToolboxCopy['common'];
  locale: ToolboxLocale;
}

/** Formulaire âge — différence calendaire exacte à une date de référence. */
export function AgeForm({ copy, common, locale }: AgeFormProps) {
  const [values, setValue] = useToolState({ birthDate: '1990-05-15', referenceDate: '2026-11-08' });
  const birthDate = values.birthDate;
  const referenceDate = values.referenceDate;

  const result = ageAt(birthDate, referenceDate);

  return (
    <div className="space-y-5">
      <div className="grid gap-4 sm:grid-cols-2">
        <div className="w-full">
          <label
            htmlFor="age-birth"
            className="block text-sm font-semibold text-slate-900 dark:text-white mb-2"
          >
            {copy.fields.birthDate}
          </label>
          <input
            id="age-birth"
            type="date"
            value={birthDate}
            onChange={(event) => setValue('birthDate', event.target.value)}
            className="w-full px-4 py-2.5 text-sm font-medium bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500"
          />
        </div>

        <div className="w-full">
          <label
            htmlFor="age-ref"
            className="block text-sm font-semibold text-slate-900 dark:text-white mb-2"
          >
            {copy.fields.referenceDate}
          </label>
          <input
            id="age-ref"
            type="date"
            value={referenceDate}
            onChange={(event) => setValue('referenceDate', event.target.value)}
            className="w-full px-4 py-2.5 text-sm font-medium bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500"
          />
        </div>
      </div>

      {result !== null ? (
        <ResultPanel
          title={common.result}
          rows={[
            { label: copy.results.years, value: formatNumber(result.years, locale, 0) },
            { label: copy.results.months, value: formatNumber(result.months, locale, 0) },
            { label: copy.results.days, value: formatNumber(result.days, locale, 0) },
            {
              label: copy.results.totalDays,
              value: formatNumber(result.totalDays, locale, 0),
              emphasis: true,
            },
          ]}
          copyLabel={common.copy}
          copiedLabel={common.copied}
          copyText={`${result.years} ${copy.results.years}, ${result.months} ${copy.results.months}, ${result.days} ${copy.results.days}`}
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
