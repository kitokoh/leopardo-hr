'use client';

import React from 'react';
import { NumberField } from '../NumberField';
import { ResultPanel } from '../ResultPanel';
import { computeExportMargin, targetPriceForMarginRate } from '../../tools/marge-export/logic';
import { formatNumber } from '../../lib/format';
import { parseNumber, round } from '../../lib/validation';
import { useToolState } from '../../lib/useToolState';
import type { ToolCopy, ToolboxCopy, ToolboxLocale } from '../../data/copy';

interface ExportMarginFormProps {
  copy: ToolCopy;
  common: ToolboxCopy['common'];
  locale: ToolboxLocale;
}

/**
 * Formulaire marge et prix cible export.
 *
 * L'outil affiche DÉLIBÉRÉMENT les deux taux : marge sur coût (markup) et
 * marge sur prix de vente (taux de marque). Les confondre est l'erreur n°1 en
 * commerce — ajouter 30 % au coût ne donne que 23,08 % de marque. Le prix cible
 * se calcule depuis le taux de MARQUE, la formule que l'utilisateur attend.
 */
export function ExportMarginForm({ copy, common, locale }: ExportMarginFormProps) {
  const [values, setValue] = useToolState({
    unitCost: '100',
    unitPrice: '130',
    targetRate: '30',
  });

  const unitCost = parseNumber(values.unitCost);
  const unitPrice = parseNumber(values.unitPrice);
  const targetRate = parseNumber(values.targetRate);

  const result =
    unitCost !== null && unitPrice !== null ? computeExportMargin({ unitCost, unitPrice }) : null;

  const targetPrice =
    unitCost !== null && targetRate !== null ? targetPriceForMarginRate(unitCost, targetRate) : null;

  return (
    <div className="space-y-5">
      <div className="grid gap-4 sm:grid-cols-2">
        <NumberField
          id="me-unit-cost"
          label={copy.fields.unitCost}
          value={values.unitCost}
          onChange={(next) => setValue('unitCost', next)}
          min={0}
        />
        <NumberField
          id="me-unit-price"
          label={copy.fields.unitPrice}
          value={values.unitPrice}
          onChange={(next) => setValue('unitPrice', next)}
          min={0}
        />
      </div>

      {result ? (
        <ResultPanel
          title={common.result}
          rows={[
            { label: copy.results.margin, value: formatNumber(result.margin, locale, 2) },
            { label: copy.results.markupPercent, value: `${formatNumber(result.markupPercent, locale, 2)} %` },
            {
              label: copy.results.marginRatePercent,
              // `null` = prix de vente nul : le taux est indéfini, on affiche
              // un tiret plutôt qu'un « 0 % » faux.
              value:
                result.marginRatePercent === null
                  ? '—'
                  : `${formatNumber(result.marginRatePercent, locale, 2)} %`,
              emphasis: true,
            },
            { label: copy.results.coefficient, value: formatNumber(result.coefficient, locale, 4) },
          ]}
          copyLabel={common.copy}
          copiedLabel={common.copied}
          copyText={`${copy.results.margin}: ${formatNumber(result.margin, locale, 2)} — ${copy.results.markupPercent}: ${formatNumber(result.markupPercent, locale, 2)} % — ${copy.results.marginRatePercent}: ${result.marginRatePercent === null ? '—' : `${formatNumber(result.marginRatePercent, locale, 2)} %`}`}
          share={{ values, copy: common }}
          csv={{
            headers: [copy.results.margin, copy.results.markupPercent, copy.results.marginRatePercent, copy.results.coefficient],
            rows: [[
              String(round(result.margin, 2)),
              String(round(result.markupPercent, 2)),
              String(round(result.marginRatePercent ?? 0, 2)),
              String(round(result.coefficient, 4)),
            ]],
          }}
        />
      ) : (
        <p className="text-sm text-slate-500 dark:text-slate-400">{common.invalidInput}</p>
      )}

      <div className="rounded-2xl border border-slate-200 dark:border-slate-800 p-5 space-y-4">
        <NumberField
          id="me-target-rate"
          label={copy.fields.targetRate}
          value={values.targetRate}
          onChange={(next) => setValue('targetRate', next)}
          min={0}
          max={99}
        />
        {targetPrice !== null ? (
          <div className="flex items-baseline justify-between gap-4">
            <span className="text-sm text-slate-600 dark:text-slate-400">{copy.results.targetPrice}</span>
            <span className="text-2xl font-bold text-emerald-700 dark:text-emerald-300 tabular-nums">
              {formatNumber(targetPrice, locale, 2)}
            </span>
          </div>
        ) : (
          <p className="text-sm text-slate-500 dark:text-slate-400">{common.invalidInput}</p>
        )}
      </div>

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
