'use client';

import React from 'react';
import { NumberField } from '../NumberField';
import { ResultPanel } from '../ResultPanel';
import { computeCustomsDuty } from '../../tools/droits-douane/logic';
import { parseNumber, round } from '../../lib/validation';
import { formatNumber } from '../../lib/format';
import { useToolState } from '../../lib/useToolState';
import type { ToolCopy, ToolboxCopy, ToolboxLocale } from '../../data/copy';

interface CustomsDutyFormProps {
  copy: ToolCopy;
  common: ToolboxCopy['common'];
  locale: ToolboxLocale;
}

/**
 * Formulaire droits de douane et TVA à l'import — estimation indicative.
 *
 * Les taux sont SAISIS : l'outil ne devine jamais un taux de droit (il dépend
 * du classement HS, de l'origine et des accords commerciaux). Présenter un taux
 * inventé comme une décision douanière serait le pire service à rendre ici.
 */
export function CustomsDutyForm({ copy, common, locale }: CustomsDutyFormProps) {
  const [values, setValue] = useToolState({
    goodsValue: '10000',
    freight: '1000',
    insurance: '200',
    dutyRate: '12',
    vatRate: '20',
  });

  const goodsValue = parseNumber(values.goodsValue) ?? 0;
  const freight = parseNumber(values.freight) ?? 0;
  const insurance = parseNumber(values.insurance) ?? 0;
  const dutyRate = parseNumber(values.dutyRate);
  const vatRate = parseNumber(values.vatRate);

  const result =
    dutyRate !== null && vatRate !== null
      ? computeCustomsDuty({
          goodsValue,
          freight,
          insurance,
          dutyRatePercent: dutyRate,
          vatRatePercent: vatRate,
        })
      : null;

  return (
    <div className="space-y-5">
      <div className="grid gap-4 sm:grid-cols-3">
        <NumberField
          id="dd-goods-value"
          label={copy.fields.goodsValue}
          value={values.goodsValue}
          onChange={(next) => setValue('goodsValue', next)}
          min={0}
        />
        <NumberField
          id="dd-freight"
          label={copy.fields.freight}
          value={values.freight}
          onChange={(next) => setValue('freight', next)}
          min={0}
        />
        <NumberField
          id="dd-insurance"
          label={copy.fields.insurance}
          value={values.insurance}
          onChange={(next) => setValue('insurance', next)}
          min={0}
        />
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <NumberField
          id="dd-duty-rate"
          label={copy.fields.dutyRate}
          value={values.dutyRate}
          onChange={(next) => setValue('dutyRate', next)}
          min={0}
          max={100}
        />
        <NumberField
          id="dd-vat-rate"
          label={copy.fields.vatRate}
          value={values.vatRate}
          onChange={(next) => setValue('vatRate', next)}
          min={0}
          max={100}
        />
      </div>

      {result ? (
        <ResultPanel
          title={common.result}
          rows={[
            { label: copy.results.cifValue, value: formatNumber(result.cifValue, locale, 2) },
            { label: copy.results.dutyAmount, value: formatNumber(result.dutyAmount, locale, 2) },
            { label: copy.results.vatBase, value: formatNumber(result.vatBase, locale, 2) },
            { label: copy.results.vatAmount, value: formatNumber(result.vatAmount, locale, 2) },
            { label: copy.results.totalTaxes, value: formatNumber(result.totalTaxes, locale, 2) },
            { label: copy.results.landedCost, value: formatNumber(result.landedCost, locale, 2), emphasis: true },
          ]}
          copyLabel={common.copy}
          copiedLabel={common.copied}
          copyText={`${copy.results.totalTaxes}: ${formatNumber(result.totalTaxes, locale, 2)} — ${copy.results.landedCost}: ${formatNumber(result.landedCost, locale, 2)}`}
          share={{ values, copy: common }}
          csv={{
            headers: [copy.results.colItem, copy.results.colAmount],
            rows: [
              [copy.results.cifValue, String(round(result.cifValue, 2))],
              [copy.results.dutyAmount, String(round(result.dutyAmount, 2))],
              [copy.results.vatBase, String(round(result.vatBase, 2))],
              [copy.results.vatAmount, String(round(result.vatAmount, 2))],
              [copy.results.totalTaxes, String(round(result.totalTaxes, 2))],
              [copy.results.landedCost, String(round(result.landedCost, 2))],
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
