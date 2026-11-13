'use client';

import React, { useState } from 'react';
import { NumberField } from '../NumberField';
import { ResultPanel } from '../ResultPanel';
import { inverseRuleOfThree, ruleOfThree } from '../../tools/regle-de-trois/logic';
import { parseNumber } from '../../lib/validation';
import { formatNumber } from '../../lib/format';
import { useToolState } from '../../lib/useToolState';
import type { ToolCopy, ToolboxCopy, ToolboxLocale } from '../../data/copy';

interface RegleDeTroisFormProps {
  copy: ToolCopy;
  common: ToolboxCopy['common'];
  locale: ToolboxLocale;
}

type ProportionMode = 'direct' | 'inverse';

/** Formulaire règle de trois — proportionnalité directe ou inverse. */
export function RegleDeTroisForm({ copy, common, locale }: RegleDeTroisFormProps) {
  const [values, setValue] = useToolState({ mode: 'direct', a: '3', b: '12', c: '5' });
  const mode = values.mode as ProportionMode;
  const a = values.a;
  const b = values.b;
  const c = values.c;

  const aValue = parseNumber(a);
  const bValue = parseNumber(b);
  const cValue = parseNumber(c);

  const result =
    aValue !== null && bValue !== null && cValue !== null
      ? mode === 'direct'
        ? ruleOfThree(aValue, bValue, cValue)
        : inverseRuleOfThree(aValue, bValue, cValue)
      : null;

  return (
    <div className="space-y-5">
      <div className="w-full">
        <label
          htmlFor="r3-mode"
          className="block text-sm font-semibold text-slate-900 dark:text-white mb-2"
        >
          {copy.fields.mode}
        </label>
        <select
          id="r3-mode"
          value={mode}
          onChange={(event) => setValue('mode', event.target.value)}
          className="w-full px-4 py-2.5 text-sm font-medium bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500"
        >
          <option value="direct">{copy.options?.direct}</option>
          <option value="inverse">{copy.options?.inverse}</option>
        </select>
      </div>

      <div className="grid gap-4 sm:grid-cols-3">
        <NumberField id="r3-a" label={copy.fields.a} value={a} onChange={(next) => setValue('a', next)} />
        <NumberField id="r3-b" label={copy.fields.b} value={b} onChange={(next) => setValue('b', next)} />
        <NumberField id="r3-c" label={copy.fields.c} value={c} onChange={(next) => setValue('c', next)} />
      </div>

      {result !== null ? (
        <ResultPanel
          title={common.result}
          rows={[
            {
              label: copy.results.value,
              value: formatNumber(result, locale, 4),
              emphasis: true,
            },
          ]}
          copyLabel={common.copy}
          copiedLabel={common.copied}
          copyText={`${copy.results.value}: ${formatNumber(result, locale, 4)}`}
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
