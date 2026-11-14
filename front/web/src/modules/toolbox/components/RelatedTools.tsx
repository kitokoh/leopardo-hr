import React from 'react';
import Link from 'next/link';
import { ArrowRight } from 'lucide-react';

export interface RelatedToolItem {
  slug: string;
  name: string;
  categoryLabel: string;
}

interface RelatedToolsProps {
  title: string;
  items: RelatedToolItem[];
}

/**
 * Section « Outils similaires » en bas d'une page d'outil.
 *
 * Composant SERVEUR (aucun état) : la liste est résolue depuis le catalogue
 * (`getRelatedTools`) et passée sérialisée. Elle évite l'impasse où
 * l'utilisateur, après avoir obtenu son résultat, ne pouvait que revenir au
 * catalogue complet.
 *
 * Rien n'est rendu si la liste est vide : pas de titre orphelin.
 */
export function RelatedTools({ title, items }: RelatedToolsProps) {
  if (items.length === 0) return null;

  return (
    <section aria-labelledby="related-tools-title" className="mt-10">
      <h2
        id="related-tools-title"
        className="mb-3 text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400"
      >
        {title}
      </h2>
      <ul className="grid gap-3 sm:grid-cols-3">
        {items.map((item) => (
          <li key={item.slug}>
            <Link
              href={`/toolbox/${item.slug}`}
              className="group flex h-full flex-col rounded-xl border border-slate-200 bg-white p-4 transition-colors hover:border-emerald-400 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-emerald-600"
            >
              <span className="mb-1.5 text-xs font-semibold text-emerald-700 dark:text-emerald-300">
                {item.categoryLabel}
              </span>
              <span className="flex-1 text-sm font-semibold text-slate-900 dark:text-white">
                {item.name}
              </span>
              <ArrowRight className="mt-2 h-4 w-4 text-slate-400 transition-transform group-hover:translate-x-1" />
            </Link>
          </li>
        ))}
      </ul>
    </section>
  );
}
