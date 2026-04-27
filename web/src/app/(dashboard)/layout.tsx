'use client';

import Link from 'next/link';
import { useEffect, useMemo, useState } from 'react';
import { useRouter } from 'next/navigation';
import { apiFetch } from '@/lib/api-client';
import {
  applyDocumentLocale,
  clearAuthSession,
  getCopy,
  getDisplayName,
  getStoredUser,
  normalizeLocale,
  storeAuthSession,
  type AppLocale,
  type StoredAuthUser,
} from '@/lib/i18n';

export default function DashboardLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const router = useRouter();
  const [user, setUser] = useState<StoredAuthUser | null>(null);
  const [locale, setLocale] = useState<AppLocale>('fr');
  const labels = useMemo(() => getCopy(locale), [locale]);

  useEffect(() => {
    const storedUser = getStoredUser();
    if (!storedUser) {
      router.replace('/auth/login');
      return;
    }

    const resolvedLocale = normalizeLocale(storedUser.language);
    setUser(storedUser);
    setLocale(resolvedLocale);
    applyDocumentLocale(resolvedLocale, storedUser.is_rtl);
  }, [router]);

  const handleLogout = () => {
    clearAuthSession();
    router.push('/auth/login');
  };

  const handleLanguageChange = async (value: string) => {
    const nextLocale = normalizeLocale(value);
    const response = await apiFetch('/auth/language', {
      method: 'PATCH',
      body: JSON.stringify({ language: nextLocale }),
    });

    const payload = await response.json() as { data?: StoredAuthUser };
    if (!payload.data) {
      return;
    }

    const token = localStorage.getItem('auth_token');
    if (token) {
      storeAuthSession(token, payload.data);
    }

    setUser(payload.data);
    setLocale(normalizeLocale(payload.data.language));
    applyDocumentLocale(normalizeLocale(payload.data.language), payload.data.is_rtl);
  };

  return (
    <div className="flex min-h-screen bg-app-card">
      <aside className="hidden w-64 flex-col bg-slate-900 text-white md:flex">
        <div className="p-6">
          <h1 className="text-2xl font-bold tracking-tight">Leopardo RH</h1>
          <p className="mt-1 text-xs font-semibold uppercase tracking-widest text-slate-400">Back-office Manager</p>
        </div>

        <nav className="mt-4 flex-1">
          <div className="mb-2 px-4">
            <p className="px-2 text-[10px] font-bold uppercase tracking-widest text-slate-500">General</p>
          </div>
          <Link href="/dashboard" className="flex items-center gap-3 border-r-4 border-rh bg-slate-800/50 px-6 py-3 transition-colors hover:bg-slate-800">
            <span className="h-2 w-2 rounded-full bg-rh"></span>
            {labels.dashboard.heading}
          </Link>

          <div className="mb-2 mt-6 px-4">
            <p className="px-2 text-[10px] font-bold uppercase tracking-widest text-slate-500">Module RH</p>
          </div>
          <Link href="/employees" className="flex items-center gap-3 px-6 py-3 text-slate-300 transition-colors hover:bg-slate-800 hover:text-white">
            <span className="h-2 w-2 rounded-full bg-slate-600"></span>
            {labels.dashboard.team}
          </Link>
          <Link href="/attendance" className="flex items-center gap-3 px-6 py-3 text-slate-300 transition-colors hover:bg-slate-800 hover:text-white">
            <span className="h-2 w-2 rounded-full bg-slate-600"></span>
            {labels.dashboard.attendance}
          </Link>
          <Link href="/absences" className="flex items-center gap-3 px-6 py-3 text-slate-300 transition-colors hover:bg-slate-800 hover:text-white">
            <span className="h-2 w-2 rounded-full bg-slate-600"></span>
            Absences
          </Link>

          <div className="mb-2 mt-6 px-4 opacity-40">
            <p className="px-2 text-[10px] font-bold uppercase tracking-widest text-slate-500">Phase 2</p>
          </div>
          <div className="cursor-not-allowed px-6 py-3 text-sm italic text-slate-500">
            <div className="flex items-center gap-3">
              <span className="h-2 w-2 rounded-full bg-finance opacity-30"></span>
              Finance (Bientot)
            </div>
          </div>
          <div className="cursor-not-allowed px-6 py-3 text-sm italic text-slate-500">
            <div className="flex items-center gap-3">
              <span className="h-2 w-2 rounded-full bg-security opacity-30"></span>
              Cameras (Bientot)
            </div>
          </div>
        </nav>

        <div className="m-4 rounded-xl border border-ia/20 bg-ia/10 p-4">
          <div className="mb-2 flex items-center gap-2 text-ia">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/></svg>
            <span className="text-xs font-bold uppercase tracking-wider">Leo IA</span>
          </div>
          <p className="text-[10px] leading-relaxed text-slate-400">
            Leo arrive bientot sur le web pour vous aider a analyser vos donnees RH par simple commande vocale.
          </p>
        </div>

        <div className="border-t border-slate-800 p-6">
          <div className="mb-4 flex items-center gap-3">
            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-rh text-xs font-bold">MA</div>
            <div className="overflow-hidden">
              <p className="truncate text-xs font-bold">{getDisplayName(user)}</p>
              <p className="truncate text-[10px] text-slate-500">{user?.email ?? 'Leopardo RH'}</p>
            </div>
          </div>
          <button
            className="w-full rounded-lg bg-slate-800 px-4 py-2 text-xs font-semibold transition-all hover:bg-red-900/20 hover:text-red-400"
            onClick={handleLogout}
          >
            {labels.dashboard.logout}
          </button>
        </div>
      </aside>

      <div className="flex flex-1 flex-col">
        <header className="sticky top-0 z-10 flex h-16 items-center justify-between border-b border-app-border bg-white px-8">
          <h2 className="text-lg font-bold text-slate-800">{labels.dashboard.heading}</h2>
          <div className="flex items-center gap-4">
            <label className="flex items-center gap-2 text-sm text-gray-600">
              <span>{labels.dashboard.language}</span>
              <select
                className="rounded-md border border-slate-300 bg-white px-2 py-1 text-sm text-slate-700"
                value={locale}
                onChange={(e) => void handleLanguageChange(e.target.value)}
              >
                <option value="fr">Francais</option>
                <option value="ar">العربية</option>
                <option value="tr">Turkce</option>
                <option value="en">English</option>
              </select>
            </label>
            <div className="flex items-center gap-2 rounded-full bg-rh-light px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-rh-dark">
              <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-rh"></span>
              Live: 18 {labels.dashboard.present}
            </div>
          </div>
        </header>
        <main className="mx-auto w-full max-w-7xl p-8">
          {children}
        </main>
      </div>
    </div>
  );
}
