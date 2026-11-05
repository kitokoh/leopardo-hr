import React from 'react';
import Link from 'next/link';
import { AlertTriangle, ArrowLeft, ExternalLink, Info } from 'lucide-react';

interface ToolShellProps {
  title: string;
  description: string;
  categoryLabel: string;
  warnings: string[];
  references: { label: string; url: string }[];
  referencesLabel: string;
  versionLabel: string;
  version: string;
  backLabel: string;
  disclaimer: string;
  children: React.ReactNode;
}

/**
 * Gabarit de page d'outil — en-tête, avertissements, contenu, références.
 *
 * Composant SERVEUR (aucun état) : il encadre le formulaire client passé en
 * `children`. Les avertissements sont rendus AVANT le formulaire pour qu'ils
 * soient lus avant toute interprétation du résultat.
 */
export function ToolShell({
  title,
  description,
  categoryLabel,
  warnings,
  references,
  referencesLabel,
  versionLabel,
  version,
  backLabel,
  disclaimer,
  children,
}: ToolShellProps) {
  return (
    <div className="mx-auto w-full max-w-3xl px-4 py-10 sm:py-14">
      <Link
        href="/toolbox"
        className="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 dark:text-slate-400 hover:text-emerald-700 dark:hover:text-emerald-300 transition-colors mb-6"
      >
        <ArrowLeft className="w-4 h-4" />
        {backLabel}
      </Link>

      <span className="inline-flex px-2.5 py-1 mb-3 text-xs font-semibold rounded-full bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300">
        {categoryLabel}
      </span>
      <h1 className="text-3xl sm:text-4xl font-bold text-slate-900 dark:text-white mb-3">{title}</h1>
      <p className="text-base text-slate-600 dark:text-slate-400 mb-6">{description}</p>

      {warnings.length > 0 && (
        <div className="space-y-2 mb-6">
          {warnings.map((warning) => (
            <div
              key={warning}
              className="flex items-start gap-2.5 rounded-xl border border-amber-200 dark:border-amber-900 bg-amber-50 dark:bg-amber-950/20 px-4 py-3"
            >
              <AlertTriangle className="w-4 h-4 mt-0.5 flex-shrink-0 text-amber-600 dark:text-amber-400" />
              <p className="text-sm text-amber-900 dark:text-amber-200">{warning}</p>
            </div>
          ))}
        </div>
      )}

      <div className="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 sm:p-6">
        {children}
      </div>

      {references.length > 0 && (
        <div className="mt-6">
          <h2 className="text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-2">
            {referencesLabel}
          </h2>
          <ul className="space-y-1.5">
            {references.map((reference) => (
              <li key={reference.url}>
                <a
                  href={reference.url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex items-center gap-1.5 text-sm text-emerald-700 dark:text-emerald-300 hover:underline"
                >
                  <ExternalLink className="w-3.5 h-3.5" />
                  {reference.label}
                </a>
              </li>
            ))}
          </ul>
        </div>
      )}

      <div className="mt-6 flex items-start gap-2.5 text-xs text-slate-500 dark:text-slate-400">
        <Info className="w-3.5 h-3.5 mt-0.5 flex-shrink-0" />
        <div>
          <p>{disclaimer}</p>
          <p className="mt-1">
            {versionLabel} : {version}
          </p>
        </div>
      </div>
    </div>
  );
}
