'use client';

import React, { useMemo, useState } from 'react';
import { Search } from 'lucide-react';
import { ResourceCard } from './ResourceCard';

export interface CatalogItem {
  slug: string;
  title: string;
  author: string;
  category: string;
  categoryLabel: string;
  language: string;
  accessModel: string;
  accessLabel: string;
  accessTone: 'free' | 'paid' | 'private';
  documentType: string;
  keywords: string[];
}

interface LibraryCatalogProps {
  items: CatalogItem[];
  categories: { id: string; label: string }[];
  languages: { id: string; label: string }[];
  copy: {
    searchPlaceholder: string;
    noResults: string;
    allCategories: string;
    allLanguages: string;
    allAccess: string;
    allTypes: string;
    freeOnly: string;
    openLabel: string;
    resourcesCount: string;
  };
  accessOptions: { id: string; label: string }[];
  typeOptions: { id: string; label: string }[];
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
 * Catalogue de la bibliothèque — recherche plein-texte + filtres combinés
 * (catégorie, langue, modèle d'accès, type de document, gratuit uniquement).
 *
 * Composant CLIENT : le filtrage est instantané et 100 % local (aucun appel
 * réseau). Les données sont sérialisables (passées depuis le composant serveur).
 */
export function LibraryCatalog({
  items,
  categories,
  languages,
  copy,
  accessOptions,
  typeOptions,
}: LibraryCatalogProps) {
  const [query, setQuery] = useState('');
  const [category, setCategory] = useState('all');
  const [language, setLanguage] = useState('all');
  const [access, setAccess] = useState('all');
  const [type, setType] = useState('all');
  const [freeOnly, setFreeOnly] = useState(false);

  const filtered = useMemo(() => {
    const needle = normalize(query);
    return items.filter((item) => {
      if (category !== 'all' && item.category !== category) return false;
      if (language !== 'all' && item.language !== language) return false;
      if (access !== 'all' && item.accessModel !== access) return false;
      if (type !== 'all' && item.documentType !== type) return false;
      if (freeOnly && item.accessModel !== 'free' && item.accessModel !== 'read_only') return false;
      if (needle === '') return true;
      const haystack = normalize(
        [item.title, item.author, item.slug, ...item.keywords].join(' '),
      );
      return haystack.includes(needle);
    });
  }, [items, query, category, language, access, type, freeOnly]);

  const selectClass =
    'px-3 py-2.5 text-sm font-medium bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500';

  return (
    <div>
      <div className="flex flex-col gap-3 mb-6">
        <div className="relative">
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

        <div className="flex flex-wrap gap-2">
          <select
            value={category}
            onChange={(event) => setCategory(event.target.value)}
            aria-label={copy.allCategories}
            className={selectClass}
          >
            <option value="all">{copy.allCategories}</option>
            {categories.map((entry) => (
              <option key={entry.id} value={entry.id}>
                {entry.label}
              </option>
            ))}
          </select>

          <select
            value={language}
            onChange={(event) => setLanguage(event.target.value)}
            aria-label={copy.allLanguages}
            className={selectClass}
          >
            <option value="all">{copy.allLanguages}</option>
            {languages.map((entry) => (
              <option key={entry.id} value={entry.id}>
                {entry.label}
              </option>
            ))}
          </select>

          <select
            value={access}
            onChange={(event) => setAccess(event.target.value)}
            aria-label={copy.allAccess}
            className={selectClass}
          >
            <option value="all">{copy.allAccess}</option>
            {accessOptions.map((entry) => (
              <option key={entry.id} value={entry.id}>
                {entry.label}
              </option>
            ))}
          </select>

          <select
            value={type}
            onChange={(event) => setType(event.target.value)}
            aria-label={copy.allTypes}
            className={selectClass}
          >
            <option value="all">{copy.allTypes}</option>
            {typeOptions.map((entry) => (
              <option key={entry.id} value={entry.id}>
                {entry.label}
              </option>
            ))}
          </select>

          <label className="inline-flex items-center gap-2 px-3 py-2.5 text-sm font-medium text-slate-700 dark:text-slate-300">
            <input
              type="checkbox"
              checked={freeOnly}
              onChange={(event) => setFreeOnly(event.target.checked)}
              className="rounded border-slate-300 dark:border-slate-700 text-emerald-600 focus:ring-emerald-500"
            />
            {copy.freeOnly}
          </label>
        </div>
      </div>

      <p className="text-sm text-slate-500 dark:text-slate-400 mb-4">
        {filtered.length} {copy.resourcesCount}
      </p>

      {filtered.length === 0 ? (
        <p className="text-sm text-slate-500 dark:text-slate-400 py-8 text-center">{copy.noResults}</p>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {filtered.map((item) => (
            <ResourceCard
              key={item.slug}
              href={`/library/${item.slug}`}
              title={item.title}
              author={item.author}
              categoryLabel={item.categoryLabel}
              accessLabel={item.accessLabel}
              accessTone={item.accessTone}
              openLabel={copy.openLabel}
            />
          ))}
        </div>
      )}
    </div>
  );
}
