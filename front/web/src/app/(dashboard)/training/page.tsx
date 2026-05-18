'use client';

import { useEffect, useState, useCallback } from 'react';
import { motion } from 'framer-motion';
import { apiFetch } from '@/lib/api-client';
import { GraduationCap, Calendar, Users, Clock, BookOpen, Award } from 'lucide-react';

interface Training {
  id: number;
  title: string;
  description: string;
  trainer: string;
  start_date: string;
  end_date: string;
  status: string;
  max_participants: number;
  enrolled_count: number;
  category: string;
}

export default function TrainingPage() {
  const [trainings, setTrainings] = useState<Training[]>([]);
  const [loading, setLoading] = useState(true);
  const [tab, setTab] = useState<'upcoming' | 'completed'>('upcoming');

  const loadTrainings = useCallback(async () => {
    setLoading(true);
    try {
      const res = await apiFetch('/trainings');
      const data = await res.json();
      setTrainings(data.data || []);
    } catch {
      // silently handle
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { loadTrainings(); }, [loadTrainings]);

  const upcoming = trainings.filter(t => t.status === 'scheduled' || t.status === 'in_progress');
  const completed = trainings.filter(t => t.status === 'completed');
  const displayed = tab === 'upcoming' ? upcoming : completed;

  const statusBadge = (status: string) => {
    const map: Record<string, { class: string; label: string }> = {
      scheduled: { class: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400', label: 'Planifie' },
      in_progress: { class: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400', label: 'En cours' },
      completed: { class: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400', label: 'Termine' },
      cancelled: { class: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400', label: 'Annule' },
    };
    const style = map[status] || map.scheduled;
    return <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-semibold ${style.class}`}>{style.label}</span>;
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Formations</h1>
        <p className="text-sm text-slate-500 dark:text-slate-400">Suivi des formations et developpement des competences</p>
      </div>

      <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
        {[
          { label: 'Planifiees', value: upcoming.length, icon: Calendar, color: 'text-blue-600' },
          { label: 'Terminees', value: completed.length, icon: Award, color: 'text-emerald-600' },
          { label: 'Total', value: trainings.length, icon: GraduationCap, color: 'text-purple-600' },
          { label: 'Participants', value: trainings.reduce((s, t) => s + (t.enrolled_count || 0), 0), icon: Users, color: 'text-amber-600' },
        ].map((stat, i) => (
          <motion.div key={i} initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: i * 0.05 }} className="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4">
            <stat.icon className={`h-5 w-5 ${stat.color} mb-2`} />
            <p className="text-2xl font-bold text-slate-900 dark:text-white">{stat.value}</p>
            <p className="text-xs text-slate-500">{stat.label}</p>
          </motion.div>
        ))}
      </div>

      <div className="flex gap-2 border-b border-slate-200 dark:border-slate-700">
        <button onClick={() => setTab('upcoming')} className={`px-4 py-2.5 text-sm font-medium border-b-2 transition-colors ${tab === 'upcoming' ? 'border-emerald-500 text-emerald-600' : 'border-transparent text-slate-500 hover:text-slate-700'}`}>A venir ({upcoming.length})</button>
        <button onClick={() => setTab('completed')} className={`px-4 py-2.5 text-sm font-medium border-b-2 transition-colors ${tab === 'completed' ? 'border-emerald-500 text-emerald-600' : 'border-transparent text-slate-500 hover:text-slate-700'}`}>Terminees ({completed.length})</button>
      </div>

      {loading ? (
        <div className="text-center py-12 text-slate-400">Chargement...</div>
      ) : displayed.length === 0 ? (
        <div className="text-center py-16">
          <BookOpen className="h-12 w-12 text-slate-300 mx-auto mb-3" />
          <p className="text-slate-500">{tab === 'upcoming' ? 'Aucune formation planifiee' : 'Aucune formation terminee'}</p>
        </div>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {displayed.map((t, i) => (
            <motion.div
              key={t.id}
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: i * 0.03 }}
              className="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-5 hover:shadow-md transition-shadow"
            >
              <div className="flex items-start justify-between mb-3">
                <h3 className="font-bold text-slate-900 dark:text-white text-sm leading-tight">{t.title}</h3>
                {statusBadge(t.status)}
              </div>
              {t.description && <p className="text-xs text-slate-500 dark:text-slate-400 mb-3 line-clamp-2">{t.description}</p>}
              <div className="space-y-1.5 text-xs text-slate-500 dark:text-slate-400">
                <div className="flex items-center gap-1.5"><GraduationCap className="h-3 w-3" />{t.trainer}</div>
                <div className="flex items-center gap-1.5"><Calendar className="h-3 w-3" />{t.start_date} - {t.end_date}</div>
                <div className="flex items-center gap-1.5"><Users className="h-3 w-3" />{t.enrolled_count}/{t.max_participants} participants</div>
                {t.category && <div className="flex items-center gap-1.5"><Clock className="h-3 w-3" />{t.category}</div>}
              </div>
              <div className="mt-3 pt-3 border-t border-slate-100 dark:border-slate-700">
                <div className="h-1.5 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden">
                  <div className="h-full rounded-full bg-emerald-500 transition-all" style={{ width: `${Math.min(100, ((t.enrolled_count || 0) / (t.max_participants || 1)) * 100)}%` }} />
                </div>
              </div>
            </motion.div>
          ))}
        </div>
      )}
    </div>
  );
}
