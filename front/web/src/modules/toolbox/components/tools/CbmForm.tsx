'use client';

import React, { useState } from 'react';
import { NumberField } from '../NumberField';
import { ResultPanel } from '../ResultPanel';
import { computeCbm, type FreightMode } from '../../tools/cbm-poids-volumetrique/logic';
import { parseNumber } from '../../lib/validation';
import { formatNumber } from '../../lib/format';
import { useToolState } from '../../lib/useToolState';
import { FREIGHT_MODE_LABELS } from '../../data/tool-options';
import type { ToolCopy, ToolboxCopy, ToolboxLocale } from '../../data/copy';

interface CbmFormProps {
  copy: ToolCopy;
  common: ToolboxCopy['common'];
  locale: ToolboxLocale;
}

const MODE_IDS: FreightMode[] = ['aerien', 'routier', 'maritime'];

/** Formulaire CBM et poids volumétrique — logistique et transport. */
export function CbmForm({ copy, common, locale }: CbmFormProps) {
  const [values, setValue] = useToolState({
    length: '100',
    width: '50',
    height: '40',
    quantity: '1',
    weight: '0',
    mode: 'aerien',
  });
  const length = values.length;
  const width = values.width;
  const height = values.height;
  const quantity = values.quantity;
  const weight = values.weight;
  const mode = values.mode as FreightMode;

  const lengthValue = parseNumber(length);
  const widthValue = parseNumber(width);
  const heightValue = parseNumber(height);
  const quantityValue = parseNumber(quantity);
  const weightValue = parseNumber(weight);

  const result =
    lengthValue !== null &&
    widthValue !== null &&
    heightValue !== null &&
    quantityValue !== null &&
    weightValue !== null
      ? computeCbm({
          lengthCm: lengthValue,
          widthCm: widthValue,
          heightCm: heightValue,
          quantity: quantityValue,
          actualWeightKg: weightValue,
          mode,
        })
      : null;

  return (
    <div className="space-y-5">
      <div className="grid gap-4 sm:grid-cols-3">
        <NumberField
          id="cbm-length"
          label={copy.fields.length}
          value={length}
          onChange={(next) => setValue('length', next)}
          min={0}
        />
        <NumberField
          id="cbm-width"
          label={copy.fields.width}
          value={width}
          onChange={(next) => setValue('width', next)}
          min={0}
        />
        <NumberField
          id="cbm-height"
          label={copy.fields.height}
          value={height}
          onChange={(next) => setValue('height', next)}
          min={0}
        />
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <NumberField
          id="cbm-quantity"
          label={copy.fields.quantity}
          value={quantity}
          onChange={(next) => setValue('quantity', next)}
          min={1}
        />
        <NumberField
          id="cbm-weight"
          label={copy.fields.weight}
          value={weight}
          onChange={(next) => setValue('weight', next)}
          min={0}
        />
      </div>

      <div className="w-full">
        <label
          htmlFor="cbm-mode"
          className="block text-sm font-semibold text-slate-900 dark:text-white mb-2"
        >
          {copy.fields.mode}
        </label>
        <select
          id="cbm-mode"
          value={mode}
          onChange={(event) => setValue('mode', event.target.value)}
          className="w-full px-4 py-2.5 text-sm font-medium bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500"
        >
          {MODE_IDS.map((id) => (
            <option key={id} value={id}>
              {FREIGHT_MODE_LABELS[locale][id]}
            </option>
          ))}
        </select>
      </div>

      {result ? (
        <ResultPanel
          title={common.result}
          rows={[
            {
              label: copy.results.unitCbm,
              value: `${formatNumber(result.unitCbm, locale, 4)} m³`,
            },
            {
              label: copy.results.totalCbm,
              value: `${formatNumber(result.totalCbm, locale, 4)} m³`,
            },
            {
              label: copy.results.volumetricWeight,
              value: `${formatNumber(result.volumetricWeightKg, locale, 2)} kg`,
            },
            {
              label: copy.results.chargeableWeight,
              value: `${formatNumber(result.chargeableWeightKg, locale, 2)} kg`,
              emphasis: true,
            },
          ]}
          copyLabel={common.copy}
          copiedLabel={common.copied}
          copyText={`${copy.results.totalCbm}: ${formatNumber(result.totalCbm, locale, 4)} m³ — ${copy.results.chargeableWeight}: ${formatNumber(result.chargeableWeightKg, locale, 2)} kg`}
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
