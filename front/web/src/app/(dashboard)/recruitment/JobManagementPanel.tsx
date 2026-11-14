'use client';

import { useCallback, useEffect, useState } from 'react';
import {
  Archive,
  Briefcase,
  CheckCircle2,
  Loader2,
  Plus,
  RotateCcw,
  Send,
  X,
  XCircle,
} from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { getRecruitmentCopy } from '@/lib/i18n/recruitment';

/**
 * Gestion des offres d'emploi (ATS #9461).
 *
 * Le backend exposait déjà le CRUD complet (`/recruitment/jobs`, `/publish`,
 * `/close`, `/archive`, `/reopen`) mais AUCUN écran ne l'appelait : un tenant
 * ne pouvait publier une offre qu'en appelant l'API à la main. Sans cette
 * pièce, le parcours « recrutement de A à Z » s'arrêtait à la première étape.
 *
 * Le cycle de vie est explicite à l'écran, parce qu'il ne l'est pas dans les
 * données : `draft` n'est pas visible du public, `published` l'est, `closed`
 * reste consultable mais refuse les candidatures, `archived` disparaît. Un
 * recruteur qui ne voit pas cette différence publie une offre brouillon et
 * s'étonne qu'elle n'apparaisse pas sur la vitrine.
 *
 * Les libellés viennent du catalogue i18n (`@/lib/i18n/recruitment`) : un
 * écran RH francophone uniquement est inutilisable pour un tenant arabophone
 * ou turcophone, et la garde CI refuse les nouvelles chaînes en dur.
 */

type JobStatus = 'draft' | 'published' | 'closed' | 'archived';

interface Job {
  id: number;
  title: string;
  status: JobStatus;
  location: string | null;
  contract_type: string | null;
  remote_policy: string | null;
  skills_required: string[] | null;
  published_at: string | null;
  closes_at: string | null;
  applicants_count?: number;
}

const STATUS_STYLES: Record<JobStatus, string> = {
  draft: 'bg-slate-100 text-slate-600 border-slate-200',
  published: 'bg-emerald-50 text-emerald-700 border-emerald-200',
  closed: 'bg-amber-50 text-amber-700 border-amber-200',
  archived: 'bg-slate-100 text-slate-500 border-slate-200',
};

/** Codes techniques → clés du catalogue. Les codes ne sont jamais traduits. */
const CONTRACT_CODES = ['cdi', 'cdd', 'stage', 'freelance'] as const;
const REMOTE_CODES = ['onsite', 'hybrid', 'remote'] as const;

interface JobManagementPanelProps {
  /** Appelé après toute mutation : le pipeline doit recharger ses offres. */
  onJobsChanged?: () => void;
}

