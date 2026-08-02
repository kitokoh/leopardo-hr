'use client';

import { useEffect, useState, useCallback } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { ApiError, apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
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
      scheduled: { class: 'bg-info/15 text-info', label: 'Planifie' },
      in_progress: { class: 'bg-amber-50 text-amber-700', label: 'En cours' },
      completed: { class: 'bg-emerald-50 text-emerald-700', label: 'Termine' },
      cancelled: { class: 'bg-red-50 text-red-700', label: 'Annule' },
    };
    const style = map[status] || map.scheduled;
    return <span className={`inline-flex rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider ${style.class}`}>{style.label}</span>;
  };

  const categoryCounts = courses.reduce<Record<string, number>>((acc, c) => {
    const key = c.category || 'Sans categorie';
    acc[key] = (acc[key] || 0) + 1;
    return acc;
  }, {});

  const statCards = [
    { label: 'Total formations', value: total, icon: GraduationCap, accent: 'text-ia-dark bg-ia-light' },
    { label: 'Categories', value: Object.keys(categoryCounts).length, icon: BookOpen, accent: 'text-security-dark bg-security-light' },
    { label: 'Certifications', value: courses.filter((c) => c.type === 'certification').length, icon: Award, accent: 'text-emerald-600 bg-emerald-50' },
    { label: 'Capacite totale', value: courses.reduce((s, c) => s + (c.max_participants || 0), 0), icon: Users, accent: 'text-amber-600 bg-amber-50' },
  ];

  return (
    <>
      <ModulePageShell
        title="Formations"
        subtitle="Catalogue de formations et sessions associees, connecte a /training/courses de l'API RH."
        accentClassName="bg-gradient-to-br from-finance-light via-white to-white"
      >
        <div className="flex justify-end">
          <button
            onClick={() => setShowCreateModal(true)}
            className="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brand-700"
          >
            <Plus className="h-4 w-4" /> Nouvelle formation
          </button>
        </div>

        {error ? (
          <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>
        ) : null}

        <section className="grid grid-cols-2 gap-4 sm:grid-cols-4">
          {statCards.map((stat, i) => (
            <motion.div
              key={stat.label}
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: i * 0.05 }}
              className="rounded-2xl border border-app-border bg-white p-5 shadow-sm"
            >
              <div className={`mb-3 inline-flex h-10 w-10 items-center justify-center rounded-xl ${stat.accent}`}>
                <stat.icon className="h-5 w-5" />
              </div>
              <p className="text-2xl font-black text-slate-950">{stat.value}</p>
              <p className="text-xs font-bold uppercase tracking-widest text-slate-400">{stat.label}</p>
            </motion.div>
          ))}
        </section>

        {loading ? (
          <div className="py-12 text-center text-sm text-slate-500">Chargement...</div>
        ) : courses.length === 0 ? (
          <section className="rounded-3xl border border-dashed border-app-border bg-white py-16 text-center">
            <BookOpen className="mx-auto mb-3 h-12 w-12 text-slate-300" />
            <p className="text-sm text-slate-500">Aucune formation au catalogue pour le moment.</p>
          </section>
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
                  className="overflow-hidden rounded-2xl border border-app-border bg-white shadow-sm"
                >
                  <button
                    onClick={() => toggleExpand(course.id)}
                    className="flex w-full items-center justify-between gap-3 p-5 text-left transition hover:bg-transparent/60"
                  >
                    <div className="flex-1">
                      <div className="mb-1 flex items-center gap-2">
                        <h3 className="text-sm font-bold text-slate-950">{course.title}</h3>
                        <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                          {TYPE_LABELS[course.type] ?? course.type}
                        </span>
                      </div>
                      {course.description ? <p className="line-clamp-1 text-xs text-slate-500">{course.description}</p> : null}
                      <div className="mt-2 flex flex-wrap items-center gap-3 text-xs text-slate-500">
                        {course.provider ? <span className="flex items-center gap-1"><GraduationCap className="h-3 w-3" />{course.provider}</span> : null}
                        {course.duration_hours ? <span className="flex items-center gap-1"><Clock className="h-3 w-3" />{course.duration_hours}h</span> : null}
                        {course.max_participants ? <span className="flex items-center gap-1"><Users className="h-3 w-3" />{course.max_participants} places</span> : null}
                        {course.category ? <span className="rounded-full bg-slate-100 px-2 py-0.5">{course.category}</span> : null}
                      </div>
                    </div>
                    <ChevronDown className={`h-4 w-4 shrink-0 text-slate-400 transition-transform ${isExpanded ? 'rotate-180' : ''}`} />
                  </button>

                  <AnimatePresence initial={false}>
                    {isExpanded ? (
                      <motion.div
                        initial={{ height: 0, opacity: 0 }}
                        animate={{ height: 'auto', opacity: 1 }}
                        exit={{ height: 0, opacity: 0 }}
                        className="border-t border-app-border"
                      >
                        <div className="space-y-2 p-5">
                          <p className="mb-2 text-xs font-bold uppercase tracking-wider text-slate-400">Sessions programmees</p>
                          {sessionsLoading === course.id ? (
                            <p className="text-sm text-slate-400">Chargement des sessions...</p>
                          ) : !sessions || sessions.length === 0 ? (
                            <p className="text-sm text-slate-400">Aucune session pour cette formation.</p>
                          ) : (
                            sessions.map((session) => (
                              <div key={session.id} className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-app-border bg-transparent px-3 py-2">
                                <div className="flex items-center gap-3 text-xs text-slate-600">
                                  <span className="flex items-center gap-1"><Calendar className="h-3 w-3" />{session.start_date ?? 'â€”'} au {session.end_date ?? 'â€”'}</span>
                                  {session.location ? <span className="flex items-center gap-1"><MapPin className="h-3 w-3" />{session.location}</span> : null}
                                  <span className="flex items-center gap-1"><Users className="h-3 w-3" />{session.enrollments?.length ?? 0} inscrits</span>
                                </div>
                                {statusBadge(session.status)}
                              </div>
                            ))
                          )}
                        </div>
                      </motion.div>
                    ) : null}
                  </AnimatePresence>
                </motion.div>
              );
            })}
          </div>
        )}
      </ModulePageShell>

      <AnimatePresence>
        {showCreateModal ? (
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
              className="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl"
            >
              <div className="mb-4 flex items-center justify-between">
                <h2 className="text-lg font-black text-slate-950">Nouvelle formation</h2>
                <button onClick={() => setShowCreateModal(false)} className="text-slate-400 hover:text-slate-600"><X className="h-5 w-5" /></button>
              </div>
              <div className="space-y-3">
                <input
                  type="text"
                  placeholder="Titre *"
                  value={newCourse.title}
                  onChange={(e) => setNewCourse({ ...newCourse, title: e.target.value })}
                  className="w-full rounded-xl border border-app-border bg-transparent px-3 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-500"
                />
                <textarea
                  placeholder="Description"
                  value={newCourse.description}
                  onChange={(e) => setNewCourse({ ...newCourse, description: e.target.value })}
                  className="w-full rounded-xl border border-app-border bg-transparent px-3 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-500"
                  rows={2}
                />
                <div className="grid grid-cols-2 gap-3">
                  <input
                    type="text"
                    placeholder="Categorie"
                    value={newCourse.category}
                    onChange={(e) => setNewCourse({ ...newCourse, category: e.target.value })}
                    className="rounded-xl border border-app-border bg-transparent px-3 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-500"
                  />
                  <select
                    value={newCourse.type}
                    onChange={(e) => setNewCourse({ ...newCourse, type: e.target.value })}
                    className="rounded-xl border border-app-border bg-transparent px-3 py-2 text-sm text-slate-900"
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
                    className="rounded-xl border border-app-border bg-transparent px-3 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-500"
                  />
                  <input
                    type="number"
                    placeholder="Duree (h)"
                    value={newCourse.duration_hours}
                    onChange={(e) => setNewCourse({ ...newCourse, duration_hours: e.target.value })}
                    className="rounded-xl border border-app-border bg-transparent px-3 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-500"
                  />
                </div>
                <input
                  type="number"
                  placeholder="Participants max"
                  value={newCourse.max_participants}
                  onChange={(e) => setNewCourse({ ...newCourse, max_participants: e.target.value })}
                  className="w-full rounded-xl border border-app-border bg-transparent px-3 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-500"
                />
              </div>
              <div className="mt-5 flex gap-3">
                <button onClick={() => setShowCreateModal(false)} className="flex-1 rounded-xl border border-app-border px-4 py-2 text-sm font-bold text-slate-700 hover:bg-transparent">Annuler</button>
                <button
                  onClick={handleCreate}
                  disabled={!newCourse.title.trim() || creating}
                  className="flex-1 rounded-xl bg-brand-600 px-4 py-2 text-sm font-bold text-white hover:bg-brand-700 disabled:opacity-50"
                >
                  {creating ? 'Creation...' : 'Creer'}
                </button>
              </div>
            </motion.div>
          </motion.div>
        ) : null}
      </AnimatePresence>
    </>
  );
}

