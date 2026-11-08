import React from 'react';
import Link from 'next/link';
import { ArrowRight } from 'lucide-react';

interface ResourceCardProps {
  href: string;
  title: string;
  author: string;
  categoryLabel: string;
  accessLabel: string;
  accessTone: 'free' | 'paid' | 'private';
  openLabel: string;
}

const TONE_CLASSES: Record<ResourceCardProps['accessTone'], string> = {
  free: 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300',
  paid: 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300',
  private: 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300',
};

/** Carte de ressource de la bibliothèque — lien vers la fiche. */
export function ResourceCard({
  href,
  title,
  author,
  categoryLabel,
  accessLabel,
  accessTone,
  openLabel,
}: ResourceCardProps) {
  return (
    <Link
      href={href}
      className="group flex flex-col h-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 transition-all duration-300 hover:border-emerald-400 dark:hover:border-emerald-600 hover:shadow-lg hover:-translate-y-1"
    >
      <div className="flex items-center gap-2 mb-3">
        <span className="inline-flex px-2.5 py-1 text-xs font-semibold rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
          {categoryLabel}
        </span>
        <span className={`inline-flex px-2.5 py-1 text-xs font-semibold rounded-full ${TONE_CLASSES[accessTone]}`}>
          {accessLabel}
        </span>
      </div>
      <h3 className="text-base font-bold text-slate-900 dark:text-white mb-1">{title}</h3>
      <p className="text-sm text-slate-500 dark:text-slate-400 flex-1">{author}</p>
      <span className="inline-flex items-center gap-1.5 mt-4 text-sm font-semibold text-emerald-700 dark:text-emerald-300">
        {openLabel}
        <ArrowRight className="w-4 h-4 transition-transform group-hover:translate-x-1" />
      </span>
    </Link>
  );
}
