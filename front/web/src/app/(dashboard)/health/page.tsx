'use client';

/**
 * HealthManager (BC-30, #7792) — hub de la verticale hôpitaux & cliniques
 * privées, calqué sur les hubs travel/edu-manager. Affiche les KPIs du jour
 * (`GET /health-manager/dashboard`) : rendez-vous du jour, occupation des
 * lits, recettes du mois, derniers patients — plus les tuiles de navigation
 * vers les sous-espaces (patients, rendez-vous, hospitalisations,
 * facturation, référentiel).
 */
import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import {
  BedDouble,
  CalendarClock,
  ClipboardList,
  HeartPulse,
  Receipt,
  Users,
} from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import { getHealthDashboard, type HealthDashboard } from '@/lib/health-api';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

function formatAmount(value: number | string | undefined, locale: string, currency?: string): string {
  if (value === undefined || value === null || value === '') return '—';
  const numeric = typeof value === 'string' ? Number(value) : value;
  if (Number.isNaN(numeric)) return String(value);
  return `${numeric.toLocaleString(locale)}${currency ? ` ${currency}` : ''}`;
}

export default function HealthHomePage() {
  const locale = getPreferredLocale();
  const [dashboard, setDashboard] = useState<HealthDashboard | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      setDashboard(await getHealthDashboard());
    } catch {
      setError(t(locale, 'health.common.loadError', 'Impossible de charger les données.'));
    } finally {
      setLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    void load();
  }, [load]);

  const occupancy = dashboard?.occupancy;
  const occupancyLabel =
    occupancy && occupancy.total_beds
      ? `${occupancy.occupied_beds ?? 0} / ${occupancy.total_beds}`
      : occupancy?.occupancy_rate !== undefined
        ? `${Math.round((occupancy.occupancy_rate ?? 0) * 100)}%`
        : '—';

  const kpis = dashboard
    ? [
        {
          label: t(locale, 'health.home.kpiAppointmentsToday', 'Rendez-vous du jour'),
          value: (dashboard.appointments_today ?? 0).toLocaleString(locale),
        },
        {
          label: t(locale, 'health.home.kpiOccupancy', 'Occupation des lits'),
          value: occupancyLabel,
        },
        {
          label: t(locale, 'health.home.kpiMonthRevenue', 'Recettes du mois'),
          value: formatAmount(dashboard.month_revenue, locale, dashboard.currency),
        },
        {
          label: t(locale, 'health.home.kpiActiveAdmissions', 'Hospitalisations en cours'),
          value: (dashboard.admissions_active ?? 0).toLocaleString(locale),
        },
      ]
    : [];

  const tiles = [
    {
      href: '/health/patients',
      icon: Users,
      title: t(locale, 'health.patients.title', 'Patients'),
      description: t(locale, 'health.home.patientsDesc', 'Registre patients : identités, assurance, archivage.'),
      accent: 'from-emerald-500 to-teal-600',
    },
    {
      href: '/health/appointments',
      icon: CalendarClock,
      title: t(locale, 'health.appointments.title', 'Rendez-vous'),
      description: t(locale, 'health.home.appointmentsDesc', 'Agenda du jour, prise de rendez-vous et statuts.'),
      accent: 'from-cyan-500 to-blue-600',
    },
    {
      href: '/health/admissions',
      icon: BedDouble,
      title: t(locale, 'health.admissions.title', 'Hospitalisations'),
      description: t(locale, 'health.home.admissionsDesc', 'Admissions, transferts, sorties et occupation des lits.'),
      accent: 'from-violet-500 to-purple-600',
    },
    {
      href: '/health/billing',
      icon: Receipt,
      title: t(locale, 'health.billing.title', 'Facturation des soins'),
      description: t(locale, 'health.home.billingDesc', 'Catalogue d’actes, factures, encaissements.'),
      accent: 'from-amber-500 to-orange-600',
    },
    {
      href: '/health/referential',
      icon: ClipboardList,
      title: t(locale, 'health.referential.title', 'Référentiel'),
      description: t(locale, 'health.home.referentialDesc', 'Services, salles, lits, spécialités, praticiens et rôles.'),
      accent: 'from-rose-500 to-pink-600',
    },
  ];

  return (
    <ModulePageShell
      icon={HeartPulse}
      title={t(locale, 'health.section.title', 'HealthManager')}
      description={t(
        locale,
        'health.section.subtitle',
        'Pilotage hôpital & clinique : patients, rendez-vous, hospitalisations, facturation des soins.',
      )}
    >
      <div className="space-y-6">
        {error ? (
          <div className="flex items-center justify-between gap-4 rounded-lg bg-red-50 px-4 py-3">
            <p className="text-sm font-semibold text-red-700">{error}</p>
            <button
              type="button"
              onClick={() => void load()}
              className="rounded-lg bg-red-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-red-700"
            >
              {t(locale, 'health.common.retry', 'Réessayer')}
            </button>
          </div>
        ) : null}

        <section aria-label={t(locale, 'health.home.kpisTitle', 'Indicateurs du jour')}>
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            {loading
              ? Array.from({ length: 4 }, (_, i) => (
                  <div key={i} className="h-24 animate-pulse rounded-2xl border border-slate-200/60 bg-white/60" />
                ))
              : kpis.map((kpi) => (
                  <div
                    key={kpi.label}
                    className="rounded-2xl border border-slate-200/60 bg-white/80 p-4 shadow-sm backdrop-blur-xl"
                  >
                    <p className="text-[10px] font-black uppercase tracking-widest text-slate-500">{kpi.label}</p>
                    <p className="mt-2 text-2xl font-black tracking-tight text-slate-950">{kpi.value}</p>
                  </div>
                ))}
          </div>
        </section>

        <section aria-label={t(locale, 'health.home.tilesTitle', 'Espaces de gestion')}>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {tiles.map((tile) => (
              <Link
                key={tile.href}
                href={tile.href}
                className="group relative overflow-hidden rounded-2xl border border-slate-200/60 bg-white/80 p-5 shadow-sm backdrop-blur-xl transition hover:-translate-y-0.5 hover:shadow-md"
              >
                <span
                  className={`inline-flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br ${tile.accent} text-white shadow`}
                >
                  <tile.icon className="h-5 w-5" aria-hidden="true" />
                </span>
                <h2 className="mt-3 text-base font-black tracking-tight text-slate-950">{tile.title}</h2>
                <p className="mt-1 text-sm leading-relaxed text-slate-600">{tile.description}</p>
              </Link>
            ))}
          </div>
        </section>

        <section aria-label={t(locale, 'health.home.recentPatients', 'Derniers patients')}>
          <h2 className="mb-3 text-lg font-black tracking-tight text-slate-950">
            {t(locale, 'health.home.recentPatients', 'Derniers patients')}
          </h2>
          {loading ? (
            <div className="h-24 animate-pulse rounded-2xl border border-slate-200/60 bg-white/60" />
          ) : (dashboard?.recent_patients?.length ?? 0) === 0 ? (
            <p className="rounded-2xl border border-dashed border-slate-300 bg-white/50 p-6 text-sm text-slate-500">
              {t(locale, 'health.home.noRecentPatients', 'Aucun patient enregistré récemment.')}
            </p>
          ) : (
            <div className="overflow-x-auto rounded-2xl border border-slate-200/60 bg-white/80 shadow-sm backdrop-blur-xl">
              <table className="min-w-full divide-y divide-slate-200 text-sm">
                <thead className="bg-slate-50">
                  <tr>
                    <th className="px-4 py-3 text-left font-semibold text-slate-700">
                      {t(locale, 'health.patients.mrn', 'N° dossier')}
                    </th>
                    <th className="px-4 py-3 text-left font-semibold text-slate-700">
                      {t(locale, 'health.patients.fullName', 'Nom complet')}
                    </th>
                    <th className="px-4 py-3 text-left font-semibold text-slate-700">
                      {t(locale, 'health.patients.status', 'Statut')}
                    </th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {(dashboard?.recent_patients ?? []).map((patient) => (
                    <tr key={patient.id} className="hover:bg-emerald-50/40">
                      <td className="px-4 py-3 font-mono text-xs font-bold text-slate-500">{patient.mrn}</td>
                      <td className="px-4 py-3 font-bold text-slate-900">{patient.full_name}</td>
                      <td className="px-4 py-3 text-slate-600">
                        {t(locale, `health.patients.statusValue.${patient.status}`, patient.status)}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>
      </div>
    </ModulePageShell>
  );
}
