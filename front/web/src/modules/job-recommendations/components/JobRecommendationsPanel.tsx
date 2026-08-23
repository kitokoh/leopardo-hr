'use client';

import { useEffect, useState } from 'react';
import { ArrowUpRight, BriefcaseBusiness, Loader2, Sparkles } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { getPreferredJobRecommendationsCopy, type StoredAuthUser } from '@/lib/i18n';

type ApplicationEvent = { to_status: string; note?: string | null; changed_at?: string | null };
type ApplicationNotification = { id: string; message: string; status: string; read?: boolean; created_at?: string };
type Application = { id: number; job?: { title?: string | null }; status: string; resume_name?: string | null; applied_at?: string | null; status_history?: ApplicationEvent[] };
type ResumeVersion = { id: string; name: string; uploaded_at?: string };

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
  const copy = getPreferredJobRecommendationsCopy();
  const [items, setItems] = useState<Recommendation[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [applying, setApplying] = useState<number | null>(null);
  const [applied, setApplied] = useState<number[]>([]);
  const [coverLetters, setCoverLetters] = useState<Record<number, string>>({});
  const [applications, setApplications] = useState<Application[]>([]);
  const [notifications, setNotifications] = useState<ApplicationNotification[]>([]);
  const [resumeName, setResumeName] = useState<string | null>((user.job_search_preferences as { resume_name?: string } | undefined)?.resume_name ?? null);
  const [uploadingResume, setUploadingResume] = useState(false);
  const resumeVersions = user.job_search_preferences?.resumes as ResumeVersion[] | undefined;
  const [selectedResumeId, setSelectedResumeId] = useState<string | null>(user.job_search_preferences?.resume_id as string | null ?? null);

  useEffect(() => {
    let cancelled = false;
    async function load() {
      try {
        const response = await apiFetch('/user/job-recommendations?limit=6');
        const payload = await response.json() as { data?: Recommendation[]; error?: string };
        if (!response.ok) throw new Error(payload.error ?? copy.unavailable);
        if (!cancelled) setItems(payload.data ?? []);
        const applicationsResponse = await apiFetch('/user/job-applications');
        const applicationsPayload = await applicationsResponse.json() as { data?: Application[] };
        if (!cancelled && applicationsResponse.ok) setApplications(applicationsPayload.data ?? []);
        const notificationsResponse = await apiFetch('/user/job-application-notifications');
        const notificationsPayload = await notificationsResponse.json() as { data?: ApplicationNotification[] };
        if (!cancelled && notificationsResponse.ok) setNotifications((notificationsPayload.data ?? []).filter((notification) => !notification.read));
      } catch (cause) {
        if (!cancelled) setError(cause instanceof Error ? cause.message : copy.unavailable);
      } finally {
        if (!cancelled) setLoading(false);
      }
    }
    void load();
    return () => { cancelled = true; };
  }, []);

  if (!user.personal_statuses?.includes('job_seeker')) return null;

  const uploadResume = async (file: File) => {
    if (!['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'].includes(file.type) || file.size > 5 * 1024 * 1024) {
      setError(copy.resumeFormatError);
      return;
    }
    setUploadingResume(true);
    try {
      const form = new FormData();
      form.set('resume', file);
      const response = await apiFetch('/user/job-search-profile/resume', { method: 'POST', body: form });
      const payload = await response.json() as { data?: { resume_name?: string } };
      if (!response.ok) throw new Error(copy.resumeUploadError);
      setResumeName(payload.data?.resume_name ?? file.name);
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : copy.resumeUploadError);
    } finally {
      setUploadingResume(false);
    }
  };

  const apply = async (job: Recommendation) => {
    const companySlug = job.company?.slug;
    if (!companySlug) return;
    setApplying(job.id);
    try {
      const response = await apiFetch(`/user/job-applications/${encodeURIComponent(companySlug)}/${job.id}`, {
        method: 'POST',
        body: JSON.stringify({ cover_letter: coverLetters[job.id] ?? '', ...(selectedResumeId ? { resume_id: selectedResumeId } : {}) }),
        headers: { 'Content-Type': 'application/json' },
      });
      const payload = await response.json() as { error?: string };
      if (!response.ok) throw new Error(payload.error === 'ALREADY_APPLIED' ? copy.applicationAlreadySent : copy.applicationError);
      setApplied((current) => [...current, job.id]);
      const applicationsResponse = await apiFetch('/user/job-applications');
      const applicationsPayload = await applicationsResponse.json() as { data?: Application[] };
      if (applicationsResponse.ok) setApplications(applicationsPayload.data ?? []);
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : copy.applicationError);
    } finally {
      setApplying(null);
    }
  };

  return (
    <section className="mb-8 rounded-3xl border border-indigo-100 bg-gradient-to-br from-indigo-50 via-white to-emerald-50 p-6 shadow-sm" aria-labelledby="job-recommendations-title">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <p className="flex items-center gap-2 text-xs font-black uppercase tracking-[0.18em] text-indigo-700"><Sparkles className="h-4 w-4" aria-hidden="true" /> {copy.activeSearch}</p>
          <h2 id="job-recommendations-title" className="mt-2 text-2xl font-black text-slate-950">{copy.heading}</h2>
          <p className="mt-1 max-w-2xl text-sm leading-6 text-slate-600">{copy.description}</p>
          <div className="mt-4 flex flex-wrap items-center gap-3"><label className="cursor-pointer rounded-lg border border-indigo-200 bg-white px-3 py-2 text-sm font-bold text-indigo-800 hover:bg-indigo-50"><input type="file" accept=".pdf,.doc,.docx" className="sr-only" disabled={uploadingResume} onChange={(event) => { const file = event.target.files?.[0]; if (file) void uploadResume(file); event.currentTarget.value = ''; }} />{uploadingResume ? copy.uploadingResume : copy.uploadResume}</label>{resumeVersions && resumeVersions.length > 0 && <select aria-label={copy.resumeVersionLabel} value={selectedResumeId ?? ''} onChange={(event) => setSelectedResumeId(event.target.value || null)} className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700"><option value="">{copy.activeResume}</option>{resumeVersions.map((resume) => <option key={resume.id} value={resume.id}>{resume.name}</option>)}</select>}{resumeName && <span className="text-xs font-semibold text-slate-500">{copy.activeResumePrefix} {resumeName}</span>}</div>
        </div>
        <BriefcaseBusiness className="h-8 w-8 text-indigo-600" aria-hidden="true" />
      </div>
      {loading && <div className="mt-6 flex items-center gap-2 text-sm text-slate-600"><Loader2 className="h-4 w-4 animate-spin" /> {copy.searching}</div>}
      {error && <p className="mt-5 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">{error}</p>}
      {!loading && !error && items.length === 0 && <p className="mt-5 rounded-xl bg-white/70 px-4 py-3 text-sm text-slate-600">{copy.empty}</p>}
      {applications.length > 0 && (
        <div className="mt-6 rounded-2xl border border-slate-200 bg-white/70 p-4">
          <h3 className="font-bold text-slate-950">{copy.applicationsTitle}</h3>
          <div className="mt-3 space-y-4">
            {applications.map((application) => {
              const history = application.status_history ?? [];
              const currentStatus = application.status;
              const stages = ['new', 'screening', 'interview', 'offer', 'hired'];
              const currentIndex = stages.indexOf(currentStatus);
              return (
                <div key={application.id} className="rounded-xl border border-slate-100 p-3">
                  <div className="flex items-center justify-between gap-3"><span className="text-sm font-bold text-slate-900">{application.job?.title ?? copy.applicationFallback(application.id)}</span><span className="text-xs font-black uppercase text-indigo-700">{currentStatus}</span></div>
                  <div className="mt-3 grid grid-cols-5 gap-1">{stages.map((stage, index) => <span key={stage} className={`h-1.5 rounded-full ${index <= currentIndex ? 'bg-emerald-500' : 'bg-slate-200'}`} title={stage} />)}</div>
                  <p className="mt-2 text-xs text-slate-500">{history.length > 0 ? (history[history.length - 1]?.note ?? copy.lastUpdate) : copy.applicationSent}{application.resume_name ? ` · ${copy.resumePrefix} ${application.resume_name}` : ''}</p>
                </div>
              );
            })}
          </div>
        </div>
      )}
      {notifications.length > 0 && (
        <div className="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-4">
          <div className="flex items-center justify-between gap-3"><h3 className="font-bold text-amber-950">{copy.notificationsTitle}</h3><button type="button" onClick={async () => { await apiFetch('/user/job-application-notifications/read', { method: 'PATCH' }); setNotifications([]); }} className="text-xs font-bold text-amber-800 underline">{copy.markAllRead}</button></div>
          <div className="mt-2 space-y-1">{notifications.slice(0, 3).map((notification) => <p key={notification.id} className="text-sm text-amber-900">{notification.message}</p>)}</div>
        </div>
      )}
      <div className="mt-5 grid gap-3 lg:grid-cols-2">
        {items.map((job) => (
          <article key={`${job.company?.slug ?? 'company'}-${job.id}`} className="rounded-2xl border border-white bg-white/90 p-4 shadow-sm">
            <div className="flex items-start justify-between gap-3">
              <div>
                <h3 className="font-bold text-slate-950">{job.title}</h3>
                <p className="mt-1 text-xs font-semibold text-slate-500">{job.company?.name ?? copy.companyFallback} · {job.location || copy.locationFallback}</p>
              </div>
              <span className="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-black text-emerald-800">{job.match_score ?? 0}%</span>
            </div>
            <p className="mt-3 line-clamp-2 text-sm leading-5 text-slate-600">{job.ai_reason ?? job.match_reasons?.[0] ?? job.description}</p>
            <textarea
              value={coverLetters[job.id] ?? ''}
              onChange={(event) => setCoverLetters((current) => ({ ...current, [job.id]: event.target.value }))}
              placeholder={copy.messagePlaceholder}
              className="mt-4 min-h-16 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"
              maxLength={5000}
            />
            <div className="mt-3 flex flex-wrap items-center justify-between gap-3">
              <span className="text-xs font-semibold uppercase tracking-wide text-slate-400">{job.contract_type ?? copy.contractFallback}{job.remote_policy ? ` · ${job.remote_policy}` : ''}</span>
              <div className="flex items-center gap-3">
                {job.public_url && <a href={job.public_url} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 text-sm font-bold text-indigo-700 hover:text-indigo-900">{copy.viewOffer} <ArrowUpRight className="h-4 w-4" aria-hidden="true" /></a>}
                <button type="button" onClick={() => void apply(job)} disabled={applying === job.id || applied.includes(job.id)} className="rounded-lg bg-slate-950 px-3 py-2 text-xs font-bold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50">
                  {applied.includes(job.id) ? copy.applicationSent : applying === job.id ? copy.sending : copy.apply}
                </button>
              </div>
            </div>
          </article>
        ))}
      </div>
    </section>
  );
}
