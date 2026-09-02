'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { BookOpen, CalendarCheck, GraduationCap } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';
import { ModulePageShell } from '@/components/module-page-shell';
import { Card, ErrorState, Spinner, StatusBadge } from '../_components/edu-ui';

/**
 * EDU-012 (#5828) — Interface enseignant : liste des classes du périmètre
 * de l'enseignant (API /edu-manager/classes — EduAccess::teacherClassIds,
 * garde EduClassPolicy). Un enseignant ne voit JAMAIS une autre classe.
 */

type TeacherClass = {
  id: number;
  code: string;
  name: string;
  level?: string | null;
  status: string;
  campus?: { id: number; code: string; name: string } | null;
};

export default function TeacherHomePage() {
  const locale = getPreferredLocale();
  const [classes, setClasses] = useState<TeacherClass[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiFetch('/edu-manager/classes?per_page=200', { _cacheBust: true });
      const json = (await res.json()) as { data?: TeacherClass[] };
      setClasses(Array.isArray(json.data) ? json.data : []);
    } catch {
      setError(t(locale, 'edu.common.error'));
    } finally {
      setLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <ModulePageShell
      title={t(locale, 'edu.teacher.title')}
      subtitle={t(locale, 'edu.teacher.subtitle')}
      accentClassName="border-emerald-500/10 bg-emerald-500/5"
    >
      {loading ? (
        <Spinner label={t(locale, 'edu.common.loading')} />
      ) : error ? (
        <ErrorState message={error} onRetry={() => void load()} />
      ) : classes.length === 0 ? (
        <Card>
          <p className="py-8 text-center text-sm font-medium text-slate-500">{t(locale, 'edu.teacher.empty')}</p>
        </Card>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {classes.map((entry) => (
            <Link key={entry.id} href={`/edu-manager/teacher/classes/${entry.id}`} className="group">
              <Card className="transition-shadow group-hover:shadow-md">
                <div className="flex items-start justify-between gap-3">
                  <div className="flex items-center gap-3">
                    <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500/15 to-cyan-500/15 text-emerald-600">
                      <GraduationCap className="h-5 w-5" aria-hidden="true" />
                    </div>
                    <div>
                      <p className="font-black tracking-tight text-slate-950">{entry.name}</p>
                      <p className="font-mono text-xs font-bold text-slate-400">
                        {entry.code} {entry.level ? `· ${entry.level}` : ''}
                      </p>
                    </div>
                  </div>
                  <StatusBadge status={entry.status} />
                </div>
                {entry.campus?.name ? <p className="mt-3 text-xs font-medium text-slate-400">{entry.campus.name}</p> : null}
                <div className="mt-4 flex items-center gap-4 border-t border-slate-100 pt-3 text-xs font-bold text-slate-500">
                  <span className="inline-flex items-center gap-1.5"><CalendarCheck className="h-3.5 w-3.5 text-emerald-500" aria-hidden="true" />{t(locale, 'edu.teacher.attendance')}</span>
                  <span className="inline-flex items-center gap-1.5"><BookOpen className="h-3.5 w-3.5 text-cyan-500" aria-hidden="true" />{t(locale, 'edu.teacher.grades')}</span>
                </div>
              </Card>
            </Link>
          ))}
        </div>
      )}
    </ModulePageShell>
  );
}
