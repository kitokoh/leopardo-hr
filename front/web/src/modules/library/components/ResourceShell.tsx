import React from 'react';
import Link from 'next/link';
import { ArrowLeft, ExternalLink, Info } from 'lucide-react';

interface ResourceShellProps {
  title: string;
  author: string;
  categoryLabel: string;
  accessLabel: string;
  accessTone: 'free' | 'paid' | 'private';
  description: string;
  metadata: { label: string; value: string }[];
  licenseLabel: string;
  rightsHolder: string;
  sourceUrl?: string;
  sourceNotice: string;
  demoNotice?: string;
  backLabel: string;
  disclaimer: string;
  children: React.ReactNode;
}

const TONE_CLASSES: Record<ResourceShellProps['accessTone'], string> = {
  free: 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300',
  paid: 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300',
  private: 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300',
};

/**
 * Gabarit de fiche d'une ressource — en-tête, métadonnées, licence, contenu.
 *
 * Composant SERVEUR (aucun état) : il encadre les actions client passées en
 * `children`. Les métadonnées inconnues sont affichées « Non renseigné » —
 * jamais présentées comme vérifiées.
 */
export function ResourceShell({
  title,
  author,
  categoryLabel,
  accessLabel,
  accessTone,
  description,
  metadata,
  licenseLabel,
  rightsHolder,
  sourceUrl,
  sourceNotice,
  demoNotice,
  backLabel,
  disclaimer,
  children,
}: ResourceShellProps) {
  return (
    <div className="mx-auto w-full max-w-3xl px-4 py-10 sm:py-14">
      <Link
        href="/library"
        className="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 dark:text-slate-400 hover:text-emerald-700 dark:hover:text-emerald-300 transition-colors mb-6"
      >
        <ArrowLeft className="w-4 h-4" />
        {backLabel}
      </Link>

      <div className="flex flex-wrap items-center gap-2 mb-3">
        <span className="inline-flex px-2.5 py-1 text-xs font-semibold rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
          {categoryLabel}
        </span>
        <span className={`inline-flex px-2.5 py-1 text-xs font-semibold rounded-full ${TONE_CLASSES[accessTone]}`}>
          {accessLabel}
        </span>
      </div>

      <h1 className="text-3xl sm:text-4xl font-bold text-slate-900 dark:text-white mb-2">{title}</h1>
      <p className="text-base text-slate-600 dark:text-slate-400 mb-6">{author}</p>

      {demoNotice && (
        <div className="flex items-start gap-2.5 rounded-xl border border-amber-200 dark:border-amber-900 bg-amber-50 dark:bg-amber-950/20 px-4 py-3 mb-6">
          <Info className="w-4 h-4 mt-0.5 flex-shrink-0 text-amber-600 dark:text-amber-400" />
          <p className="text-sm text-amber-900 dark:text-amber-200">{demoNotice}</p>
        </div>
      )}

      <p className="text-base text-slate-700 dark:text-slate-300 mb-6">{description}</p>

      <dl className="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 mb-6 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5">
        {metadata.map((entry) => (
          <div key={entry.label} className="flex flex-col">
            <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
              {entry.label}
            </dt>
            <dd className="text-sm font-medium text-slate-900 dark:text-white">{entry.value}</dd>
          </div>
        ))}
      </dl>

      <div className="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 sm:p-6 mb-6">
        {children}
      </div>

      <div className="rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50 p-5 mb-6">
        <h2 className="text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-2">
          {licenseLabel}
        </h2>
        <p className="text-sm text-slate-700 dark:text-slate-300">{rightsHolder}</p>
        {sourceUrl && (
          <a
            href={sourceUrl}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center gap-1.5 mt-2 text-sm text-emerald-700 dark:text-emerald-300 hover:underline"
          >
            <ExternalLink className="w-3.5 h-3.5" />
            {sourceNotice}
          </a>
        )}
      </div>

      <div className="flex items-start gap-2.5 text-xs text-slate-500 dark:text-slate-400">
        <Info className="w-3.5 h-3.5 mt-0.5 flex-shrink-0" />
        <p>{disclaimer}</p>
      </div>
    </div>
  );
}
