import React from 'react';
import Link from 'next/link';
import { ArrowRight } from 'lucide-react';

interface ToolCardProps {
  href: string;
  name: string;
  description: string;
  categoryLabel: string;
  openLabel: string;
}

/** Carte d'outil du catalogue — lien vers la page de l'outil. */
export function ToolCard({ href, name, description, categoryLabel, openLabel }: ToolCardProps) {
  return (
    <Link
      href={href}
      className="group flex flex-col h-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 transition-all duration-300 hover:border-emerald-400 dark:hover:border-emerald-600 hover:shadow-lg hover:-translate-y-1"
    >
      <span className="inline-flex self-start px-2.5 py-1 mb-3 text-xs font-semibold rounded-full bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300">
        {categoryLabel}
      </span>
      <h3 className="text-base font-bold text-slate-900 dark:text-white mb-2">{name}</h3>
      <p className="text-sm text-slate-600 dark:text-slate-400 flex-1">{description}</p>
      <span className="inline-flex items-center gap-1.5 mt-4 text-sm font-semibold text-emerald-700 dark:text-emerald-300">
        {openLabel}
        <ArrowRight className="w-4 h-4 transition-transform group-hover:translate-x-1" />
      </span>
    </Link>
  );
}
