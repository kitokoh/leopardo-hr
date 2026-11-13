'use client';

import React, { useMemo, useState } from 'react';
import { NumberField } from '../NumberField';
import { ResultPanel } from '../ResultPanel';
import { UNIT_CATEGORIES, convertUnit, type UnitCategoryId } from '../../tools/convertisseur-unites/logic';
import { parseNumber } from '../../lib/validation';
import { formatNumber } from '../../lib/format';
import { useToolState } from '../../lib/useToolState';
import { UNIT_CATEGORY_LABELS, UNIT_LABELS } from '../../data/tool-options';
import type { ToolCopy, ToolboxCopy, ToolboxLocale } from '../../data/copy';

interface UnitConverterFormProps {
  copy: ToolCopy;
  common: ToolboxCopy['common'];
  locale: ToolboxLocale;
}

const CATEGORY_IDS: UnitCategoryId[] = [
  'longueur',
  'masse',
  'volume',
  'surface',
  'vitesse',
  'temperature',
];

/** Formulaire convertisseur d'unités — facteurs exacts, calcul local. */
export function UnitConverterForm({ copy, common, locale }: UnitConverterFormProps) {
  const [values, setValue] = useToolState({ category: 'longueur', value: '1', from: 'm', to: 'cm' });
  const category = values.category as UnitCategoryId;
  const value = values.value;
  const from = values.from;
  const to = values.to;

  const units = useMemo(() => UNIT_CATEGORIES[category], [category]);

  function handleCategoryChange(next: UnitCategoryId) {
    const nextUnits = UNIT_CATEGORIES[next];
    setValue('category', next);
    setValue('from', nextUnits[0].id);
    setValue('to', nextUnits[1]?.id ?? nextUnits[0].id);
  }

  const numeric = parseNumber(value);
  const converted = numeric !== null ? convertUnit(category, from, to, numeric) : null;

  return (
    <div className="space-y-5">
      <div className="w-full">
        <label
          htmlFor="unit-category"
          className="block text-sm font-semibold text-slate-900 dark:text-white mb-2"
        >
          {copy.fields.category}
        </label>
        <select
          id="unit-category"
          value={category}
          onChange={(event) => handleCategoryChange(event.target.value as UnitCategoryId)}
          className="w-full px-4 py-2.5 text-sm font-medium bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500"
        >
          {CATEGORY_IDS.map((id) => (
            <option key={id} value={id}>
              {UNIT_CATEGORY_LABELS[locale][id]}
            </option>
          ))}
        </select>
      </div>

      <NumberField
        id="unit-value"
        label={copy.fields.value}
        value={value}
        onChange={(next) => setValue('value', next)}
      />

      <div className="grid gap-4 sm:grid-cols-2">
        <div className="w-full">
          <label
            htmlFor="unit-from"
            className="block text-sm font-semibold text-slate-900 dark:text-white mb-2"
          >
            {copy.fields.from}
          </label>
          <select
            id="unit-from"
            value={from}
            onChange={(event) => setValue('from', event.target.value)}
            className="w-full px-4 py-2.5 text-sm font-medium bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500"
          >
            {units.map((unit) => (
              <option key={unit.id} value={unit.id}>
                {UNIT_LABELS[unit.id] ?? unit.id}
              </option>
            ))}
          </select>
        </div>
        <div className="w-full">
          <label
            htmlFor="unit-to"
            className="block text-sm font-semibold text-slate-900 dark:text-white mb-2"
          >
            {copy.fields.to}
          </label>
          <select
            id="unit-to"
            value={to}
            onChange={(event) => setValue('to', event.target.value)}
            className="w-full px-4 py-2.5 text-sm font-medium bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500"
          >
            {units.map((unit) => (
              <option key={unit.id} value={unit.id}>
                {UNIT_LABELS[unit.id] ?? unit.id}
              </option>
            ))}
          </select>
        </div>
      </div>

      {converted !== null ? (
        <ResultPanel
          title={common.result}
          rows={[
            {
              label: copy.results.converted,
              value: `${formatNumber(converted, locale, 6)} ${UNIT_LABELS[to] ?? to}`,
              emphasis: true,
            },
          ]}
          copyLabel={common.copy}
          copiedLabel={common.copied}
          copyText={`${formatNumber(converted, locale, 6)} ${UNIT_LABELS[to] ?? to}`}
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
