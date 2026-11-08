'use client';

import React, { useState } from 'react';
import { ResultPanel } from '../ResultPanel';
import { durationBetween } from '../../tools/duree-entre-dates/logic';
import { formatNumber } from '../../lib/format';
import type { ToolCopy, ToolboxCopy, ToolboxLocale } from '../../data/copy';

interface DureeEntreDatesFormProps {
  copy: ToolCopy;
  common: ToolboxCopy['common'];
  locale: ToolboxLocale;
}

/** Formulaire durée entre deux dates — décomposition calendaire exacte. */
export function DureeEntreDatesForm({ copy, common, locale }: DureeEntreDatesFormProps) {
  const [from, setFrom] = useState('2026-01-01');
  const [to, setTo] = useState('2026-11-08');

  const result = durationBetween(from, to);

  return (
    <div className="space-y-5">
      <div className="grid gap-4 sm:grid-cols-2">
        <div className="w-full">
          <label
            htmlFor="dur-from"
            className="block text-sm font-semibold text-slate-900 dark:text-white mb-2"
          >
            {copy.fields.from}
          </label>
          <input
            id="dur-from"
            type="date"
            value={from}
            onChange={(event) => setFrom(event.target.value)}
            className="w-full px-4 py-2.5 text-sm font-medium bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500"
          />
        </div>

        <div className="w-full">
          <label
            htmlFor="dur-to"
            className="block text-sm font-semibold text-slate-900 dark:text-white mb-2"
          >
            {copy.fields.to}
          </label>
          <input
            id="dur-to"
            type="date"
            value={to}
            onChange={(event) => setTo(event.target.value)}
            className="w-full px-4 py-2.5 text-sm font-medium bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500"
          />
        </div>
      </div>

      {result !== null ? (
        <ResultPanel
          title={common.result}
          rows={[
            {
              label: copy.results.years,
              value: formatNumber(result.years, locale, 0),
            },
            {
              label: copy.results.months,
              value: formatNumber(result.months, locale, 0),
            },
            {
              label: copy.results.days,
              value: formatNumber(result.days, locale, 0),
            },
            {
              label: copy.results.totalDays,
              value: formatNumber(result.totalDays, locale, 0),
              emphasis: true,
            },
          ]}
          copyLabel={common.copy}
          copiedLabel={common.copied}
          copyText={`${result.years} ${copy.results.years}, ${result.months} ${copy.results.months}, ${result.days} ${copy.results.days} (${result.totalDays} ${copy.results.totalDays})`}
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
