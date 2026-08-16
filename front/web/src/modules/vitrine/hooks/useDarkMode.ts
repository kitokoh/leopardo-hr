'use client';

import { useState, useEffect } from 'react';

export function useDarkMode() {
  // #4301 : état initial résolu de façon SYNCHRONE côté client (SSR → false)
  // pour que le premier paint corresponde au thème stocké/préféré — le
  // composant peut ainsi se rendre immédiatement (plus de layout nul en SSR).
  const [isDark, setIsDark] = useState<boolean>(() => {
    if (typeof window === 'undefined') {
      return false;
    }
    const stored = localStorage.getItem('theme');
    if (stored) {
      return stored === 'dark';
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
  });
  const [isMounted, setIsMounted] = useState(false);

  useEffect(() => {
    setIsMounted(true);
    applyTheme(isDark);
  }, [isDark]);

  const toggleDarkMode = () => {
    setIsDark((prev) => {
      const newValue = !prev;
      applyTheme(newValue);
      localStorage.setItem('theme', newValue ? 'dark' : 'light');
      return newValue;
    });
  };

  const setDarkMode = (value: boolean) => {
    setIsDark(value);
    applyTheme(value);
    localStorage.setItem('theme', value ? 'dark' : 'light');
  };

  return {
    isDark,
    toggleDarkMode,
    setDarkMode,
    isMounted,
  };
}

function applyTheme(isDark: boolean) {
  const html = document.documentElement;
  if (isDark) {
    html.classList.add('dark');
  } else {
    html.classList.remove('dark');
  }
}
