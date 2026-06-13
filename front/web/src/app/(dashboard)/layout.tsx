'use client';

import Link from 'next/link';
import { useEffect, useMemo, useState } from 'react';
import { usePathname, useRouter } from 'next/navigation';
import { Bell, LockKeyhole, Sparkles } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { trackClientEvent } from '@/lib/client-analytics';
import { getClientModuleAccess, getModuleAccessForPath, type ClientModuleAccess } from '@/lib/client-features';
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
  const pathname = usePathname();
  const [storedUser, setStoredUser] = useState<StoredAuthUser | null>(null);
  const [mounted, setMounted] = useState(false);
  const [userOverride, setUserOverride] = useState<StoredAuthUser | null>(null);
  const [localeOverride, setLocaleOverride] = useState<AppLocale | null>(null);
  const [notificationPreview, setNotificationPreview] = useState<ClientNotification[]>([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [notificationsOpen, setNotificationsOpen] = useState(false);
  const user = userOverride ?? storedUser;
  const locale = localeOverride ?? normalizeLocale(user?.language);
  const labels = useMemo(() => getCopy(locale), [locale]);
  const modules = useMemo(() => getClientModuleAccess(user), [user]);
  const currentModule = useMemo(() => getModuleAccessForPath(pathname, user), [pathname, user]);

  useEffect(() => {
    setStoredUser(getStoredUser());
    setMounted(true);
  }, []);

  useEffect(() => {
    if (!mounted) {
      return;
    }

    if (!user) {
      clearAuthSession();
      window.location.replace('/auth/login');
      return;
    }
    applyDocumentLocale(locale, user.is_rtl);
  }, [locale, mounted, router, user]);

  const handleLogout = () => {
    router.push('/auth/logout');
  };

  useEffect(() => {
    let cancelled = false;

    async function loadNotifications() {
      if (!mounted || !user) {
        return;
      }

      try {
        const response = await apiFetch('/notifications?per_page=5&sort_dir=desc');
        const payload = await response.json() as {
          data?: ClientNotification[];
          meta?: { unread_count?: number };
        };

        if (cancelled) {
          return;
        }

        setNotificationPreview(Array.isArray(payload.data) ? payload.data : []);
        setUnreadCount(Number(payload.meta?.unread_count ?? 0));
      } catch {
        if (!cancelled) {
          setNotificationPreview([]);
          setUnreadCount(0);
        }
      }
    }

    void loadNotifications();
    const timer = window.setInterval(() => {
      void loadNotifications();
    }, 30000);

    return () => {
      cancelled = true;
      window.clearInterval(timer);
    };
  }, [mounted, user]);

  const markNotificationRead = async (notification: ClientNotification) => {
    if (notification.is_read) {
      return;
    }

    await apiFetch(`/notifications/${notification.id}/read`, {
      method: 'PUT',
    });

    setNotificationPreview((items) => items.map((item) => (
      item.id === notification.id ? { ...item, is_read: true } : item
    )));
    setUnreadCount((count) => Math.max(0, count - 1));
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

    setUserOverride(payload.data);
    setLocaleOverride(normalizeLocale(payload.data.language));
    applyDocumentLocale(normalizeLocale(payload.data.language), payload.data.is_rtl);
  };

  if (!mounted) {
    return null;
  }

  const navGroups = {
    general: modules.filter((module) => module.group === 'general' && module.href),
    hr: modules.filter((module) => module.group === 'hr' && module.href),
    finance: modules.filter((module) => module.group === 'finance' && module.href),
    platform: modules.filter((module) => module.group === 'platform'),
  };

  return (
    <div className="flex min-h-screen bg-slate-50 dark:bg-slate-950 transition-colors duration-500">
      {/* Decorative background elements */}
      <div className="fixed inset-0 z-0 overflow-hidden pointer-events-none">
        <div className="absolute -top-[10%] -left-[10%] w-[40%] h-[40%] rounded-full bg-brand-500/5 blur-[120px]" />
        <div className="absolute top-[20%] -right-[5%] w-[30%] h-[30%] rounded-full bg-cyan-500/5 blur-[100px]" />
      </div>

      <aside className="relative z-10 hidden w-64 flex-col border-r border-slate-200/50 bg-white/80 text-slate-900 backdrop-blur-xl dark:border-slate-800/50 dark:bg-slate-900/80 dark:text-white md:flex">
        <div className="flex h-20 items-center border-b border-slate-200/50 px-6 dark:border-slate-800/50">
          <div className="flex items-center gap-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-cyan-600 shadow-lg shadow-emerald-500/20">
              <span className="text-sm font-black text-white">LRH</span>
            </div>
            <div>
              <h1 className="text-lg font-black tracking-tight text-slate-950 dark:text-white">Leopardo</h1>
              <p className="text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-500">Manager</p>
            </div>
          </div>
        </div>

        <nav className="mt-6 flex-1 px-3">
          <div className="mb-2 px-4">
            <p className="px-2 text-[10px] font-bold uppercase tracking-widest text-slate-500">General</p>
          </div>
          {navGroups.general.map((module) => (
            <SidebarLink key={module.key} module={module} active={pathname === module.href} />
          ))}

          <div className="mb-2 mt-6 px-4">
            <p className="px-2 text-[10px] font-bold uppercase tracking-widest text-slate-500">Module RH</p>
          </div>
          {navGroups.hr.map((module) => (
            <SidebarLink key={module.key} module={module} active={pathname === module.href} />
          ))}

          <div className="mb-2 mt-6 px-4">
            <p className="px-2 text-[10px] font-bold uppercase tracking-widest text-slate-500">Finance & Formation</p>
          </div>
          {navGroups.finance.map((module) => (
            <SidebarLink key={module.key} module={module} active={pathname === module.href} />
          ))}
        </nav>

        <div className="m-3 space-y-3 rounded-2xl border border-brand-500/10 bg-brand-500/5 p-4 dark:border-brand-400/10 dark:bg-brand-400/5">
          <div className="flex items-center gap-2 text-brand-600 dark:text-brand-400">
            <Sparkles className="h-4 w-4" aria-hidden="true" />
            <span className="text-[10px] font-black uppercase tracking-widest">Plan & Modules</span>
          </div>
          <div className="grid gap-2">
            {navGroups.platform.map((module) => (
              <div key={module.key} className="flex items-center justify-between gap-2 text-[11px] font-bold text-slate-600 dark:text-slate-400">
                <span>{module.label}</span>
                <span className={`rounded-lg border px-2 py-0.5 text-[9px] font-black uppercase tracking-widest ${
                  module.enabled
                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800/50 dark:bg-emerald-900/30 dark:text-emerald-400'
                    : 'border-slate-200 bg-slate-100 text-slate-500 dark:border-slate-700/50 dark:bg-slate-800/50 dark:text-slate-500'
                }`}>
                  {module.enabled ? (module.state === 'trial' ? 'Trial' : 'Actif') : 'Lock'}
                </span>
              </div>
            ))}
          </div>
        </div>

        <div className="border-t border-slate-200/50 p-4 dark:border-slate-800/50">
          <div className="flex items-center gap-3 rounded-2xl bg-slate-50 p-3 transition-colors hover:bg-slate-100 dark:bg-slate-800/50 dark:hover:bg-slate-800">
            <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-slate-200 to-slate-300 text-xs font-black text-slate-600 dark:from-slate-700 dark:to-slate-800 dark:text-slate-300">
              {user?.first_name?.charAt(0)}{user?.last_name?.charAt(0)}
            </div>
            <div className="flex-1 overflow-hidden">
              <p className="truncate text-xs font-black text-slate-900 dark:text-white">{getDisplayName(user)}</p>
              <p className="truncate text-[10px] font-medium text-slate-500">{user?.email}</p>
            </div>
            <button
              onClick={handleLogout}
              className="group rounded-lg p-2 text-slate-400 transition-colors hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-900/20"
              title={labels.dashboard.logout}
            >
              <LockKeyhole className="h-4 w-4 transition-transform group-hover:scale-110" />
            </button>
          </div>
        </div>
      </aside>

      <div className="relative z-10 flex flex-1 flex-col">
        <header className="sticky top-0 z-40 flex h-20 items-center justify-between border-b border-slate-200/50 bg-white/70 px-8 backdrop-blur-md dark:border-slate-800/50 dark:bg-slate-900/70">
          <h2 className="text-xl font-black tracking-tight text-slate-950 dark:text-white uppercase">{labels.dashboard.heading}</h2>
          <div className="flex items-center gap-4">
            <div className="relative">
              <button
                type="button"
                className="relative flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:border-teal-300 hover:text-teal-700"
                aria-label="Notifications"
                aria-expanded={notificationsOpen}
                onClick={() => setNotificationsOpen((value) => !value)}
              >
                <Bell className="h-5 w-5" aria-hidden="true" />
                {unreadCount > 0 ? (
                  <span className="absolute -right-1 -top-1 min-w-5 rounded-full bg-red-500 px-1.5 py-0.5 text-center text-[10px] font-bold text-white">
                    {unreadCount > 9 ? '9+' : unreadCount}
                  </span>
                ) : null}
              </button>
              {notificationsOpen ? (
                <div className="absolute right-0 top-12 z-20 w-80 rounded-lg border border-slate-200 bg-white p-3 shadow-xl">
                  <div className="flex items-center justify-between border-b border-slate-100 pb-2">
                    <p className="text-sm font-bold text-slate-900">Notifications</p>
                    <span className="text-xs font-semibold text-slate-500">{unreadCount} non lue(s)</span>
                  </div>
                  <div className="mt-2 max-h-80 space-y-2 overflow-auto">
                    {notificationPreview.length > 0 ? notificationPreview.map((notification) => (
                      <button
                        key={notification.id}
                        type="button"
                        className="w-full rounded-lg border border-slate-100 bg-slate-50 p-3 text-left transition hover:border-teal-200 hover:bg-teal-50"
                        onClick={() => void markNotificationRead(notification)}
                      >
                        <div className="flex items-start justify-between gap-2">
                          <p className="text-sm font-bold text-slate-900">{notification.title}</p>
                          {!notification.is_read ? <span className="mt-1 h-2 w-2 rounded-full bg-teal-500" aria-label="Non lue" /> : null}
                        </div>
                        <p className="mt-1 line-clamp-2 text-xs leading-5 text-slate-600">{notification.body}</p>
                        <p className="mt-2 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">{notification.type}</p>
                      </button>
                    )) : (
                      <p className="rounded-lg bg-slate-50 p-3 text-sm text-slate-600">Aucune notification recente.</p>
                    )}
                  </div>
                  <Link
                    href="/settings/notifications"
                    className="mt-3 flex items-center justify-center rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 transition hover:border-teal-300 hover:text-teal-700"
                    onClick={() => setNotificationsOpen(false)}
                  >
                    Gerer mes preferences
                  </Link>
                </div>
              ) : null}
            </div>
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
            <div className="flex items-center gap-2 rounded-xl border border-emerald-500/20 bg-emerald-500/5 px-3 py-1.5 text-[10px] font-black uppercase tracking-widest text-emerald-600 dark:text-emerald-400">
              <div className="relative flex h-2 w-2">
                <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                <span className="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
              </div>
              Live: 18 {labels.dashboard.present}
            </div>
          </div>
        </header>
        <main className="mx-auto w-full max-w-7xl p-8">
          {currentModule && !currentModule.enabled ? (
            <FeatureLockedPanel module={currentModule} />
          ) : (
            children
          )}
        </main>
      </div>
    </div>
  );
}

type ClientNotification = {
  id: number | string;
  type: string;
  title: string;
  body?: string | null;
  is_read?: boolean;
};

function SidebarLink({ module, active }: { module: ClientModuleAccess; active: boolean }) {
  const className = [
    'group flex items-center justify-between gap-3 px-4 py-2.5 rounded-xl transition-all duration-200 text-sm font-bold uppercase tracking-tight mx-2',
    active
      ? 'bg-brand-50 text-brand-700 shadow-glass-sm border border-brand-100 dark:bg-brand-900/20 dark:text-brand-400 dark:border-brand-800/50'
      : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800/50 dark:hover:text-white',
    module.enabled ? '' : 'opacity-50 cursor-not-allowed',
  ].filter(Boolean).join(' ');

  return (
    <Link href={module.href ?? '#'} className={className} aria-disabled={!module.enabled}>
      <span className="flex items-center gap-3">
        <div className={`h-1.5 w-1.5 rounded-full transition-transform group-hover:scale-150 ${
          active ? 'bg-brand-500 shadow-[0_0_8px_rgba(20,184,166,0.5)]' : (module.enabled ? 'bg-slate-300 dark:bg-slate-600' : 'bg-slate-200 dark:bg-slate-800')
        }`}></div>
        {module.label}
      </span>
      <div className="flex items-center gap-1.5">
        {!module.enabled ? <LockKeyhole className="h-3 w-3 text-slate-400" aria-label="Module non inclus" /> : null}
        {module.enabled && module.state === 'trial' ? (
          <span className="rounded-md border border-amber-200 bg-amber-50 px-1.5 py-0.5 text-[8px] font-black uppercase tracking-widest text-amber-600 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-400">Trial</span>
        ) : null}
      </div>
    </Link>
  );
}

function FeatureLockedPanel({ module }: { module: ClientModuleAccess }) {
  const reason = module.reason === 'role_locked'
    ? 'Votre role actuel ne permet pas d acceder a ce module.'
    : 'Ce module n est pas inclus dans votre plan actuel.';

  useEffect(() => {
    trackClientEvent('feature_blocked', {
      module: module.key,
      reason: module.reason,
      state: module.state,
    });
  }, [module.key, module.reason, module.state]);

  return (
    <section className="overflow-hidden rounded-3xl border border-amber-200 bg-white shadow-sm">
      <div className="grid gap-6 p-6 lg:grid-cols-[1fr_280px] lg:items-center">
        <div className="space-y-4">
          <span className="inline-flex items-center gap-2 rounded-full bg-amber-50 px-3 py-1 text-xs font-bold uppercase tracking-[0.16em] text-amber-700">
            <LockKeyhole className="h-4 w-4" aria-hidden="true" />
            Module non inclus
          </span>
          <div>
            <h1 className="text-3xl font-black text-slate-950">{module.upgradeLabel}</h1>
            <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
              {reason} Leopardo RH garde l interface explicite afin d eviter les 404 confuses et les erreurs API inutiles.
            </p>
          </div>
          <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
            Demandez l activation au super administrateur de la plateforme ou passez sur un plan incluant ce module.
          </div>
        </div>
        <div className="rounded-2xl bg-slate-950 p-5 text-white">
          <p className="text-xs font-bold uppercase tracking-[0.16em] text-teal-200">Plan & role</p>
          <p className="mt-3 text-sm leading-6 text-slate-300">
            Les modules visibles dans cet espace sont calcules depuis les droits, le plan de l entreprise et le role utilisateur.
          </p>
          <Link href="/contact?topic=upgrade" className="mt-5 inline-flex w-full items-center justify-center rounded-xl bg-teal-400 px-4 py-3 text-sm font-bold text-slate-950 transition hover:bg-teal-300">
            Demander l activation
          </Link>
        </div>
      </div>
    </section>
  );
}