export function JobManagementPanel({ onJobsChanged }: JobManagementPanelProps) {
  const copy = getRecruitmentCopy(getPreferredLocale()).jobs;

  const [jobs, setJobs] = useState<Job[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busyId, setBusyId] = useState<number | null>(null);
  const [showForm, setShowForm] = useState(false);

  const statusLabels: Record<JobStatus, string> = {
    draft: copy.statusDraft,
    published: copy.statusPublished,
    closed: copy.statusClosed,
    archived: copy.statusArchived,
  };

  const remoteLabels: Record<string, string> = {
    onsite: copy.onSite,
    hybrid: copy.hybrid,
    remote: copy.remote,
  };

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);

    try {
      const response = await apiFetch('/recruitment/jobs');
      if (!response.ok) {
        setError(copy.loadError);
        return;
      }

      const payload = (await response.json()) as { data?: Job[] } | Job[];
      setJobs(Array.isArray(payload) ? payload : (payload.data ?? []));
    } catch {
      setError(copy.loadError);
    } finally {
      setLoading(false);
    }
  }, [copy.loadError]);

  useEffect(() => {
    void load();
  }, [load]);

  const act = async (job: Job, action: 'publish' | 'close' | 'archive' | 'reopen') => {
    setBusyId(job.id);
    setError(null);

    try {
      const response = await apiFetch(`/recruitment/jobs/${job.id}/${action}`, { method: 'POST' });

      if (!response.ok) {
        // On remonte le message du serveur : « offre sans description » ou
        // « déjà publiée » sont des refus métier, pas des pannes réseau, et le
        // recruteur doit savoir lequel des deux.
        const body = (await response.json().catch(() => null)) as { message?: string } | null;
        setError(body?.message ?? copy.loadError);
        return;
      }

      await load();
      onJobsChanged?.();
    } catch {
      setError(copy.loadError);
    } finally {
      setBusyId(null);
    }
  };

  return (
    <section className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-lg font-black text-slate-950">{copy.title}</h2>
          <p className="text-xs text-slate-500">{copy.subtitle}</p>
        </div>
        <button
          type="button"
          onClick={() => setShowForm((value) => !value)}
          className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-emerald-700"
        >
          {showForm ? <X className="h-4 w-4" /> : <Plus className="h-4 w-4" />}
          {showForm ? copy.cancel : copy.newJob}
        </button>
      </div>

      {error && (
        <p role="alert" className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700">
          {error}
        </p>
      )}

      {showForm && (
        <JobForm
          onCreated={async () => {
            setShowForm(false);
            await load();
            onJobsChanged?.();
          }}
          onError={setError}
        />
      )}

      {loading ? (
        <p className="flex items-center gap-2 text-sm text-slate-500">
          <Loader2 className="h-4 w-4 animate-spin" />
          {copy.title}
        </p>
      ) : jobs.length === 0 ? (
        <p className="rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500">
          {copy.empty}
        </p>
      ) : (
        <ul className="space-y-2">
          {jobs.map((job) => (
            <li
              key={job.id}
              className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-app-border bg-white p-4"
            >
              <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-2">
                  <p className="font-bold text-slate-950">{job.title}</p>
                  <span
                    className={`inline-flex rounded-full border px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider ${
                      STATUS_STYLES[job.status] ?? STATUS_STYLES.draft
                    }`}
                  >
                    {statusLabels[job.status] ?? job.status}
                  </span>
                </div>
                <p className="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-slate-500">
                  <Briefcase className="h-3 w-3" />
                  {[
                    job.location,
                    job.contract_type ? job.contract_type.toUpperCase() : null,
                    job.remote_policy ? remoteLabels[job.remote_policy] ?? job.remote_policy : null,
                  ]
                    .filter(Boolean)
                    .join(' · ') || copy.noDetail}
                </p>
                {job.skills_required && job.skills_required.length > 0 && (
                  <p className="mt-1 text-[11px] text-slate-500">
                    {copy.skills} : {job.skills_required.join(', ')}
                  </p>
                )}
              </div>

              <div className="flex flex-wrap items-center gap-1.5">
                {job.status === 'draft' && (
                  <ActionButton
                    label={copy.publish}
                    icon={Send}
                    busy={busyId === job.id}
                    onClick={() => act(job, 'publish')}
                    tone="primary"
                  />
                )}
                {job.status === 'published' && (
                  <ActionButton
                    label={copy.close}
                    icon={XCircle}
                    busy={busyId === job.id}
                    onClick={() => act(job, 'close')}
                  />
                )}
                {job.status === 'closed' && (
                  <ActionButton
                    label={copy.reopen}
                    icon={RotateCcw}
                    busy={busyId === job.id}
                    onClick={() => act(job, 'reopen')}
                  />
                )}
                {job.status !== 'archived' && (
                  <ActionButton
                    label={copy.archive}
                    icon={Archive}
                    busy={busyId === job.id}
                    onClick={() => act(job, 'archive')}
                  />
                )}
              </div>
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}

function ActionButton({
  label,
  icon: Icon,
  busy,
  onClick,
  tone = 'neutral',
}: {
  label: string;
  icon: typeof Send;
  busy: boolean;
  onClick: () => void;
  tone?: 'primary' | 'neutral';
}) {
  return (
    <button
      type="button"
      disabled={busy}
      onClick={onClick}
      className={`inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs font-bold transition disabled:opacity-50 ${
        tone === 'primary'
          ? 'border-emerald-300 bg-emerald-50 text-emerald-700 hover:bg-emerald-100'
          : 'border-app-border text-slate-700 hover:bg-slate-50'
      }`}
    >
      {busy ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Icon className="h-3.5 w-3.5" />}
      {label}
    </button>
  );
}

function JobForm({
  onCreated,
  onError,
}: {
  onCreated: () => Promise<void> | void;
  onError: (message: string | null) => void;
}) {
  const copy = getRecruitmentCopy(getPreferredLocale()).jobs;

  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [location, setLocation] = useState('');
  const [contractType, setContractType] = useState('cdi');
  const [remotePolicy, setRemotePolicy] = useState('onsite');
  const [skills, setSkills] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});

  const remoteLabels: Record<string, string> = {
    onsite: copy.onSite,
    hybrid: copy.hybrid,
    remote: copy.remote,
  };

  const submit = async (event: React.FormEvent) => {
    event.preventDefault();
    setSubmitting(true);
    setFieldErrors({});
    onError(null);

    try {
      const response = await apiFetch('/recruitment/jobs', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          title: title.trim(),
          description: description.trim() || null,
          location: location.trim() || null,
          contract_type: contractType,
          remote_policy: remotePolicy,
          // Les compétences sont la SEULE source du score ATS : sans elles,
          // le score est `null` et le tri par correspondance ne sert à rien.
          skills_required: skills
            .split(',')
            .map((skill) => skill.trim())
            .filter(Boolean),
        }),
      });

      if (response.status === 422) {
        const body = (await response.json().catch(() => null)) as { errors?: Record<string, string[]> } | null;
        setFieldErrors(body?.errors ?? {});
        return;
      }

      if (!response.ok) {
        onError(copy.createError);
        return;
      }

      setTitle('');
      setDescription('');
      setLocation('');
      setSkills('');
      await onCreated();
    } catch {
      onError(copy.createError);
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <form
      onSubmit={submit}
      className="space-y-3 rounded-2xl border border-app-border bg-slate-50/60 p-4"
    >
      <div className="grid gap-3 sm:grid-cols-2">
        <label className="block sm:col-span-2">
          <span className="mb-1 block text-xs font-bold text-slate-600">{copy.titleField} *</span>
          <input
            required
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            placeholder={copy.titlePlaceholder}
            className="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
          />
          {fieldErrors.title && (
            <span className="mt-1 block text-[11px] text-red-600">{fieldErrors.title[0]}</span>
          )}
        </label>

        <label className="block">
          <span className="mb-1 block text-xs font-bold text-slate-600">{copy.location}</span>
          <input
            value={location}
            onChange={(e) => setLocation(e.target.value)}
            placeholder={copy.locationPlaceholder}
            className="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
          />
        </label>

        <label className="block">
          <span className="mb-1 block text-xs font-bold text-slate-600">{copy.contract}</span>
          <select
            value={contractType}
            onChange={(e) => setContractType(e.target.value)}
            className="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
          >
            {CONTRACT_CODES.map((code) => (
              <option key={code} value={code}>
                {code.toUpperCase()}
              </option>
            ))}
          </select>
        </label>

        <label className="block">
          <span className="mb-1 block text-xs font-bold text-slate-600">{copy.workMode}</span>
          <select
            value={remotePolicy}
            onChange={(e) => setRemotePolicy(e.target.value)}
            className="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
          >
            {REMOTE_CODES.map((code) => (
              <option key={code} value={code}>
                {remoteLabels[code]}
              </option>
            ))}
          </select>
        </label>

        <label className="block">
          <span className="mb-1 block text-xs font-bold text-slate-600">{copy.skills}</span>
          <input
            value={skills}
            onChange={(e) => setSkills(e.target.value)}
            placeholder={copy.skillsPlaceholder}
            className="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
          />
          <span className="mt-1 block text-[11px] text-slate-500">{copy.skillsHint}</span>
        </label>

        <label className="block sm:col-span-2">
          <span className="mb-1 block text-xs font-bold text-slate-600">{copy.description}</span>
          <textarea
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            rows={4}
            placeholder={copy.descriptionPlaceholder}
            className="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
          />
        </label>
      </div>

      <div className="flex items-center gap-2">
        <button
          type="submit"
          disabled={submitting}
          className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-emerald-700 disabled:opacity-60"
        >
          {submitting ? <Loader2 className="h-4 w-4 animate-spin" /> : <CheckCircle2 className="h-4 w-4" />}
          {submitting ? copy.creating : copy.create}
        </button>
      </div>
    </form>
  );
}
