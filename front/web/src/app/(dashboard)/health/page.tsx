'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import {
  BedDouble,
  CalendarClock,
  FileText,
  HeartPulse,
  Users,
  Wallet,
} from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';
import { Card, SectionTitle, Spinner, StatCard, StatusBadge } from './_components/health-ui';

/**
 * HC-008 (#7792) — Accueil HealthManager (BC-30).
 *
 * Tableau de bord du directeur d'établissement : RDV du jour, taux
 * d'occupation des lits, CA encaissé du mois, patients récents, accès
 * rapides vers les 5 sous-écrans. Chaque bloc est FAIL-OPEN : un 403 de
 * l'API (rôle sans le droit — ex. la facturation est réservée à
 * health.billing/health.admin) affiche « – » sans casser la page ; la
 * garde réelle reste l'API (deny-by-default).
 */

type Occupancy = {
  total_beds: number;
  occupied_beds: number;
};

type Patient = {
  id: number;
  mrn?: string;
  first_name?: string;
  last_name?: string;
  status?: string;
};

export default function HealthManagerHomePage() {
  const locale = getPreferredLocale();
  const [loading, setLoading] = useState(true);
  const [todayCount, setTodayCount] = useState<number | null>(null);
  const [occupancyRate, setOccupancyRate] = useState<string | null>(null);
  const [monthRevenue, setMonthRevenue] = useState<string | null>(null);
  const [recentPatients, setRecentPatients] = useState<Patient[]>([]);

  useEffect(() => {
    let active = true;

    async function loadDashboard() {
      const startOfDay = new Date();
      startOfDay.setHours(0, 0, 0, 0);
      const endOfDay = new Date();
      endOfDay.setHours(23, 59, 59, 999);
      const from = encodeURIComponent(startOfDay.toISOString());
      const to = encodeURIComponent(endOfDay.toISOString());

      const [appointments, occupancy, stats, patients] = await Promise.all([
        apiFetch(`/health-manager/appointments?from=${from}&to=${to}&per_page=1`)
          .then((r) => (r.ok ? r.json() : null))
          .catch(() => null),
        apiFetch('/health-manager/occupancy')
          .then((r) => (r.ok ? r.json() : null))
          .catch(() => null),
        apiFetch('/health-manager/billing/stats')
          .then((r) => (r.ok ? r.json() : null))
          .catch(() => null),
        apiFetch('/health-manager/patients?per_page=5')
          .then((r) => (r.ok ? r.json() : null))
          .catch(() => null),
      ]);

      if (!active) {
        return;
      }

      setTodayCount(typeof appointments?.meta?.total === 'number' ? appointments.meta.total : null);

      const rows = Array.isArray(occupancy?.data) ? (occupancy.data as Occupancy[]) : null;
      if (rows) {
        const total = rows.reduce((sum, row) => sum + (row.total_beds ?? 0), 0);
        const occupied = rows.reduce((sum, row) => sum + (row.occupied_beds ?? 0), 0);
        setOccupancyRate(total > 0 ? `${Math.round((occupied / total) * 100)}%` : '0%');
      } else {
        setOccupancyRate(null);
      }

      setMonthRevenue(typeof stats?.data?.month_revenue === 'string' ? stats.data.month_revenue : null);
      setRecentPatients(Array.isArray(patients?.data) ? (patients.data as Patient[]) : []);
      setLoading(false);
    }

    void loadDashboard();

    return () => {
      active = false;
    };
  }, []);

  const quickLinks: { label: string; href: string; icon: typeof Users }[] = [
    { label: t(locale, 'health.home.managePatients'), href: '/health/patients', icon: Users },
    { label: t(locale, 'health.home.manageAppointments'), href: '/health/appointments', icon: CalendarClock },
    { label: t(locale, 'health.home.manageAdmissions'), href: '/health/admissions', icon: BedDouble },
    { label: t(locale, 'health.home.manageBilling'), href: '/health/billing', icon: Wallet },
    { label: t(locale, 'health.home.manageReferential'), href: '/health/referential', icon: FileText },
  ];

  return (
    <ModulePageShell
      title={t(locale, 'health.home.title')}
      subtitle={t(locale, 'health.home.subtitle')}
      accentClassName="border-emerald-500/10 bg-emerald-500/5"
      icon={HeartPulse}
    >
      {loading ? (
        <Spinner />
      ) : (
        <div className="space-y-6">
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <StatCard
              icon={CalendarClock}
              label={t(locale, 'health.home.todayAppointments')}
              value={todayCount === null ? '–' : String(todayCount)}
            />
            <StatCard
              icon={BedDouble}
              label={t(locale, 'health.home.occupancyRate')}
              value={occupancyRate ?? '–'}
            />
            <StatCard
              icon={Wallet}
              label={t(locale, 'health.home.monthRevenue')}
              value={monthRevenue ?? '–'}
            />
          </div>

          <Card>
            <SectionTitle title={t(locale, 'health.home.recentPatients')} />
            {recentPatients.length === 0 ? (
              <p className="text-sm text-slate-500">{t(locale, 'health.home.recentPatientsEmpty')}</p>
            ) : (
              <ul className="divide-y divide-slate-100">
                {recentPatients.map((patient) => (
                  <li key={patient.id} className="flex items-center justify-between gap-3 py-2.5">
                    <div className="flex items-center gap-3">
                      <span className="font-mono text-xs font-bold text-slate-500">{patient.mrn ?? '—'}</span>
                      <span className="text-sm font-bold text-slate-900">
                        {[patient.first_name, patient.last_name].filter(Boolean).join(' ') || '—'}
                      </span>
                    </div>
                    <StatusBadge status={patient.status ?? 'active'} />
                  </li>
                ))}
              </ul>
            )}
          </Card>

          <Card>
            <SectionTitle title={t(locale, 'health.home.quickLinks')} />
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
              {quickLinks.map((link) => {
                const Icon = link.icon;
                return (
                  <Link
                    key={link.href}
                    href={link.href}
                    className="flex items-center gap-3 rounded-2xl border border-slate-200/70 bg-white/60 px-4 py-3 text-sm font-bold text-slate-700 transition-colors hover:border-emerald-300 hover:bg-emerald-50/50"
                  >
                    <Icon className="h-4 w-4 text-emerald-700" aria-hidden="true" />
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
