'use client';

/**
 * Frais scolaires — read model hérité — /edu-manager/fees (#9790).
 *
 * `GET/POST /edu-manager/fees` et les transitions `pay` / `cancel` / `waive`
 * existaient (EDU-016, #5832) sans aucune page. Ce contrat est DISTINCT de
 * `/edu-manager/fee-charges` : `EduFee` est le read model consommé par le
 * module Accounting (montant unique, statut terminal), là où `EduFeeCharge`
 * porte les encaissements partiels. Les deux écrans coexistent donc, et
 * celui-ci reste le seul accès aux frais créés par l'ancien flux.
 *
 * Le statut est terminal (`paid` / `waived` / `cancelled`) : les actions sont
 * désactivées dans ce cas, comme le refuse le service (422).
 */

import { useCallback, useEffect, useMemo, useState } from 'react';
import { Ban, CheckCircle2, Plus, Undo2 } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';
import { ModulePageShell } from '@/components/module-page-shell';
import {
  Button,
  DataTable,
  Field,
  Modal,
  SelectInput,
  StatusBadge,
  TextInput,
  type Column,
} from '../_components/edu-ui';

type Row = Record<string, unknown>;
type Option = { value: string; label: string };

const FEE_STATUSES = ['pending', 'paid', 'waived', 'cancelled'];

function money(value: unknown): string {
  const amount = Number(value ?? 0);
  return Number.isFinite(amount) ? amount.toLocaleString() : '—';
}

function studentLabel(row: Row): string {
  const student = row.student as Row | null | undefined;
  if (student && typeof student === 'object') {
    return String(student.display_name ?? student.student_number ?? student.id ?? '—');
  }
  return `#${String(row.student_id ?? '—')}`;
}

