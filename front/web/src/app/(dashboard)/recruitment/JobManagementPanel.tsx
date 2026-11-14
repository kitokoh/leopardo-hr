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

const STATUS_LABELS: Record<JobStatus, string> = {
  draft: 'Brouillon',
  published: 'Publiee',
  closed: 'Cloturee',
  archived: 'Archivee',
};

const STATUS_STYLES: Record<JobStatus, string> = {
  draft: 'bg-slate-100 text-slate-600 border-slate-200',
  published: 'bg-emerald-50 text-emerald-700 border-emerald-200',
  closed: 'bg-amber-50 text-amber-700 border-amber-200',
  archived: 'bg-slate-100 text-slate-500 border-slate-200',
};

const CONTRACT_LABELS: Record<string, string> = {
  cdi: 'CDI',
  cdd: 'CDD',
  stage: 'Stage',
  freelance: 'Freelance',
};

const REMOTE_LABELS: Record<string, string> = {
  onsite: 'Sur site',
  hybrid: 'Hybride',
  remote: 'Teletravail',
};

interface JobManagementPanelProps {
  /** Appelé après toute mutation : le pipeline doit recharger ses offres. */
  onJobsChanged?: () => void;
}

export function JobManagementPanel({ onJobsChanged }: JobManagementPanelProps) {
  const [jobs, setJobs] = useState<Job[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busyId, setBusyId] = useState<number | null>(null);
  const [showForm, setShowForm] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);

    try {
      const response = await apiFetch('/recruitment/jobs');
      if (!response.ok) {
        setError('Impossible de charger les offres.');
        return;
      }

      const payload = (await response.json()) as { data?: Job[] } | Job[];
      setJobs(Array.isArray(payload) ? payload : (payload.data ?? []));
    } catch {
      setError('Impossible de charger les offres.');
    } finally {
      setLoading(false);
    }
  }, []);

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
        // « deja publiee » sont des refus metier, pas des pannes reseau, et le
        // recruteur doit savoir lequel des deux.
        const body = (await response.json().catch(() => null)) as { message?: string } | null;
        setError(body?.message ?? `L'action « ${action} » a echoue.`);
        return;
      }

      await load();
      onJobsChanged?.();
    } catch {
      setError(`L'action « ${action} » a echoue.`);
    } finally {
      setBusyId(null);
    }
  };

  return (
    <section className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-lg font-black text-slate-950">Offres d&apos;emploi</h2>
          <p className="text-xs text-slate-500">
            Une offre en brouillon n&apos;est pas visible sur la vitrine. Publiez-la pour la rendre
            visible et ouvrir les candidatures.
          </p>
        </div>
        <button
          type="button"
          onClick={() => setShowForm((value) => !value)}
          className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-emerald-700"
        >
          {showForm ? <X className="h-4 w-4" /> : <Plus className="h-4 w-4" />}
          {showForm ? 'Annuler' : 'Nouvelle offre'}
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
          Chargement des offres...
        </p>
      ) : jobs.length === 0 ? (
        <p className="rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500">
          Aucune offre pour le moment. Creez-en une pour commencer a recruter.
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
                    {STATUS_LABELS[job.status] ?? job.status}
                  </span>
                </div>
                <p className="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-slate-500">
                  <Briefcase className="h-3 w-3" />
                  {[
                    job.location,
                    job.contract_type ? CONTRACT_LABELS[job.contract_type] ?? job.contract_type : null,
                    job.remote_policy ? REMOTE_LABELS[job.remote_policy] ?? job.remote_policy : null,
                  ]
                    .filter(Boolean)
                    .join(' · ') || 'Aucun detail'}
                </p>
                {job.skills_required && job.skills_required.length > 0 && (
                  <p className="mt-1 text-[11px] text-slate-500">
                    Competences ATS : {job.skills_required.join(', ')}
                  </p>
                )}
              </div>

              <div className="flex flex-wrap items-center gap-1.5">
                {job.status === 'draft' && (
                  <ActionButton
                    label="Publier"
                    icon={Send}
                    busy={busyId === job.id}
                    onClick={() => act(job, 'publish')}
                    tone="primary"
                  />
                )}
                {job.status === 'published' && (
                  <ActionButton
                    label="Cloturer"
                    icon={XCircle}
                    busy={busyId === job.id}
                    onClick={() => act(job, 'close')}
                  />
                )}
                {job.status === 'closed' && (
                  <ActionButton
                    label="Rouvrir"
                    icon={RotateCcw}
                    busy={busyId === job.id}
                    onClick={() => act(job, 'reopen')}
                  />
                )}
                {job.status !== 'archived' && (
                  <ActionButton
                    label="Archiver"
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
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [location, setLocation] = useState('');
  const [contractType, setContractType] = useState('cdi');
  const [remotePolicy, setRemotePolicy] = useState('onsite');
  const [skills, setSkills] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});

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
          // Les competences sont la SEULE source du score ATS : sans elles,
          // le score est `null` et le tri par correspondance ne sert a rien.
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
        onError("La creation de l'offre a echoue.");
        return;
      }

      setTitle('');
      setDescription('');
      setLocation('');
      setSkills('');
      await onCreated();
    } catch {
      onError("La creation de l'offre a echoue.");
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
          <span className="mb-1 block text-xs font-bold text-slate-600">Intitule du poste *</span>
          <input
            required
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            className="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
          />
          {fieldErrors.title && (
            <span className="mt-1 block text-[11px] text-red-600">{fieldErrors.title[0]}</span>
          )}
        </label>

        <label className="block">
          <span className="mb-1 block text-xs font-bold text-slate-600">Lieu</span>
          <input
            value={location}
            onChange={(e) => setLocation(e.target.value)}
            className="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
          />
        </label>

        <label className="block">
          <span className="mb-1 block text-xs font-bold text-slate-600">Type de contrat</span>
          <select
            value={contractType}
            onChange={(e) => setContractType(e.target.value)}
            className="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
          >
            {Object.entries(CONTRACT_LABELS).map(([value, label]) => (
              <option key={value} value={value}>
                {label}
              </option>
            ))}
          </select>
        </label>

        <label className="block">
          <span className="mb-1 block text-xs font-bold text-slate-600">Modalite</span>
          <select
            value={remotePolicy}
            onChange={(e) => setRemotePolicy(e.target.value)}
            className="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
          >
            {Object.entries(REMOTE_LABELS).map(([value, label]) => (
              <option key={value} value={value}>
                {label}
              </option>
            ))}
          </select>
        </label>

        <label className="block">
          <span className="mb-1 block text-xs font-bold text-slate-600">
            Competences (separees par des virgules)
          </span>
          <input
            value={skills}
            onChange={(e) => setSkills(e.target.value)}
            placeholder="PHP, Laravel, PostgreSQL"
            className="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
          />
          <span className="mt-1 block text-[11px] text-slate-500">
            Elles servent au score de correspondance ATS.
          </span>
        </label>

        <label className="block sm:col-span-2">
          <span className="mb-1 block text-xs font-bold text-slate-600">Description</span>
          <textarea
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            rows={4}
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
          Creer l&apos;offre
        </button>
        <span className="text-[11px] text-slate-500">
          L&apos;offre est creee en brouillon : publiez-la ensuite pour la rendre visible.
        </span>
      </div>
    </form>
  );
}
