'use client';

import { useEffect, useState, useSyncExternalStore } from 'react';
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
} from 'lucide-react';
import { ApiError, apiFetch } from '@/lib/api-client';
import { getPreferredLocale, type AppLocale } from '@/lib/i18n';

const emptySubscribe = () => () => {};

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
    <div className="relative overflow-hidden rounded-2xl border border-slate-200/50 bg-white/80 shadow-lg backdrop-blur-xl dark:border-slate-700/50 dark:bg-slate-900/80">
      {children}
    </div>
  </motion.div>
);

export default function DashboardPage() {
  const locale = useSyncExternalStore<AppLocale>(emptySubscribe, getPreferredLocale, () => 'fr');
  const [activeTab, setActiveTab] = useState('today');
  const [summary, setSummary] = useState<DashboardSummary | null>(null);
  const [activities, setActivities] = useState<RecentActivity[]>([]);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);

  useEffect(() => {
    document.documentElement.lang = locale;
  }, [locale]);

  useEffect(() => {
    let cancelled = false;

    async function loadDashboard() {
      setLoading(true);
      setLoadError(null);

      try {
        const [summaryResponse, activityResponse] = await Promise.all([
          apiFetch('/dashboard/summary'),
          apiFetch('/dashboard/recent-activity?limit=5'),
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
      } catch (error) {
        if (cancelled) return;
        setLoadError(error instanceof ApiError ? error.message : 'Impossible de charger les donnees du dashboard.');
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
  }, []);

  const stats: DashboardStat[] = [
    {
      title: 'Employes actifs',
      value: summary?.employees_active ?? 0,
      change: `${summary?.employees_total ?? 0} total`,
      trend: 'up',
      icon: Users,
      color: 'from-blue-500 to-blue-600',
      bgColor: 'bg-blue-50 dark:bg-blue-900/20',
    },
    {
      title: 'Presents aujourd hui',
      value: summary?.today_attendance ?? 0,
      change: summary && summary.employees_active > 0
        ? `${Math.round((summary.today_attendance / summary.employees_active) * 100)}%`
        : '0%',
      trend: 'up',
      icon: CheckCircle2,
      color: 'from-emerald-500 to-emerald-600',
      bgColor: 'bg-emerald-50 dark:bg-emerald-900/20',
    },
    {
      title: 'Absences en attente',
      value: summary?.pending_absences ?? 0,
      change: 'a traiter',
      trend: 'down',
      icon: Clock,
      color: 'from-amber-500 to-amber-600',
      bgColor: 'bg-amber-50 dark:bg-amber-900/20',
    },
    {
      title: 'Departements',
      value: summary?.departments ?? 0,
      change: 'actifs',
      trend: 'up',
      icon: Clock,
      color: 'from-violet-500 to-violet-600',
      bgColor: 'bg-violet-50 dark:bg-violet-900/20',
    },
  ];

  const activityRows = activities.length > 0
    ? activities.map((activity) => ({
        key: String(activity.id),
        name: activity.auditable_type?.split('\\').pop() ?? 'Systeme',
        action: activity.action,
        time: activity.created_at ? new Date(activity.created_at).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) : '--:--',
        avatar: (activity.action || 'A').slice(0, 2).toUpperCase(),
      }))
    : [];

  return (
    <div className="space-y-6 p-6">
      <motion.div
        initial={{ opacity: 0, y: -20 }}
        animate={{ opacity: 1, y: 0 }}
        className="flex flex-col justify-between gap-4 lg:flex-row lg:items-center"
      >
        <div>
          <h1 className="text-3xl font-bold text-slate-900 dark:text-white">Tableau de bord</h1>
          <p className="mt-1 text-slate-500 dark:text-slate-400">
            {loading ? 'Chargement des donnees tenant...' : 'Bienvenue ! Voici ce qui se passe aujourd hui.'}
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
            <input
              type="text"
              placeholder="Rechercher..."
              className="w-64 rounded-xl border border-slate-200 bg-white py-2 pl-10 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-800"
            />
          </div>
          <button className="relative rounded-xl border border-slate-200 bg-white p-2 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800">
            <Bell className="h-5 w-5 text-slate-600 dark:text-slate-400" />
            <span className="absolute right-1 top-1 h-2 w-2 rounded-full bg-red-500" />
          </button>
        </div>
      </motion.div>

      <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
        {stats.map((stat, index) => (
          <GlassCard key={stat.title} delay={index * 0.1}>
            <div className="p-6">
              <div className="mb-4 flex items-start justify-between">
                <div className={`flex h-12 w-12 items-center justify-center rounded-xl ${stat.bgColor}`}>
                  <stat.icon className={`h-6 w-6 bg-gradient-to-br ${stat.color} bg-clip-text text-transparent`} style={{ color: 'inherit' }} />
                </div>
                <div className={`flex items-center gap-1 rounded-full px-2 py-1 text-xs font-medium ${
                  stat.trend === 'up'
                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                    : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                }`}>
                  {stat.trend === 'up' ? <ArrowUp className="h-3 w-3" /> : <ArrowDown className="h-3 w-3" />}
                  {stat.change}
                </div>
              </div>
              <div className="mb-1 text-3xl font-bold text-slate-900 dark:text-white">
                <AnimatedNumber value={stat.value} suffix={stat.suffix ?? ''} />
              </div>
              <p className="text-sm text-slate-500 dark:text-slate-400">{stat.title}</p>
            </div>
          </GlassCard>
        ))}
      </div>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <GlassCard className="lg:col-span-2" delay={0.4}>
          <div className="p-6">
            <div className="mb-6 flex items-center justify-between">
              <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-600">
                  <Zap className="h-5 w-5 text-white" />
                </div>
                <div>
                  <h3 className="font-semibold text-slate-900 dark:text-white">Activite recente</h3>
                  <p className="text-sm text-slate-500 dark:text-slate-400">Dernieres actions de votre equipe</p>
                </div>
              </div>
              <div className="flex items-center gap-2">
                <button
                  onClick={() => setActiveTab('today')}
                  className={`rounded-lg px-3 py-1.5 text-sm font-medium transition-colors ${
                    activeTab === 'today'
                      ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                      : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'
                  }`}
                >
                  Aujourd&apos;hui
                </button>
                <button
                  onClick={() => setActiveTab('week')}
                  className={`rounded-lg px-3 py-1.5 text-sm font-medium transition-colors ${
                    activeTab === 'week'
                      ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                      : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'
                  }`}
                >
                  Cette semaine
                </button>
              </div>
            </div>

            <div className="space-y-3">
              {activityRows.length === 0 ? (
                <div className="rounded-xl border border-dashed border-slate-200 p-6 text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                  Aucune activite recente a afficher pour ce tenant.
                </div>
              ) : activityRows.map((activity, index) => (
                <motion.div
                  key={activity.key}
                  initial={{ opacity: 0, x: -20 }}
                  animate={{ opacity: 1, x: 0 }}
                  transition={{ delay: 0.5 + index * 0.1 }}
                  className="group flex cursor-pointer items-center gap-4 rounded-xl p-4 transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50"
                >
                  <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-100 text-sm font-bold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                    {activity.avatar}
                  </div>
                  <div className="flex-1">
                    <p className="font-medium text-slate-900 dark:text-white">{activity.name}</p>
                    <p className="text-sm text-slate-500 dark:text-slate-400">
                      {activity.action} • {activity.time}
                    </p>
                  </div>
                  <span className="rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                    Journal
                  </span>
                </motion.div>
              ))}
            </div>

            <button className="mt-4 w-full rounded-xl border border-slate-200 py-3 font-medium text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-800">
              Voir toute l&apos;activite
            </button>
          </div>
        </GlassCard>

        <div className="space-y-6">
          <GlassCard delay={0.5}>
            <div className="bg-gradient-to-br from-violet-500/5 to-fuchsia-500/5 p-6">
              <div className="mb-4 flex items-start gap-3">
                <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-violet-500 to-fuchsia-500 shadow-lg shadow-violet-500/30">
                  <Sparkles className="h-6 w-6 text-white" />
                </div>
                <div>
                  <h4 className="font-semibold text-slate-900 dark:text-white">Leo IA</h4>
                  <p className="text-xs text-slate-500 dark:text-slate-400">Assistant intelligent</p>
                </div>
              </div>

              <div className="mb-4 rounded-xl bg-white/50 p-4 dark:bg-slate-900/50">
                <p className="text-sm leading-relaxed text-slate-700 dark:text-slate-300">
                  &quot;Vos retards sont en baisse de 15% cette semaine. Souhaitez-vous que j&apos;envoie un message de felicitations a l&apos;equipe ?&quot;
                </p>
              </div>

              <div className="flex gap-2">
                <button className="flex-1 rounded-xl bg-gradient-to-r from-violet-500 to-fuchsia-500 py-2.5 text-sm font-medium text-white transition-all hover:shadow-lg hover:shadow-violet-500/30">
                  Oui, envoyer
                </button>
                <button className="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-800">
                  Plus tard
                </button>
              </div>
            </div>
          </GlassCard>

          <GlassCard delay={0.6}>
            <div className="p-6">
              <h4 className="mb-4 font-semibold text-slate-900 dark:text-white">Actions rapides</h4>
              <div className="grid grid-cols-2 gap-3">
                {[
                  { icon: Users, label: 'Nouvel employe', color: 'bg-blue-500' },
                  { icon: Calendar, label: 'Conges', color: 'bg-emerald-500' },
                  { icon: TrendingUp, label: 'Rapports', color: 'bg-violet-500' },
                  { icon: Download, label: 'Export', color: 'bg-amber-500' },
                ].map((action) => (
                  <button
                    key={action.label}
                    className="group flex flex-col items-center gap-2 rounded-xl p-4 transition-colors hover:bg-slate-50 dark:hover:bg-slate-800"
                  >
                    <div className={`flex h-10 w-10 items-center justify-center rounded-xl ${action.color} transition-transform group-hover:scale-110`}>
                      <action.icon className="h-5 w-5 text-white" />
                    </div>
                    <span className="text-xs font-medium text-slate-600 dark:text-slate-400">{action.label}</span>
                  </button>
                ))}
              </div>
            </div>
          </GlassCard>

          <GlassCard delay={0.7}>
            <div className="p-6">
              <div className="mb-4 flex items-center justify-between">
                <h4 className="font-semibold text-slate-900 dark:text-white">Presence hebdo</h4>
                <span className="text-xs font-medium text-emerald-600 dark:text-emerald-400">+12%</span>
              </div>
              <div className="flex h-32 items-end gap-2">
                {[65, 80, 75, 90, 85, 70, 88].map((height, index) => (
                  <div key={index} className="flex flex-1 flex-col items-center gap-1">
                    <motion.div
                      initial={{ height: 0 }}
                      animate={{ height: `${height}%` }}
                      transition={{ delay: 0.8 + index * 0.1, duration: 0.5 }}
                      className={`w-full rounded-t-lg ${
                        height > 80 ? 'bg-emerald-500' : height > 70 ? 'bg-emerald-400' : 'bg-emerald-300'
                      }`}
                    />
                    <span className="text-xs text-slate-400">{['L', 'M', 'M', 'J', 'V', 'S', 'D'][index]}</span>
                  </div>
                ))}
              </div>
            </div>
          </GlassCard>
        </div>
      </div>
    </div>
  );
}
