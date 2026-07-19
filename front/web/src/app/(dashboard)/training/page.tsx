'use client';

import { useEffect, useState, useCallback } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { ApiError, apiFetch } from '@/lib/api-client';
import { GraduationCap, Calendar, Users, Clock, BookOpen, Award, ChevronDown, Plus, X, MapPin } from 'lucide-react';

interface TrainingSession {
  id: number;
  training_course_id: number;
  trainer_id?: number | null;
  external_trainer?: string | null;
  start_date: string | null;
  end_date: string | null;
  location?: string | null;
  status: string;
  notes?: string | null;
  enrollments?: Array<{ id: number; status: string }>;
}

interface TrainingCourse {
  id: number;
  title: string;
  description?: string | null;
  category?: string | null;
  type: string;
  provider?: string | null;
  duration_hours?: number | null;
  max_participants?: number | null;
  cost_per_participant?: number | null;
  currency?: string | null;
  sessions?: TrainingSession[];
}

interface CoursesPayload {
  data?: TrainingCourse[];
  meta?: { total?: number };
}

const TYPE_LABELS: Record<string, string> = {
  internal: 'Interne',
  external: 'Externe',
  online: 'En ligne',
  certification: 'Certification',
};

export default function TrainingPage() {
  const [courses, setCourses] = useState<TrainingCourse[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [expandedId, setExpandedId] = useState<number | null>(null);
  const [sessionsByCourse, setSessionsByCourse] = useState<Record<number, TrainingSession[]>>({});
  const [sessionsLoading, setSessionsLoading] = useState<number | null>(null);
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [creating, setCreating] = useState(false);
  const [newCourse, setNewCourse] = useState({
    title: '',
    description: '',
    category: '',
    type: 'internal',
    provider: '',
    duration_hours: '',
    max_participants: '',
  });

  const loadCourses = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiFetch('/training/courses?per_page=50');
      const data = (await res.json()) as CoursesPayload;
      setCourses(Array.isArray(data.data) ? data.data : []);
      setTotal(data.meta?.total ?? data.data?.length ?? 0);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Impossible de charger les formations.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { void loadCourses(); }, [loadCourses]);

  const toggleExpand = useCallback(async (courseId: number) => {
    if (expandedId === courseId) {
      setExpandedId(null);
      return;
    }
    setExpandedId(courseId);
    if (sessionsByCourse[courseId]) {
      return;
    }
    setSessionsLoading(courseId);
    try {
      const res = await apiFetch(`/training/courses/${courseId}/sessions`);
      const data = await res.json() as { data?: TrainingSession[] };
      setSessionsByCourse((prev) => ({ ...prev, [courseId]: Array.isArray(data.data) ? data.data : [] }));
    } catch {
      setSessionsByCourse((prev) => ({ ...prev, [courseId]: [] }));
    } finally {
      setSessionsLoading(null);
    }
  }, [expandedId, sessionsByCourse]);

  const handleCreate = async () => {
    if (!newCourse.title.trim()) return;
    setCreating(true);
    try {
      await apiFetch('/training/courses', {
        method: 'POST',
        body: JSON.stringify({
          title: newCourse.title,
          description: newCourse.description || null,
          category: newCourse.category || null,
          type: newCourse.type,
          provider: newCourse.provider || null,
          duration_hours: newCourse.duration_hours ? Number(newCourse.duration_hours) : null,
          max_participants: newCourse.max_participants ? Number(newCourse.max_participants) : null,
        }),
      });
      setShowCreateModal(false);
      setNewCourse({ title: '', description: '', category: '', type: 'internal', provider: '', duration_hours: '', max_participants: '' });
      await loadCourses();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Impossible de creer la formation.');
    } finally {
      setCreating(false);
    }
  };

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

  const categoryCounts = courses.reduce<Record<string, number>>((acc, c) => {
    const key = c.category || 'Sans categorie';
    acc[key] = (acc[key] || 0) + 1;
    return acc;
  }, {});

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Formations</h1>
          <p className="text-sm text-slate-500 dark:text-slate-400">Catalogue de formations et sessions associees (source: /training/courses)</p>
        </div>
        <button
          onClick={() => setShowCreateModal(true)}
          className="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700"
        >
          <Plus className="h-4 w-4" /> Nouvelle formation
        </button>
      </div>

      {error ? (
        <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>
      ) : null}

      <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
        {[
          { label: 'Total formations', value: total, icon: GraduationCap, color: 'text-purple-600' },
          { label: 'Categories', value: Object.keys(categoryCounts).length, icon: BookOpen, color: 'text-blue-600' },
          { label: 'Certifications', value: courses.filter((c) => c.type === 'certification').length, icon: Award, color: 'text-emerald-600' },
          { label: 'Capacite totale', value: courses.reduce((s, c) => s + (c.max_participants || 0), 0), icon: Users, color: 'text-amber-600' },
        ].map((stat, i) => (
          <motion.div key={stat.label} initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: i * 0.05 }} className="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4">
            <stat.icon className={`h-5 w-5 ${stat.color} mb-2`} />
            <p className="text-2xl font-bold text-slate-900 dark:text-white">{stat.value}</p>
            <p className="text-xs text-slate-500">{stat.label}</p>
          </motion.div>
        ))}
      </div>

      {loading ? (
        <div className="text-center py-12 text-slate-400">Chargement...</div>
      ) : courses.length === 0 ? (
        <div className="text-center py-16">
          <BookOpen className="h-12 w-12 text-slate-300 mx-auto mb-3" />
          <p className="text-slate-500">Aucune formation au catalogue pour le moment.</p>
        </div>
      ) : (
        <div className="space-y-3">
          {courses.map((course, i) => {
            const sessions = sessionsByCourse[course.id];
            const isExpanded = expandedId === course.id;
            return (
              <motion.div
                key={course.id}
                initial={{ opacity: 0, y: 10 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: i * 0.02 }}
                className="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 overflow-hidden"
              >
                <button
                  onClick={() => toggleExpand(course.id)}
                  className="w-full flex items-center justify-between gap-3 p-5 text-left hover:bg-slate-50 dark:hover:bg-slate-900/40"
                >
                  <div className="flex-1">
                    <div className="flex items-center gap-2 mb-1">
                      <h3 className="font-bold text-slate-900 dark:text-white text-sm">{course.title}</h3>
                      <span className="rounded-full bg-slate-100 dark:bg-slate-700 px-2 py-0.5 text-[10px] font-semibold uppercase text-slate-500 dark:text-slate-300">
                        {TYPE_LABELS[course.type] ?? course.type}
                      </span>
                    </div>
                    {course.description && <p className="text-xs text-slate-500 dark:text-slate-400 line-clamp-1">{course.description}</p>}
                    <div className="mt-2 flex flex-wrap items-center gap-3 text-xs text-slate-500 dark:text-slate-400">
                      {course.provider && <span className="flex items-center gap-1"><GraduationCap className="h-3 w-3" />{course.provider}</span>}
                      {course.duration_hours ? <span className="flex items-center gap-1"><Clock className="h-3 w-3" />{course.duration_hours}h</span> : null}
                      {course.max_participants ? <span className="flex items-center gap-1"><Users className="h-3 w-3" />{course.max_participants} places</span> : null}
                      {course.category ? <span className="rounded-full bg-slate-100 dark:bg-slate-700 px-2 py-0.5">{course.category}</span> : null}
                    </div>
                  </div>
                  <ChevronDown className={`h-4 w-4 shrink-0 text-slate-400 transition-transform ${isExpanded ? 'rotate-180' : ''}`} />
                </button>

                <AnimatePresence initial={false}>
                  {isExpanded && (
                    <motion.div
                      initial={{ height: 0, opacity: 0 }}
                      animate={{ height: 'auto', opacity: 1 }}
                      exit={{ height: 0, opacity: 0 }}
                      className="border-t border-slate-100 dark:border-slate-700"
                    >
                      <div className="p-5 space-y-2">
                        <p className="text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Sessions programmees</p>
                        {sessionsLoading === course.id ? (
                          <p className="text-sm text-slate-400">Chargement des sessions...</p>
                        ) : !sessions || sessions.length === 0 ? (
                          <p className="text-sm text-slate-400">Aucune session pour cette formation.</p>
                        ) : (
                          sessions.map((session) => (
                            <div key={session.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40 px-3 py-2">
                              <div className="flex items-center gap-3 text-xs text-slate-600 dark:text-slate-300">
                                <span className="flex items-center gap-1"><Calendar className="h-3 w-3" />{session.start_date ?? '—'} au {session.end_date ?? '—'}</span>
                                {session.location && <span className="flex items-center gap-1"><MapPin className="h-3 w-3" />{session.location}</span>}
                                <span className="flex items-center gap-1"><Users className="h-3 w-3" />{session.enrollments?.length ?? 0} inscrits</span>
                              </div>
                              {statusBadge(session.status)}
                            </div>
                          ))
                        )}
                      </div>
                    </motion.div>
                  )}
                </AnimatePresence>
              </motion.div>
            );
          })}
        </div>
      )}

      <AnimatePresence>
        {showCreateModal && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
          >
            <motion.div
              initial={{ opacity: 0, scale: 0.95 }}
              animate={{ opacity: 1, scale: 1 }}
              exit={{ opacity: 0, scale: 0.95 }}
              className="w-full max-w-md rounded-2xl bg-white dark:bg-slate-800 p-6 shadow-xl"
            >
              <div className="flex items-center justify-between mb-4">
                <h2 className="text-lg font-bold text-slate-900 dark:text-white">Nouvelle formation</h2>
                <button onClick={() => setShowCreateModal(false)} className="text-slate-400 hover:text-slate-600"><X className="h-5 w-5" /></button>
              </div>
              <div className="space-y-3">
                <input
                  type="text"
                  placeholder="Titre *"
                  value={newCourse.title}
                  onChange={(e) => setNewCourse({ ...newCourse, title: e.target.value })}
                  className="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 px-3 py-2 text-sm text-slate-900 dark:text-white"
                />
                <textarea
                  placeholder="Description"
                  value={newCourse.description}
                  onChange={(e) => setNewCourse({ ...newCourse, description: e.target.value })}
                  className="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 px-3 py-2 text-sm text-slate-900 dark:text-white"
                  rows={2}
                />
                <div className="grid grid-cols-2 gap-3">
                  <input
                    type="text"
                    placeholder="Categorie"
                    value={newCourse.category}
                    onChange={(e) => setNewCourse({ ...newCourse, category: e.target.value })}
                    className="rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 px-3 py-2 text-sm text-slate-900 dark:text-white"
                  />
                  <select
                    value={newCourse.type}
                    onChange={(e) => setNewCourse({ ...newCourse, type: e.target.value })}
                    className="rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 px-3 py-2 text-sm text-slate-900 dark:text-white"
                  >
                    <option value="internal">Interne</option>
                    <option value="external">Externe</option>
                    <option value="online">En ligne</option>
                    <option value="certification">Certification</option>
                  </select>
                </div>
                <div className="grid grid-cols-2 gap-3">
                  <input
                    type="text"
                    placeholder="Prestataire"
                    value={newCourse.provider}
                    onChange={(e) => setNewCourse({ ...newCourse, provider: e.target.value })}
                    className="rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 px-3 py-2 text-sm text-slate-900 dark:text-white"
                  />
                  <input
                    type="number"
                    placeholder="Duree (h)"
                    value={newCourse.duration_hours}
                    onChange={(e) => setNewCourse({ ...newCourse, duration_hours: e.target.value })}
                    className="rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 px-3 py-2 text-sm text-slate-900 dark:text-white"
                  />
                </div>
                <input
                  type="number"
                  placeholder="Participants max"
                  value={newCourse.max_participants}
                  onChange={(e) => setNewCourse({ ...newCourse, max_participants: e.target.value })}
                  className="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 px-3 py-2 text-sm text-slate-900 dark:text-white"
                />
              </div>
              <div className="mt-5 flex gap-3">
                <button onClick={() => setShowCreateModal(false)} className="flex-1 rounded-lg border border-slate-200 dark:border-slate-700 px-4 py-2 text-sm hover:bg-slate-50 dark:hover:bg-slate-700">Annuler</button>
                <button
                  onClick={handleCreate}
                  disabled={!newCourse.title.trim() || creating}
                  className="flex-1 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
                >
                  {creating ? 'Creation...' : 'Creer'}
                </button>
              </div>
            </motion.div>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
}
