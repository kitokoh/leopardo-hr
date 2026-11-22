'use client';

import React, { useCallback, useMemo, useState } from 'react';
import { Plus, Trash2 } from 'lucide-react';
import { NumberField } from '../NumberField';
import { ResultPanel } from '../ResultPanel';
import {
  allocateContainerCost,
  type AllocationBasis,
  type AllocationLine,
} from '../../tools/repartition-conteneur/logic';
import { formatNumber } from '../../lib/format';
import { parseNumber, round } from '../../lib/validation';
import { useToolState } from '../../lib/useToolState';
import type { ToolCopy, ToolboxCopy, ToolboxLocale } from '../../data/copy';

interface ContainerAllocationFormProps {
  copy: ToolCopy;
  common: ToolboxCopy['common'];
  locale: ToolboxLocale;
}

const BASES: AllocationBasis[] = ['cbm', 'weight', 'value'];

/** Nombre maximal de lignes (le lien de partage ne transporte pas un export). */
const MAX_LINES = 10;

/** Ligne d'état : clés indexées, comme la TVA multi-taux. */
interface LineState {
  id: string;
  cbm: string;
  weight: string;
  value: string;
}

/** Motif des clés de ligne acceptées à l'hydratation d'un lien partagé. */
const LINE_KEY_PATTERN = /^(id|cbm|weight|value)\d+$/;

const INITIAL_LINES: LineState[] = [
  { id: 'A', cbm: '10', weight: '1000', value: '5000' },
  { id: 'B', cbm: '30', weight: '3000', value: '15000' },
];

/** Aplatit les lignes en clés d'état indexées (`cbm0`, `weight0`…). */
function linesToState(lines: LineState[]): Record<string, string> {
  const state: Record<string, string> = {};
  lines.forEach((line, index) => {
    state[`id${index}`] = line.id;
    state[`cbm${index}`] = line.cbm;
    state[`weight${index}`] = line.weight;
    state[`value${index}`] = line.value;
  });
  return state;
}

/** Relit les lignes depuis l'état, dans l'ordre des indices. */
function stateToLines(state: Record<string, string>): LineState[] {
  const indices = Object.keys(state)
    .filter((key) => /^cbm\d+$/.test(key))
    .map((key) => Number(key.replace('cbm', '')))
    .sort((a, b) => a - b);

  return indices.map((index) => ({
    id: state[`id${index}`] ?? String(index + 1),
    cbm: state[`cbm${index}`] ?? '',
    weight: state[`weight${index}`] ?? '',
    value: state[`value${index}`] ?? '',
  }));
}

/** Clés orphelines après une suppression de ligne (défaut historique #9531). */
function pruneOrphanKeys(state: Record<string, string>, lines: LineState[]): string[] {
  const live = new Set(Object.keys(linesToState(lines)));
  return Object.keys(state).filter((key) => LINE_KEY_PATTERN.test(key) && !live.has(key));
}

/**
 * Formulaire répartition d'un coût conteneur.
 *
 * Trois clés au choix — le CHOIX est le vrai sujet : le volume convient à la
 * marchandise légère, le poids à la marchandise dense, la valeur aux frais
 * d'assurance et financiers. Le résultat est tabulaire, donc exportable en CSV.
 *
 * La conversion lignes ↔ état partageable est testée séparément (`lines.test.ts`) :
 * supprimer une ligne laissait sinon des clés orphelines qui ressuscitaient à
 * la relecture et faussaient le total (défaut réel #9531).
 */
