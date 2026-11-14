'use client';

import { useState } from 'react';
import Link from 'next/link';
import { AlertCircle, ArrowLeft, CheckCircle2, Clock, FileSearch, Search } from 'lucide-react';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';
import { getCandidateToolsCopy } from '@/modules/vitrine/data/candidate-tools';
import { trackApplications, type TrackedApplication } from '@/lib/candidate-tracking-api';

/**
 * Suivi de candidature (ATS #9461).
 *
 * Le candidat n'a pas de compte : il retrouve ses dossiers avec l'adresse
 * email utilisée pour postuler. C'est le seul moyen qui ne demande pas de
 * créer un compte à un visiteur qui a postulé une fois.
 *
 * Ce que l'écran affiche, et ce qu'il n'affiche pas : intitulé du poste,
 * statut, dates. Jamais la note interne, les notes du recruteur ni le score
 * ATS — le backend ne les envoie d'ailleurs pas sur cette route.
 */

interface TrackPageProps {
  companySlug: string;
}

const STATUS_TONE: Record<string, string> = {
  new: 'bg-sky-50 text-sky-700 border-sky-200',
  screening: 'bg-amber-50 text-amber-700 border-amber-200',
  interview: 'bg-violet-50 text-violet-700 border-violet-200',
  offer: 'bg-indigo-50 text-indigo-700 border-indigo-200',
  hired: 'bg-emerald-50 text-emerald-700 border-emerald-200',
  rejected: 'bg-rose-50 text-rose-700 border-rose-200',
  withdrawn: 'bg-slate-100 text-slate-600 border-slate-200',
};

function formatDate(value: string | null, locale: string): string {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '—';
  return date.toLocaleDateString(locale, { day: '2-digit', month: 'short', year: 'numeric' });
}

export function TrackApplicationsForm({ companySlug }: TrackPageProps) {
  const { locale } = useVitrineLocale();
  const copy = getCandidateToolsCopy(locale).track;
  const statusLabels = getCandidateToolsCopy(locale).statusLabels;

  const [email, setEmail] = useState('');
  const [status, setStatus] = useState<'idle' | 'loading' | 'done' | 'error'>('idle');
  const [results, setResults] = useState<TrackedApplication[]>([]);
  const [retentionDays, setRetentionDays] = useState<number | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [fieldError, setFieldError] = useState<string | null>(null);

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    setFieldError(null);
    setErrorMessage(null);

    const trimmed = email.trim();
    // Validation côté client pour éviter un aller-retour inutile ; le serveur
    // revalide de toute façon.
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(trimmed)) {
      setFieldError(copy.invalidEmail);
      return;
    }

    setStatus('loading');

    const result = await trackApplications(companySlug, trimmed);

    if (!result.success) {
      setStatus('error');
      setErrorMessage(
        result.status === 429
          ? copy.error
          : copy.error
      );
      return;
    }

    setResults(result.applications);
    setRetentionDays(result.retentionDays);
    setStatus('done');
  };

  return (
    <div className="space-y-6">
      <form onSubmit={handleSubmit} className="space-y-4" noValidate>
        <div>
          <label
            htmlFor="track-email"
            className="block text-sm font-semibold text-slate-900 mb-2"
          >
            {copy.emailLabel}
          </label>
          <div className="flex flex-col gap-3 sm:flex-row">
            <input
              id="track-email"
              type="email"
              inputMode="email"
              autoComplete="email"
              required
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder={copy.emailPlaceholder}
              aria-invalid={fieldError ? true : undefined}
              aria-describedby={fieldError ? 'track-email-error' : undefined}
              className="flex-1 rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500"
            />
            <button
              type="submit"
              disabled={status === 'loading'}
              className="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-60"
            >
              <Search className="h-4 w-4" />
              {status === 'loading' ? copy.submitting : copy.submit}
            </button>
          </div>
          {fieldError && (
            <p id="track-email-error" role="alert" className="mt-1.5 text-xs text-red-600">
              {fieldError}
            </p>
          )}
        </div>
      </form>

      {status === 'error' && errorMessage && (
        <div
          role="alert"
          className="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4"
        >
          <AlertCircle className="mt-0.5 h-5 w-5 flex-shrink-0 text-red-600" />
          <p className="text-sm font-semibold text-red-900">{errorMessage}</p>
        </div>
      )}

      {status === 'done' && results.length === 0 && (
        <div className="rounded-xl border border-slate-200 bg-slate-50 p-5">
          <div className="flex items-start gap-3">
            <FileSearch className="mt-0.5 h-5 w-5 flex-shrink-0 text-slate-500" />
            <div>
              <p className="text-sm font-semibold text-slate-900">{copy.empty}</p>
              <p className="mt-1 text-xs text-slate-600">{copy.emptyHint}</p>
            </div>
          </div>
        </div>
      )}

      {status === 'done' && results.length > 0 && (
        <section className="space-y-3">
          <h2 className="text-sm font-bold uppercase tracking-wider text-slate-800">
            {copy.resultsTitle(results.length)}
          </h2>

          <ul className="space-y-3">
            {results.map((application) => (
              <li
                key={application.reference}
                className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
              >
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <p className="text-base font-bold text-slate-950">
                      {application.job_title ?? '—'}
                    </p>
                    <p className="mt-0.5 text-xs text-slate-500">
                      {copy.reference} : <span className="font-mono">{application.reference}</span>
                      {application.job_location ? ` · ${application.job_location}` : ''}
                    </p>
                  </div>
                  <span
                    className={`inline-flex items-center rounded-full border px-3 py-1 text-xs font-bold ${
                      STATUS_TONE[application.status] ?? STATUS_TONE.withdrawn
                    }`}
                  >
                    {statusLabels[application.status] ?? application.status}
                  </span>
                </div>

                <dl className="mt-4 grid grid-cols-1 gap-3 text-xs sm:grid-cols-2">
                  <div className="flex items-center gap-2 text-slate-600">
                    <Clock className="h-3.5 w-3.5" />
                    <dt className="font-semibold">{copy.appliedAt} :</dt>
                    <dd>{formatDate(application.applied_at, locale)}</dd>
                  </div>
                  <div className="flex items-center gap-2 text-slate-600">
                    <CheckCircle2 className="h-3.5 w-3.5" />
                    <dt className="font-semibold">{copy.lastUpdate} :</dt>
                    <dd>{formatDate(application.last_update, locale)}</dd>
                  </div>
                </dl>
              </li>
            ))}
          </ul>

          {retentionDays !== null && (
            <p className="text-xs text-slate-500">{copy.retention(retentionDays)}</p>
          )}
        </section>
      )}

      <p className="text-xs text-slate-500">{copy.privacyNote}</p>

      <Link
        href={`/${companySlug}/careers`}
        className="inline-flex items-center gap-2 text-sm font-semibold text-emerald-700 hover:text-emerald-800"
      >
        <ArrowLeft className="h-4 w-4" />
        {copy.backToJobs}
      </Link>
    </div>
  );
}
