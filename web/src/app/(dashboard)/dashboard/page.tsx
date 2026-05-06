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

  useEffect(() => {
    document.documentElement.lang = locale;
  }, [locale]);

  const stats: DashboardStat[] = [
    {
      title: 'Employes actifs',
      value: 24,
      change: '+2',
      trend: 'up',
      icon: Users,
      color: 'from-blue-500 to-blue-600',
      bgColor: 'bg-blue-50 dark:bg-blue-900/20',
    },
    {
      title: 'Presents aujourd hui',
      value: 18,
      change: '75%',
      trend: 'up',
      icon: CheckCircle2,
      color: 'from-emerald-500 to-emerald-600',
      bgColor: 'bg-emerald-50 dark:bg-emerald-900/20',
    },
    {
      title: 'Retards ce mois',
      value: 2,
      change: '-15%',
      trend: 'down',
      icon: Clock,
      color: 'from-amber-500 to-amber-600',
      bgColor: 'bg-amber-50 dark:bg-amber-900/20',
    },
    {
      title: 'Heures travaillees',
      value: 142,
      suffix: 'h',
      change: '+12h',
      trend: 'up',
      icon: Clock,
      color: 'from-violet-500 to-violet-600',
      bgColor: 'bg-violet-50 dark:bg-violet-900/20',
    },
  ];

  const activities = [
    { name: 'Ahmed Ben', action: 'pointage entree', time: '08:30', status: 'present', avatar: 'AB' },
    { name: 'Sarah Mou', action: 'pointage entree', time: '08:35', status: 'present', avatar: 'SM' },
    { name: 'Karim Had', action: 'demande conge', time: '09:15', status: 'pending', avatar: 'KH' },
    { name: 'Leila Ben', action: 'pointage entree', time: '09:20', status: 'late', avatar: 'LB' },
    { name: 'Youssef A', action: 'pointage entree', time: '08:45', status: 'present', avatar: 'YA' },
  ] as const;

  return (
    <div className="space-y-6 p-6">
      <motion.div
        initial={{ opacity: 0, y: -20 }}
        animate={{ opacity: 1, y: 0 }}
        className="flex flex-col justify-between gap-4 lg:flex-row lg:items-center"
      >
        <div>
          <h1 className="text-3xl font-bold text-slate-900 dark:text-white">Tableau de bord</h1>
          <p className="mt-1 text-slate-500 dark:text-slate-400">Bienvenue ! Voici ce qui se passe aujourd&apos;hui.</p>
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
              {activities.map((activity, index) => (
                <motion.div
                  key={`${activity.name}-${activity.time}`}
                  initial={{ opacity: 0, x: -20 }}
                  animate={{ opacity: 1, x: 0 }}
                  transition={{ delay: 0.5 + index * 0.1 }}
                  className="group flex cursor-pointer items-center gap-4 rounded-xl p-4 transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50"
                >
                  <div className={`flex h-12 w-12 items-center justify-center rounded-xl text-sm font-bold ${
                    activity.status === 'present'
                      ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                      : activity.status === 'late'
                        ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
                        : 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
                  }`}>
                    {activity.avatar}
                  </div>
                  <div className="flex-1">
                    <p className="font-medium text-slate-900 dark:text-white">{activity.name}</p>
                    <p className="text-sm text-slate-500 dark:text-slate-400">
                      {activity.action} • {activity.time}
                    </p>
                  </div>
                  <span className={`rounded-full px-3 py-1 text-xs font-medium ${
                    activity.status === 'present'
                      ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                      : activity.status === 'late'
                        ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
                        : 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
                  }`}>
                    {activity.status === 'present' ? 'Present' : activity.status === 'late' ? 'Retard' : 'En attente'}
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
