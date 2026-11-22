'use client';

/**
 * Encaissement EN LOT au guichet — /edu-manager/fee-charges/batch (#9791).
 *
 * À la rentrée, une classe entière règle le même jour. Le guichet rouvrait la
 * modale d'encaissement une fois par élève : trente allers-retours, et rien ne
 * garantissait que le moyen de paiement ou la référence de bordereau fussent
 * identiques d'une ligne à l'autre.
 *
 * Cet écran saisit le CONTEXTE COMMUN une seule fois (moyen, référence de
 * bordereau, date) puis une ligne par élève. Le serveur valide TOUTES les
 * lignes avant d'écrire : si une seule est fautive, rien n'est enregistré et
 * la réponse désigne la ligne (index) — l'écran la surligne au lieu de laisser
 * le caissier chercher laquelle, parmi trente, a échoué.
 *
 * Le solde restant affiché vient du serveur (`remaining_amount`) : l'écran ne
 * le recompose jamais, sinon deux sources de vérité divergeraient dès le
 * premier encaissement partiel.
 */

import { useCallback, useEffect, useMemo, useState } from 'react';
import Link from 'next/link';
import { ArrowLeft, CheckCircle2, Layers, Plus, Trash2 } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale, type AppLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';
import { ModulePageShell } from '@/components/module-page-shell';
import {
  Button,
  Card,
  Field,
  SectionTitle,
  SelectInput,
  TextInput,
} from '../../_components/edu-ui';

type Row = Record<string, unknown>;

const PAYMENT_METHODS = ['cash', 'transfer', 'card', 'mobile_money', 'other'] as const;

/** Libellé humain d'un moyen de paiement (cf. page de facturation). */
function methodLabel(locale: AppLocale, method: string): string {
  const key = `edu.feeCharges.method${method
    .split('_')
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join('')}`;
  return t(locale, key) || method;
}

type Line = {
  key: string;
  chargeId: string;
  amount: string;
};

type LineError = { index: number; charge_id: number; error: string };

function money(value: unknown, currency: unknown): string {
  const amount = Number(value ?? 0);
  const code = String(currency ?? '');
  if (!Number.isFinite(amount)) {
    return '—';
  }
  return `${amount.toLocaleString()} ${code}`.trim();
}

function studentLabel(row: Row): string {
  const student = row.student as Row | null | undefined;
  if (student && typeof student === 'object') {
    return String(student.display_name ?? student.student_number ?? student.id ?? '—');
  }
  return `#${String(row.student_id ?? '—')}`;
}

function feeTypeLabel(row: Row): string {
  const feeType = row.fee_type as Row | null | undefined;
  if (feeType && typeof feeType === 'object') {
    return String(feeType.label ?? feeType.code ?? feeType.id ?? '—');
  }
  return `#${String(row.fee_type_id ?? '—')}`;
}

let lineSeq = 0;
function newLine(): Line {
  lineSeq += 1;
  return { key: `line-${lineSeq}`, chargeId: '', amount: '' };
}

