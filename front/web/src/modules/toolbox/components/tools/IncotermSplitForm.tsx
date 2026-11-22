'use client';

import React from 'react';
import { ResultPanel } from '../ResultPanel';
import { INCOTERMS, splitByIncoterm, type CostBearer, type CostItem } from '../../tools/incoterm-repartition/logic';
import { useToolState } from '../../lib/useToolState';
import type { ToolCopy, ToolboxCopy, ToolboxLocale } from '../../data/copy';

interface IncotermSplitFormProps {
  copy: ToolCopy;
  common: ToolboxCopy['common'];
  locale: ToolboxLocale;
}

/** Libellé localisé d'un poste de coût, depuis la copie de l'outil. */
function itemLabel(copy: ToolCopy, item: CostItem): string {
  return copy.results[item] ?? item;
}

/** Libellé localisé du porteur (« vendeur » / « acheteur »). */
function bearerLabel(copy: ToolCopy, bearer: CostBearer): string {
  return bearer === 'seller' ? copy.results.seller : copy.results.buyer;
}

/**
 * Formulaire répartition des frais par Incoterm.
 *
 * Une liste déroulante + une table : la valeur ici est la TABLE elle-même, pas
 * un calcul. Aucune interprétation automatique — l'outil dit qui paie quoi
 * selon les Incoterms 2020, et rappelle qu'un contrat peut y déroger.
 */
export function IncotermSplitForm({ copy, common, locale }: IncotermSplitFormProps) {
  const [values, setValue] = useToolState({ incoterm: 'CIF' });

  const result = splitByIncoterm(values.incoterm);

  return (
    <div className="space-y-5">
      <div className="w-full">
        <label
          htmlFor="inc-incoterm"
          className="block text-sm font-semibold text-slate-900 dark:text-white mb-2"
        >
          {copy.fields.incoterm}
        </label>
        <select
          id="inc-incoterm"
          value={values.incoterm}
          onChange={(event) => setValue('incoterm', event.target.value)}
          className="w-full px-4 py-2.5 text-sm font-medium bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500"
        >
          {INCOTERMS.map((incoterm) => (
            <option key={incoterm} value={incoterm}>
              {incoterm}
            </option>
          ))}
        </select>
      </div>

      {result ? (
        <>
          <div className="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800">
            <table className="w-full text-sm">
              <thead className="bg-slate-50 dark:bg-slate-900/60">
                <tr>
                  <th className="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">
                    {copy.results.colItem}
                  </th>
                  <th className="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">
                    {copy.results.colBearer}
                  </th>
                </tr>
              </thead>
              <tbody>
                {result.items.map((entry) => (
                  <tr key={entry.item} className="border-t border-slate-200 dark:border-slate-800">
                    <td className="px-4 py-3 text-slate-700 dark:text-slate-300">
                      {itemLabel(copy, entry.item)}
                    </td>
                    <td className="px-4 py-3">
                      <span
                        className={
                          entry.bearer === 'seller'
                            ? 'inline-flex rounded-full bg-emerald-100 dark:bg-emerald-900/40 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 dark:text-emerald-300'
                            : 'inline-flex rounded-full bg-amber-100 dark:bg-amber-900/40 px-2.5 py-0.5 text-xs font-semibold text-amber-800 dark:text-amber-300'
                        }
                      >
                        {bearerLabel(copy, entry.bearer)}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <ResultPanel
            title={common.result}
            rows={[
              { label: copy.results.incoterm, value: result.incoterm },
              { label: copy.results.sellerItems, value: String(result.sellerItems) },
              { label: copy.results.buyerItems, value: String(result.buyerItems), emphasis: true },
            ]}
            copyLabel={common.copy}
            copiedLabel={common.copied}
            copyText={result.items
              .map((entry) => `${itemLabel(copy, entry.item)}: ${bearerLabel(copy, entry.bearer)}`)
              .join('\n')}
            share={{ values, copy: common }}
            csv={{
              headers: [copy.results.colItem, copy.results.colBearer],
              rows: result.items.map((entry) => [itemLabel(copy, entry.item), bearerLabel(copy, entry.bearer)]),
            }}
          />
        </>
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
