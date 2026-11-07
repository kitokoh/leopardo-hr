'use client';

import React, { useMemo, useState } from 'react';
import { Search } from 'lucide-react';
import { ToolCard } from './ToolCard';

export interface CatalogItem {
  slug: string;
  name: string;
  description: string;
  category: string;
  categoryLabel: string;
  keywords: string[];
}

interface ToolboxCatalogProps {
  items: CatalogItem[];
  categories: { id: string; label: string }[];
  copy: {
    searchPlaceholder: string;
    noResults: string;
    allCategories: string;
    openTool: string;
    toolsCount: string;
  };
}

/** Minuscules sans diacritiques — recherche insensible à la casse/accents. */
function normalize(value: string): string {
  return value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim();
}

/**
 * Catalogue Toolbox — recherche plein-texte + filtre par catégorie.
 *
 * Composant CLIENT : le filtrage est instantané et 100 % local (aucun appel
 * réseau). Les données sont sérialisables (passées depuis le composant serveur).
 */
export function ToolboxCatalog({ items, categories, copy }: ToolboxCatalogProps) {
  const [query, setQuery] = useState('');
  const [category, setCategory] = useState<string>('all');

  const filtered = useMemo(() => {
    const needle = normalize(query);
    return items.filter((item) => {
      if (category !== 'all' && item.category !== category) return false;
      if (needle === '') return true;
      const haystack = normalize([item.name, item.description, item.slug, ...item.keywords].join(' '));
      return haystack.includes(needle);
    });
  }, [items, query, category]);

  return (
    <div>
      <div className="flex flex-col sm:flex-row gap-3 mb-6">
        <div className="relative flex-1">
          <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
          <input
            type="search"
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            placeholder={copy.searchPlaceholder}
            aria-label={copy.searchPlaceholder}
            className="w-full pl-10 pr-4 py-2.5 text-sm font-medium bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
          />
        </div>
        <select
          value={category}
          onChange={(event) => setCategory(event.target.value)}
          aria-label={copy.allCategories}
          className="px-4 py-2.5 text-sm font-medium bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500"
        >
          <option value="all">{copy.allCategories}</option>
          {categories.map((entry) => (
            <option key={entry.id} value={entry.id}>
              {entry.label}
            </option>
          ))}
        </select>
      </div>

      <p className="text-sm text-slate-500 dark:text-slate-400 mb-4">
        {filtered.length} {copy.toolsCount}
      </p>

      {filtered.length === 0 ? (
        <p className="text-sm text-slate-500 dark:text-slate-400 py-8 text-center">{copy.noResults}</p>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {filtered.map((item) => (
            <ToolCard
              key={item.slug}
              href={`/toolbox/${item.slug}`}
              name={item.name}
              description={item.description}
              categoryLabel={item.categoryLabel}
              openLabel={copy.openTool}
            />
          ))}
        </div>
      )}
    </div>
  );
}
