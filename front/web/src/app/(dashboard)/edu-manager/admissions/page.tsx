'use client';

import { useCallback, useEffect, useState } from 'react';
import { UserCheck } from 'lucide-react';
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
  SelectInput,
  StatusBadge,
  TextInput,
  type Column,
} from '../_components/edu-ui';

/**
 * EDU-011 (#5827) — Admissions : pipeline RGPD + conversion élève
 * idempotente (POST /admissions/{admission}/convert, EduAdmissionService).
 */

type Admission = {
  id: number;
  admission_number: string;
  applicant_first_name: string;
  applicant_last_name: string;
  applicant_email?: string | null;
  status: string;
  applied_at?: string | null;
  converted_at?: string | null;
  student?: { id: number; student_number: string; display_name: string } | null;
};

export default function AdmissionsPage() {
  const locale = getPreferredLocale();
  const [rows, setRows] = useState<Admission[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [modalOpen, setModalOpen] = useState(false);
  const [form, setForm] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);
  const [convertingId, setConvertingId] = useState<number | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiFetch('/edu-manager/admissions?per_page=200', { _cacheBust: true });
      const json = (await res.json()) as { data?: Admission[] };
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

  const convert = async (admission: Admission) => {
    if (!window.confirm(t(locale, 'edu.admissions.convertConfirm'))) {
      return;
    }
    setConvertingId(admission.id);
    try {
      await apiFetch(`/edu-manager/admissions/${admission.id}/convert`, { method: 'POST' });
      await load();
    } catch {
      setError(t(locale, 'edu.common.error'));
    } finally {
      setConvertingId(null);
    }
  };

  const save = async () => {
    setSaving(true);
    try {
      await apiFetch('/edu-manager/admissions', { method: 'POST', body: JSON.stringify(form) });
      setModalOpen(false);
      await load();
    } catch {
      setError(t(locale, 'edu.common.error'));
    } finally {
      setSaving(false);
    }
  };

  const columns: Column<Admission>[] = [
    {
      key: 'number',
      header: 'N°',
      render: (row) => <span className="font-mono text-xs font-bold text-slate-500">{row.admission_number}</span>,
    },
    {
      key: 'applicant',
      header: 'Candidat',
      render: (row) => (
        <span className="font-bold text-slate-900">
          {row.applicant_first_name} {row.applicant_last_name}
        </span>
      ),
    },
    { key: 'status', header: 'Statut', render: (row) => <StatusBadge status={row.status} /> },
    {
      key: 'student',
      header: 'Élève',
      render: (row) =>
        row.student ? (
          <span className="text-sm text-slate-600">
            {row.student.display_name} <span className="font-mono text-xs text-slate-400">({row.student.student_number})</span>
          </span>
        ) : (
          <span className="text-slate-400">—</span>
        ),
    },
    {
      key: 'actions',
      header: t(locale, 'edu.common.actions'),
      render: (row) =>
        row.status !== 'converted' ? (
          <Button variant="ghost" onClick={() => void convert(row)} disabled={convertingId === row.id}>
            <UserCheck className="h-4 w-4" aria-hidden="true" />
            {t(locale, 'edu.admissions.convert')}
          </Button>
        ) : (
          <span className="text-xs font-bold uppercase tracking-widest text-emerald-600">{t(locale, 'edu.admissions.converted')}</span>
        ),
    },
  ];

  return (
    <ModulePageShell
      title={t(locale, 'edu.admissions.title')}
      subtitle={t(locale, 'edu.admissions.subtitle')}
      accentClassName="border-cyan-500/10 bg-cyan-500/5"
    >
      <div className="flex items-center justify-between">
        <div />
        <Button onClick={() => { setForm({}); setModalOpen(true); }}>
          + {t(locale, 'edu.admissions.create')}
        </Button>
      </div>

      <DataTable columns={columns} rows={rows} rowKey={(row) => row.id} emptyLabel={t(locale, 'edu.admissions.empty')} loading={loading} error={error} onRetry={() => void load()} />

      <Modal open={modalOpen} title={t(locale, 'edu.admissions.create')} onClose={() => setModalOpen(false)}>
        <div className="space-y-4">
          <Field label={t(locale, 'edu.admissions.firstName')}>
            <TextInput value={form.applicant_first_name ?? ''} onChange={(e) => setForm((p) => ({ ...p, applicant_first_name: e.target.value }))} required />
          </Field>
          <Field label={t(locale, 'edu.admissions.lastName')}>
            <TextInput value={form.applicant_last_name ?? ''} onChange={(e) => setForm((p) => ({ ...p, applicant_last_name: e.target.value }))} required />
          </Field>
          <Field label="Email">
            <TextInput type="email" value={form.applicant_email ?? ''} onChange={(e) => setForm((p) => ({ ...p, applicant_email: e.target.value }))} />
          </Field>
          <Field label={t(locale, 'edu.campuses.status')}>
            <SelectInput value={form.status ?? 'pending'} onChange={(e) => setForm((p) => ({ ...p, status: e.target.value }))}>
              <option value="pending">pending</option>
              <option value="approved">approved</option>
              <option value="rejected">rejected</option>
            </SelectInput>
          </Field>
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="ghost" onClick={() => setModalOpen(false)}>{t(locale, 'edu.common.cancel')}</Button>
            <Button onClick={() => void save()} disabled={saving}>{t(locale, 'edu.common.save')}</Button>
          </div>
        </div>
      </Modal>
    </ModulePageShell>
  );
}
