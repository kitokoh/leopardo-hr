'use client';

/**
 * Facturation & encaissement scolaires — /edu-manager/fee-charges (#9790).
 *
 * Les endpoints de frais existaient côté API (EDU-016, #5832) mais AUCUNE page
 * ne les servait : un directeur ne pouvait ni facturer un élève, ni encaisser
 * un règlement, ni suivre un solde depuis l'interface. Seul le catalogue des
 * tarifs (`/edu-manager/fee-types`) était accessible — c'est-à-dire le
 * préalable, jamais l'acte.
 *
 * Cette page couvre le guichet complet :
 *   1. la LISTE des charges avec montant, déjà payé et SOLDE RESTANT ;
 *   2. la CRÉATION d'une charge (élève, type de frais, année, montant, échéance) ;
 *   3. l'ENCAISSEMENT (partiel ou soldé) avec méthode et référence ;
 *   4. l'ABANDON d'un solde (remise) ;
 *   5. le JOURNAL des encaissements du guichet.
 *
 * Le solde est calculé côté serveur (`remaining_amount`) : la page ne le
 * recompose jamais en PHP/JS, sinon deux sources de vérité divergeraient dès
 * le premier encaissement partiel.
 */

import { useCallback, useEffect, useMemo, useState } from 'react';
import { Banknote, HandCoins, Plus, Receipt, Undo2 } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';
import { ModulePageShell } from '@/components/module-page-shell';
import {
  Button,
  Card,
  DataTable,
  Field,
  Modal,
  SectionTitle,
  SelectInput,
  StatusBadge,
  TextInput,
  type Column,
} from '../_components/edu-ui';

type Row = Record<string, unknown>;
type Option = { value: string; label: string };

const PAYMENT_METHODS = ['cash', 'transfer', 'card', 'mobile_money', 'other'];

const CHARGE_STATUSES = ['pending', 'partial', 'paid', 'waived', 'cancelled'];

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

