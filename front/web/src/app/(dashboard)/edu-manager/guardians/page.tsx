'use client';

/**
 * Responsables légaux (parents / tuteurs) — /edu-manager/guardians (#9789).
 *
 * L'API existait (`GET/POST /edu-manager/guardians`,
 * `POST /edu-manager/students/{student}/guardians`) mais aucune page ne la
 * servait : un directeur ne pouvait ni créer un responsable légal ni le
 * rattacher à un élève depuis l'interface — ce qui rendait le portail parents
 * inatteignable en pratique.
 *
 * Cette page couvre les deux gestes :
 *   1. le référentiel des responsables (liste + création) ;
 *   2. le RATTACHEMENT d'un responsable à un élève, avec les deux drapeaux de
 *      portée (`can_view_grades`, `can_receive_notifications`) — par défaut un
 *      responsable ne voit PAS les notes (contrat serveur).
 */

import { useCallback, useEffect, useState } from 'react';
import { Link2, Plus } from 'lucide-react';
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
  TextInput,
  type Column,
} from '../_components/edu-ui';

type Guardian = Record<string, unknown>;
type Student = Record<string, unknown>;

const RELATIONSHIPS = ['parent', 'mother', 'father', 'legal_guardian', 'other'];

export default function GuardiansPage() {
  const locale = getPreferredLocale();

  const [rows, setRows] = useState<Guardian[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [createOpen, setCreateOpen] = useState(false);
  const [form, setForm] = useState<Record<string, string>>({
    first_name: '',
    last_name: '',
    contact_reference: '',
    relationship_code: 'parent',
  });
  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);

  const [linkOpen, setLinkOpen] = useState(false);
  const [linkGuardian, setLinkGuardian] = useState<Guardian | null>(null);
  const [students, setStudents] = useState<Student[]>([]);
  const [linkForm, setLinkForm] = useState<Record<string, string>>({
    student_id: '',
    relationship_code: 'parent',
    can_view_grades: 'false',
    can_receive_notifications: 'true',
  });
  const [linkError, setLinkError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiFetch('/edu-manager/guardians?per_page=200', { _cacheBust: true });
      const json = (await res.json()) as { data?: Guardian[] };
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

  const create = async () => {
    setSaving(true);
    setSaveError(null);
    try {
      const res = await apiFetch('/edu-manager/guardians', {
        method: 'POST',
        body: JSON.stringify({
          first_name: form.first_name,
          last_name: form.last_name,
          contact_reference: form.contact_reference || null,
          relationship_code: form.relationship_code,
        }),
      });

      if (!res.ok) {
        let message = t(locale, 'edu.common.error');
        try {
          const payload = (await res.json()) as { message?: string };
          if (payload.message) message = payload.message;
        } catch {
          // garde le message par défaut
        }
        setSaveError(message);
        return;
      }

      setCreateOpen(false);
      setForm({ first_name: '', last_name: '', contact_reference: '', relationship_code: 'parent' });
      await load();
    } catch {
      setSaveError(t(locale, 'edu.common.error'));
    } finally {
      setSaving(false);
    }
  };

  const openLink = async (guardian: Guardian) => {
    setLinkGuardian(guardian);
    setLinkError(null);
    setLinkForm({
      student_id: '',
      relationship_code: 'parent',
      can_view_grades: 'false',
      can_receive_notifications: 'true',
    });
    setLinkOpen(true);

    try {
      const res = await apiFetch('/edu-manager/students?per_page=200', { _cacheBust: true });
      const json = (await res.json()) as { data?: Student[] };
      setStudents(Array.isArray(json.data) ? json.data : []);
    } catch {
      setStudents([]);
    }
  };

  const link = async () => {
    if (!linkGuardian) return;
    setSaving(true);
    setLinkError(null);
    try {
      const res = await apiFetch(
        `/edu-manager/students/${String(linkForm.student_id)}/guardians`,
        {
          method: 'POST',
          body: JSON.stringify({
            guardian_id: Number(linkGuardian.id),
            relationship_code: linkForm.relationship_code,
            can_view_grades: linkForm.can_view_grades === 'true',
            can_receive_notifications: linkForm.can_receive_notifications === 'true',
          }),
        },
      );

      if (!res.ok) {
        let message = t(locale, 'edu.common.error');
        try {
          const payload = (await res.json()) as { message?: string };
          if (payload.message) message = payload.message;
        } catch {
          // garde le message par défaut
        }
        setLinkError(message);
        return;
      }

      setLinkOpen(false);
      await load();
    } catch {
      setLinkError(t(locale, 'edu.common.error'));
    } finally {
      setSaving(false);
    }
  };

  const columns: Column<Guardian>[] = [
    {
      key: 'name',
      header: t(locale, 'edu.guardians.name'),
      render: (row) => (
        <span className="font-bold text-slate-900">
          {String(row.first_name ?? '')} {String(row.last_name ?? '')}
        </span>
      ),
    },
    {
      key: 'relationship_code',
      header: t(locale, 'edu.guardians.relationship'),
      render: (row) => <span className="text-slate-500">{String(row.relationship_code ?? '—')}</span>,
    },
    {
      key: 'contact_reference',
      header: t(locale, 'edu.guardians.contact'),
      render: (row) => (
        <span className="text-slate-500">{row.contact_reference ? '••••' : '—'}</span>
      ),
    },
    {
      key: '__actions',
      header: t(locale, 'edu.common.actions'),
      render: (row) => (
        <button
          type="button"
          title={t(locale, 'edu.guardians.link')}
          onClick={() => void openLink(row)}
          className="inline-flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-xs font-bold text-emerald-700 hover:bg-emerald-50"
        >
          <Link2 className="h-4 w-4" aria-hidden="true" />
          {t(locale, 'edu.guardians.link')}
        </button>
      ),
    },
  ];

  return (
    <ModulePageShell
      title={t(locale, 'edu.guardians.title')}
      subtitle={t(locale, 'edu.guardians.subtitle')}
      accentClassName="border-cyan-500/10 bg-cyan-500/5"
    >
      <div className="flex items-center justify-between">
        <div />
        <Button onClick={() => setCreateOpen(true)}>
          <Plus className="h-4 w-4" aria-hidden="true" />
          {t(locale, 'edu.guardians.create')}
        </Button>
      </div>

      <DataTable
        columns={columns}
        rows={rows}
        rowKey={(row) => String(row.id)}
        emptyLabel={t(locale, 'edu.guardians.empty')}
        loading={loading}
        error={error}
        onRetry={() => void load()}
      />

      <Modal
        open={createOpen}
        title={t(locale, 'edu.guardians.create')}
        onClose={() => setCreateOpen(false)}
      >
        <div className="space-y-4">
          <Field label={t(locale, 'edu.guardians.firstName')}>
            <TextInput
              value={form.first_name}
              required
              onChange={(event) => setForm((prev) => ({ ...prev, first_name: event.target.value }))}
            />
          </Field>
          <Field label={t(locale, 'edu.guardians.lastName')}>
            <TextInput
              value={form.last_name}
              required
              onChange={(event) => setForm((prev) => ({ ...prev, last_name: event.target.value }))}
            />
          </Field>
          <Field label={t(locale, 'edu.guardians.contact')} hint={t(locale, 'edu.guardians.contactHint')}>
            <TextInput
              value={form.contact_reference}
              onChange={(event) =>
                setForm((prev) => ({ ...prev, contact_reference: event.target.value }))
              }
            />
          </Field>
          <Field label={t(locale, 'edu.guardians.relationship')}>
            <SelectInput
              value={form.relationship_code}
              onChange={(event) =>
                setForm((prev) => ({ ...prev, relationship_code: event.target.value }))
              }
            >
              {RELATIONSHIPS.map((relationship) => (
                <option key={relationship} value={relationship}>
                  {relationship}
                </option>
              ))}
            </SelectInput>
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
            <Button onClick={() => void create()} disabled={saving}>
              {t(locale, 'edu.common.save')}
            </Button>
          </div>
        </div>
      </Modal>

      <Modal
        open={linkOpen}
        title={t(locale, 'edu.guardians.linkTitle')}
        onClose={() => setLinkOpen(false)}
      >
        <div className="space-y-4">
          <Card>
            <SectionTitle
              title={`${String(linkGuardian?.first_name ?? '')} ${String(linkGuardian?.last_name ?? '')}`}
            />
          </Card>

          <Field label={t(locale, 'edu.guardians.student')}>
            <SelectInput
              value={linkForm.student_id}
              onChange={(event) => setLinkForm((prev) => ({ ...prev, student_id: event.target.value }))}
            >
              <option value="">—</option>
              {students.map((student) => (
                <option key={String(student.id)} value={String(student.id)}>
                  {String(student.display_name ?? student.student_number ?? student.id)}
                </option>
              ))}
            </SelectInput>
          </Field>

          <Field label={t(locale, 'edu.guardians.relationship')}>
            <SelectInput
              value={linkForm.relationship_code}
              onChange={(event) =>
                setLinkForm((prev) => ({ ...prev, relationship_code: event.target.value }))
              }
            >
              {RELATIONSHIPS.map((relationship) => (
                <option key={relationship} value={relationship}>
                  {relationship}
                </option>
              ))}
            </SelectInput>
          </Field>

          <Field label={t(locale, 'edu.guardians.canViewGrades')}>
            <SelectInput
              value={linkForm.can_view_grades}
              onChange={(event) =>
                setLinkForm((prev) => ({ ...prev, can_view_grades: event.target.value }))
              }
            >
              <option value="false">non</option>
              <option value="true">oui</option>
            </SelectInput>
          </Field>

          <Field label={t(locale, 'edu.guardians.canReceiveNotifications')}>
            <SelectInput
              value={linkForm.can_receive_notifications}
              onChange={(event) =>
                setLinkForm((prev) => ({ ...prev, can_receive_notifications: event.target.value }))
              }
            >
              <option value="true">oui</option>
              <option value="false">non</option>
            </SelectInput>
          </Field>

          {linkError ? (
            <p className="rounded-xl bg-rose-50 px-3 py-2 text-sm font-medium text-rose-600">
              {linkError}
            </p>
          ) : null}

          <div className="flex justify-end gap-2 pt-2">
            <Button variant="ghost" onClick={() => setLinkOpen(false)}>
              {t(locale, 'edu.common.cancel')}
            </Button>
            <Button onClick={() => void link()} disabled={saving || linkForm.student_id === ''}>
              {t(locale, 'edu.guardians.link')}
            </Button>
          </div>
        </div>
      </Modal>
    </ModulePageShell>
  );
}
