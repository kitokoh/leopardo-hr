'use client';

import React, { useMemo, useState } from 'react';
import { NumberField } from '../NumberField';
import { ResultPanel, type ResultRow } from '../ResultPanel';
import { computeShape, type ShapeId } from '../../tools/surface-volume/logic';
import { parseNumber } from '../../lib/validation';
import { formatNumber } from '../../lib/format';
import { useToolState } from '../../lib/useToolState';
import { SHAPE_LABELS } from '../../data/tool-options';
import type { ToolCopy, ToolboxCopy, ToolboxLocale } from '../../data/copy';

interface SurfaceVolumeFormProps {
  copy: ToolCopy;
  common: ToolboxCopy['common'];
  locale: ToolboxLocale;
}

const SHAPE_IDS: ShapeId[] = [
  'carre',
  'rectangle',
  'triangle',
  'cercle',
  'cube',
  'pave-droit',
  'cylindre',
  'sphere',
];

/** Champs de dimension requis par forme (identifiants techniques, non traduits). */
const SHAPE_FIELDS: Record<ShapeId, string[]> = {
  carre: ['a'],
  rectangle: ['length', 'width'],
  triangle: ['base', 'height'],
  cercle: ['radius'],
  cube: ['a'],
  'pave-droit': ['length', 'width', 'height'],
  cylindre: ['radius', 'height'],
  sphere: ['radius'],
};

const DEFAULT_VALUES: Record<string, string> = {
  a: '4',
  length: '5',
  width: '3',
  height: '2',
  base: '6',
  radius: '2',
};

/** Formulaire surfaces et volumes — formes planes et solides usuels. */
export function SurfaceVolumeForm({ copy, common, locale }: SurfaceVolumeFormProps) {
  const [values, setValue] = useToolState({ shape: 'carre', ...DEFAULT_VALUES });
  const shape = values.shape as ShapeId;

  const fields = useMemo(() => SHAPE_FIELDS[shape], [shape]);

  const dimensions = useMemo(() => {
    const parsed: Record<string, number> = {};
    for (const field of fields) {
      const value = parseNumber(values[field] ?? '');
      if (value === null) return null;
      parsed[field] = value;
    }
    return parsed;
  }, [fields, values]);

  const result = dimensions ? computeShape(shape, dimensions) : null;

  function setField(field: string, value: string) {
    setValue(field, value);
  }

  const rows: ResultRow[] = [];
  if (result && result.area !== null) {
    rows.push({
      label: copy.results.area,
      value: formatNumber(result.area, locale, 4),
      emphasis: result.volume === null,
    });
  }
  if (result && result.volume !== null) {
    rows.push({
      label: copy.results.volume,
      value: formatNumber(result.volume, locale, 4),
      emphasis: true,
    });
  }

  return (
    <div className="space-y-5">
      <div className="w-full">
        <label
          htmlFor="sv-shape"
          className="block text-sm font-semibold text-slate-900 dark:text-white mb-2"
        >
          {copy.fields.shape}
        </label>
        <select
          id="sv-shape"
          value={shape}
          onChange={(event) => setValue('shape', event.target.value)}
          className="w-full px-4 py-2.5 text-sm font-medium bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500"
        >
          {SHAPE_IDS.map((id) => (
            <option key={id} value={id}>
              {SHAPE_LABELS[locale][id]}
            </option>
          ))}
        </select>
      </div>

      <div className="grid gap-4 sm:grid-cols-3">
        {fields.map((field) => (
          <NumberField
            key={field}
            id={`sv-${field}`}
            label={copy.fields[field] ?? field}
            value={values[field] ?? ''}
            onChange={(value) => setField(field, value)}
            min={0}
          />
        ))}
      </div>

      {rows.length > 0 ? (
        <ResultPanel
          title={common.result}
          rows={rows}
          copyLabel={common.copy}
          copiedLabel={common.copied}
          copyText={rows.map((row) => `${row.label}: ${row.value}`).join(' — ')}
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
