'use client';

import React, { useState } from 'react';
import { NumberField } from '../NumberField';
import { ResultPanel } from '../ResultPanel';
import { calculateImc } from '../../tools/imc/logic';
import { parseNumber } from '../../lib/validation';
import { formatNumber } from '../../lib/format';
import { IMC_CATEGORY_LABELS } from '../../data/tool-options';
import type { ToolCopy, ToolboxCopy, ToolboxLocale } from '../../data/copy';

interface ImcFormProps {
  copy: ToolCopy;
  common: ToolboxCopy['common'];
  locale: ToolboxLocale;
}

/** Formulaire IMC — calcul en direct à la saisie, 100 % côté client. */
export function ImcForm({ copy, common, locale }: ImcFormProps) {
  const [weight, setWeight] = useState('70');
  const [height, setHeight] = useState('175');

  const weightKg = parseNumber(weight);
  const heightCm = parseNumber(height);
  const result =
    weightKg !== null && heightCm !== null ? calculateImc({ weightKg, heightCm }) : null;

  const categoryLabel = result
    ? (IMC_CATEGORY_LABELS[locale][result.category] ?? result.category)
    : '';

  return (
    <div className="space-y-5">
      <div className="grid gap-4 sm:grid-cols-2">
        <NumberField
          id="imc-weight"
          label={copy.fields.weight}
          value={weight}
          onChange={setWeight}
          unit="kg"
          min={1}
          max={500}
        />
        <NumberField
          id="imc-height"
          label={copy.fields.height}
          value={height}
          onChange={setHeight}
          unit="cm"
          min={30}
          max={300}
        />
      </div>

      {result ? (
        <ResultPanel
          title={common.result}
          rows={[
            { label: copy.results.imc, value: formatNumber(result.imc, locale, 1), emphasis: true },
            { label: copy.results.category, value: categoryLabel },
          ]}
          copyLabel={common.copy}
          copiedLabel={common.copied}
          copyText={`${copy.results.imc}: ${formatNumber(result.imc, locale, 1)} — ${copy.results.category}: ${categoryLabel}`}
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
