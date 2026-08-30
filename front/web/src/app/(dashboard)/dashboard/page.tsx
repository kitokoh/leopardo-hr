'use client';

import { useCallback, useEffect, useRef, useState, useSyncExternalStore } from 'react';
import Link from 'next/link';
import { motion } from 'framer-motion';
import {
  Users,
  Clock,
  Sparkles,
  ArrowUp,
  ArrowDown,
  Zap,
  TrendingUp,
  Calendar,
  Bell,
  Search,
  Download,
  CheckCircle2,
  Building2,
  ClipboardList,
  FileCheck,
  Languages,
  ArrowRight,
  Loader2,
} from 'lucide-react';
import { ApiError, apiFetch } from '@/lib/api-client';
import { trackClientEvent } from '@/lib/client-analytics';
import { getDisplayName, getPreferredLocale, getStoredUser, toIntlLocale, type AppLocale, type StoredAuthUser } from '@/lib/i18n';
import { getClientModuleAccess } from '@/lib/client-features';
import { t as i18nT } from '@/lib/i18n/locale-catalog';

const emptySubscribe = () => () => {};

// Persistance « {i18nT(locale, 'dashboard.later')} » de la carte Leo IA (localStorage, pas de cookie).
const LEO_IA_CARD_DISMISS_KEY = 'leopardo_ia_card_dismissed';

type DashboardStat = {
  title: string;
  value: number;
  change: string;
  trend: 'up' | 'down';
  icon: typeof Users;
  color: string;
  bgColor: string;
  suffix?: string;
};

type DashboardSummary = {
  employees_total: number;
  employees_active: number;
  departments: number;
  today_attendance: number;
  pending_absences: number;
};

type RecentActivity = {
  id: number | string;
  action: string;
  auditable_type?: string | null;
  created_at?: string | null;
};

type LaunchReadiness = {
  score: number;
  go_live_ready: boolean;
  required_blockers?: Array<{ key: string; label: string }>;
  next_actions?: Array<{ key: string; label: string; required: boolean }>;
};

// Interpolation légère pour les clés i18n à trous ({placeholder}) du
// dashboard — le catalogue JSON ne fait pas d'interpolation lui-même.
function formatMessage(template: string, values: Record<string, string | number>): string {
  return template.replace(/\{(\w+)\}/g, (match, name: string) =>
    name in values ? String(values[name]) : match
  );
}

const AnimatedNumber = ({ value, suffix = '' }: { value: number; suffix?: string }) => {
  const [count, setCount] = useState(0);

  useEffect(() => {
    let start = 0;
    const end = value;
    const duration = 1500;
    const increment = end / (duration / 16);

    const timer = setInterval(() => {
      start += increment;
      if (start >= end) {
        setCount(end);
        clearInterval(timer);
      } else {
        setCount(Math.floor(start));
      }
    }, 16);

    return () => clearInterval(timer);
  }, [value]);

  return <span className="tabular-nums">{count}{suffix}</span>;
};

const GlassCard = ({ children, className = '', delay = 0 }: { children: React.ReactNode; className?: string; delay?: number }) => (
  <motion.div
    initial={{ opacity: 0, y: 20 }}
    animate={{ opacity: 1, y: 0 }}
    transition={{ duration: 0.5, delay }}
    whileHover={{ y: -2, transition: { duration: 0.2 } }}
    className={`relative group ${className}`}
  >
    <div className="absolute -inset-0.5 rounded-2xl bg-gradient-to-r from-emerald-500 to-cyan-500 opacity-0 blur transition duration-500 group-hover:opacity-20" />
    <div className="relative overflow-hidden rounded-2xl border border-slate-200/50 bg-white/80 shadow-lg backdrop-blur-xl">
      {children}
    </div>
  </motion.div>
);