export default function FeesPage() {
  const locale = getPreferredLocale();

  const [rows, setRows] = useState<Row[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [statusFilter, setStatusFilter] = useState('');

  const [students, setStudents] = useState<Option[]>([]);
  const [createOpen, setCreateOpen] = useState(false);
  const [form, setForm] = useState<Record<string, string>>({
    student_id: '',
    label: '',
    amount: '',
    due_date: '',
  });
  const [saveError, setSaveError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const query = statusFilter ? `&status=${encodeURIComponent(statusFilter)}` : '';
      const res = await apiFetch(`/edu-manager/fees?per_page=200${query}`, { _cacheBust: true });
      const json = (await res.json()) as { data?: Row[] };
      setRows(Array.isArray(json.data) ? json.data : []);
    } catch {
      setError(t(locale, 'edu.common.error'));
    } finally {
      setLoading(false);
    }
  }, [locale, statusFilter]);

  useEffect(() => {
    void load();
  }, [load]);

  const openCreate = async () => {
    setForm({ student_id: '', label: '', amount: '', due_date: '' });
    setSaveError(null);
    setCreateOpen(true);
    try {
      const res = await apiFetch('/edu-manager/students?per_page=200', { _cacheBust: true });
      const json = (await res.json()) as { data?: Row[] };
      setStudents(
        (Array.isArray(json.data) ? json.data : []).map((row) => ({
          value: String(row.id),
          label: String(row.display_name ?? row.student_number ?? row.id),
        })),
      );
    } catch {
      setStudents([]);
    }
  };

  const create = async () => {
    setSaving(true);
    setSaveError(null);
    try {
      const res = await apiFetch('/edu-manager/fees', {
        method: 'POST',
        body: JSON.stringify({
          student_id: Number(form.student_id),
          label: form.label,
          amount: Number(form.amount),
          due_date: form.due_date,
        }),
      });

      if (!res.ok) {
        setSaveError(await readError(res, locale));
        return;
      }

      setCreateOpen(false);
      await load();
    } catch {
      setSaveError(t(locale, 'edu.common.error'));
    } finally {
      setSaving(false);
    }
  };

  const transition = async (row: Row, action: 'pay' | 'cancel' | 'waive') => {
    if (action !== 'pay' && !window.confirm(t(locale, `edu.fees.${action}Confirm`))) {
      return;
    }
    try {
      const res = await apiFetch(`/edu-manager/fees/${String(row.id)}/${action}`, {
        method: 'POST',
        body: JSON.stringify({}),
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
        header: t(locale, 'edu.fees.student'),
        render: (row) => <span className="font-bold text-slate-900">{studentLabel(row)}</span>,
      },
      {
        key: 'label',
        header: t(locale, 'edu.fees.label'),
        render: (row) => <span className="text-slate-600">{String(row.label ?? '—')}</span>,
      },
      {
        key: 'amount',
        header: t(locale, 'edu.fees.amount'),
        render: (row) => (
          <span className="tabular-nums font-bold text-slate-900">{money(row.amount)}</span>
        ),
      },
      {
        key: 'due_date',
        header: t(locale, 'edu.fees.dueDate'),
        render: (row) => <span className="text-slate-500">{String(row.due_date ?? '—')}</span>,
      },
      {
        key: 'status',
        header: t(locale, 'edu.fees.status'),
        render: (row) => {
          const status = String(row.status ?? '');
          return (
            <StatusBadge status={status} label={t(locale, `edu.fees.statuses.${status}`) || status} />
          );
        },
      },
      {
        key: 'paid_at',
        header: t(locale, 'edu.fees.paidAt'),
        render: (row) => (
          <span className="text-slate-500">
            {row.paid_at ? new Date(String(row.paid_at)).toLocaleDateString() : '—'}
          </span>
        ),
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
                title={t(locale, 'edu.fees.pay')}
                disabled={terminal}
                onClick={() => void transition(row, 'pay')}
                className="inline-flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-xs font-bold text-emerald-700 hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-40"
              >
                <CheckCircle2 className="h-4 w-4" aria-hidden="true" />
                {t(locale, 'edu.fees.pay')}
              </button>
              <button
                type="button"
                title={t(locale, 'edu.fees.waive')}
                disabled={terminal}
                onClick={() => void transition(row, 'waive')}
                className="inline-flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-xs font-bold text-slate-500 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40"
              >
                <Undo2 className="h-4 w-4" aria-hidden="true" />
                {t(locale, 'edu.fees.waive')}
              </button>
              <button
                type="button"
                title={t(locale, 'edu.fees.cancel')}
                disabled={terminal}
                onClick={() => void transition(row, 'cancel')}
                className="inline-flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-xs font-bold text-rose-600 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-40"
              >
                <Ban className="h-4 w-4" aria-hidden="true" />
                {t(locale, 'edu.fees.cancel')}
              </button>
            </div>
          );
        },
      },
    ],
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [locale],
  );

  return (
    <ModulePageShell
      title={t(locale, 'edu.fees.title')}
      subtitle={t(locale, 'edu.fees.subtitle')}
      accentClassName="border-cyan-500/10 bg-cyan-500/5"
    >
      <div className="space-y-6">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <SelectInput
            value={statusFilter}
            onChange={(event) => setStatusFilter(event.target.value)}
            aria-label={t(locale, 'edu.fees.status')}
          >
            <option value="">{t(locale, 'edu.fees.allStatuses')}</option>
            {FEE_STATUSES.map((status) => (
              <option key={status} value={status}>
                {status}
              </option>
            ))}
          </SelectInput>
          <Button onClick={() => void openCreate()}>
            <Plus className="h-4 w-4" aria-hidden="true" />
            {t(locale, 'edu.fees.create')}
          </Button>
        </div>

        <DataTable
          columns={columns}
          rows={rows}
          rowKey={(row) => String(row.id)}
          emptyLabel={t(locale, 'edu.fees.empty')}
          loading={loading}
          error={error}
          onRetry={() => void load()}
        />
      </div>

      <Modal
        open={createOpen}
        title={t(locale, 'edu.fees.create')}
        onClose={() => setCreateOpen(false)}
        closeLabel={t(locale, 'edu.common.close')}
      >
        <div className="space-y-4">
          <Field label={t(locale, 'edu.fees.student')}>
            <SelectInput
              value={form.student_id}
              required
              onChange={(event) => setForm((prev) => ({ ...prev, student_id: event.target.value }))}
            >
              <option value="">—</option>
              {students.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </SelectInput>
          </Field>

          <Field label={t(locale, 'edu.fees.label')}>
            <TextInput
              value={form.label}
              required
              onChange={(event) => setForm((prev) => ({ ...prev, label: event.target.value }))}
            />
          </Field>

          <Field label={t(locale, 'edu.fees.amount')}>
            <TextInput
              type="number"
              min={0}
              value={form.amount}
              required
              onChange={(event) => setForm((prev) => ({ ...prev, amount: event.target.value }))}
            />
          </Field>

          <Field label={t(locale, 'edu.fees.dueDate')}>
            <TextInput
              type="date"
              value={form.due_date}
              required
              onChange={(event) => setForm((prev) => ({ ...prev, due_date: event.target.value }))}
            />
          </Field>

          {saveError ? (
            <p className="rounded-xl bg-rose-50 px-3 py-2 text-sm font-medium text-rose-600">
              {saveError}
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
                form.student_id === '' ||
                form.label === '' ||
                form.amount === '' ||
                form.due_date === ''
              }
            >
              {t(locale, 'edu.common.save')}
            </Button>
          </div>
        </div>
      </Modal>
    </ModulePageShell>
  );
}

async function readError(res: Response, locale: ReturnType<typeof getPreferredLocale>): Promise<string> {
  try {
    const payload = (await res.json()) as { error?: string; message?: string };
    if (payload.error) {
      const translated = t(locale, `edu.fees.errors.${payload.error}`);
      return translated || payload.error;
    }
    if (payload.message) {
      return payload.message;
    }
  } catch {
    // garde le message par défaut
  }
  return t(locale, 'edu.common.error');
}
