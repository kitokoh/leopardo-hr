'use client';

import React from 'react';
import { NumberField } from '../NumberField';
import { ResultPanel } from '../ResultPanel';
import { computeImportUnitCost } from '../../tools/prix-revient-import/logic';
import { parseNumber, round } from '../../lib/validation';
import { formatNumber } from '../../lib/format';
import { useToolState } from '../../lib/useToolState';
import type { ToolCopy, ToolboxCopy, ToolboxLocale } from '../../data/copy';

interface ImportUnitCostFormProps {
  copy: ToolCopy;
  common: ToolboxCopy['common'];
  locale: ToolboxLocale;
}

/**
 * Formulaire prix de revient à l'import — coût unitaire rendu.
 *
 * Tous les montants dans la même devise : l'outil ne convertit pas, et le
 * rappeler dans les notes évite de faire croire à une conversion implicite.
 */
export function ImportUnitCostForm({ copy, common, locale }: ImportUnitCostFormProps) {
  const [values, setValue] = useToolState({
    purchase: '10000',
    freight: '1500',
    insurance: '200',
    duty: '800',
    taxes: '2000',
    otherFees: '300',
    quantity: '100',
  });

  const purchase = parseNumber(values.purchase) ?? 0;
  const freight = parseNumber(values.freight) ?? 0;
  const insurance = parseNumber(values.insurance) ?? 0;
  const duty = parseNumber(values.duty) ?? 0;
  const taxes = parseNumber(values.taxes) ?? 0;
  const otherFees = parseNumber(values.otherFees) ?? 0;
  const quantity = parseNumber(values.quantity);

  const result =
    quantity !== null
      ? computeImportUnitCost({ purchase, freight, insurance, duty, taxes, otherFees, quantity })
      : null;

  return (
    <div className="space-y-5">
      <div className="grid gap-4 sm:grid-cols-2">
        <NumberField
          id="pri-purchase"
          label={copy.fields.purchase}
          value={values.purchase}
          onChange={(next) => setValue('purchase', next)}
          min={0}
        />
        <NumberField
          id="pri-freight"
          label={copy.fields.freight}
          value={values.freight}
          onChange={(next) => setValue('freight', next)}
          min={0}
        />
        <NumberField
          id="pri-insurance"
          label={copy.fields.insurance}
          value={values.insurance}
          onChange={(next) => setValue('insurance', next)}
          min={0}
        />
        <NumberField
          id="pri-duty"
          label={copy.fields.duty}
          value={values.duty}
          onChange={(next) => setValue('duty', next)}
          min={0}
        />
        <NumberField
          id="pri-taxes"
          label={copy.fields.taxes}
          value={values.taxes}
          onChange={(next) => setValue('taxes', next)}
          min={0}
        />
        <NumberField
          id="pri-other-fees"
          label={copy.fields.otherFees}
          value={values.otherFees}
          onChange={(next) => setValue('otherFees', next)}
          min={0}
        />
      </div>

      <NumberField
        id="pri-quantity"
        label={copy.fields.quantity}
        value={values.quantity}
        onChange={(next) => setValue('quantity', next)}
        min={1}
      />

      {result ? (
        <ResultPanel
          title={common.result}
          rows={[
            { label: copy.results.totalCost, value: formatNumber(result.totalCost, locale, 2) },
            { label: copy.results.unitCost, value: formatNumber(result.unitCost, locale, 2), emphasis: true },
          ]}
          copyLabel={common.copy}
          copiedLabel={common.copied}
          copyText={`${copy.results.totalCost}: ${formatNumber(result.totalCost, locale, 2)} — ${copy.results.unitCost}: ${formatNumber(result.unitCost, locale, 2)}`}
          share={{ values, copy: common }}
          /*
           * Résultat TABULAIRE : chaque poste avec son montant et sa part — la
           * décomposition est le vrai livrable ici (savoir QUE le fret pèse
           * 10 % du coût de revient change une négociation).
           */
          csv={{
            headers: [copy.results.colItem, copy.results.colAmount, copy.results.colShare],
            rows: [
              [copy.fields.purchase, String(round(purchase, 2)), String(round(result.breakdown.purchase, 2))],
              [copy.fields.freight, String(round(freight, 2)), String(round(result.breakdown.freight, 2))],
              [copy.fields.insurance, String(round(insurance, 2)), String(round(result.breakdown.insurance, 2))],
              [copy.fields.duty, String(round(duty, 2)), String(round(result.breakdown.duty, 2))],
              [copy.fields.taxes, String(round(taxes, 2)), String(round(result.breakdown.taxes, 2))],
              [copy.fields.otherFees, String(round(otherFees, 2)), String(round(result.breakdown.otherFees, 2))],
              [copy.results.totalCost, String(round(result.totalCost, 2)), '100'],
              [copy.results.unitCost, String(round(result.unitCost, 2)), ''],
            ],
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