export default function DashboardPage() {
  const locale = useSyncExternalStore<AppLocale>(emptySubscribe, getPreferredLocale, () => 'fr');
  const [activeTab, setActiveTab] = useState('today');
  const [user, setUser] = useState<StoredAuthUser | null>(null);
  const [userLoaded, setUserLoaded] = useState(false);
  const [summary, setSummary] = useState<DashboardSummary | null>(null);
  const [activities, setActivities] = useState<RecentActivity[]>([]);
  const [readiness, setReadiness] = useState<LaunchReadiness | null>(null);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [leoCardDismissed, setLeoCardDismissed] = useState(false);
  const [announcementsCount, setAnnouncementsCount] = useState<number | null>(null);
  const [announcementSending, setAnnouncementSending] = useState(false);
  const [announcementSent, setAnnouncementSent] = useState(false);
  const [announcementError, setAnnouncementError] = useState<string | null>(null);
  const dashboardStartRef = useRef<number>(0);
  const dashboardTrackedRef = useRef(false);
  const role = user?.role?.toLowerCase() ?? null;
  const isEmployee = role === 'employee';
  const isSuperAdmin = role === 'super_admin';
  const companyName = user?.company?.name ?? i18nT(locale, 'dashboard.yourCompany');
  const modules = getClientModuleAccess(user);
  const activeModules = modules.filter((module) => module.enabled && module.key !== 'dashboard').length;
  const lockedModules = modules.filter((module) => !module.enabled).length;

  useEffect(() => {
    document.documentElement.lang = locale;
  }, [locale]);

  useEffect(() => {
    dashboardStartRef.current = performance.now();
    setUser(getStoredUser());
    setUserLoaded(true);
  }, []);

  // Carte Leo IA : « Plus tard » masque la carte de facon persistante
  // (localStorage) — verifie au montage, jamais via useSyncExternalStore.
  useEffect(() => {
    try {
      if (localStorage.getItem(LEO_IA_CARD_DISMISS_KEY) === 'true') {
        setLeoCardDismissed(true);
      }
    } catch {
      // localStorage indisponible (navigation privee) — la carte reste visible.
    }
  }, []);

  const sendCongratsAnnouncement = useCallback(async () => {
    if (announcementSending) {
      return;
    }

    setAnnouncementSending(true);
    setAnnouncementError(null);
    const startedAt = performance.now();

    try {
      // POST /api/v1/announcements — Store AnnouncementController (PA2-COMM-004) :
      // title/body obligatoires, audience_type 'company' (broadcast entreprise).
      await apiFetch('/announcements', {
        method: 'POST',
        body: JSON.stringify({
          title: i18nT(locale, 'dashboard.leo_ia_announcement_title'),
          body: i18nT(locale, 'dashboard.leo_ia_announcement_body'),
          priority: 'normal',
          audience_type: 'company',
        }),
      });

      setAnnouncementSent(true);
      trackClientEvent('leo_ia_announcement_sent', {
        status: 'success',
        audience_type: 'company',
        duration_ms: Math.round(performance.now() - startedAt),
      });
    } catch (error) {
      setAnnouncementError(error instanceof ApiError ? error.message : i18nT(locale, 'dashboard.leo_ia_announcement_error'));
      trackClientEvent('leo_ia_announcement_sent', {
        status: 'error',
        audience_type: 'company',
        duration_ms: Math.round(performance.now() - startedAt),
      });
    } finally {
      setAnnouncementSending(false);
    }
  }, [announcementSending, locale]);

  const dismissLeoCard = useCallback(() => {
    try {
      localStorage.setItem(LEO_IA_CARD_DISMISS_KEY, 'true');
    } catch {
      // localStorage indisponible — masquee pour la session uniquement.
    }
    setLeoCardDismissed(true);
    trackClientEvent('leo_ia_card_dismissed', {
      surface: 'manager_dashboard',
    });
  }, []);

  const trackDashboardLoaded = useCallback((extra: Record<string, unknown> = {}) => {
    if (dashboardTrackedRef.current) {
      return;
    }

    dashboardTrackedRef.current = true;
    trackClientEvent('dashboard_loaded', {
      duration_ms: Math.round(performance.now() - dashboardStartRef.current),
      role: user?.role ?? null,
      manager_role: user?.manager_role ?? null,
      company_id: user?.company?.id ?? null,
      company_name: user?.company?.name ?? null,
      active_modules: activeModules,
      locked_modules: lockedModules,
      ...extra,
    });
  }, [activeModules, lockedModules, user]);

  useEffect(() => {
    let cancelled = false;

    async function loadDashboard() {
      if (!userLoaded) {
        return;
      }

      if (isEmployee || isSuperAdmin) {
        setLoading(false);
        trackDashboardLoaded({ surface: isEmployee ? 'employee' : 'super_admin' });
        return;
      }

      setLoading(true);
      setLoadError(null);

      try {
        const [summaryResponse, activityResponse, readinessPayload, announcementsResponse] = await Promise.all([
          apiFetch('/dashboard/summary'),
          apiFetch('/dashboard/recent-activity?limit=5'),
          apiFetch('/launch-readiness')
            .then((response) => response.json() as Promise<{ data?: LaunchReadiness }>)
            .catch(() => null),
          // #3027 : compteur réel d'annonces entreprise (PA2-COMM-004) —
          // alimente la carte « Leo IA » sans pourcentage fabriqué.
          apiFetch('/announcements?per_page=1')
            .then((response) => response.json() as Promise<{ data?: unknown[]; meta?: { total?: number } }>)
            .catch(() => null),
        ]);

        const summaryPayload = await summaryResponse.json() as { data?: Partial<DashboardSummary> };
        const activityPayload = await activityResponse.json() as { data?: RecentActivity[] };

        if (cancelled) return;

        setSummary({
          employees_total: Number(summaryPayload.data?.employees_total ?? 0),
          employees_active: Number(summaryPayload.data?.employees_active ?? 0),
          departments: Number(summaryPayload.data?.departments ?? 0),
          today_attendance: Number(summaryPayload.data?.today_attendance ?? 0),
          pending_absences: Number(summaryPayload.data?.pending_absences ?? 0),
        });
        setActivities(Array.isArray(activityPayload.data) ? activityPayload.data : []);
        setReadiness(readinessPayload?.data ?? null);
        const announcementsPayload = announcementsResponse as { data?: unknown[]; meta?: { total?: number } } | null;
        setAnnouncementsCount(
          Number(announcementsPayload?.meta?.total ?? announcementsPayload?.data?.length ?? 0)
        );
        trackDashboardLoaded({
          surface: 'manager',
          employees_active: Number(summaryPayload.data?.employees_active ?? 0),
          pending_absences: Number(summaryPayload.data?.pending_absences ?? 0),
          launch_readiness_score: readinessPayload?.data?.score ?? null,
        });
      } catch (error) {
        if (cancelled) return;
        setLoadError(error instanceof ApiError ? error.message : i18nT(locale, 'dashboard.dashboard_load_error'));
        trackDashboardLoaded({
          surface: 'manager',
          status: 'error',
        });
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    }

    void loadDashboard();

    return () => {
      cancelled = true;
    };
  }, [isEmployee, isSuperAdmin, trackDashboardLoaded, userLoaded, locale]);

  const stats: DashboardStat[] = [
    {
      title: i18nT(locale, 'dashboard.activeEmployees'),
      value: summary?.employees_active ?? 0,
      change: `${summary?.employees_total ?? 0} ${i18nT(locale, 'dashboard.kpi_total')}`,
      trend: 'up',
      icon: Users,
      color: 'from-security to-security-dark',
      bgColor: 'bg-security-light',
    },
    {
      title: i18nT(locale, 'dashboard.presence_today_title'),
      value: summary?.today_attendance ?? 0,
      change: summary && summary.employees_active > 0
        ? `${Math.round((summary.today_attendance / summary.employees_active) * 100)}%`
        : '0%',
      trend: 'up',
      icon: CheckCircle2,
      color: 'from-rh to-rh-dark',
      bgColor: 'bg-rh-light',
    },
    {
      title: i18nT(locale, 'dashboard.pending_absences'),
      value: summary?.pending_absences ?? 0,
      change: i18nT(locale, 'dashboard.kpi_to_process'),
      trend: 'down',
      icon: Clock,
      color: 'from-finance to-finance-dark',
      bgColor: 'bg-finance-light',
    },
    {
      title: i18nT(locale, 'dashboard.departments'),
      value: summary?.departments ?? 0,
      change: i18nT(locale, 'dashboard.kpi_active'),
      trend: 'up',
      icon: Clock,
      color: 'from-ia to-ia-dark',
      bgColor: 'bg-ia-light',
    },
  ];

  const activityRows = activities.length > 0
    ? activities.map((activity) => ({
        key: String(activity.id),
        name: activity.auditable_type?.split('\\').pop() ?? i18nT(locale, 'dashboard.system_fallback'),
        action: activity.action,
        time: activity.created_at ? new Date(activity.created_at).toLocaleTimeString(toIntlLocale(locale), { hour: '2-digit', minute: '2-digit' }) : '--:--',
        avatar: (activity.action || 'A').slice(0, 2).toUpperCase(),
      }))
    : [];

  if (!userLoaded) {
    return null;
  }

  if (isEmployee) {
    return <EmployeeDashboard user={user} />;
  }

  if (isSuperAdmin) {
    return <SuperAdminBridge user={user} />;
  }

  return (
    <div className="space-y-6 p-6">
      <motion.div
        initial={{ opacity: 0, y: -20 }}
        animate={{ opacity: 1, y: 0 }}
        className="flex flex-col justify-between gap-4 lg:flex-row lg:items-center"
      >
        <div>
          <h1 className="text-3xl font-black text-slate-950">{i18nT(locale, 'dashboard.title', 'Tableau de bord')}</h1>
          <p className="mt-1 text-slate-500">
            {loading ? i18nT(locale, 'dashboard.tenant_loading') : i18nT(locale, 'dashboard.welcome_today')}
          </p>
          {loadError ? (
            <p className="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
              {loadError}
            </p>
          ) : null}
        </div>

        <div className="flex items-center gap-3">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            {/* #4574 : placeholder localisé */}
            <input
              type="text"
              placeholder={i18nT(locale, 'dashboard.searchPlaceholder')}
              className="w-64 rounded-xl border border-app-border bg-white py-2 pl-10 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
            />
          </div>
          <button className="relative rounded-xl border border-app-border bg-white p-2 transition-colors hover:bg-transparent">
            <Bell className="h-5 w-5 text-slate-600" />
            <span className="absolute right-1 top-1 h-2 w-2 rounded-full bg-red-500" />
          </button>
        </div>
      </motion.div>

      <section className="grid gap-4 lg:grid-cols-[1.4fr_1fr]">
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
              <p className="text-xs font-bold uppercase tracking-[0.16em] text-slate-400">{i18nT(locale, 'dashboard.company', 'Entreprise')}</p>
              <h2 className="mt-2 text-2xl font-black text-slate-950">{companyName}</h2>
              <p className="mt-1 text-sm text-slate-500">
                {formatMessage(i18nT(locale, 'dashboard.modulesActiveSentence'), { active: activeModules, locked: lockedModules })}
              </p>
            </div>
            <div className="grid grid-cols-2 gap-2 text-center">
              <div className="rounded-xl bg-emerald-50 px-4 py-3">
                <p className="text-2xl font-black text-emerald-700">{activeModules}</p>
                <p className="text-[10px] font-bold uppercase tracking-wider text-emerald-900">{i18nT(locale, 'dashboard.active_employees', 'Actifs')}</p>
              </div>
              <div className="rounded-xl bg-amber-50 px-4 py-3">
                <p className="text-2xl font-black text-amber-700">{lockedModules}</p>
                <p className="text-[10px] font-bold uppercase tracking-wider text-amber-900">{i18nT(locale, 'dashboard.upgrade', 'Upgrade')}</p>
              </div>
            </div>
          </div>
        </div>
        <div className="rounded-2xl border border-slate-800 bg-slate-950 p-5 text-white shadow-sm">
          <p className="text-xs font-bold uppercase tracking-[0.16em] text-emerald-200">{i18nT(locale, 'dashboard.priority_actions', 'Actions prioritaires')}</p>
          <div className="mt-4 grid gap-3 text-sm">
            <PriorityAction label={i18nT(locale, 'dashboard.priority_process_absences')} value={summary?.pending_absences ?? 0} href="/absences" />
            <PriorityAction label={i18nT(locale, 'dashboard.priority_check_presences')} value={summary?.today_attendance ?? 0} href="/attendance" />
          </div>
        </div>
      </section>

      {readiness ? (
        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
              <p className="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700">{i18nT(locale, 'dashboard.launch_readiness', 'Readiness lancement')}</p>
              <h2 className="mt-2 text-2xl font-black text-slate-950">
                {readiness.go_live_ready ? i18nT(locale, 'dashboard.go_live_ready') : i18nT(locale, 'dashboard.go_live_required')}
              </h2>
              <p className="mt-1 text-sm leading-6 text-slate-500">
                {formatMessage(i18nT(locale, 'dashboard.go_live_score'), { score: readiness.score })}
              </p>
            </div>
            <div className={`flex h-24 w-24 shrink-0 items-center justify-center rounded-2xl text-3xl font-black ${
              readiness.go_live_ready ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'
            }`}>
              {readiness.score}
            </div>
          </div>
          <div className="mt-4 grid gap-3 md:grid-cols-2">
            {(readiness.go_live_ready ? readiness.next_actions : readiness.required_blockers)?.slice(0, 2).map((item) => (
              <div key={item.key} className="rounded-xl border border-slate-100 bg-transparent px-4 py-3 text-sm text-slate-700">
                {item.label}
              </div>
            ))}
          </div>
        </section>
      ) : null}

      <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
        {stats.map((stat, index) => (
          <GlassCard key={stat.title} delay={index * 0.1}>
            <div className="p-6">
              <div className="mb-4 flex items-start justify-between">
                <div className={`flex h-12 w-12 items-center justify-center rounded-xl ${stat.bgColor}`}>
                  <stat.icon className={`h-6 w-6 bg-gradient-to-br ${stat.color} bg-clip-text text-transparent`} style={{ color: 'inherit' }} />
                </div>
                <div className={`flex items-center gap-1 rounded-full px-2 py-1 text-xs font-bold ${
                  stat.trend === 'up'
                    ? 'bg-emerald-50 text-emerald-700'
                    : 'bg-red-50 text-red-700'
                }`}>
                  {stat.trend === 'up' ? <ArrowUp className="h-3 w-3" /> : <ArrowDown className="h-3 w-3" />}
                  {stat.change}
                </div>
              </div>
              <div className="mb-1 text-3xl font-black text-slate-950">
                <AnimatedNumber value={stat.value} suffix={stat.suffix ?? ''} />
              </div>
              <p className="text-sm text-slate-500">{stat.title}</p>
            </div>
          </GlassCard>
        ))}
      </div>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <GlassCard className="lg:col-span-2" delay={0.4}>
          <div className="p-6">
            <div className="mb-6 flex items-center justify-between">
              <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-rh to-rh-dark">
                  <Zap className="h-5 w-5 text-white" />
                </div>
                <div>
                  <h3 className="font-bold text-slate-950">{i18nT(locale, 'dashboard.recent_activity')}</h3>
                  <p className="text-sm text-slate-500">{i18nT(locale, 'dashboard.recent_activity_hint', 'Dernières actions de votre équipe')}</p>
                </div>
              </div>
              <div className="flex items-center gap-2">
                <button
                  onClick={() => setActiveTab('today')}
                  className={`rounded-lg px-3 py-1.5 text-sm font-bold transition-colors ${
                    activeTab === 'today'
                      ? 'bg-emerald-50 text-emerald-700'
                      : 'text-slate-600 hover:bg-slate-100'
                  }`}
                >
                  {i18nT(locale, 'dashboard.tab_today')}
                </button>
                <button
                  onClick={() => setActiveTab('week')}
                  className={`rounded-lg px-3 py-1.5 text-sm font-bold transition-colors ${
                    activeTab === 'week'
                      ? 'bg-emerald-50 text-emerald-700'
                      : 'text-slate-600 hover:bg-slate-100'
                  }`}
                >
                  {i18nT(locale, 'dashboard.tab_week')}
                </button>
              </div>
            </div>

            <div className="space-y-3">
              {activityRows.length === 0 ? (
                <div className="rounded-xl border border-dashed border-app-border p-6 text-sm text-slate-500">
                  {i18nT(locale, 'dashboard.recent_activity_empty')}
                </div>
              ) : activityRows.map((activity, index) => (
                <motion.div
                  key={activity.key}
                  initial={{ opacity: 0, x: -20 }}
                  animate={{ opacity: 1, x: 0 }}
                  transition={{ delay: 0.5 + index * 0.1 }}
                  className="group flex cursor-pointer items-center gap-4 rounded-xl p-4 transition-colors hover:bg-transparent"
                >
                  <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-50 text-sm font-bold text-emerald-700">
                    {activity.avatar}
                  </div>
                  <div className="flex-1">
                    <p className="font-bold text-slate-950">{activity.name}</p>
                    <p className="text-sm text-slate-500">
                      {activity.action} • {activity.time}
                    </p>
                  </div>
                  <span className="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">
                    {i18nT(locale, 'dashboard.journal')}
                  </span>
                </motion.div>
              ))}
            </div>

            <Link
              href="/reports"
              className="mt-4 flex w-full items-center justify-center rounded-xl border border-app-border py-3 font-bold text-slate-600 transition-colors hover:bg-transparent"
            >
              {i18nT(locale, 'dashboard.see_all_activity')}
            </Link>
          </div>
        </GlassCard>

        <div className="space-y-6">
          {!leoCardDismissed ? (
            <GlassCard delay={0.5}>
              <div className="bg-gradient-to-br from-ia/5 to-ia-light p-6">
                <div className="mb-4 flex items-start gap-3">
                  <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-ia to-ia-dark shadow-lg shadow-ia/30">
                    <Sparkles className="h-6 w-6 text-white" />
                  </div>
                  <div>
                    <h4 className="font-bold text-slate-950">Leo IA</h4>
                    <p className="text-xs text-slate-500">{i18nT(locale, 'dashboard.ai_assistant')}</p>
                  </div>
                </div>

                <div className="mb-4 rounded-xl bg-white/50 p-4">
                  {/* #3027 : plus d'« insight » fabriqué (ex. « retards en
                      baisse de 15% ») — uniquement des chiffres réels issus de
                      /dashboard/summary et du compteur d'annonces réel. */}
                  {summary && summary.employees_active > 0 ? (
                    <p className="text-sm leading-relaxed text-slate-700">
                      {formatMessage(i18nT(locale, 'dashboard.leo_presence_insight'), {
                        today: summary.today_attendance,
                        active: summary.employees_active,
                      })}
                    </p>
                  ) : (
                    <p className="text-sm leading-relaxed text-slate-700">
                      {i18nT(locale, 'dashboard.leo_presence_empty')}
                    </p>
                  )}
                  {announcementsCount !== null && (
                    <p className="mt-2 text-xs font-medium text-slate-500">
                      {formatMessage(i18nT(locale, 'dashboard.leo_announcements_count'), {
                        count: announcementsCount,
                      })}
                    </p>
                  )}
                </div>

                {announcementSent ? (
                  <div className="flex items-center gap-2 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700">
                    <CheckCircle2 className="h-5 w-5 shrink-0" />
                    {i18nT(locale, 'dashboard.message_sent')}
                  </div>
                ) : (
                  <>
                    {announcementError ? (
                      <p className="mb-3 rounded-xl border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-700">
                        {announcementError}
                      </p>
                    ) : null}
                    <div className="flex gap-2">
                      <button
                        type="button"
                        onClick={() => void sendCongratsAnnouncement()}
                        disabled={announcementSending}
                        className="flex-1 rounded-xl bg-gradient-to-r from-ia to-ia-dark py-2.5 text-sm font-bold text-white transition-all hover:shadow-lg hover:shadow-ia/30 disabled:cursor-not-allowed disabled:opacity-60"
                      >
                        {announcementSending ? i18nT(locale, 'dashboard.sending') : i18nT(locale, 'dashboard.send_yes')}
                      </button>
                      <button
                        type="button"
                        onClick={dismissLeoCard}
                        disabled={announcementSending}
                        className="flex-1 rounded-xl border border-app-border py-2.5 text-sm font-bold text-slate-600 transition-colors hover:bg-transparent disabled:cursor-not-allowed disabled:opacity-60"
                      >
                        Plus tard
                      </button>
                    </div>
                  </>
                )}
              </div>
            </GlassCard>
          ) : null}

          <GlassCard delay={0.6}>
            <div className="p-6">
              <h4 className="mb-4 font-bold text-slate-950">{i18nT(locale, 'dashboard.quick_actions')}</h4>
              {/* #4095 : data-testid stable pour les e2e (la sidebar a des
                  liens doublons/disablés feature-gated — sélecteur scopé). */}
              <div data-testid="quick-actions" className="grid grid-cols-2 gap-3">
                {[
                  { icon: Users, label: i18nT(locale, 'dashboard.shortcut_employees'), color: 'bg-security', href: '/employees' },
                  { icon: Calendar, label: i18nT(locale, 'dashboard.shortcut_leave'), color: 'bg-rh', href: '/absences' },
                  { icon: TrendingUp, label: i18nT(locale, 'dashboard.quick_reports'), color: 'bg-ia', href: '/reports' },
                  { icon: Download, label: i18nT(locale, 'dashboard.quick_export'), color: 'bg-finance', href: '/reports' },
                ].map((action) => (
                  <Link
                    key={action.label}
                    href={action.href}
                    className="group flex flex-col items-center gap-2 rounded-xl p-4 transition-colors hover:bg-transparent"
                  >
                    <div className={`flex h-10 w-10 items-center justify-center rounded-xl ${action.color} transition-transform group-hover:scale-110`}>
                      <action.icon className="h-5 w-5 text-white" />
                    </div>
                    <span className="text-xs font-bold text-slate-600">{action.label}</span>
                  </Link>
                ))}
              </div>
            </div>
          </GlassCard>

          <GlassCard delay={0.7}>
            <div className="p-6">
              <div className="mb-4 flex items-center justify-between">
                <h4 className="font-bold text-slate-950">{i18nT(locale, 'dashboard.presence_today_title')}</h4>
                {summary && summary.employees_active > 0 ? (
                  <span className="text-xs font-bold text-emerald-600">
                    {Math.round((summary.today_attendance / summary.employees_active) * 100)}%
                  </span>
                ) : null}
              </div>
              {/* #3027 : les barres hebdo et le « +12% » étaient codés en dur
                  (aucun endpoint ne les fournit). Le taux affiché est calculé
                  depuis /dashboard/summary (données réelles) ; sans donnée, un
                  état vide honnête remplace le graphique fictif. */}
              {summary && summary.employees_active > 0 ? (
                <>
                  <div className="h-4 w-full overflow-hidden rounded-full bg-slate-100">
                    <motion.div
                      initial={{ width: 0 }}
                      animate={{ width: `${Math.min(100, Math.round((summary.today_attendance / summary.employees_active) * 100))}%` }}
                      transition={{ delay: 0.8, duration: 0.5 }}
                      className="h-full rounded-full bg-emerald-500"
                    />
                  </div>
                  <p className="mt-3 text-sm text-slate-500">
                    {formatMessage(i18nT(locale, 'dashboard.presence_today_summary'), {
                      present: summary.today_attendance,
                      active: summary.employees_active,
                    })}
                  </p>
                </>
              ) : (
                <div className="flex h-20 items-center justify-center rounded-xl bg-slate-50">
                  <p className="text-sm text-slate-500">
                    {i18nT(locale, 'dashboard.presence_today_empty')}
                  </p>
                </div>
              )}
            </div>
          </GlassCard>
        </div>
      </div>
    </div>
  );
}

function PriorityAction({ label, value, href }: { label: string; value: number; href: string }) {
  return (
    <Link href={href} className="flex items-center justify-between rounded-xl bg-white/10 px-4 py-3 transition hover:bg-white/15">
      <span>{label}</span>
      <span className="inline-flex items-center gap-2 font-bold text-emerald-100">
        {value}
        <ArrowRight className="h-4 w-4" aria-hidden="true" />
      </span>
    </Link>
  );
}

type LeaveBalance = {
  id: number | string;
  absence_type?: { id?: number; name?: string; code?: string } | null;
  allocated_days?: number | null;
  used_days?: number | null;
  remaining_days?: number | null;
};

/**
 * #5694 — Carte solde congés pour le dashboard employé.
 * Charge GET /api/v1/me/leave-balances et affiche les soldes par type.
 */
function LeaveBalanceCard({ locale }: { locale: AppLocale }) {
  const [balances, setBalances] = useState<LeaveBalance[]>([]);
  const [loading, setLoading]   = useState(true);
  const [error, setError]       = useState(false);

  useEffect(() => {
    let active = true;
    apiFetch('/me/leave-balances')
      .then((r) => r.json() as Promise<{ data?: LeaveBalance[] }>)
      .then((p) => {
        if (!active) return;
        setBalances(Array.isArray(p.data) ? p.data : []);
      })
      .catch(() => { if (active) setError(true); })
      .finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, []);

  if (error || (!loading && balances.length === 0)) return null;

  return (
    <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
      <div className="flex items-center gap-2 mb-5">
        <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-600 text-white shrink-0">
          <Calendar className="h-4 w-4" aria-hidden="true" />
        </div>
        <div>
          <h2 className="text-base font-black text-slate-950">
            {i18nT(locale, 'leaveBalance.title', 'Mes soldes de congés')}
          </h2>
          <p className="text-xs text-slate-500">
            {i18nT(locale, 'leaveBalance.subtitle', 'Jours disponibles pour l\'année en cours')}
          </p>
        </div>
      </div>

      {loading ? (
        <div className="flex items-center gap-2 text-sm text-slate-400 py-4">
          <Loader2 className="h-4 w-4 animate-spin" />
          <span>{i18nT(locale, 'leaveBalance.loading', 'Chargement...')}</span>
        </div>
      ) : (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          {balances.map((balance) => {
            const allocated = Number(balance.allocated_days ?? 0);
            const used      = Number(balance.used_days ?? 0);
            const remaining = Number(balance.remaining_days ?? (allocated - used));
            const pct       = allocated > 0 ? Math.min(100, Math.round((used / allocated) * 100)) : 0;
            const critical  = remaining <= 2 && allocated > 0;

            return (
              <div
                key={String(balance.id)}
                className={`rounded-2xl border p-4 ${critical ? 'border-amber-200 bg-amber-50' : 'border-slate-100 bg-slate-50'}`}
              >
                <p className="text-xs font-bold uppercase tracking-wider text-slate-500 truncate">
                  {balance.absence_type?.name ?? i18nT(locale, 'leaveBalance.leave', 'Congé')}
                </p>
                <div className="mt-2 flex items-end justify-between gap-2">
                  <p className={`text-3xl font-black tabular-nums ${critical ? 'text-amber-700' : 'text-slate-950'}`}>
                    {remaining}
                  </p>
                  <p className="text-xs text-slate-400 pb-1">
                    / {allocated} j
                  </p>
                </div>
                {/* Barre de progression */}
                {allocated > 0 && (
                  <div className="mt-3 h-2 w-full overflow-hidden rounded-full bg-slate-200">
                    <div
                      className={`h-full rounded-full transition-all duration-500 ${critical ? 'bg-amber-400' : 'bg-emerald-500'}`}
                      style={{ width: `${pct}%` }}
                    />
                  </div>
                )}
                <p className="mt-1.5 text-[10px] text-slate-400">
                  {used} {i18nT(locale, 'leaveBalance.used', 'utilisé(s)')} · {remaining} {i18nT(locale, 'leaveBalance.remaining', 'restant(s)')}
                </p>
              </div>
            );
          })}
        </div>
      )}

      <div className="mt-4 flex justify-end">
        <Link href="/absences" className="inline-flex items-center gap-1 text-xs font-bold text-emerald-600 hover:text-emerald-800 transition">
          {i18nT(locale, 'leaveBalance.viewAll', 'Voir mes demandes')}
          <ArrowRight className="h-3.5 w-3.5" />
        </Link>
      </div>
    </section>
  );
}

function EmployeeDashboard({ user }: { user: StoredAuthUser | null }) {
  const locale = useSyncExternalStore<AppLocale>(emptySubscribe, getPreferredLocale, () => 'fr');
  const cards = [
    { icon: CheckCircle2, title: i18nT(locale, 'dashboard.emp_checkin'), text: i18nT(locale, 'dashboard.emp_checkin_hint'), href: '/attendance' },
    { icon: Calendar, title: i18nT(locale, 'dashboard.emp_absences'), text: i18nT(locale, 'dashboard.emp_absences_hint'), href: '/absences' },
    { icon: FileCheck, title: i18nT(locale, 'dashboard.emp_paystubs'), text: i18nT(locale, 'dashboard.emp_paystubs_hint'), href: '/payroll' },
    { icon: Languages, title: i18nT(locale, 'dashboard.emp_language'), text: i18nT(locale, 'dashboard.emp_language_hint'), href: '/dashboard' },
  ];

  return (
    <div className="space-y-6 p-6">
      <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <p className="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700">{i18nT(locale, 'dashboard.employee_space')}</p>
        <h1 className="mt-3 text-3xl font-black text-slate-950">{formatMessage(i18nT(locale, 'dashboard.hello'), { name: getDisplayName(user) })}</h1>
        <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
          {i18nT(locale, 'dashboard.employee_intro')}
        </p>
      </section>

      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        {cards.map((card) => (
          <Link key={card.title} href={card.href} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
            <card.icon className="h-7 w-7 text-emerald-600" aria-hidden="true" />
            <h2 className="mt-4 text-lg font-bold text-slate-950">{card.title}</h2>
            <p className="mt-2 text-sm leading-6 text-slate-500">{card.text}</p>
          </Link>
        ))}
      </div>

      {/* #5694 — Carte solde congés */}
      <LeaveBalanceCard locale={locale} />
    </div>
  );
}

function SuperAdminBridge({ user }: { user: StoredAuthUser | null }) {
  const locale = useSyncExternalStore<AppLocale>(emptySubscribe, getPreferredLocale, () => 'fr');
  const adminUrl = process.env.NEXT_PUBLIC_ADMIN_URL;

  return (
    <div className="space-y-6 p-6">
      <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-950 text-white">
          <Building2 className="h-6 w-6" aria-hidden="true" />
        </div>
        <p className="mt-5 text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Super admin</p>
        <h1 className="mt-2 text-3xl font-black text-slate-950">{formatMessage(i18nT(locale, 'dashboard.hello'), { name: getDisplayName(user) })}</h1>
        <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
          {i18nT(locale, 'dashboard.superadmin_intro')}
        </p>
        {adminUrl ? (
          <Link href={adminUrl} className="mt-5 inline-flex items-center gap-2 rounded-xl bg-slate-950 px-4 py-3 text-sm font-bold text-white transition hover:bg-slate-800">
            {i18nT(locale, 'dashboard.open_admin_dashboard')}
            <ArrowRight className="h-4 w-4" aria-hidden="true" />
          </Link>
        ) : (
          <div className="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            {i18nT(locale, 'dashboard.admin_url_hint')}
          </div>
        )}
      </section>

      <section className="grid gap-4 md:grid-cols-3">
        {[i18nT(locale, 'dashboard.platform_health'), i18nT(locale, 'dashboard.client_requests'), i18nT(locale, 'dashboard.tenants_at_risk')].map((item) => (
          <div key={item} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <ClipboardList className="h-6 w-6 text-slate-500" aria-hidden="true" />
            <h2 className="mt-4 text-lg font-bold text-slate-950">{item}</h2>
            <p className="mt-2 text-sm text-slate-500">{i18nT(locale, 'dashboard.platform_dashboard_hint')}</p>
          </div>
        ))}
      </section>
    </div>
  );
}

