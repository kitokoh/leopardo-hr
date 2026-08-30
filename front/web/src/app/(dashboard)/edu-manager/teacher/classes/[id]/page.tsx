'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { useParams } from 'next/navigation';
import { CheckCircle2, Save } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';
import { ModulePageShell } from '@/components/module-page-shell';
import { Button, Card, ErrorState, SectionTitle, SelectInput, Spinner, StatusBadge, TextInput } from '../../../_components/edu-ui';

/**
 * EDU-012 (#5828) — Détail classe enseignant : saisie de présence
 * (idempotente, POST /classes/{class}/attendances), consultation et saisie
 * de notes (barème serveur), soumission pour validation (publication
 * verrouillante + historique des corrections conservé).
 */

type RosterStudent = { id: number; student_number: string; display_name: string };
type AttendanceRow = { id: number; student?: RosterStudent | null; status: string; attendance_date: string };
type AssessmentRow = {
  id: number;
  title: string;
  type: string;
  max_score: number;
  assessment_date?: string | null;
  published_at?: string | null;
  subject?: { id: number; code: string; name: string } | null;
};

const ATTENDANCE_STATUSES = ['present', 'absent', 'late', 'excused'];

export default function TeacherClassPage() {
  const params = useParams<{ id: string }>();
  const classId = Number(params.id);
  const locale = getPreferredLocale();

  const [className, setClassName] = useState<string | null>(null);
  const [roster, setRoster] = useState<RosterStudent[]>([]);
  const [attendanceDate, setAttendanceDate] = useState(() => new Date().toISOString().slice(0, 10));
  const [attendance, setAttendance] = useState<Record<string, string>>({});
  const [assessments, setAssessments] = useState<AssessmentRow[]>([]);
  const [scores, setScores] = useState<Record<string, string>>({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [savingAttendance, setSavingAttendance] = useState(false);
  const [savingGrades, setSavingGrades] = useState(false);
  const [notice, setNotice] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [classRes, attendanceRes, assessmentsRes] = await Promise.all([
        apiFetch(`/edu-manager/classes/${classId}`).then((r) => r.json()),
        apiFetch(`/edu-manager/classes/${classId}/attendances?per_page=200`).then((r) => r.json()),
        apiFetch(`/edu-manager/assessments?class_id=${classId}&per_page=100`).then((r) => r.json()),
      ]);

      const classData = classRes?.data as { name?: string } | undefined;
      setClassName(classData?.name ?? `#${classId}`);

      // Roster dérivé de l'historique de présence (v0 — pas de table d'inscription dédiée).
      const seen = new Map<number, RosterStudent>();
      for (const item of (attendanceRes?.data ?? []) as AttendanceRow[]) {
        if (item.student && !seen.has(item.student.id)) {
          seen.set(item.student.id, item.student);
        }
      }
      setRoster([...seen.values()]);
      setAssessments(Array.isArray(assessmentsRes?.data) ? assessmentsRes.data : []);
    } catch {
      setError(t(locale, 'edu.common.error'));
    } finally {
      setLoading(false);
    }
  }, [classId, locale]);

  useEffect(() => {
    void load();
  }, [load]);

  const saveAttendance = async () => {
    setSavingAttendance(true);
    setNotice(null);
    try {
      for (const [studentId, status] of Object.entries(attendance)) {
        if (!status) {
          continue;
        }
        await apiFetch(`/edu-manager/classes/${classId}/attendances`, {
          method: 'POST',
          body: JSON.stringify({ student_id: Number(studentId), attendance_date: attendanceDate, status }),
        });
      }
      setNotice(t(locale, 'edu.teacher.attendanceSaved'));
      setAttendance({});
    } catch {
      setError(t(locale, 'edu.common.error'));
    } finally {
      setSavingAttendance(false);
    }
  };

  const saveGrades = async (assessment: AssessmentRow) => {
    setSavingGrades(true);
    setNotice(null);
    try {
      for (const [studentId, score] of Object.entries(scores)) {
        if (score.trim() === '') {
          continue;
        }
        const res = await apiFetch(`/edu-manager/assessments/${assessment.id}/grades`, {
          method: 'POST',
          body: JSON.stringify({ student_id: Number(studentId), score: Number(score) }),
        });
        const grade = (await res.json()) as { data?: { id?: number } };
        // Soumission pour validation : publication idempotente de la note.
        if (grade.data?.id) {
          await apiFetch(`/edu-manager/grades/${grade.data.id}/publish`, { method: 'POST' });
        }
      }
      setNotice(t(locale, 'edu.teacher.attendanceSaved'));
      setScores({});
    } catch {
      setError(t(locale, 'edu.common.error'));
    } finally {
      setSavingGrades(false);
    }
  };

  const attendanceColumns = useMemo(() => roster, [roster]);

  if (loading) {
    return <ModulePageShell title={t(locale, 'edu.teacher.title')} subtitle="" accentClassName="border-emerald-500/10 bg-emerald-500/5"><Spinner /></ModulePageShell>;
  }

  return (
    <ModulePageShell
      title={className ?? t(locale, 'edu.teacher.title')}
      subtitle={`${t(locale, 'edu.teacher.subtitle')} — ${roster.length} ${t(locale, 'edu.teacher.students').toLowerCase()}`}
      accentClassName="border-emerald-500/10 bg-emerald-500/5"
    >
      {error ? <ErrorState message={error} onRetry={() => void load()} /> : null}
      {notice ? <p className="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700">{notice}</p> : null}

      <Card>
        <SectionTitle title={t(locale, 'edu.teacher.attendance')} />
        <div className="mb-4 flex flex-wrap items-center gap-3">
          <TextInput type="date" value={attendanceDate} onChange={(e) => setAttendanceDate(e.target.value)} className="w-auto" />
          <Button onClick={() => void saveAttendance()} disabled={savingAttendance}>
            <Save className="h-4 w-4" aria-hidden="true" />
            {t(locale, 'edu.teacher.saveAttendance')}
          </Button>
        </div>
        {attendanceColumns.length === 0 ? (
          <p className="rounded-xl bg-slate-50 px-3 py-4 text-sm text-slate-500">{t(locale, 'edu.assessments.noStudents')}</p>
        ) : (
          <div className="space-y-2">
            {attendanceColumns.map((student) => (
              <div key={student.id} className="flex items-center justify-between gap-3 rounded-xl border border-slate-100 px-3 py-2">
                <div>
                  <p className="text-sm font-bold text-slate-800">{student.display_name}</p>
                  <p className="font-mono text-xs text-slate-400">{student.student_number}</p>
                </div>
                <SelectInput
                  value={attendance[String(student.id)] ?? ''}
                  onChange={(e) => setAttendance((p) => ({ ...p, [String(student.id)]: e.target.value }))}
                  className="w-36"
                >
                  <option value="">—</option>
                  {ATTENDANCE_STATUSES.map((status) => (
                    <option key={status} value={status}>{t(locale, `edu.teacher.${status}`)}</option>
                  ))}
                </SelectInput>
              </div>
            ))}
          </div>
        )}
      </Card>

      <Card>
        <SectionTitle title={t(locale, 'edu.teacher.assessments')} subtitle={t(locale, 'edu.teacher.submitHint')} />
        {assessments.length === 0 ? (
          <p className="rounded-xl bg-slate-50 px-3 py-4 text-sm text-slate-500">{t(locale, 'edu.teacher.noAssessments')}</p>
        ) : (
          <div className="space-y-4">
            {assessments.map((assessment) => (
              <div key={assessment.id} className="rounded-2xl border border-slate-100 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <div>
                    <p className="font-bold text-slate-900">{assessment.title}</p>
                    <p className="text-xs font-medium text-slate-400">
                      {assessment.subject?.name ?? `#${assessment.id}`} · {t(locale, 'edu.assessments.maxScore')} {assessment.max_score} ·{' '}
                      {assessment.assessment_date ?? '—'}
                    </p>
                  </div>
                  {assessment.published_at ? <StatusBadge status="published" /> : <StatusBadge status="draft" />}
                </div>

                {!assessment.published_at && roster.length > 0 ? (
                  <div className="mt-3 space-y-2">
                    {roster.map((student) => (
                      <div key={student.id} className="flex items-center justify-between gap-3">
                        <span className="text-sm text-slate-700">{student.display_name}</span>
                        <div className="flex items-center gap-2">
                          <span className="text-xs text-slate-400">/ {assessment.max_score}</span>
                          <TextInput
                            type="number"
                            min={0}
                            max={assessment.max_score}
                            step="0.25"
                            value={scores[`${assessment.id}:${student.id}`] ?? ''}
                            placeholder="—"
                            className="w-24 text-right"
                            onChange={(e) => setScores((p) => ({ ...p, [`${assessment.id}:${student.id}`]: e.target.value }))}
                          />
                        </div>
                      </div>
                    ))}
                    <div className="flex justify-end pt-1">
                      <Button onClick={() => void saveGrades(assessment)} disabled={savingGrades}>
                        <CheckCircle2 className="h-4 w-4" aria-hidden="true" />
                        {t(locale, 'edu.teacher.submit')}
                      </Button>
                    </div>
                  </div>
                ) : null}
              </div>
            ))}
          </div>
        )}
      </Card>
    </ModulePageShell>
  );
}