export function ContainerAllocationForm({ copy, common, locale }: ContainerAllocationFormProps) {
  const [state, setStateValue, removeValue] = useToolState(linesToState(INITIAL_LINES), {
    extraKeyPattern: LINE_KEY_PATTERN,
  });
  const [capReached, setCapReached] = useState(false);
  const [basis, setBasis] = useState<AllocationBasis>('cbm');
  const [totalCostRaw, setTotalCostRaw] = useState('4000');

  const lines = useMemo(() => stateToLines(state), [state]);

  const commitLines = useCallback(
    (next: LineState[]) => {
      const flat = linesToState(next);
      const orphans = pruneOrphanKeys(state, next);

      for (const [key, value] of Object.entries(flat)) {
        if (state[key] !== value) setStateValue(key, value);
      }
      removeValue(orphans);
    },
    [state, setStateValue, removeValue],
  );

  function handleSetLine(index: number, field: keyof LineState, value: string) {
    commitLines(lines.map((line, position) => (position === index ? { ...line, [field]: value } : line)));
  }

  function handleAddLine() {
    if (lines.length >= MAX_LINES) {
      setCapReached(true);
      return;
    }
    setCapReached(false);
    commitLines([...lines, { id: String(lines.length + 1), cbm: '', weight: '', value: '' }]);
  }

  function handleRemoveLine(index: number) {
    if (lines.length <= 1) return;
    setCapReached(false);
    commitLines(lines.filter((_, position) => position !== index));
  }

  const totalCost = parseNumber(totalCostRaw);
  const parsedLines: AllocationLine[] | null = useMemo(() => {
    const parsed: AllocationLine[] = [];
    for (const line of lines) {
      const cbm = parseNumber(line.cbm);
      const weight = parseNumber(line.weight);
      const value = parseNumber(line.value);
      if (cbm === null || weight === null || value === null) return null;
      parsed.push({ id: line.id, cbm, weight, value });
    }
    return parsed.length > 0 ? parsed : null;
  }, [lines]);

  const result =
    totalCost !== null && parsedLines !== null
      ? allocateContainerCost(totalCost, parsedLines, basis)
      : null;

  return (
    <div className="space-y-5">
      <div className="grid gap-4 sm:grid-cols-2">
        <NumberField
          id="rc-total-cost"
          label={copy.fields.totalCost}
          value={totalCostRaw}
          onChange={setTotalCostRaw}
          min={0}
        />
        <div className="w-full">
          <label
            htmlFor="rc-basis"
            className="block text-sm font-semibold text-slate-900 dark:text-white mb-2"
          >
            {copy.fields.basis}
          </label>
          <select
            id="rc-basis"
            value={basis}
            onChange={(event) => setBasis(event.target.value as AllocationBasis)}
            className="w-full px-4 py-2.5 text-sm font-medium bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500"
          >
            {BASES.map((id) => (
              <option key={id} value={id}>
                {copy.options?.[id] ?? id}
              </option>
            ))}
          </select>
        </div>
      </div>

      <div className="space-y-4">
        {lines.map((line, index) => (
          <div
            key={`line-${index}`}
            className="rounded-2xl border border-slate-200 dark:border-slate-800 p-4 space-y-3"
          >
            <div className="flex items-center justify-between gap-3">
              <div className="flex-1">
                <NumberField
                  id={`rc-id-${index}`}
                  label={`${copy.fields.lineId} ${index + 1}`}
                  value={line.id}
                  onChange={(next) => handleSetLine(index, 'id', next)}
                />
              </div>
              <button
                type="button"
                onClick={() => handleRemoveLine(index)}
                disabled={lines.length <= 1}
                aria-label={`${copy.fields.lineId} ${index + 1}`}
                className="mt-6 inline-flex items-center gap-1.5 rounded-lg border border-red-300 dark:border-red-900 px-3 py-2 text-xs font-semibold text-red-700 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/30 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
              >
                <Trash2 className="w-3.5 h-3.5" />
              </button>
            </div>
            <div className="grid gap-3 sm:grid-cols-3">
              <NumberField
                id={`rc-cbm-${index}`}
                label={copy.fields.cbm}
                value={line.cbm}
                onChange={(next) => handleSetLine(index, 'cbm', next)}
                min={0}
              />
              <NumberField
                id={`rc-weight-${index}`}
                label={copy.fields.weight}
                value={line.weight}
                onChange={(next) => handleSetLine(index, 'weight', next)}
                min={0}
              />
              <NumberField
                id={`rc-value-${index}`}
                label={copy.fields.value}
                value={line.value}
                onChange={(next) => handleSetLine(index, 'value', next)}
                min={0}
              />
            </div>
          </div>
        ))}

        <button
          type="button"
          onClick={handleAddLine}
          disabled={lines.length >= MAX_LINES}
          className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-900 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
        >
          <Plus className="w-3.5 h-3.5" />
          {copy.fields.lineId} +1
        </button>

        {capReached && (
          <p className="text-xs text-amber-700 dark:text-amber-400" role="status">
            {copy.notes?.[0] ?? ''}
          </p>
        )}
      </div>

      {result ? (
        <ResultPanel
          title={common.result}
          rows={[
            { label: copy.results.basisTotal, value: formatNumber(result.basisTotal, locale, 3) },
            { label: copy.results.allocatedTotal, value: formatNumber(result.allocatedTotal, locale, 2), emphasis: true },
          ]}
          copyLabel={common.copy}
          copiedLabel={common.copied}
          copyText={result.lines
            .map((line) => `${line.id}: ${formatNumber(line.allocated, locale, 2)} (${formatNumber(line.sharePercent, locale, 2)} %)`)
            .join('\n')}
          share={{ values: state, copy: common }}
          csv={{
            headers: [copy.results.colId, copy.results.colBasis, copy.results.colShare, copy.results.colAllocated],
            rows: result.lines.map((line) => [
              line.id,
              String(round(line.basisValue, 3)),
              String(round(line.sharePercent, 2)),
              String(round(line.allocated, 2)),
            ]),
          }}
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
