'use client';

import { useEffect, useState } from 'react';
import { ArrowUpRight, BriefcaseBusiness, Loader2, Sparkles } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import type { StoredAuthUser } from '@/lib/i18n';

type Recommendation = {
  id: number;
  title: string;
  description?: string;
  location?: string;
  contract_type?: string;
  remote_policy?: string;
  match_score?: number;
  match_reasons?: string[];
  ai_reason?: string;
  company?: { name?: string; slug?: string };
  public_url?: string;
};

export function JobRecommendationsPanel({ user }: { user: StoredAuthUser }) {
  const [items, setItems] = useState<Recommendation[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [applying, setApplying] = useState<number | null>(null);
  const [applied, setApplied] = useState<number[]>([]);
  const [coverLetters, setCoverLetters] = useState<Record<number, string>>({});

  useEffect(() => {
    let cancelled = false;
    async function load() {
      try {
        const response = await apiFetch('/user/job-recommendations?limit=6');
        const payload = await response.json() as { data?: Recommendation[]; error?: string };
        if (!response.ok) throw new Error(payload.error ?? 'Recommandations indisponibles.');
        if (!cancelled) setItems(payload.data ?? []);
      } catch (cause) {
        if (!cancelled) setError(cause instanceof Error ? cause.message : 'Recommandations indisponibles.');
      } finally {
        if (!cancelled) setLoading(false);
      }
    }
    void load();
    return () => { cancelled = true; };
  }, []);

  if (!user.personal_statuses?.includes('job_seeker')) return null;

  const apply = async (job: Recommendation) => {
    const companySlug = job.company?.slug;
    if (!companySlug) return;
    setApplying(job.id);
    try {
      const response = await apiFetch(`/user/job-applications/${encodeURIComponent(companySlug)}/${job.id}`, {
        method: 'POST',
        body: JSON.stringify({ cover_letter: coverLetters[job.id] ?? '' }),
        headers: { 'Content-Type': 'application/json' },
      });
      const payload = await response.json() as { error?: string };
      if (!response.ok) throw new Error(payload.error === 'ALREADY_APPLIED' ? 'Vous avez déjà postulé à cette offre.' : 'Candidature impossible.');
      setApplied((current) => [...current, job.id]);
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : 'Candidature impossible.');
    } finally {
      setApplying(null);
    }
  };

  return (
    <section className="mb-8 rounded-3xl border border-indigo-100 bg-gradient-to-br from-indigo-50 via-white to-emerald-50 p-6 shadow-sm" aria-labelledby="job-recommendations-title">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <p className="flex items-center gap-2 text-xs font-black uppercase tracking-[0.18em] text-indigo-700"><Sparkles className="h-4 w-4" aria-hidden="true" /> Recherche active</p>
          <h2 id="job-recommendations-title" className="mt-2 text-2xl font-black text-slate-950">Des offres qui correspondent à votre profil</h2>
          <p className="mt-1 max-w-2xl text-sm leading-6 text-slate-600">Les offres sont d’abord filtrées par vos préférences, puis l’IA peut affiner le classement. Les raisons affichées restent liées aux informations du profil et de l’offre.</p>
        </div>
        <BriefcaseBusiness className="h-8 w-8 text-indigo-600" aria-hidden="true" />
      </div>
      {loading && <div className="mt-6 flex items-center gap-2 text-sm text-slate-600"><Loader2 className="h-4 w-4 animate-spin" /> Recherche des offres…</div>}
      {error && <p className="mt-5 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">{error}</p>}
      {!loading && !error && items.length === 0 && <p className="mt-5 rounded-xl bg-white/70 px-4 py-3 text-sm text-slate-600">Aucune offre publiée ne correspond encore à vos préférences.</p>}
      <div className="mt-5 grid gap-3 lg:grid-cols-2">
        {items.map((job) => (
          <article key={`${job.company?.slug ?? 'company'}-${job.id}`} className="rounded-2xl border border-white bg-white/90 p-4 shadow-sm">
            <div className="flex items-start justify-between gap-3">
              <div>
                <h3 className="font-bold text-slate-950">{job.title}</h3>
                <p className="mt-1 text-xs font-semibold text-slate-500">{job.company?.name ?? 'Entreprise'} · {job.location || 'Localisation non précisée'}</p>
              </div>
              <span className="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-black text-emerald-800">{job.match_score ?? 0}%</span>
            </div>
            <p className="mt-3 line-clamp-2 text-sm leading-5 text-slate-600">{job.ai_reason ?? job.match_reasons?.[0] ?? job.description}</p>
            <textarea
              value={coverLetters[job.id] ?? ''}
              onChange={(event) => setCoverLetters((current) => ({ ...current, [job.id]: event.target.value }))}
              placeholder="Ajouter un message (facultatif)"
              className="mt-4 min-h-16 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"
              maxLength={5000}
            />
            <div className="mt-3 flex flex-wrap items-center justify-between gap-3">
              <span className="text-xs font-semibold uppercase tracking-wide text-slate-400">{job.contract_type ?? 'Contrat'}{job.remote_policy ? ` · ${job.remote_policy}` : ''}</span>
              <div className="flex items-center gap-3">
                {job.public_url && <a href={job.public_url} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 text-sm font-bold text-indigo-700 hover:text-indigo-900">Voir l’offre <ArrowUpRight className="h-4 w-4" aria-hidden="true" /></a>}
                <button type="button" onClick={() => void apply(job)} disabled={applying === job.id || applied.includes(job.id)} className="rounded-lg bg-slate-950 px-3 py-2 text-xs font-bold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50">
                  {applied.includes(job.id) ? 'Candidature envoyée' : applying === job.id ? 'Envoi…' : 'Postuler'}
                </button>
              </div>
            </div>
          </article>
        ))}
      </div>
    </section>
  );
}
