'use client';

import { useEffect, useMemo, useState, useSyncExternalStore } from 'react';
import { motion } from 'framer-motion';
import {
  Users, Clock, Wallet, Shield, Sparkles, ArrowUp, ArrowDown,
  Zap, TrendingUp, Calendar, Bell, Search, Filter, MoreHorizontal,
  Download, Share2, Clock3, CheckCircle2, AlertCircle, BarChart3
} from 'lucide-react';
import { getCopy, getPreferredLocale, type AppLocale } from '@/lib/i18n';

const emptySubscribe = () => () => {};

// Animated Counter Component
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

// Glass Card Component
const GlassCard = ({ children, className = '', delay = 0 }: { children: React.ReactNode; className?: string; delay?: number }) => (
  <motion.div
    initial={{ opacity: 0, y: 20 }}
    animate={{ opacity: 1, y: 0 }}
    transition={{ duration: 0.5, delay }}
    whileHover={{ y: -2, transition: { duration: 0.2 } }}
    className={`relative group ${className}`}
  >
    <div className="absolute -inset-0.5 bg-gradient-to-r from-emerald-500 to-cyan-500 rounded-2xl opacity-0 group-hover:opacity-20 transition duration-500 blur" />
    <div className="relative bg-white/80 dark:bg-slate-900/80 backdrop-blur-xl rounded-2xl border border-slate-200/50 dark:border-slate-700/50 shadow-lg overflow-hidden">
      {children}
    </div>
  </motion.div>
);

