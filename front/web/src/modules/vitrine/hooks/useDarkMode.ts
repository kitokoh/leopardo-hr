'use client';

import { useState, useEffect } from 'react';

export function useDarkMode() {
  const [isDark, setIsDark] = useState(false);
  const [isMounted, setIsMounted] = useState(false);

  useEffect(() => {
    setIsMounted(true);

    // Check localStorage first
    const stored = localStorage.getItem('theme');
    if (stored) {
      const dark = stored === 'dark';
      setIsDark(dark);
      applyTheme(dark);
      return;
    }

    // Check system preference
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    setIsDark(prefersDark);
    applyTheme(prefersDark);
  }, []);

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