export default function FeeChargesPage() {
  const locale = getPreferredLocale();

  const [rows, setRows] = useState<Row[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [statusFilter, setStatusFilter] = useState('');

  const [students, setStudents] = useState<Option[]>([]);
  const [feeTypes, setFeeTypes] = useState<Option[]>([]);
  const [years, setYears] = useState<Option[]>([]);

  const [createOpen, setCreateOpen] = useState(false);
  const [createForm, setCreateForm] = useState<Record<string, string>>({
    student_id: '',
    fee_type_id: '',
    academic_year_id: '',
    amount: '',
    due_date: '',
  });
  const [createError, setCreateError] = useState<string | null>(null);

  const [payOpen, setPayOpen] = useState(false);
  const [payTarget, setPayTarget] = useState<Row | null>(null);
  const [payForm, setPayForm] = useState<Record<string, string>>({
    amount: '',
    method: 'cash',
    reference: '',
  });
  const [payError, setPayError] = useState<string | null>(null);

  const [saving, setSaving] = useState(false);

  const [payments, setPayments] = useState<Row[]>([]);
  const [paymentsLoading, setPaymentsLoading] = useState(true);
  const [paymentsError, setPaymentsError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const query = statusFilter ? `&status=${encodeURIComponent(statusFilter)}` : '';
      const res = await apiFetch(`/edu-manager/fee-charges?per_page=200${query}`, { _cacheBust: true });
      const json = (await res.json()) as { data?: Row[] };
      setRows(Array.isArray(json.data) ? json.data : []);
    } catch {
      setError(t(locale, 'edu.common.error'));
    } finally {
      setLoading(false);
    }
  }, [locale, statusFilter]);

  const loadPayments = useCallback(async () => {
    setPaymentsLoading(true);
    setPaymentsError(null);
    try {
      const res = await apiFetch('/edu-manager/fee-payments?per_page=50', { _cacheBust: true });
      const json = (await res.json()) as { data?: Row[] };
      setPayments(Array.isArray(json.data) ? json.data : []);
    } catch {
      setPaymentsError(t(locale, 'edu.common.error'));
    } finally {
      setPaymentsLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    void loadPayments();
  }, [loadPayments]);

  const loadOptions = useCallback(async () => {
    const fetchOptions = async (path: string, label: (row: Row) => string): Promise<Option[]> => {
      try {
        const res = await apiFetch(`${path}?per_page=200`, { _cacheBust: true });
        const json = (await res.json()) as { data?: Row[] };
        return (Array.isArray(json.data) ? json.data : []).map((row) => ({
          value: String(row.id),
          label: label(row),
        }));
      } catch {
        return [];
      }
    };

    const [studentOptions, feeTypeOptions, yearOptions] = await Promise.all([
      fetchOptions('/edu-manager/students', (row) =>
        String(row.display_name ?? row.student_number ?? row.id),
      ),
      fetchOptions('/edu-manager/fee-types', (row) => String(row.label ?? row.code ?? row.id)),
      fetchOptions('/edu-manager/academic-years', (row) => String(row.name ?? row.id)),
    ]);

    setStudents(studentOptions);
    setFeeTypes(feeTypeOptions);
    setYears(yearOptions);
  }, []);

  const openCreate = async () => {
    setCreateForm({
      student_id: '',
      fee_type_id: '',
      academic_year_id: '',
      amount: '',
      due_date: '',
    });
    setCreateError(null);
    setCreateOpen(true);
    await loadOptions();
  };

  const create = async () => {
    setSaving(true);
    setCreateError(null);
    try {
      const body: Record<string, unknown> = {
        student_id: Number(createForm.student_id),
        fee_type_id: Number(createForm.fee_type_id),
        academic_year_id: Number(createForm.academic_year_id),
      };
      // Montant vide → le serveur applique le tarif du type de frais
      // (contrat `StoreEduFeeChargeRequest` : `amount` nullable).
      if (createForm.amount !== '') {
        body.amount = Number(createForm.amount);
      }
      if (createForm.due_date !== '') {
        body.due_date = createForm.due_date;
      }

      const res = await apiFetch('/edu-manager/fee-charges', {
        method: 'POST',
        body: JSON.stringify(body),
      });

      if (!res.ok) {
        setCreateError(await readError(res, locale));
        return;
      }

      setCreateOpen(false);
      await load();
    } catch {
      setCreateError(t(locale, 'edu.common.error'));
    } finally {
      setSaving(false);
    }
  };

  const openPay = (row: Row) => {
    setPayTarget(row);
    setPayForm({
      amount: String(row.remaining_amount ?? ''),
      method: 'cash',
      reference: '',
    });
    setPayError(null);
    setPayOpen(true);
  };

  const pay = async () => {
    if (!payTarget) return;
    setSaving(true);
    setPayError(null);
    try {
      const res = await apiFetch(`/edu-manager/fee-charges/${String(payTarget.id)}/payments`, {
        method: 'POST',
        body: JSON.stringify({
          amount: Number(payForm.amount),
          method: payForm.method,
          reference: payForm.reference || null,
        }),
      });

      if (!res.ok) {
        setPayError(await readError(res, locale));
        return;
      }

      setPayOpen(false);
      await Promise.all([load(), loadPayments()]);
    } catch {
      setPayError(t(locale, 'edu.common.error'));
    } finally {
      setSaving(false);
    }
  };

  const waive = async (row: Row) => {
    if (!window.confirm(t(locale, 'edu.feeCharges.waiveConfirm'))) {
      return;
    }
    try {
      const res = await apiFetch(`/edu-manager/fee-charges/${String(row.id)}/waive`, {
        method: 'POST',
      });
      if (!res.ok) {
        setError(await readError(res, locale));
        return;
      }
      await load();
    } catch {
      setError(t(locale, 'edu.common.error'));
    }
  };

  const columns: Column<Row>[] = useMemo(
    () => [
      {
        key: 'student',
        header: t(locale, 'edu.feeCharges.student'),
        render: (row) => <span className="font-bold text-slate-900">{studentLabel(row)}</span>,
      },
      {
        key: 'fee_type',
        header: t(locale, 'edu.feeCharges.feeType'),
        render: (row) => <span className="text-slate-600">{feeTypeLabel(row)}</span>,
      },
      {
        key: 'amount',
        header: t(locale, 'edu.feeCharges.amount'),
        render: (row) => (
          <span className="tabular-nums text-slate-700">{money(row.amount, row.currency)}</span>
        ),
      },
      {
        key: 'paid_amount',
        header: t(locale, 'edu.feeCharges.paid'),
        render: (row) => (
          <span className="tabular-nums text-emerald-700">{money(row.paid_amount, row.currency)}</span>
        ),
      },
      {
        key: 'remaining_amount',
        header: t(locale, 'edu.feeCharges.remaining'),
        render: (row) => {
          const remaining = Number(row.remaining_amount ?? 0);
          return (
            <span
              className={`tabular-nums font-bold ${remaining > 0 ? 'text-rose-600' : 'text-slate-400'}`}
            >
              {money(row.remaining_amount, row.currency)}
            </span>
          );
        },
      },
      {
        key: 'due_date',
        header: t(locale, 'edu.feeCharges.dueDate'),
        render: (row) => <span className="text-slate-500">{String(row.due_date ?? '—')}</span>,
      },
      {
        key: 'status',
        header: t(locale, 'edu.feeCharges.status'),
        render: (row) => {
          const status = String(row.status ?? '');
          return (
            <StatusBadge
              status={status}
              label={t(locale, `edu.feeCharges.statuses.${status}`) || status}
            />
          );
        },
      },
      {
        key: '__actions',
        header: t(locale, 'edu.common.actions'),
        render: (row) => {
          const status = String(row.status ?? '');
          const terminal = status === 'paid' || status === 'waived' || status === 'cancelled';
          return (
            <div className="flex items-center gap-1">
              <button
                type="button"
                title={t(locale, 'edu.feeCharges.collect')}
                disabled={terminal}
                onClick={() => openPay(row)}
                className="inline-flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-xs font-bold text-emerald-700 hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-40"
              >
                <HandCoins className="h-4 w-4" aria-hidden="true" />
                {t(locale, 'edu.feeCharges.collect')}
              </button>
              <button
                type="button"
                title={t(locale, 'edu.feeCharges.waive')}
                disabled={terminal}
                onClick={() => void waive(row)}
                className="inline-flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-xs font-bold text-slate-500 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40"
              >
                <Undo2 className="h-4 w-4" aria-hidden="true" />
                {t(locale, 'edu.feeCharges.waive')}
              </button>
            </div>
          );
        },
      },
    ],
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [locale],
  );

  const paymentColumns: Column<Row>[] = useMemo(
    () => [
      {
        key: 'paid_at',
        header: t(locale, 'edu.feeCharges.paidAt'),
        render: (row) => (
          <span className="text-slate-500">
            {row.paid_at ? new Date(String(row.paid_at)).toLocaleString() : '—'}
          </span>
        ),
      },
      {
        key: 'amount',
        header: t(locale, 'edu.feeCharges.amount'),
        render: (row) => (
          <span className="tabular-nums font-bold text-slate-900">
            {money(row.amount, row.currency)}
          </span>
        ),
      },
      {
        key: 'method',
        header: t(locale, 'edu.feeCharges.method'),
        render: (row) => <span className="text-slate-600">{String(row.method ?? '—')}</span>,
      },
      {
        key: 'reference',
        header: t(locale, 'edu.feeCharges.reference'),
        render: (row) => (
          <span className="font-mono text-xs text-slate-500">{String(row.reference ?? '—')}</span>
        ),
      },
      {
        key: 'fee_charge_id',
        header: t(locale, 'edu.feeCharges.charge'),
        render: (row) => <span className="text-slate-500">#{String(row.fee_charge_id ?? '—')}</span>,
      },
    ],
    [locale],
  );

  return (
    <ModulePageShell
      title={t(locale, 'edu.feeCharges.title')}
      subtitle={t(locale, 'edu.feeCharges.subtitle')}
      accentClassName="border-cyan-500/10 bg-cyan-500/5"
    >
      <div className="space-y-6">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="flex items-center gap-2">
            <Receipt className="h-4 w-4 text-emerald-700" aria-hidden="true" />
            <SelectInput
              value={statusFilter}
              onChange={(event) => setStatusFilter(event.target.value)}
              aria-label={t(locale, 'edu.feeCharges.status')}
            >
              <option value="">{t(locale, 'edu.feeCharges.allStatuses')}</option>
              {CHARGE_STATUSES.map((status) => (
                <option key={status} value={status}>
                  {status}
                </option>
              ))}
            </SelectInput>
          </div>
          <Button onClick={() => void openCreate()}>
            <Plus className="h-4 w-4" aria-hidden="true" />
            {t(locale, 'edu.feeCharges.create')}
          </Button>
        </div>

        <DataTable
          columns={columns}
          rows={rows}
          rowKey={(row) => String(row.id)}
          emptyLabel={t(locale, 'edu.feeCharges.empty')}
          loading={loading}
          error={error}
          onRetry={() => void load()}
        />

        <Card>
          <SectionTitle
            title={t(locale, 'edu.feeCharges.journal')}
            subtitle={t(locale, 'edu.feeCharges.journalSubtitle')}
          />
          <DataTable
            columns={paymentColumns}
            rows={payments}
            rowKey={(row) => String(row.id)}
            emptyLabel={t(locale, 'edu.feeCharges.journalEmpty')}
            loading={paymentsLoading}
            error={paymentsError}
            onRetry={() => void loadPayments()}
          />
        </Card>
      </div>

      <Modal
        open={createOpen}
        title={t(locale, 'edu.feeCharges.create')}
        onClose={() => setCreateOpen(false)}
        closeLabel={t(locale, 'edu.common.close')}
      >
        <div className="space-y-4">
          <Field label={t(locale, 'edu.feeCharges.student')}>
            <SelectInput
              value={createForm.student_id}
              required
              onChange={(event) =>
                setCreateForm((prev) => ({ ...prev, student_id: event.target.value }))
              }
            >
              <option value="">—</option>
              {students.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </SelectInput>
          </Field>

          <Field label={t(locale, 'edu.feeCharges.feeType')}>
            <SelectInput
              value={createForm.fee_type_id}
              required
              onChange={(event) =>
                setCreateForm((prev) => ({ ...prev, fee_type_id: event.target.value }))
              }
            >
              <option value="">—</option>
              {feeTypes.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </SelectInput>
          </Field>

          <Field label={t(locale, 'edu.feeCharges.academicYear')}>
            <SelectInput
              value={createForm.academic_year_id}
              required
              onChange={(event) =>
                setCreateForm((prev) => ({ ...prev, academic_year_id: event.target.value }))
              }
            >
              <option value="">—</option>
              {years.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </SelectInput>
          </Field>

          <Field label={t(locale, 'edu.feeCharges.amount')} hint={t(locale, 'edu.feeCharges.amountHint')}>
            <TextInput
              type="number"
              min={0}
              value={createForm.amount}
              placeholder={t(locale, 'edu.feeCharges.amountPlaceholder')}
              onChange={(event) =>
                setCreateForm((prev) => ({ ...prev, amount: event.target.value }))
              }
            />
          </Field>

          <Field label={t(locale, 'edu.feeCharges.dueDate')}>
            <TextInput
              type="date"
              value={createForm.due_date}
              onChange={(event) =>
                setCreateForm((prev) => ({ ...prev, due_date: event.target.value }))
              }
            />
          </Field>

          {createError ? (
            <p className="rounded-xl bg-rose-50 px-3 py-2 text-sm font-medium text-rose-600">
              {createError}
            </p>
          ) : null}

          <div className="flex justify-end gap-2 pt-2">
            <Button variant="ghost" onClick={() => setCreateOpen(false)}>
              {t(locale, 'edu.common.cancel')}
            </Button>
            <Button
              onClick={() => void create()}
              disabled={
                saving ||
                createForm.student_id === '' ||
                createForm.fee_type_id === '' ||
                createForm.academic_year_id === ''
              }
            >
              {t(locale, 'edu.common.save')}
            </Button>
          </div>
        </div>
      </Modal>

      <Modal
        open={payOpen}
        title={t(locale, 'edu.feeCharges.collectTitle')}
        onClose={() => setPayOpen(false)}
        closeLabel={t(locale, 'edu.common.close')}
      >
        <div className="space-y-4">
          <Card>
            <div className="flex items-center justify-between gap-3">
              <div>
                <p className="text-sm font-bold text-slate-900">
                  {payTarget ? studentLabel(payTarget) : '—'}
                </p>
                <p className="text-xs text-slate-500">
                  {payTarget ? feeTypeLabel(payTarget) : '—'}
                </p>
              </div>
              <div className="text-right">
                <p className="text-xs font-bold uppercase tracking-widest text-slate-400">
                  {t(locale, 'edu.feeCharges.remaining')}
                </p>
                <p className="tabular-nums text-lg font-black text-rose-600">
                  {payTarget ? money(payTarget.remaining_amount, payTarget.currency) : '—'}
                </p>
              </div>
            </div>
          </Card>

          <Field label={t(locale, 'edu.feeCharges.amount')}>
            <TextInput
              type="number"
              min={0}
              value={payForm.amount}
              required
              onChange={(event) => setPayForm((prev) => ({ ...prev, amount: event.target.value }))}
            />
          </Field>

          <Field label={t(locale, 'edu.feeCharges.method')}>
            <SelectInput
              value={payForm.method}
              onChange={(event) => setPayForm((prev) => ({ ...prev, method: event.target.value }))}
            >
              {PAYMENT_METHODS.map((method) => (
                <option key={method} value={method}>
                  {method}
                </option>
              ))}
            </SelectInput>
          </Field>

          <Field label={t(locale, 'edu.feeCharges.reference')}>
            <TextInput
              value={payForm.reference}
              onChange={(event) =>
                setPayForm((prev) => ({ ...prev, reference: event.target.value }))
              }
            />
          </Field>

          {payError ? (
            <p className="rounded-xl bg-rose-50 px-3 py-2 text-sm font-medium text-rose-600">
              {payError}
            </p>
          ) : null}

          <div className="flex justify-end gap-2 pt-2">
            <Button variant="ghost" onClick={() => setPayOpen(false)}>
              {t(locale, 'edu.common.cancel')}
            </Button>
            <Button onClick={() => void pay()} disabled={saving || payForm.amount === ''}>
              <Banknote className="h-4 w-4" aria-hidden="true" />
              {t(locale, 'edu.feeCharges.collect')}
            </Button>
          </div>
        </div>
      </Modal>
    </ModulePageShell>
  );
}

/**
 * Message d'erreur serveur : le contrat EDU renvoie `{ error: 'EDU_FEE_*' }`
 * pour les refus métier (surdébit, terminal, devise) et `{ message }` pour la
 * validation. On préfère le code métier traduit, sinon le message brut.
 */
async function readError(res: Response, locale: ReturnType<typeof getPreferredLocale>): Promise<string> {
  try {
    const payload = (await res.json()) as { error?: string; message?: string };
    if (payload.error) {
      const translated = t(locale, `edu.feeCharges.errors.${payload.error}`);
      if (translated) {
        return translated;
      }
      return payload.error;
    }
    if (payload.message) {
      return payload.message;
    }
  } catch {
    // garde le message par défaut
  }
  return t(locale, 'edu.common.error');
}