export default function DashboardPage() {
  const locale = useSyncExternalStore<AppLocale>(emptySubscribe, getPreferredLocale, () => 'fr');
  const labels = useMemo(() => getCopy(locale), [locale]);
  const [activeTab, setActiveTab] = useState('today');

  useEffect(() => {
    document.documentElement.lang = locale;
  }, [locale]);

  const stats = [
    {
      title: 'Employés actifs',
      value: 24,
      change: '+2',
      trend: 'up',
      icon: Users,
      color: 'from-blue-500 to-blue-600',
      bgColor: 'bg-blue-50 dark:bg-blue-900/20',
    },
    {
      title: 'Présents aujourd\'hui',
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
      icon: Clock3,
      color: 'from-amber-500 to-amber-600',
      bgColor: 'bg-amber-50 dark:bg-amber-900/20',
    },
    {
      title: 'Heures travaillées',
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
    { name: 'Ahmed Ben', action: 'pointage entrée', time: '08:30', status: 'present', avatar: 'AB' },
    { name: 'Sarah Mou', action: 'pointage entrée', time: '08:35', status: 'present', avatar: 'SM' },
    { name: 'Karim Had', action: 'demande congé', time: '09:15', status: 'pending', avatar: 'KH' },
    { name: 'Leila Ben', action: 'pointage entrée', time: '09:20', status: 'late', avatar: 'LB' },
    { name: 'Youssef A', action: 'pointage entrée', time: '08:45', status: 'present', avatar: 'YA' },
  ];

  return (
    <div className="space-y-6 p-6">
      {/* Header */}
      <motion.div
        initial={{ opacity: 0, y: -20 }}
        animate={{ opacity: 1, y: 0 }}
        className="flex flex-col lg:flex-row lg:items-center justify-between gap-4"
      >
        <div>
          <h1 className="text-3xl font-bold text-slate-900 dark:text-white">Tableau de bord</h1>
          <p className="text-slate-500 dark:text-slate-400 mt-1">Bienvenue ! Voici ce qui se passe aujourd&apos;hui.</p>
        </div>

        <div className="flex items-center gap-3">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
            <input
              type="text"
              placeholder="Rechercher..."
              className="pl-10 pr-4 py-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 w-64"
            />
          </div>
          <button className="p-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 transition-colors relative">
            <Bell className="w-5 h-5 text-slate-600 dark:text-slate-400" />
            <span className="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full" />
          </button>
        </div>
      </motion.div>

      {/* Stats Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        {stats.map((stat, index) => (
          <GlassCard key={index} delay={index * 0.1}>
            <div className="p-6">
              <div className="flex items-start justify-between mb-4">
                <div className={`w-12 h-12 rounded-xl ${stat.bgColor} flex items-center justify-center`}>
                  <stat.icon className={`w-6 h-6 bg-gradient-to-br ${stat.color} bg-clip-text text-transparent`} style={{ color: 'inherit' }} />
                </div>
                <div className={`flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium ${
                  stat.trend === 'up' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                }`}>
                  {stat.trend === 'up' ? <ArrowUp className="w-3 h-3" /> : <ArrowDown className="w-3 h-3" />}
                  {stat.change}
                </div>
              </div>
              <div className="text-3xl font-bold text-slate-900 dark:text-white mb-1">
                <AnimatedNumber value={stat.value} suffix={stat.suffix || ''} />
              </div>
              <p className="text-sm text-slate-500 dark:text-slate-400">{stat.title}</p>
            </div>
          </GlassCard>
        ))}
      </div>

      {/* Main Content */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Activity Feed */}
        <GlassCard className="lg:col-span-2" delay={0.4}>
          <div className="p-6">
            <div className="flex items-center justify-between mb-6">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-600 flex items-center justify-center">
                  <Zap className="w-5 h-5 text-white" />
                </div>
                <div>
                  <h3 className="font-semibold text-slate-900 dark:text-white">Activité récente</h3>
                  <p className="text-sm text-slate-500 dark:text-slate-400">Dernières actions de votre équipe</p>
                </div>
              </div>
              <div className="flex items-center gap-2">
                <button
                  onClick={() => setActiveTab('today')}
                  className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-colors ${
                    activeTab === 'today'
                      ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                      : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'
                  }`}
                >
                  Aujourd&apos;hui
                </button>
                <button
                  onClick={() => setActiveTab('week')}
                  className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-colors ${
                    activeTab === 'week'
                      ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                      : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'
                  }`}
                >
                  Cette semaine
                </button>
              </div>
            </div>

            <div className="space-y-3">
              {activities.map((activity, index) => (
                <motion.div
                  key={index}
                  initial={{ opacity: 0, x: -20 }}
                  animate={{ opacity: 1, x: 0 }}
                  transition={{ delay: 0.5 + index * 0.1 }}
                  className="flex items-center gap-4 p-4 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group cursor-pointer"
                >
                  <div className={`w-12 h-12 rounded-xl flex items-center justify-center text-sm font-bold ${
                    activity.status === 'present' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' :
                    activity.status === 'late' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' :
                    'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
                  }`}>
                    {activity.avatar}
                  </div>
                  <div className="flex-1">
                    <p className="font-medium text-slate-900 dark:text-white">{activity.name}</p>
                    <p className="text-sm text-slate-500 dark:text-slate-400">
                      {activity.action} • {activity.time}
                    </p>
                  </div>
                  <span className={`px-3 py-1 rounded-full text-xs font-medium ${
                    activity.status === 'present' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' :
                    activity.status === 'late' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' :
                    'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
                  }`}>
                    {activity.status === 'present' ? 'Présent' : activity.status === 'late' ? 'Retard' : 'En attente'}
                  </span>
                </motion.div>
              ))}
            </div>

            <button className="w-full mt-4 py-3 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
              Voir toute l&apos;activité
            </button>
          </div>
        </GlassCard>

        {/* Right Sidebar */}
        <div className="space-y-6">
          {/* AI Assistant Card */}
          <GlassCard delay={0.5}>
            <div className="p-6 bg-gradient-to-br from-violet-500/5 to-fuchsia-500/5">
              <div className="flex items-start gap-3 mb-4">
                <div className="w-12 h-12 rounded-xl bg-gradient-to-br from-violet-500 to-fuchsia-500 flex items-center justify-center shadow-lg shadow-violet-500/30">
                  <Sparkles className="w-6 h-6 text-white" />
                </div>
                <div>
                  <h4 className="font-semibold text-slate-900 dark:text-white">Leo IA</h4>
                  <p className="text-xs text-slate-500 dark:text-slate-400">Assistant intelligent</p>
                </div>
              </div>

              <div className="bg-white/50 dark:bg-slate-900/50 rounded-xl p-4 mb-4">
                <p className="text-sm text-slate-700 dark:text-slate-300 leading-relaxed">
                  &quot;Vos retards sont en baisse de 15% cette semaine. Souhaitez-vous que j&apos;envoie un message de félicitations à l&apos;équipe ?&quot;
                </p>
              </div>

              <div className="flex gap-2">
                <button className="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-violet-500 to-fuchsia-500 text-white text-sm font-medium hover:shadow-lg hover:shadow-violet-500/30 transition-all">
                  Oui, envoyer
                </button>
                <button className="flex-1 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 text-sm font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                  Plus tard
                </button>
              </div>
            </div>
          </GlassCard>

          {/* Quick Actions */}
          <GlassCard delay={0.6}>
            <div className="p-6">
              <h4 className="font-semibold text-slate-900 dark:text-white mb-4">Actions rapides</h4>
              <div className="grid grid-cols-2 gap-3">
                {[
                  { icon: Users, label: 'Nouvel employé', color: 'bg-blue-500' },
                  { icon: Calendar, label: 'Congés', color: 'bg-emerald-500' },
                  { icon: TrendingUp, label: 'Rapports', color: 'bg-violet-500' },
                  { icon: Download, label: 'Export', color: 'bg-amber-500' },
                ].map((action, i) => (
                  <button
                    key={i}
                    className="flex flex-col items-center gap-2 p-4 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors group"
                  >
                    <div className={`w-10 h-10 rounded-xl ${action.color} flex items-center justify-center group-hover:scale-110 transition-transform`}>
                      <action.icon className="w-5 h-5 text-white" />
                    </div>
                    <span className="text-xs font-medium text-slate-600 dark:text-slate-400">{action.label}</span>
                  </button>
                ))}
              </div>
            </div>
          </GlassCard>

          {/* Mini Chart */}
          <GlassCard delay={0.7}>
            <div className="p-6">
              <div className="flex items-center justify-between mb-4">
                <h4 className="font-semibold text-slate-900 dark:text-white">Présence hebdo</h4>
                <span className="text-xs text-emerald-600 dark:text-emerald-400 font-medium">+12%</span>
              </div>
              <div className="flex items-end gap-2 h-32">
                {[65, 80, 75, 90, 85, 70, 88].map((height, i) => (
                  <div key={i} className="flex-1 flex flex-col items-center gap-1">
                    <motion.div
                      initial={{ height: 0 }}
                      animate={{ height: `${height}%` }}
                      transition={{ delay: 0.8 + i * 0.1, duration: 0.5 }}
                      className={`w-full rounded-t-lg ${
                        height > 80 ? 'bg-emerald-500' : height > 70 ? 'bg-emerald-400' : 'bg-emerald-300'
                      }`}
                    />
                    <span className="text-xs text-slate-400">{['L', 'M', 'M', 'J', 'V', 'S', 'D'][i]}</span>
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
