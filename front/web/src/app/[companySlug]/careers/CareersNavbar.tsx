'use client';

import { useEffect, useState } from 'react';
import { Navbar } from '@/modules/vitrine/components/Navbar';

const DARK_PREF_KEY = 'leopardo_theme';

/**
 * Wrapper client qui rend le toggle de theme fonctionnel sur les portails
 * carrieres (server components) : bascule de la classe `dark` sur
 * <html> (tailwind darkMode: "class"), persistee dans localStorage.
 */
export function CareersNavbar() {
  const [isDark, setIsDark] = useState(false);
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    let dark = false;

    try {
      const stored = window.localStorage.getItem(DARK_PREF_KEY);
      dark = stored === 'dark';
    } catch {
      // localStorage indisponible — on retombe sur le mode systeme.
    }

    if (!dark) {
      dark = window.matchMedia(`(prefers-color-scheme: ${'dark'})`).matches;
    }

    setIsDark(dark);
    document.documentElement.classList.toggle('dark', dark);
    setMounted(true);
  }, []);

  function toggleDark() {
    setIsDark((current) => {
      const next = !current;
      document.documentElement.classList.toggle('dark', next);

      try {
        window.localStorage.setItem(DARK_PREF_KEY, next ? 'dark' : 'light');
      } catch {
        // localStorage indisponible — l'etat reste en memoire pour la session.
      }

      return next;
    });
  }

  // Evite un flash de mauvais theme au premier rendu (SSR).
  if (!mounted) {
    return <Navbar isDark={false} onToggleDark={() => {}} />;
  }

  return <Navbar isDark={isDark} onToggleDark={toggleDark} />;
}
