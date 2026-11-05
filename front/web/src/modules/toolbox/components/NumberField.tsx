'use client';

import React from 'react';

interface NumberFieldProps {
  id: string;
  label: string;
  value: string;
  onChange: (value: string) => void;
  unit?: string;
  min?: number;
  max?: number;
  step?: number;
  placeholder?: string;
  error?: string;
}

/**
 * Champ numérique de la Toolbox — saisie contrôlée avec unité et bornes.
 *
 * `type="text"` + `inputMode="decimal"` : accepte la virgule décimale (fr) et
 * évite les artefacts de `type="number"` (molette, e/+, valeur vide).
 */
export function NumberField({
  id,
  label,
  value,
  onChange,
  unit,
  min,
  max,
  placeholder,
  error,
}: NumberFieldProps) {
  const errorId = `${id}-error`;

  return (
    <div className="w-full">
      <label htmlFor={id} className="block text-sm font-semibold text-slate-900 dark:text-white mb-2">
        {label}
      </label>
      <div className="relative">
        <input
          id={id}
          type="text"
          inputMode="decimal"
          value={value}
          onChange={(event) => onChange(event.target.value)}
          placeholder={placeholder}
          aria-invalid={error ? true : undefined}
          aria-describedby={error ? errorId : undefined}
          className={`w-full px-4 py-2.5 text-sm font-medium bg-white dark:bg-slate-900 border rounded-xl transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent ${
            error ? 'border-red-500 focus:ring-red-500' : 'border-slate-300 dark:border-slate-700'
          } ${unit ? 'pr-14' : ''}`}
        />
        {unit && (
          <span className="absolute right-3.5 top-1/2 -translate-y-1/2 text-sm text-slate-400 pointer-events-none">
            {unit}
          </span>
        )}
      </div>
      {error && (
        <p id={errorId} role="alert" className="mt-2 text-sm text-red-600 dark:text-red-400">
          {error}
        </p>
      )}
      {(min !== undefined || max !== undefined) && !error && (
        <p className="mt-1.5 text-xs text-slate-400">
          {min !== undefined && max !== undefined
            ? `${min} – ${max}`
            : min !== undefined
              ? `≥ ${min}`
              : `≤ ${max}`}
        </p>
      )}
    </div>
  );
}
