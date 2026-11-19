'use client';

import { useEffect, useState } from 'react';
import { Monitor, Moon, Sun } from 'lucide-react';
import type { PublicHomeCopy } from '../data/home-copy';

/**
 * Bascule d'apparence du portail grand public (clair / sombre / système).
 *
 * ## Pourquoi trois états et pas deux
 *
 * Un simple interrupteur clair↔sombre force un choix explicite dès le premier
 * clic et empêche de revenir au comportement du système. Trois états
 * (`light` / `dark` / `system`) laissent le visiteur suivre son OS par défaut
 * — c'est le comportement attendu en 2026 — tout en lui permettant de forcer
 * un mode.
 *
 * ## Anti-FOUC
 *
 * Le thème est appliqué par un script inline bloquant dans `<head>` (voir
 * `ThemeScript`), AVANT le premier paint : sans lui, un visiteur en mode
 * sombre verrait un flash blanc à chaque navigation. Ce composant ne fait que
 * refléter l'état déjà posé sur `<html class="dark">`.
 *
 * ## Accessibilité
 *
 * - `role="group"` + `aria-label` : le groupe est annoncé.
 * - Chaque bouton porte `aria-pressed` : l'état actif est annoncé, pas
 *   seulement coloré (la couleur seule ne suffit pas — WCAG 1.4.1).
 * - Cibles de 40 px minimum.
 */

export type ThemeMode = 'light' | 'dark' | 'system';

const STORAGE_KEY = 'leopardo-public-theme';

/** Applique le mode au document et persiste le choix. */
function applyTheme(mode: ThemeMode): void {
  const root = document.documentElement;
  const prefersDark =
    typeof window !== 'undefined' &&
    window.matchMedia('(prefers-color-scheme: dark)').matches;
  const dark = mode === 'dark' || (mode === 'system' && prefersDark);

  root.classList.toggle('dark', dark);
  root.dataset.theme = mode;
  try {
    window.localStorage.setItem(STORAGE_KEY, mode);
  } catch {
    // Navigation privée / stockage plein : le thème reste appliqué pour la
    // session, seule la persistance est perdue. Ne jamais casser la page.
  }
}

export function ThemeToggle({ copy }: { copy: PublicHomeCopy['theme'] }) {
  // `null` au premier rendu : le serveur ne connaît pas la préférence, et
  // rendre un état par défaut provoquerait un mismatch d'hydratation.
  const [mode, setMode] = useState<ThemeMode | null>(null);

  useEffect(() => {
    const stored = (() => {
      try {
        return window.localStorage.getItem(STORAGE_KEY) as ThemeMode | null;
      } catch {
        return null;
      }
    })();
    setMode(stored ?? 'system');
  }, []);

  const choose = (next: ThemeMode) => {
    setMode(next);
    applyTheme(next);
  };

  const options: { value: ThemeMode; label: string; Icon: typeof Sun }[] = [
    { value: 'light', label: copy.light, Icon: Sun },
    { value: 'dark', label: copy.dark, Icon: Moon },
    { value: 'system', label: copy.system, Icon: Monitor },
  ];

  return (
    <div
      role="group"
      aria-label={copy.label}
      className="inline-flex items-center gap-0.5 rounded-full border border-slate-200 bg-white/70 p-0.5 backdrop-blur dark:border-white/10 dark:bg-white/5"
    >
      {options.map(({ value, label, Icon }) => {
        const active = mode === value;
        return (
          <button
            key={value}
            type="button"
            onClick={() => choose(value)}
            aria-pressed={active}
            aria-label={label}
            title={label}
            className={`inline-flex h-9 w-9 items-center justify-center rounded-full transition-colors ${
              active
                ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900'
                : 'text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-white/10 dark:hover:text-white'
            }`}
          >
            <Icon className="h-4 w-4" aria-hidden />
          </button>
        );
      })}
    </div>
  );
}

/**
 * Script inline à poser dans `<head>` : applique le thème AVANT le premier
 * paint. Rendu côté serveur, il ne dépend d'aucun état React.
 *
 * Il est volontairement minuscule et sans dépendance : il s'exécute sur
 * chaque page du portail, avant tout autre script.
 */
export const THEME_SCRIPT = `(function(){try{var m=localStorage.getItem('${STORAGE_KEY}')||'system';var d=m==='dark'||(m==='system'&&window.matchMedia('(prefers-color-scheme: dark)').matches);document.documentElement.classList.toggle('dark',d);document.documentElement.dataset.theme=m;}catch(e){}})();`;
