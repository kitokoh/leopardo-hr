'use client';

import { createContext, useContext, useEffect, useState } from 'react';

type Theme = 'light' | 'dark' | 'system';

interface DarkModeContextType {
  theme: Theme;
  isDark: boolean;
  setTheme: (theme: Theme) => void;
  toggleDarkMode: () => void;
}

const DarkModeContext = createContext<DarkModeContextType | undefined>(undefined);

/**
 * DarkModeProvider Component
 * Manages dark mode state with localStorage persistence
 * Supports system preference detection
 */
export function DarkModeProvider({ children }: { children: React.ReactNode }) {
  const [theme, setThemeState] = useState<Theme>('system');
  const [isDark, setIsDark] = useState(false);
  const [mounted, setMounted] = useState(false);

  // Initialize theme from localStorage and system preference
  useEffect(() => {
    // Get saved theme from localStorage
    const savedTheme = localStorage.getItem('theme') as Theme | null;
    const initialTheme = savedTheme || 'system';

    // Determine if dark mode should be active
    const shouldBeDark = getDarkModeState(initialTheme);
    
    // Update state together
    setThemeState(initialTheme);
    setIsDark(shouldBeDark);
    applyTheme(shouldBeDark);

    setMounted(true);
  }, []);

  // Listen for system theme changes
  useEffect(() => {
    if (!mounted) return;

    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

    const handleChange = () => {
      if (theme === 'system') {
        const shouldBeDark = mediaQuery.matches;
        setIsDark(shouldBeDark);
        applyTheme(shouldBeDark);
      }
    };

    mediaQuery.addEventListener('change', handleChange);

    return () => {
      mediaQuery.removeEventListener('change', handleChange);
    };
  }, [theme, mounted]);

  const setTheme = (newTheme: Theme) => {
    setThemeState(newTheme);
    localStorage.setItem('theme', newTheme);

    const shouldBeDark = getDarkModeState(newTheme);
    setIsDark(shouldBeDark);
    applyTheme(shouldBeDark);
  };

  const toggleDarkMode = () => {
    const newTheme = isDark ? 'light' : 'dark';
    setTheme(newTheme);
  };

  if (!mounted) {
    return <>{children}</>;
  }

  return (
    <DarkModeContext.Provider value={{ theme, isDark, setTheme, toggleDarkMode }}>
      {children}
    </DarkModeContext.Provider>
  );
}

/**
 * useDarkMode Hook
 * Access dark mode state and functions from components
 */
export function useDarkMode() {
  const context = useContext(DarkModeContext);

  if (!context) {
    throw new Error('useDarkMode must be used within DarkModeProvider');
  }

  return context;
}

/**
 * Determine if dark mode should be active
 */
function getDarkModeState(theme: Theme): boolean {
  if (theme === 'dark') {
    return true;
  }

  if (theme === 'light') {
    return false;
  }

  // System preference
  if (typeof window !== 'undefined') {
    return window.matchMedia('(prefers-color-scheme: dark)').matches;
  }

  return false;
}

/**
 * Apply theme to document
 */
function applyTheme(isDark: boolean) {
  const html = document.documentElement;

  if (isDark) {
    html.classList.add('dark');
    html.style.colorScheme = 'dark';
  } else {
    html.classList.remove('dark');
    html.style.colorScheme = 'light';
  }

  // Update CSS variables
  updateCSSVariables(isDark);
}

/**
 * Update CSS variables based on theme
 */
function updateCSSVariables(isDark: boolean) {
  const root = document.documentElement;

  if (isDark) {
    root.style.setProperty('--background', '#0f172a');
    root.style.setProperty('--foreground', '#ffffff');
    root.style.setProperty('--card', '#1e293b');
    root.style.setProperty('--card-foreground', '#ffffff');
    root.style.setProperty('--primary', '#10b981');
    root.style.setProperty('--primary-foreground', '#ffffff');
    root.style.setProperty('--secondary', '#06b6d4');
    root.style.setProperty('--secondary-foreground', '#ffffff');
    root.style.setProperty('--muted', '#475569');
    root.style.setProperty('--muted-foreground', '#cbd5e1');
    root.style.setProperty('--accent', '#10b981');
    root.style.setProperty('--accent-foreground', '#ffffff');
    root.style.setProperty('--destructive', '#ef4444');
    root.style.setProperty('--destructive-foreground', '#ffffff');
    root.style.setProperty('--border', '#334155');
    root.style.setProperty('--input', '#1e293b');
    root.style.setProperty('--ring', '#10b981');
  } else {
    root.style.setProperty('--background', '#ffffff');
    root.style.setProperty('--foreground', '#0f172a');
    root.style.setProperty('--card', '#f8fafc');
    root.style.setProperty('--card-foreground', '#0f172a');
    root.style.setProperty('--primary', '#10b981');
    root.style.setProperty('--primary-foreground', '#ffffff');
    root.style.setProperty('--secondary', '#06b6d4');
    root.style.setProperty('--secondary-foreground', '#ffffff');
    root.style.setProperty('--muted', '#94a3b8');
    root.style.setProperty('--muted-foreground', '#475569');
    root.style.setProperty('--accent', '#10b981');
    root.style.setProperty('--accent-foreground', '#ffffff');
    root.style.setProperty('--destructive', '#ef4444');
    root.style.setProperty('--destructive-foreground', '#ffffff');
    root.style.setProperty('--border', '#e2e8f0');
    root.style.setProperty('--input', '#f1f5f9');
    root.style.setProperty('--ring', '#10b981');
  }
}

export default DarkModeProvider;
