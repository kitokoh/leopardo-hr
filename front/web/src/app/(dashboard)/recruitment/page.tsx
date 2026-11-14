'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { motion } from 'framer-motion';
import {
  AlertTriangle,
  Briefcase,
  CheckCircle2,
  ChevronRight,
  Download,
  Filter,
  Search,
  Star,
  Users,
  X,
} from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { getRecruitmentCopy } from '@/lib/i18n/recruitment';
import { ModulePageShell } from '@/components/module-page-shell';
import { JobManagementPanel } from './JobManagementPanel';

/**
 * ATS — pipeline de recrutement du recruteur.
 *
 * Cet écran répond à une seule question : « quels dossiers dois-je traiter
 * maintenant ? ». Il affiche les compteurs par étape, laisse filtrer
 * (statut, note, recherche, ancienneté, état du CV), et permet de déplacer un
 * dossier dans le pipeline avec une justification.
 *
 * Les filtres sont envoyés au serveur : filtrer côté navigateur ne tiendrait
 * pas au-delà de la première page de résultats, et donnerait un compteur faux.
 */

type ApplicantStatus =
  | 'new'
  | 'screening'
  | 'interview'
  | 'offer'
  | 'hired'
  | 'rejected'
  | 'withdrawn';

interface Applicant {
  id: number;
  job_posting_id: number;
  job_posting?: { id: number; title: string } | null;
  first_name: string;
  last_name: string;
  full_name: string;
  email: string;
  phone: string | null;
  source: string;
  status: ApplicantStatus;
  rating: number | null;
  notes: string | null;
  cover_letter: string | null;
  has_resume: boolean;
  resume_url: string | null;
  resume_scan_status: string | null;
  applied_at: string | null;
}

interface PipelineMeta {
  counts?: Record<string, number>;
  statuses?: ApplicantStatus[];
  job?: { id: number; title: string; status: string; skills_required: string[] };
}

interface JobOption {
  id: number;
  title: string;
  status: string;
}

const STATUS_LABELS: Record<ApplicantStatus, string> = {
  new: 'Nouveau',
  screening: 'Preselection',
  interview: 'Entretien',
  offer: 'Offre',
  hired: 'Recrute',
  rejected: 'Refuse',
  withdrawn: 'Retire',
};

/**
 * Libelles de la decision finale et du score, depuis le catalogue i18n.
 *
 * Resolus au niveau du module et non dans le composant : `decide()` est une
 * fonction de rappel qui n'a pas acces a un hook, et le catalogue ne depend
 * que de la locale stockee.
 */
const recruitmentCopy = getRecruitmentCopy(getPreferredLocale());
const decisionCopy = recruitmentCopy.decision;
const scoreCopy = recruitmentCopy.score;

const STATUS_STYLES: Record<ApplicantStatus, string> = {
  new: 'bg-sky-50 text-sky-700 border-sky-200',
  screening: 'bg-amber-50 text-amber-700 border-amber-200',
  interview: 'bg-violet-50 text-violet-700 border-violet-200',
  offer: 'bg-indigo-50 text-indigo-700 border-indigo-200',
  hired: 'bg-emerald-50 text-emerald-700 border-emerald-200',
  rejected: 'bg-rose-50 text-rose-700 border-rose-200',
  withdrawn: 'bg-slate-100 text-slate-600 border-slate-200',
};

const SOURCE_LABELS: Record<string, string> = {
  website: 'Site carriere',
  referral: 'Cooptation',
  linkedin: 'LinkedIn',
  agency: 'Cabinet',
  other: 'Autre',
};

function formatDate(value: string | null): string {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '—';
  return date.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
}

