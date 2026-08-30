'use client';

import { useEffect, useMemo, useState } from 'react';
import Link from 'next/link';
import {
  Building2,
  CalendarRange,
  ClipboardCheck,
  GraduationCap,
  Layers,
  ListChecks,
  School,
  UserPlus,
  Users,
} from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { getPreferredLocale, getStoredUser } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';
import { Card, SectionTitle, Spinner, ErrorState } from './_components/edu-ui';

/**
 * EDU-011 (#5827) — Accueil EduManager, navigation rôle-aware.
 *
 * - Manager direction (principal/rh/propriétaire) : cartes d'accès à
 *   l'administration scolaire (campus, années, matières, classes, élèves,
 *   admissions, évaluations, bulletins).
 * - Employé enseignant : accès à son espace enseignant (périmètre = ses
 *   classes — gardé côté API par les Policies EduManager).
 * Les compteurs sont chargés depuis l'API /edu-manager (fail-open : si la
 * solution n'est pas activée, l'API répond 403 et on affiche un état vide).
 */

type Counter = { label: string; href: string; icon: typeof Users; value: number | null };

function isTeacherRole(): boolean {
  const user = getStoredUser();
  return user?.role === 'employee';
}

export default function EduManagerHomePage() {
  const locale = getPreferredLocale();
  const [counts, setCounts] = useState<Record<string, number | null>>({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const isTeacher = useMemo(() => isTeacherRole(), []);

  useEffect(() => {
    let active = true;

    async function loadCounts() {
      try {
        const [campuses, classes, students, admissions, cards] = await Promise.all([
          apiFetch('/edu-manager/campuses?per_page=1').then((r) => r.json()).catch(() => null),
          apiFetch('/edu-manager/classes?per_page=1').then((r) => r.json()).catch(() => null),
          apiFetch('/edu-manager/students?per_page=1').then((r) => r.json()).catch(() => null),
          apiFetch('/edu-manager/admissions?per_page=1').then((r) => r.json()).catch(() => null),
          apiFetch('/edu-manager/report-cards?per_page=1&status=generated').then((r) => r.json()).catch(() => null),
        ]);

        if (!active) {
          return;
        }

        setCounts({
          campuses: campuses?.meta?.total ?? null,
          classes: classes?.meta?.total ?? null,
          students: students?.meta?.total ?? null,
          admissions: admissions?.meta?.total ?? null,
          reportCards: cards?.meta?.total ?? null,
        });
      } catch {
        if (active) {
          setError(t(locale, 'edu.common.error'));
        }
      } finally {
        if (active) {
          setLoading(false);
        }
      }
    }

    void loadCounts();

    return () => {
      active = false;
    };
  }, [locale]);

  const adminCounters: Counter[] = [
    { label: t(locale, 'edu.home.campuses'), href: '/edu-manager/campuses', icon: Building2, value: counts.campuses },
    { label: t(locale, 'edu.home.classes'), href: '/edu-manager/classes', icon: School, value: counts.classes },
    { label: t(locale, 'edu.home.students'), href: '/edu-manager/students', icon: Users, value: counts.students },
    { label: t(locale, 'edu.home.admissions'), href: '/edu-manager/admissions', icon: UserPlus, value: counts.admissions },
  ];

  const adminLinks: { label: string; href: string; icon: typeof Layers }[] = [
    { label: t(locale, 'edu.home.manageCampuses'), href: '/edu-manager/campuses', icon: Building2 },
    { label: t(locale, 'edu.home.manageClasses'), href: '/edu-manager/classes', icon: School },
    { label: t(locale, 'edu.home.manageStudents'), href: '/edu-manager/students', icon: Users },
    { label: t(locale, 'edu.home.manageAssessments'), href: '/edu-manager/assessments', icon: ListChecks },
    { label: t(locale, 'edu.home.manageReportCards'), href: '/edu-manager/report-cards', icon: ClipboardCheck },
    { label: t(locale, 'edu.academicYears.title'), href: '/edu-manager/academic-years', icon: CalendarRange },
    { label: t(locale, 'edu.subjects.title'), href: '/edu-manager/subjects', icon: Layers },
  ];

  if (isTeacher) {
    return (
      <ModulePageShell
        title={t(locale, 'edu.teacher.title')}
        subtitle={t(locale, 'edu.teacher.subtitle')}
        accentClassName="border-emerald-500/10 bg-emerald-500/5"
      >
        <Card>
          <SectionTitle title={t(locale, 'edu.teacher.myClasses')} />
          <Link
            href="/edu-manager/teacher"
            className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-emerald-500 to-cyan-600 px-4 py-2 text-sm font-bold text-white shadow-md shadow-emerald-500/20 hover:from-emerald-600 hover:to-cyan-700"
          >
            <GraduationCap className="h-4 w-4" aria-hidden="true" />
            {t(locale, 'edu.home.teacherSpace')}
          </Link>
        </Card>
      </ModulePageShell>
    );
  }

  return (
    <ModulePageShell
      title={t(locale, 'edu.home.title')}
      subtitle={t(locale, 'edu.home.subtitle')}
      accentClassName="border-cyan-500/10 bg-cyan-500/5"
    >
      {loading ? (
        <Spinner label={t(locale, 'edu.common.loading')} />
      ) : error ? (
        <ErrorState message={error} />
      ) : (
        <div className="space-y-6">
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {adminCounters.map((counter) => {
              const Icon = counter.icon;
              return (
                <Link key={counter.href} href={counter.href} className="group">
                  <Card className="transition-shadow group-hover:shadow-md">
                    <div className="flex items-center gap-3">
                      <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500/15 to-cyan-500/15 text-emerald-600">
                        <Icon className="h-5 w-5" aria-hidden="true" />
                      </div>
                      <div>
                        <p className="text-2xl font-black tracking-tight text-slate-950">{counter.value ?? '–'}</p>
                        <p className="text-xs font-bold text-slate-500">{counter.label}</p>
                      </div>
                    </div>
                  </Card>
                </Link>
              );
            })}
          </div>

          <Card>
            <SectionTitle title={t(locale, 'edu.home.quickLinks')} />
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
              {adminLinks.map((link) => {
                const Icon = link.icon;
                return (
                  <Link
                    key={link.href}
                    href={link.href}
                    className="flex items-center gap-3 rounded-2xl border border-slate-200/70 bg-white/60 px-4 py-3 text-sm font-bold text-slate-700 transition-colors hover:border-emerald-300 hover:bg-emerald-50/50"
                  >
                    <Icon className="h-4 w-4 text-emerald-600" aria-hidden="true" />
                    {link.label}
                  </Link>
                );
              })}
            </div>
          </Card>
        </div>
      )}
    </ModulePageShell>
  );
}
