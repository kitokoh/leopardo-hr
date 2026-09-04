'use client';

import { useCallback, useEffect, useState } from 'react';
import { CheckCircle2, Send, Sparkles } from 'lucide-react';
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
  type Column,
} from '../_components/edu-ui';

/**
 * EDU-011 (#5827) — Bulletins : génération (read model recalculable),
 * validation direction, publication verrouillante (EduReportCardService).
 */

type ReportCard = {
  id: number;
  student_id: number;
  student?: { id: number; student_number: string; display_name: string } | null;
  period: string;
  average?: number | null;
  status: string;
  created_at?: string;
};

export default function ReportCardsPage() {
  const locale = getPreferredLocale();
  const [rows, setRows] = useState<ReportCard[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [students, setStudents] = useState<{ id: number; display_name: string }[]>([]);
  const [modalOpen, setModalOpen] = useState(false);
  const [form, setForm] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);
  const [actingId, setActingId] = useState<number | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiFetch('/edu-manager/report-cards?per_page=200', { _cacheBust: true });
      const json = (await res.json()) as { data?: ReportCard[] };
      setRows(Array.isArray(json.data) ? json.data : []);
    } catch {
      setError(t(locale, 'edu.common.error'));
    } finally {
      setLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    void load();
  }, [load]);

  const openCreate = async () => {
    setForm({ period: 'S1' });
    setModalOpen(true);
    try {
      const res = await apiFetch('/edu-manager/students?per_page=200');
      const json = (await res.json()) as { data?: { id: number; display_name: string }[] };
      setStudents(Array.isArray(json.data) ? json.data : []);
    } catch {
      setStudents([]);
    }
  };

  const generate = async () => {
    setSaving(true);
    try {
      await apiFetch('/edu-manager/report-cards/generate', { method: 'POST', body: JSON.stringify(form) });
      setModalOpen(false);
      await load();
    } catch {
      setError(t(locale, 'edu.common.error'));
    } finally {
      setSaving(false);
    }
  };

  const act = async (card: ReportCard, action: 'validate' | 'publish') => {
    setActingId(card.id);
    try {
      await apiFetch(`/edu-manager/report-cards/${card.id}/${action}`, { method: 'POST' });
      await load();
    } catch {
      setError(t(locale, 'edu.common.error'));
    } finally {
      setActingId(null);
    }
  };

  const columns: Column<ReportCard>[] = [
    {
      key: 'student',
      header: t(locale, 'edu.reportCards.student'),
      render: (row) => (
        <span className="font-bold text-slate-900">
          {row.student?.display_name ?? `#${row.student_id}`}
        </span>
      ),
    },
    { key: 'period', header: t(locale, 'edu.reportCards.period'), render: (row) => <span className="text-slate-500">{row.period}</span> },
    {
      key: 'average',
      header: t(locale, 'edu.reportCards.average'),
      render: (row) => <span className="font-mono text-sm font-bold text-slate-700">{row.average != null ? Number(row.average).toFixed(2) : '—'}</span>,
    },
    { key: 'status', header: t(locale, 'edu.reportCards.status'), render: (row) => <StatusBadge status={row.status} /> },
    {
      key: 'actions',
      header: t(locale, 'edu.common.actions'),
      render: (row) => (
        <div className="flex items-center gap-1.5">
          {row.status === 'generated' ? (
            <Button variant="ghost" onClick={() => void act(row, 'validate')} disabled={actingId === row.id}>
              <CheckCircle2 className="h-4 w-4" aria-hidden="true" />
              {t(locale, 'edu.reportCards.validate')}
            </Button>
          ) : null}
          {row.status === 'validated' ? (
            <Button onClick={() => void act(row, 'publish')} disabled={actingId === row.id}>
              <Send className="h-4 w-4" aria-hidden="true" />
              {t(locale, 'edu.reportCards.publish')}
            </Button>
          ) : null}
        </div>
      ),
    },
  ];

  return (
    <ModulePageShell
      title={t(locale, 'edu.reportCards.title')}
      subtitle={t(locale, 'edu.reportCards.subtitle')}
      accentClassName="border-cyan-500/10 bg-cyan-500/5"
    >
      <div className="flex items-center justify-between">
        <div />
        <Button onClick={() => void openCreate()}>
          <Sparkles className="h-4 w-4" aria-hidden="true" />
          {t(locale, 'edu.reportCards.generate')}
        </Button>
      </div>

      <DataTable columns={columns} rows={rows} rowKey={(row) => row.id} emptyLabel={t(locale, 'edu.reportCards.empty')} loading={loading} error={error} onRetry={() => void load()} />

      <Modal open={modalOpen} title={t(locale, 'edu.reportCards.generate')} onClose={() => setModalOpen(false)}>
        <div className="space-y-4">
          <Field label={t(locale, 'edu.reportCards.student')}>
            <SelectInput value={form.student_id ?? ''} onChange={(e) => setForm((p) => ({ ...p, student_id: e.target.value }))} required>
              <option value="">—</option>
              {students.map((student) => (
                <option key={student.id} value={String(student.id)}>
                  {student.display_name}
                </option>
              ))}
            </SelectInput>
          </Field>
          <Field label={t(locale, 'edu.reportCards.period')}>
            <SelectInput value={form.period ?? 'S1'} onChange={(e) => setForm((p) => ({ ...p, period: e.target.value }))}>
              <option value="S1">S1</option>
              <option value="S2">S2</option>
              <option value="S3">S3</option>
            </SelectInput>
          </Field>
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="ghost" onClick={() => setModalOpen(false)}>{t(locale, 'edu.common.cancel')}</Button>
            <Button onClick={() => void generate()} disabled={saving}>{t(locale, 'edu.reportCards.generate')}</Button>
          </div>
        </div>
      </Modal>
    </ModulePageShell>
  );
}
