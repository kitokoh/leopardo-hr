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
    <div className="flex min-h-screen bg-slate-100">
      <aside className="hidden w-64 bg-slate-900 text-white md:block">
        <div className="p-6">
          <h1 className="text-2xl font-bold">Leopardo RH</h1>
        </div>
        <nav className="mt-6">
          <Link href="/dashboard" className="block border-l-4 border-primary bg-slate-800 px-6 py-3 hover:bg-slate-800">
            {labels.dashboard.heading}
          </Link>
          <Link href="/employees" className="block px-6 py-3 hover:bg-slate-800">
            {labels.dashboard.team}
          </Link>
          <Link href="/attendance" className="block px-6 py-3 hover:bg-slate-800">
            {labels.dashboard.attendance}
          </Link>
          <Link href="/payroll" className="block px-6 py-3 hover:bg-slate-800">
            {labels.dashboard.payroll}
          </Link>
          <Link href="/settings" className="block px-6 py-3 hover:bg-slate-800">
            {labels.dashboard.settings}
          </Link>
        </nav>
      </aside>

      <div className="flex flex-1 flex-col">
        <header className="flex h-16 items-center justify-between bg-white px-8 shadow-sm">
          <h2 className="text-xl font-semibold text-gray-800">{labels.dashboard.heading}</h2>
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
            <span className="text-sm text-gray-600">{getDisplayName(user)}</span>
            <button className="text-sm text-red-600 hover:underline" onClick={handleLogout}>
              {labels.dashboard.logout}
            </button>
          </div>
        </header>
        <main className="p-8">
          {children}
        </main>
      </div>
    </div>
  );
}
