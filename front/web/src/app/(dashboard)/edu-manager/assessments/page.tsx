'use client';

import { useCallback, useEffect, useState } from 'react';
import { CheckCircle2, ClipboardEdit, Plus } from 'lucide-react';
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

/**
 * EDU-011 (#5827) — Évaluations : création, saisie de notes (barème
 * serveur [0, max_score]), publication idempotente. Le rôle de la classe
 * (roster) est dérivé de l'historique de présence de la classe.
 */

type Assessment = {
  id: number;
  class_id: number;
  subject_id: number;
  academic_year_id: number;
  title: string;
  type: string;
  coefficient?: number | null;
  max_score: number;
  assessment_date?: string | null;
  published_at?: string | null;
  class?: { id: number; code: string; name: string } | null;
  subject?: { id: number; code: string; name: string } | null;
};

type RosterStudent = { id: number; student_number: string; display_name: string };

export default function AssessmentsPage() {
  const locale = getPreferredLocale();
  const [rows, setRows] = useState<Assessment[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [classes, setClasses] = useState<{ id: number; name: string }[]>([]);
  const [subjects, setSubjects] = useState<{ id: number; name: string }[]>([]);
  const [createOpen, setCreateOpen] = useState(false);
  const [form, setForm] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);
  // Saisie de notes
  const [grading, setGrading] = useState<Assessment | null>(null);
  const [roster, setRoster] = useState<RosterStudent[]>([]);
  const [scores, setScores] = useState<Record<string, string>>({});
  const [gradesSaving, setGradesSaving] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiFetch('/edu-manager/assessments?per_page=200', { _cacheBust: true });
      const json = (await res.json()) as { data?: Assessment[] };
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
    setForm({ type: 'exam', coefficient: '1', max_score: '20' });
    setCreateOpen(true);
    try {
      const [classesRes, subjectsRes] = await Promise.all([
        apiFetch('/edu-manager/classes?per_page=200').then((r) => r.json()),
        apiFetch('/edu-manager/subjects?per_page=200').then((r) => r.json()),
      ]);
      setClasses((classesRes.data ?? []).map((c: { id: number; name: string }) => ({ id: c.id, name: c.name })));
      setSubjects((subjectsRes.data ?? []).map((s: { id: number; name: string }) => ({ id: s.id, name: s.name })));
    } catch {
      setClasses([]);
      setSubjects([]);
    }
  };

  const create = async () => {
    setSaving(true);
    try {
      await apiFetch('/edu-manager/assessments', { method: 'POST', body: JSON.stringify(form) });
      setCreateOpen(false);
      await load();
    } catch {
      setError(t(locale, 'edu.common.error'));
    } finally {
      setSaving(false);
    }
  };

  const openGrading = async (assessment: Assessment) => {
    setGrading(assessment);
    setScores({});
    setRoster([]);
    try {
      const res = await apiFetch(`/edu-manager/classes/${assessment.class_id}/attendances?per_page=200`);
      const json = (await res.json()) as { data?: { student?: RosterStudent }[] };
      const seen = new Map<number, RosterStudent>();
      for (const item of json.data ?? []) {
        if (item.student && !seen.has(item.student.id)) {
          seen.set(item.student.id, item.student);
        }
      }
      setRoster([...seen.values()]);
    } catch {
      setRoster([]);
    }
  };

  const saveGrades = async () => {
    if (!grading) {
      return;
    }
    setGradesSaving(true);
    try {
      for (const [studentId, score] of Object.entries(scores)) {
        if (score.trim() === '') {
          continue;
        }
        await apiFetch(`/edu-manager/assessments/${grading.id}/grades`, {
          method: 'POST',
          body: JSON.stringify({ student_id: Number(studentId), score: Number(score) }),
        });
      }
      setGrading(null);
      await load();
    } catch {
      setError(t(locale, 'edu.common.error'));
    } finally {
      setGradesSaving(false);
    }
  };

  const publish = async (assessment: Assessment) => {
    try {
      await apiFetch(`/edu-manager/assessments/${assessment.id}/publish`, { method: 'POST' });
      await load();
    } catch {
      setError(t(locale, 'edu.common.error'));
    }
  };

  const columns: Column<Assessment>[] = [
    { key: 'title', header: t(locale, 'edu.assessments.title'), render: (row) => <span className="font-bold text-slate-900">{row.title}</span> },
    {
      key: 'class',
      header: t(locale, 'edu.assessments.class'),
      render: (row) => <span className="text-slate-600">{row.class?.name ?? `#${row.class_id}`}</span>,
    },
    {
      key: 'subject',
      header: t(locale, 'edu.assessments.subject'),
      render: (row) => <span className="text-slate-600">{row.subject?.name ?? `#${row.subject_id}`}</span>,
    },
    { key: 'max_score', header: t(locale, 'edu.assessments.maxScore'), render: (row) => <span className="font-mono text-sm font-bold text-slate-700">{row.max_score}</span> },
    { key: 'status', header: t(locale, 'edu.assessments.status'), render: (row) => (row.published_at ? <StatusBadge status="published" /> : <StatusBadge status="draft" />) },
    {
      key: 'actions',
      header: t(locale, 'edu.common.actions'),
      render: (row) => (
        <div className="flex items-center gap-1.5">
          {!row.published_at ? (
            <>
              <Button variant="ghost" onClick={() => void openGrading(row)}>
                <ClipboardEdit className="h-4 w-4" aria-hidden="true" />
                {t(locale, 'edu.assessments.enterGrades')}
              </Button>
              <Button variant="ghost" onClick={() => void publish(row)}>
                <CheckCircle2 className="h-4 w-4" aria-hidden="true" />
                {t(locale, 'edu.assessments.publish')}
              </Button>
            </>
          ) : (
            <span className="text-xs font-bold uppercase tracking-widest text-emerald-600">{t(locale, 'edu.assessments.published')}</span>
          )}
        </div>
      ),
    },
  ];

  return (
    <ModulePageShell
      title={t(locale, 'edu.assessments.title')}
      subtitle={t(locale, 'edu.assessments.subtitle')}
      accentClassName="border-cyan-500/10 bg-cyan-500/5"
    >
      <div className="flex items-center justify-between">
        <div />
        <Button onClick={() => void openCreate()}>
          <Plus className="h-4 w-4" aria-hidden="true" />
          {t(locale, 'edu.assessments.create')}
        </Button>
      </div>

      <DataTable columns={columns} rows={rows} rowKey={(row) => row.id} emptyLabel={t(locale, 'edu.assessments.empty')} loading={loading} error={error} onRetry={() => void load()} />

      {/* Création */}
      <Modal open={createOpen} title={t(locale, 'edu.assessments.create')} onClose={() => setCreateOpen(false)}>
        <div className="space-y-4">
          <Field label={t(locale, 'edu.assessments.title')}>
            <TextInput value={form.title ?? ''} onChange={(e) => setForm((p) => ({ ...p, title: e.target.value }))} required />
          </Field>
          <div className="grid grid-cols-2 gap-3">
            <Field label={t(locale, 'edu.assessments.class')}>
              <SelectInput value={form.class_id ?? ''} onChange={(e) => setForm((p) => ({ ...p, class_id: e.target.value }))} required>
                <option value="">—</option>
                {classes.map((c) => <option key={c.id} value={String(c.id)}>{c.name}</option>)}
              </SelectInput>
            </Field>
            <Field label={t(locale, 'edu.assessments.subject')}>
              <SelectInput value={form.subject_id ?? ''} onChange={(e) => setForm((p) => ({ ...p, subject_id: e.target.value }))} required>
                <option value="">—</option>
                {subjects.map((s) => <option key={s.id} value={String(s.id)}>{s.name}</option>)}
              </SelectInput>
            </Field>
          </div>
          <div className="grid grid-cols-3 gap-3">
            <Field label={t(locale, 'edu.assessments.type')}>
              <SelectInput value={form.type ?? 'exam'} onChange={(e) => setForm((p) => ({ ...p, type: e.target.value }))}>
                <option value="exam">exam</option>
                <option value="quiz">quiz</option>
                <option value="homework">homework</option>
              </SelectInput>
            </Field>
            <Field label={t(locale, 'edu.assessments.coefficient')}>
              <TextInput type="number" min={0} step="0.1" value={form.coefficient ?? '1'} onChange={(e) => setForm((p) => ({ ...p, coefficient: e.target.value }))} />
            </Field>
            <Field label={t(locale, 'edu.assessments.maxScore')}>
              <TextInput type="number" min={1} value={form.max_score ?? '20'} onChange={(e) => setForm((p) => ({ ...p, max_score: e.target.value }))} />
            </Field>
          </div>
          <Field label={t(locale, 'edu.assessments.date')}>
            <TextInput type="date" value={form.assessment_date ?? ''} onChange={(e) => setForm((p) => ({ ...p, assessment_date: e.target.value }))} />
          </Field>
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="ghost" onClick={() => setCreateOpen(false)}>{t(locale, 'edu.common.cancel')}</Button>
            <Button onClick={() => void create()} disabled={saving}>{t(locale, 'edu.common.save')}</Button>
          </div>
        </div>
      </Modal>

      {/* Saisie de notes */}
      <Modal open={grading !== null} title={`${grading?.title ?? ''} — ${t(locale, 'edu.assessments.enterGrades')}`} onClose={() => setGrading(null)}>
        {roster.length === 0 ? (
          <p className="rounded-xl bg-slate-50 px-3 py-4 text-sm text-slate-500">{t(locale, 'edu.assessments.noStudents')}</p>
        ) : (
          <div className="space-y-3">
            {roster.map((student) => (
              <div key={student.id} className="flex items-center justify-between gap-3">
                <div>
                  <p className="text-sm font-bold text-slate-800">{student.display_name}</p>
                  <p className="font-mono text-xs text-slate-400">{student.student_number}</p>
                </div>
                <div className="flex items-center gap-2">
                  <span className="text-xs text-slate-400">/ {grading?.max_score}</span>
                  <TextInput
                    type="number"
                    min={0}
                    max={grading?.max_score}
                    step="0.25"
                    value={scores[String(student.id)] ?? ''}
                    placeholder="—"
                    className="w-24 text-right"
                    onChange={(e) => setScores((p) => ({ ...p, [String(student.id)]: e.target.value }))}
                  />
                </div>
              </div>
            ))}
            <div className="flex justify-end gap-2 pt-2">
              <Button variant="ghost" onClick={() => setGrading(null)}>{t(locale, 'edu.common.cancel')}</Button>
              <Button onClick={() => void saveGrades()} disabled={gradesSaving}>{t(locale, 'edu.assessments.saveGrades')}</Button>
            </div>
          </div>
        )}
      </Modal>
    </ModulePageShell>
  );
}