export default function RecruitmentAtsPage() {
  const [applicants, setApplicants] = useState<Applicant[]>([]);
  const [meta, setMeta] = useState<PipelineMeta>({});
  const [jobs, setJobs] = useState<JobOption[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [jobFilter, setJobFilter] = useState<string>('all');
  const [statusFilter, setStatusFilter] = useState<ApplicantStatus[]>([]);
  const [search, setSearch] = useState('');
  const [minRating, setMinRating] = useState<string>('');
  const [olderThan, setOlderThan] = useState<string>('');
  const [showFilters, setShowFilters] = useState(false);

  const [selected, setSelected] = useState<Applicant | null>(null);
  const [detail, setDetail] = useState<{
    history: Array<{ id: number; from_status: string | null; to_status: string; actor_name: string | null; reason: string | null; occurred_at: string | null }>;
    match: { score: number | null; matched: string[]; missing: string[]; sources?: string[] } | null;
  } | null>(null);
  const [detailLoading, setDetailLoading] = useState(false);
  const [moving, setMoving] = useState(false);
  const [moveError, setMoveError] = useState<string | null>(null);

  // ATS (#9461) — decision finale. Le motif est OBLIGATOIRE : un refus sans
  // justification ecrit est indefendable devant le candidat et devant un
  // controle, et le journal de statuts perdrait tout son interet.
  const [decisionReason, setDecisionReason] = useState('');
  const [deciding, setDeciding] = useState(false);
  const [decisionError, setDecisionError] = useState<string | null>(null);

  const queryString = useMemo(() => {
    const params = new URLSearchParams();
    statusFilter.forEach((status) => params.append('statuses[]', status));
    if (search.trim()) params.set('q', search.trim());
    if (minRating) params.set('min_rating', minRating);
    if (olderThan) params.set('older_than_days', olderThan);
    params.set('per_page', '50');
    return params.toString();
  }, [statusFilter, search, minRating, olderThan]);

  const loadJobs = useCallback(async () => {
    try {
      const res = await apiFetch('/recruitment/jobs?per_page=100');
      const payload = await res.json();
      setJobs(payload.data ?? []);
    } catch {
      // La liste des offres est un confort de filtrage : son échec ne doit pas
      // empêcher d'afficher le pipeline transverse.
      setJobs([]);
    }
  }, []);

  const loadApplicants = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const endpoint =
        jobFilter === 'all'
          ? `/recruitment/ats/applicants?${queryString}`
          : `/recruitment/ats/jobs/${jobFilter}/applicants?${queryString}`;
      const res = await apiFetch(endpoint);
      const payload = await res.json();
      setApplicants(payload.data ?? []);
      setMeta(payload.meta ?? {});
    } catch (e) {
      const status = (e as { status?: number }).status;
      setError(
        status === 403
          ? "Vous n'avez pas les droits de recrutement sur cette entreprise."
          : 'Impossible de charger le pipeline de candidatures.',
      );
      setApplicants([]);
    } finally {
      setLoading(false);
    }
  }, [jobFilter, queryString]);

  useEffect(() => {
    loadJobs();
  }, [loadJobs]);

  useEffect(() => {
    loadApplicants();
  }, [loadApplicants]);

  const openDetail = async (applicant: Applicant) => {
    setSelected(applicant);
    setDetail(null);
    setMoveError(null);
    setDecisionError(null);
    setDecisionReason('');
    setDetailLoading(true);
    try {
      const res = await apiFetch(`/recruitment/ats/applicants/${applicant.id}`);
      const payload = await res.json();
      setDetail({
        history: payload.data?.status_history ?? [],
        match: payload.meta?.match ?? null,
      });
    } catch {
      setDetail({ history: [], match: null });
    } finally {
      setDetailLoading(false);
    }
  };

  const moveTo = async (status: ApplicantStatus) => {
    if (!selected) return;
    setMoving(true);
    setMoveError(null);
    try {
      const res = await apiFetch(`/recruitment/ats/applicants/${selected.id}/status`, {
        method: 'PATCH',
        body: JSON.stringify({ status }),
      });
      const payload = await res.json();
      const updated = payload.data as Applicant;
      setApplicants((prev) => prev.map((a) => (a.id === updated.id ? { ...a, ...updated } : a)));
      setSelected((prev) => (prev ? { ...prev, ...updated } : prev));
      await openDetail(updated);
      await loadApplicants();
    } catch {
      setMoveError('Le changement de statut a echoue. Reessayez.');
    } finally {
      setMoving(false);
    }
  };

  /**
   * Decision finale : embauche ou refus, avec motif obligatoire.
   *
   * Passe par la meme route que le deplacement dans le pipeline, mais avec un
   * motif non vide : c'est la seule difference, et elle est volontaire — la
   * decision finale est un acte qui doit rester justifie dans le journal.
   */
  const decide = async (status: 'hired' | 'rejected') => {
    if (!selected) return;

    const reason = decisionReason.trim();
    if (reason.length < 3) {
      setDecisionError(decisionCopy.reasonRequired);
      return;
    }

    setDeciding(true);
    setDecisionError(null);

    try {
      const res = await apiFetch(`/recruitment/ats/applicants/${selected.id}/status`, {
        method: 'PATCH',
        body: JSON.stringify({ status, reason }),
      });

      if (!res.ok) {
        setDecisionError(decisionCopy.saveError);
        return;
      }

      const payload = await res.json();
      const updated = payload.data as Applicant;
      setApplicants((prev) => prev.map((a) => (a.id === updated.id ? { ...a, ...updated } : a)));
      setSelected((prev) => (prev ? { ...prev, ...updated } : prev));
      setDecisionReason('');
      await openDetail(updated);
      await loadApplicants();
    } catch {
      setDecisionError(decisionCopy.saveError);
    } finally {
      setDeciding(false);
    }
  };

  const toggleStatus = (status: ApplicantStatus) => {
    setStatusFilter((prev) =>
      prev.includes(status) ? prev.filter((s) => s !== status) : [...prev, status],
    );
  };

  const counts = meta.counts ?? {};
  const total = counts.total ?? applicants.length;

  const statCards = [
    { key: 'new', label: 'Nouveaux', value: counts.new ?? 0, accent: 'text-sky-700 bg-sky-50' },
    { key: 'screening', label: 'Preselection', value: counts.screening ?? 0, accent: 'text-amber-600 bg-amber-50' },
    { key: 'interview', label: 'Entretiens', value: counts.interview ?? 0, accent: 'text-violet-700 bg-violet-50' },
    { key: 'offer', label: 'Offres', value: counts.offer ?? 0, accent: 'text-indigo-700 bg-indigo-50' },
    { key: 'hired', label: 'Recrutes', value: counts.hired ?? 0, accent: 'text-emerald-700 bg-emerald-50' },
    { key: 'rejected', label: 'Refuses', value: counts.rejected ?? 0, accent: 'text-rose-600 bg-rose-50' },
  ];

  return (
    <ModulePageShell
      title="Recrutement — ATS"
      subtitle="Suivi des candidatures, filtrage et deplacement dans le pipeline."
      icon={Users}
      accentClassName="bg-gradient-to-br from-rh-light via-white to-white"
    >
      {error && (
        <div className="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
          <AlertTriangle className="h-4 w-4 shrink-0" />
          <span>{error}</span>
        </div>
      )}

      {/* ATS (#9461) — gestion des offres. Placee AVANT le pipeline : sans
          offre publiee, il n'y a aucune candidature a traiter, et un
          recruteur qui arrive sur un pipeline vide doit voir pourquoi. */}
      <JobManagementPanel onJobsChanged={loadJobs} />

      <section className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        {statCards.map((stat, i) => (
          <motion.button
            key={stat.key}
            type="button"
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: i * 0.04 }}
            onClick={() => toggleStatus(stat.key as ApplicantStatus)}
            className={`rounded-2xl border bg-white p-4 text-left shadow-sm transition hover:shadow-md ${
              statusFilter.includes(stat.key as ApplicantStatus)
                ? 'border-emerald-400 ring-2 ring-emerald-100'
                : 'border-app-border'
            }`}
          >
            <div className={`mb-2 inline-flex h-8 w-8 items-center justify-center rounded-lg ${stat.accent}`}>
              <Users className="h-4 w-4" />
            </div>
            <p className="text-2xl font-black text-slate-950">{stat.value}</p>
            <p className="text-[11px] font-bold uppercase tracking-widest text-slate-500">{stat.label}</p>
          </motion.button>
        ))}
      </section>

      <div className="flex flex-col gap-3 lg:flex-row lg:items-center">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
          <input
            type="search"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Rechercher un nom, un email, une lettre de motivation..."
            className="w-full rounded-xl border border-app-border bg-white py-2.5 pl-10 pr-4 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500"
          />
        </div>

        <select
          value={jobFilter}
          onChange={(e) => setJobFilter(e.target.value)}
          className="rounded-xl border border-app-border bg-white px-3 py-2.5 text-sm font-medium text-slate-700"
        >
          <option value="all">Toutes les offres</option>
          {jobs.map((job) => (
            <option key={job.id} value={job.id}>
              {job.title}
            </option>
          ))}
        </select>

        <button
          type="button"
          onClick={() => setShowFilters((v) => !v)}
          className="inline-flex items-center gap-2 rounded-xl border border-app-border bg-white px-3 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
        >
          <Filter className="h-4 w-4" />
          Filtres
          {statusFilter.length + (minRating ? 1 : 0) + (olderThan ? 1 : 0) > 0 && (
            <span className="rounded-full bg-emerald-600 px-1.5 text-[10px] font-bold text-white">
              {statusFilter.length + (minRating ? 1 : 0) + (olderThan ? 1 : 0)}
            </span>
          )}
        </button>
      </div>

      {showFilters && (
        <div className="flex flex-wrap items-end gap-4 rounded-2xl border border-app-border bg-white p-4">
          <label className="flex flex-col gap-1 text-xs font-bold uppercase tracking-wider text-slate-500">
            Note minimale
            <select
              value={minRating}
              onChange={(e) => setMinRating(e.target.value)}
              className="rounded-lg border border-app-border px-3 py-2 text-sm font-medium text-slate-700"
            >
              <option value="">Toutes</option>
              <option value="3">3 et plus</option>
              <option value="4">4 et plus</option>
              <option value="5">5 etoiles</option>
            </select>
          </label>

          <label className="flex flex-col gap-1 text-xs font-bold uppercase tracking-wider text-slate-500">
            Candidature plus ancienne que
            <select
              value={olderThan}
              onChange={(e) => setOlderThan(e.target.value)}
              className="rounded-lg border border-app-border px-3 py-2 text-sm font-medium text-slate-700"
            >
              <option value="">Peu importe</option>
              <option value="7">7 jours</option>
              <option value="14">14 jours</option>
              <option value="30">30 jours</option>
            </select>
          </label>

          <button
            type="button"
            onClick={() => {
              setStatusFilter([]);
              setMinRating('');
              setOlderThan('');
              setSearch('');
            }}
            className="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100"
          >
            <X className="h-4 w-4" />
            Reinitialiser
          </button>
        </div>
      )}

      <section className="overflow-hidden rounded-3xl border border-app-border bg-white shadow-sm">
        <div className="flex items-center justify-between border-b border-app-border px-6 py-4">
          <h2 className="text-sm font-bold uppercase tracking-wider text-slate-800">
            {meta.job ? `Candidatures — ${meta.job.title}` : 'Toutes les candidatures'}
          </h2>
          <span className="text-xs font-semibold text-slate-500">
            {applicants.length} affichee(s) sur {total}
          </span>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-app-border bg-slate-50/60">
                <th scope="col" className="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Candidat</th>
                <th scope="col" className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Offre</th>
                <th scope="col" className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Source</th>
                <th scope="col" className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Deposee le</th>
                <th scope="col" className="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider text-slate-500">Note</th>
                <th scope="col" className="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider text-slate-500">Etape</th>
                <th scope="col" className="px-6 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500">Dossier</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-app-border">
              {loading ? (
                <tr>
                  <td colSpan={7} className="px-6 py-10 text-center text-sm text-slate-500">
                    Chargement du pipeline...
                  </td>
                </tr>
              ) : applicants.length === 0 ? (
                <tr>
                  <td colSpan={7} className="px-6 py-10 text-center text-sm text-slate-500">
                    Aucune candidature ne correspond a ces criteres.
                  </td>
                </tr>
              ) : (
                applicants.map((applicant) => (
                  <tr key={applicant.id} className="transition-colors hover:bg-slate-50/60">
                    <td className="px-6 py-4">
                      <p className="font-bold text-slate-950">{applicant.full_name}</p>
                      <p className="text-xs text-slate-500">{applicant.email}</p>
                    </td>
                    <td className="px-4 py-4 text-slate-600">
                      <span className="inline-flex items-center gap-1.5">
                        <Briefcase className="h-3.5 w-3.5 text-slate-400" />
                        {applicant.job_posting?.title ?? '—'}
                      </span>
                    </td>
                    <td className="px-4 py-4 text-xs font-semibold uppercase tracking-wider text-slate-500">
                      {SOURCE_LABELS[applicant.source] ?? applicant.source}
                    </td>
                    <td className="px-4 py-4 text-slate-600">{formatDate(applicant.applied_at)}</td>
                    <td className="px-4 py-4 text-center">
                      {applicant.rating ? (
                        <span className="inline-flex items-center gap-0.5 text-amber-500">
                          <Star className="h-3.5 w-3.5 fill-current" />
                          <span className="text-xs font-bold text-slate-700">{applicant.rating}</span>
                        </span>
                      ) : (
                        <span className="text-xs text-slate-400">—</span>
                      )}
                    </td>
                    <td className="px-4 py-4 text-center">
                      <span
                        className={`inline-flex rounded-full border px-2.5 py-1 text-[11px] font-bold uppercase tracking-wider ${
                          STATUS_STYLES[applicant.status] ?? STATUS_STYLES.new
                        }`}
                      >
                        {STATUS_LABELS[applicant.status] ?? applicant.status}
                      </span>
                    </td>
                    <td className="px-6 py-4 text-right">
                      <button
                        type="button"
                        onClick={() => openDetail(applicant)}
                        className="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-bold text-emerald-700 hover:bg-emerald-50"
                      >
                        Ouvrir
                        <ChevronRight className="h-3.5 w-3.5" />
                      </button>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </section>

      {selected && (
        <div className="fixed inset-0 z-50 flex justify-end bg-slate-950/40" role="dialog" aria-modal="true">
          <button
            type="button"
            aria-label="Fermer"
            className="flex-1 cursor-default"
            onClick={() => setSelected(null)}
          />
          <aside className="h-full w-full max-w-xl overflow-y-auto bg-white p-6 shadow-2xl">
            <div className="mb-4 flex items-start justify-between gap-4">
              <div>
                <h2 className="text-xl font-black text-slate-950">{selected.full_name}</h2>
                <p className="text-sm text-slate-500">{selected.email}</p>
                {selected.phone && <p className="text-sm text-slate-500">{selected.phone}</p>}
              </div>
              <button
                type="button"
                onClick={() => setSelected(null)}
                className="rounded-lg p-2 text-slate-500 hover:bg-slate-100"
                aria-label="Fermer le dossier"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            <div className="mb-5 flex flex-wrap items-center gap-2">
              <span
                className={`inline-flex rounded-full border px-3 py-1 text-xs font-bold uppercase tracking-wider ${
                  STATUS_STYLES[selected.status] ?? STATUS_STYLES.new
                }`}
              >
                {STATUS_LABELS[selected.status] ?? selected.status}
              </span>
              <span className="text-xs text-slate-500">
                Deposee le {formatDate(selected.applied_at)}
              </span>
              {selected.has_resume && selected.resume_url && (
                <a
                  href={selected.resume_url}
                  className="inline-flex items-center gap-1.5 rounded-lg border border-app-border px-2.5 py-1 text-xs font-bold text-slate-700 hover:bg-slate-50"
                >
                  <Download className="h-3.5 w-3.5" />
                  CV
                </a>
              )}
            </div>

            {moveError && (
              <p className="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700" role="alert">
                {moveError}
              </p>
            )}

            <div className="mb-6">
              <h3 className="mb-2 text-xs font-bold uppercase tracking-widest text-slate-500">
                Deplacer dans le pipeline
              </h3>
              <div className="flex flex-wrap gap-2">
                {(meta.statuses ?? (Object.keys(STATUS_LABELS) as ApplicantStatus[])).map((status) => (
                  <button
                    key={status}
                    type="button"
                    disabled={moving || status === selected.status}
                    onClick={() => moveTo(status)}
                    className={`rounded-lg border px-3 py-1.5 text-xs font-bold transition ${
                      status === selected.status
                        ? 'cursor-default border-emerald-300 bg-emerald-50 text-emerald-700'
                        : 'border-app-border text-slate-700 hover:bg-slate-50 disabled:opacity-50'
                    }`}
                  >
                    {STATUS_LABELS[status]}
                  </button>
                ))}
              </div>
            </div>

            {detailLoading ? (
              <p className="text-sm text-slate-500">Chargement du dossier...</p>
            ) : (
              <>
                {/* ATS (#9461) — decision finale. Placee AVANT l'historique :
                    c'est l'acte que le recruteur vient poser, et il doit voir
                    ce qu'il reste a faire sans faire defiler. */}
                <div className="mb-6 rounded-2xl border border-app-border bg-white p-4">
                  <h3 className="mb-2 text-xs font-bold uppercase tracking-widest text-slate-500">
                    {decisionCopy.title}
                  </h3>

                  {selected.status === 'hired' || selected.status === 'rejected' ? (
                    <p className="text-sm font-semibold text-slate-700">
                      {decisionCopy.alreadyDecided(STATUS_LABELS[selected.status] ?? selected.status)}
                    </p>
                  ) : (
                    <>
                      <label htmlFor="decision-reason" className="mb-1 block text-xs font-semibold text-slate-600">
                        {decisionCopy.reasonLabel}
                      </label>
                      <textarea
                        id="decision-reason"
                        value={decisionReason}
                        onChange={(e) => setDecisionReason(e.target.value)}
                        rows={2}
                        placeholder={decisionCopy.reasonPlaceholder}
                        className="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
                      />

                      {decisionError && (
                        <p role="alert" className="mt-1 text-xs font-semibold text-red-600">
                          {decisionError}
                        </p>
                      )}

                      <div className="mt-2 flex flex-wrap gap-2">
                        <button
                          type="button"
                          disabled={deciding}
                          onClick={() => decide('hired')}
                          className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white transition hover:bg-emerald-700 disabled:opacity-50"
                        >
                          <CheckCircle2 className="h-3.5 w-3.5" />
                          {decisionCopy.hire}
                        </button>
                        <button
                          type="button"
                          disabled={deciding}
                          onClick={() => decide('rejected')}
                          className="inline-flex items-center gap-1.5 rounded-lg border border-rose-300 bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-700 transition hover:bg-rose-100 disabled:opacity-50"
                        >
                          <X className="h-3.5 w-3.5" />
                          {decisionCopy.reject}
                        </button>
                      </div>
                    </>
                  )}
                </div>

                {detail?.match && detail.match.score !== null && (
                  <div className="mb-6 rounded-2xl border border-app-border bg-slate-50/60 p-4">
                    <div className="mb-2 flex items-center justify-between">
                      <h3 className="text-xs font-bold uppercase tracking-widest text-slate-500">
                        Correspondance avec l&apos;offre
                      </h3>
                      <span className="text-lg font-black text-slate-950">{detail.match.score}%</span>
                    </div>
                    <div className="h-2 w-full overflow-hidden rounded-full bg-slate-200">
                      <div
                        className="h-full rounded-full bg-emerald-500"
                        style={{ width: `${detail.match.score}%` }}
                      />
                    </div>
                    <div className="mt-3 flex flex-wrap gap-1.5">
                      {detail.match.matched.map((skill) => (
                        <span key={skill} className="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-bold text-emerald-800">
                          <CheckCircle2 className="h-3 w-3" />
                          {skill}
                        </span>
                      ))}
                      {detail.match.missing.map((skill) => (
                        <span key={skill} className="rounded-full bg-slate-200 px-2 py-0.5 text-[11px] font-bold text-slate-600">
                          {skill}
                        </span>
                      ))}
                    </div>
                    <p className="mt-2 text-[11px] text-slate-500">
                      {scoreCopy.caption}
                      {detail.match.sources && detail.match.sources.length > 0
                        ? scoreCopy.sources(
                            detail.match.sources
                              .map((source) =>
                                source === 'resume'
                                  ? scoreCopy.resume
                                  : source === 'cover_letter'
                                    ? scoreCopy.coverLetter
                                    : scoreCopy.notes,
                              )
                              .join(', '),
                          )
                        : ''}
                      .
                    </p>
                  </div>
                )}

                {selected.cover_letter && (
                  <div className="mb-6">
                    <h3 className="mb-2 text-xs font-bold uppercase tracking-widest text-slate-500">
                      Lettre de motivation
                    </h3>
                    <p className="whitespace-pre-line rounded-xl border border-app-border bg-white p-3 text-sm text-slate-700">
                      {selected.cover_letter}
                    </p>
                  </div>
                )}

                <div>
                  <h3 className="mb-2 text-xs font-bold uppercase tracking-widest text-slate-500">
                    Historique du dossier
                  </h3>
                  {detail && detail.history.length > 0 ? (
                    <ol className="space-y-3">
                      {detail.history.map((event) => (
                        <li key={event.id} className="flex gap-3">
                          <span className="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-emerald-500" />
                          <div>
                            <p className="text-sm font-semibold text-slate-900">
                              {event.from_status
                                ? `${STATUS_LABELS[event.from_status as ApplicantStatus] ?? event.from_status} → ${
                                    STATUS_LABELS[event.to_status as ApplicantStatus] ?? event.to_status
                                  }`
                                : `Dossier cree (${STATUS_LABELS[event.to_status as ApplicantStatus] ?? event.to_status})`}
                            </p>
                            <p className="text-xs text-slate-500">
                              {formatDate(event.occurred_at)}
                              {event.actor_name ? ` · ${event.actor_name}` : ' · depot automatique'}
                            </p>
                            {event.reason && (
                              <p className="mt-1 text-xs italic text-slate-600">« {event.reason} »</p>
                            )}
                          </div>
                        </li>
                      ))}
                    </ol>
                  ) : (
                    <p className="text-sm text-slate-500">Aucun evenement enregistre pour ce dossier.</p>
                  )}
                </div>
              </>
            )}
          </aside>
        </div>
      )}
    </ModulePageShell>
  );
}