export default function FeeBatchPage() {
  const locale = getPreferredLocale();

  const [charges, setCharges] = useState<Row[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [method, setMethod] = useState('cash');
  const [reference, setReference] = useState('');
  const [paidAt, setPaidAt] = useState('');

  const [lines, setLines] = useState<Line[]>([newLine()]);
  const [lineErrors, setLineErrors] = useState<LineError[]>([]);
  const [formError, setFormError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      // Seules les charges encore encaissables ont un sens dans un lot :
      // une charge soldée/abandonnée/annulée serait refusée par le serveur.
      const res = await apiFetch('/edu-manager/fee-charges?per_page=200', { _cacheBust: true });
      const json = (await res.json()) as { data?: Row[] };
      const rows = Array.isArray(json.data) ? json.data : [];
      setCharges(
        rows.filter((row) => {
          const status = String(row.status ?? '');
          return status !== 'paid' && status !== 'waived' && status !== 'cancelled';
        }),
      );
    } catch {
      setError(t(locale, 'edu.common.error'));
    } finally {
      setLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    void load();
  }, [load]);

  const chargeById = useMemo(() => {
    const map = new Map<string, Row>();
    for (const row of charges) {
      map.set(String(row.id), row);
    }
    return map;
  }, [charges]);

  const total = useMemo(
    () =>
      lines.reduce((sum, line) => {
        const value = Number(line.amount);
        return Number.isFinite(value) ? sum + value : sum;
      }, 0),
    [lines],
  );

  const currency = useMemo(() => {
    for (const line of lines) {
      const row = chargeById.get(line.chargeId);
      if (row) {
        return String(row.currency ?? '');
      }
    }
    return '';
  }, [lines, chargeById]);

  const updateLine = (key: string, patch: Partial<Line>) => {
    setLines((prev) => prev.map((line) => (line.key === key ? { ...line, ...patch } : line)));
    setLineErrors([]);
    setFormError(null);
    setSuccess(null);
  };

  const addLine = () => {
    setLines((prev) => [...prev, newLine()]);
    setLineErrors([]);
    setSuccess(null);
  };

  const removeLine = (key: string) => {
    setLines((prev) => (prev.length <= 1 ? prev : prev.filter((line) => line.key !== key)));
    setLineErrors([]);
    setSuccess(null);
  };

  /** Pré-remplit le montant au solde restant : évite le surdébit par défaut. */
  const selectCharge = (key: string, chargeId: string) => {
    const row = chargeById.get(chargeId);
    const remaining = row ? String(row.remaining_amount ?? '') : '';
    updateLine(key, { chargeId, amount: remaining });
  };

  const submit = async () => {
    setSaving(true);
    setFormError(null);
    setLineErrors([]);
    setSuccess(null);

    try {
      const body: Record<string, unknown> = {
        method,
        lines: lines
          .filter((line) => line.chargeId !== '')
          .map((line) => ({
            charge_id: Number(line.chargeId),
            amount: Number(line.amount),
          })),
      };

      if (reference !== '') {
        body.reference = reference;
      }
      if (paidAt !== '') {
        body.paid_at = paidAt;
      }

      const res = await apiFetch('/edu-manager/fee-charges/batch-payments', {
        method: 'POST',
        body: JSON.stringify(body),
      });

      if (!res.ok) {
        const payload = (await res.json().catch(() => ({}))) as {
          error?: string;
          errors?: LineError[];
        };

        if (Array.isArray(payload.errors) && payload.errors.length > 0) {
          setLineErrors(payload.errors);
          setFormError(t(locale, 'edu.feeCharges.batchInvalid'));
        } else {
          setFormError(
            payload.error
              ? t(locale, `edu.feeCharges.errors.${payload.error}`) || payload.error
              : t(locale, 'edu.common.error'),
          );
        }
        return;
      }

      const payload = (await res.json()) as {
        summary?: { recorded?: number; total_amount?: number; currency?: string };
      };
      const recorded = Number(payload.summary?.recorded ?? 0);
      const sum = money(payload.summary?.total_amount, payload.summary?.currency);

      setSuccess(
        t(locale, 'edu.feeCharges.batchSuccess')
          .replace('{count}', String(recorded))
          .replace('{total}', sum),
      );
      setLines([newLine()]);
      setReference('');
      await load();
    } catch {
      setFormError(t(locale, 'edu.common.error'));
    } finally {
      setSaving(false);
    }
  };

  const errorForLine = (index: number): string | null => {
    const found = lineErrors.find((entry) => entry.index === index);
    if (!found) {
      return null;
    }
    const translated = t(locale, `edu.feeCharges.errors.${found.error}`);
    return translated || found.error;
  };

  const filledLines = lines.filter((line) => line.chargeId !== '');
  const canSubmit = !saving && filledLines.length > 0 && filledLines.every((line) => Number(line.amount) > 0);

  return (
    <ModulePageShell
      title={t(locale, 'edu.feeCharges.batchTitle')}
      subtitle={t(locale, 'edu.feeCharges.batchSubtitle')}
      accentClassName="border-emerald-500/10 bg-emerald-500/5"
    >
      <div className="space-y-6">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <Link
            href="/edu-manager/fee-charges"
            className="inline-flex items-center gap-1.5 text-sm font-bold text-slate-500 hover:text-slate-900"
          >
            <ArrowLeft className="h-4 w-4" aria-hidden="true" />
            {t(locale, 'edu.feeCharges.title')}
          </Link>
          <Button variant="ghost" onClick={() => void load()} disabled={loading}>
            {t(locale, 'edu.common.retry')}
          </Button>
        </div>

        {error ? (
          <p className="rounded-xl bg-rose-50 px-3 py-2 text-sm font-medium text-rose-600">{error}</p>
        ) : null}

        <Card>
          <SectionTitle
            title={t(locale, 'edu.feeCharges.batchSectionTitle')}
            subtitle={t(locale, 'edu.feeCharges.batchSubtitle')}
          />

          <div className="mt-4 grid gap-4 sm:grid-cols-3">
            <Field label={t(locale, 'edu.feeCharges.method')}>
              <SelectInput value={method} onChange={(event) => setMethod(event.target.value)}>
                {PAYMENT_METHODS.map((option) => (
                  <option key={option} value={option}>
                    {methodLabel(locale, option)}
                  </option>
                ))}
              </SelectInput>
            </Field>

            <Field label={t(locale, 'edu.feeCharges.reference')}>
              <TextInput
                value={reference}
                placeholder="BORD-2026-09"
                onChange={(event) => setReference(event.target.value)}
              />
            </Field>

            <Field label={t(locale, 'edu.feeCharges.paidAt')}>
              <TextInput
                type="date"
                value={paidAt}
                onChange={(event) => setPaidAt(event.target.value)}
              />
            </Field>
          </div>
        </Card>

        <Card>
          <div className="flex flex-wrap items-center justify-between gap-3">
            <SectionTitle title={t(locale, 'edu.feeCharges.batchLines')} />
            <Button variant="ghost" onClick={addLine}>
              <Plus className="h-4 w-4" aria-hidden="true" />
              {t(locale, 'edu.feeCharges.batchAddLine')}
            </Button>
          </div>

          <div className="mt-4 space-y-3">
            {lines.map((line, index) => {
              const lineError = errorForLine(index);
              const row = chargeById.get(line.chargeId);

              return (
                <div
                  key={line.key}
                  className={`rounded-xl border p-3 ${
                    lineError ? 'border-rose-300 bg-rose-50/60' : 'border-slate-200 bg-white'
                  }`}
                >
                  <div className="grid gap-3 sm:grid-cols-[1fr_140px_auto] sm:items-end">
                    <Field label={t(locale, 'edu.feeCharges.batchSelectCharge')}>
                      <SelectInput
                        value={line.chargeId}
                        onChange={(event) => selectCharge(line.key, event.target.value)}
                      >
                        <option value="">—</option>
                        {charges.map((charge) => (
                          <option key={String(charge.id)} value={String(charge.id)}>
                            {studentLabel(charge)} · {feeTypeLabel(charge)} ·{' '}
                            {money(charge.remaining_amount, charge.currency)}
                          </option>
                        ))}
                      </SelectInput>
                    </Field>

                    <Field label={t(locale, 'edu.feeCharges.amount')}>
                      <TextInput
                        type="number"
                        min={0}
                        value={line.amount}
                        onChange={(event) => updateLine(line.key, { amount: event.target.value })}
                      />
                    </Field>

                    <button
                      type="button"
                      title={t(locale, 'edu.feeCharges.batchRemoveLine')}
                      onClick={() => removeLine(line.key)}
                      disabled={lines.length <= 1}
                      className="mb-1 inline-flex items-center gap-1.5 rounded-lg px-2 py-2 text-xs font-bold text-rose-600 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                      <Trash2 className="h-4 w-4" aria-hidden="true" />
                      {t(locale, 'edu.feeCharges.batchRemoveLine')}
                    </button>
                  </div>

                  {row ? (
                    <p className="mt-2 text-xs text-slate-500">
                      {t(locale, 'edu.feeCharges.remaining')} :{' '}
                      <span className="font-bold tabular-nums text-slate-700">
                        {money(row.remaining_amount, row.currency)}
                      </span>
                    </p>
                  ) : null}

                  {lineError ? (
                    <p className="mt-2 text-xs font-bold text-rose-600">
                      {t(locale, 'edu.feeCharges.batchLineError').replace('{line}', String(index + 1))}
                      {' — '}
                      {lineError}
                    </p>
                  ) : null}
                </div>
              );
            })}
          </div>

          {formError ? (
            <p className="mt-4 rounded-xl bg-rose-50 px-3 py-2 text-sm font-medium text-rose-600">
              {formError}
            </p>
          ) : null}

          {success ? (
            <p className="mt-4 flex items-center gap-2 rounded-xl bg-emerald-50 px-3 py-2 text-sm font-bold text-emerald-700">
              <CheckCircle2 className="h-4 w-4" aria-hidden="true" />
              {success}
            </p>
          ) : null}

          <div className="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
            <div className="flex items-center gap-2">
              <Layers className="h-4 w-4 text-slate-400" aria-hidden="true" />
              <span className="text-xs font-bold uppercase tracking-widest text-slate-400">
                {t(locale, 'edu.feeCharges.batchTotal')}
              </span>
              <span className="tabular-nums text-lg font-black text-slate-900">
                {money(total, currency)}
              </span>
            </div>

            <Button onClick={() => void submit()} disabled={!canSubmit}>
              {t(locale, 'edu.feeCharges.batchSubmit')}
            </Button>
          </div>
        </Card>
      </div>
    </ModulePageShell>
  );
}
