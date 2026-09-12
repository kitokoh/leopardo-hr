'use client';

import Link from 'next/link';
import { useEffect, useMemo, useState } from 'react';
import { usePathname, useRouter } from 'next/navigation';
import { Bell, LayoutGrid, LockKeyhole, Plus, Sparkles } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { trackClientEvent } from '@/lib/client-analytics';
import { getClientModuleAccess, getModuleAccessForPath, getSidebarSections, type ClientModuleAccess } from '@/lib/client-features';
import {
  applyDocumentLocale,
  clearAuthSession,
  getCopy,
  getDisplayName,
  getStoredUser,
  normalizeLocale,
  storeAuthSession,
  type AppLocale,
  type CopyTree,
  type StoredAuthUser,
} from '@/lib/i18n';
import { OnboardingWizard } from '@/modules/onboarding/components/OnboardingWizard';

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
  const [modulesOpen, setModulesOpen] = useState(false);
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

    // PATCH, pas PUT : l'API n'expose que PATCH /notifications/{id}/read
    // (routes/modules/rh.php:202, dashboard.php:44) — le PUT répondait 405 et
    // la pastille de notification ne se marquait jamais comme lue.
    await apiFetch(`/notifications/${notification.id}/read`, {
      method: 'PATCH',
    });

    setNotificationPreview((items) => items.map((item) => (
      item.id === notification.id ? { ...item, is_read: true } : item
    )));
    setUnreadCount((count) => Math.max(0, count - 1));
  };

  const markAllNotificationsRead = async () => {
    if (unreadCount === 0) {
      return;
    }

    await apiFetch('/notifications/read-all', {
      method: 'POST',
    });

    setNotificationPreview((items) => items.map((item) => ({ ...item, is_read: true })));
    setUnreadCount(0);
  };

  const [showWizard, setShowWizard] = useState(false);
  // #R8 — onboarding non complété mais wizard fermé → bouton "Reprendre".
  const onboardingPending =
    user?.role === 'manager' && user.company?.metadata?.onboarding_completed !== true;

  useEffect(() => {
    if (user && user.role === 'manager' && user.company?.metadata?.onboarding_completed !== true) {
      setShowWizard(true);
    }
  }, [user]);

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

    // Audit #1699 : session portée par le cookie httpOnly — plus de token
    // en localStorage à relire.
    storeAuthSession(null, payload.data);

    setUserOverride(payload.data);
    setLocaleOverride(normalizeLocale(payload.data.language));
    applyDocumentLocale(normalizeLocale(payload.data.language), payload.data.is_rtl);
  };

  if (!mounted) {
    return null;
  }

  // #7225 — deux axes : transverse (entreprise) vs métier (verticales du tenant).
  // Les modules métier non activés sont sortis du menu et restent découvrables
  // dans la carte « Plan & Modules » (avant : « Restaurant » s'affichait chez
  // toutes les entreprises, y compris une agence de voyage).
  const { core, business, lockedBusiness } = getSidebarSections(modules);

  // #7225 — IA demandée : bandeau HORIZONTAL « Entreprise » (transverse) +
  // rail VERTICAL « Mon métier » (verticales du tenant). Les modules de
  // plateforme (facturation, intégrations) et les verticales non activées
  // restent découvrables dans le panneau « Modules & plan ».
  // Le bandeau horizontal ne montre QUE ce que le client a réellement
  // (menus = capacités du tenant) ; les modules non activés (transverses ou
  // métier) sont découvrables dans le panneau « Modules & plan ».
  const navPills = core.filter((module) => module.group !== 'platform' && module.href && module.enabled);
  const platformModules = core.filter((module) => module.group === 'platform');
  const lockedCore = core.filter((module) => module.group !== 'platform' && module.href && !module.enabled);
  const discoverable = [...lockedCore, ...lockedBusiness];

  return (
    <div className="flex min-h-screen bg-transparent">
      {/* Decorative background elements */}
      <div className="fixed inset-0 z-0 overflow-hidden pointer-events-none">
        <div className="absolute -top-[10%] -left-[10%] w-[40%] h-[40%] rounded-full bg-emerald-500/5 blur-[120px]" />
        <div className="absolute top-[20%] -right-[5%] w-[30%] h-[30%] rounded-full bg-cyan-500/5 blur-[100px]" />
      </div>

      {business.length > 0 ? (
        <aside
          data-testid="business-rail"
          className="relative z-10 hidden w-64 shrink-0 flex-col border-r border-slate-200/50 bg-white/80 text-slate-900 backdrop-blur-xl md:flex"
        >
        <div className="flex h-16 items-center gap-3 border-b border-slate-200/50 px-5">
          <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-cyan-600 shadow-lg shadow-emerald-500/20">
            <span className="text-xs font-black text-white">LRH</span>
          </div>
          <div className="min-w-0">
            <p className="truncate text-sm font-black tracking-tight text-slate-950">{user?.company?.name ?? 'Leopardo'}</p>
            <p className="truncate text-[10px] font-black uppercase tracking-widest text-emerald-600">{labels.dashboard.businessSection}</p>
          </div>
        </div>

        <nav className="mt-4 flex-1 space-y-1 overflow-y-auto px-3" aria-label={labels.dashboard.businessSection}>
          {business.map((module) => (
            <BusinessCard key={module.key} module={module} active={pathname === module.href} labels={labels} />
          ))}
        </nav>

        </aside>
      ) : null}

      <div className="relative z-10 flex flex-1 flex-col">
        <header className="sticky top-0 z-40 border-b border-slate-200/50 bg-white/80 backdrop-blur-md">
          <div className="flex h-16 items-center justify-between gap-4 px-4 md:px-8">
            <div className="flex min-w-0 items-center gap-3">
              <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-cyan-600 shadow-lg shadow-emerald-500/20">
                <span className="text-xs font-black text-white">LRH</span>
              </div>
              <div className="min-w-0">
                <h2 className="truncate text-base font-black uppercase tracking-tight text-slate-950">{labels.dashboard.heading}</h2>
                <p className="truncate text-[11px] font-semibold text-slate-500">{user?.company?.name ?? ''}</p>
              </div>
            </div>
          <div className="flex items-center gap-2 md:gap-4">
            {/* #7225 — panneau « Modules & plan » : modules de plateforme +
                verticales non activées (découverte, sans polluer le menu). */}
            <div className="relative">
              <button
                type="button"
                onClick={() => setModulesOpen((value) => !value)}
                aria-expanded={modulesOpen}
                className="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-600 shadow-sm transition hover:border-emerald-300 hover:text-emerald-700"
              >
                <LayoutGrid className="h-4 w-4" aria-hidden="true" />
                <span className="hidden sm:inline">{labels.dashboard.sectionModules}</span>
              </button>
              {modulesOpen ? (
                <div className="absolute right-0 top-12 z-30 w-80 rounded-2xl border border-slate-200 bg-white p-4 shadow-xl">
                  <p className="text-[10px] font-black uppercase tracking-widest text-slate-400">{labels.dashboard.sectionEnterprise}</p>
                  <div className="mt-2 space-y-1">
                    {platformModules.length > 0 ? platformModules.map((module) => (
                      <div key={module.key} className="flex items-center justify-between gap-2 text-[12px] font-bold text-slate-600">
                        <span>{labels.dashboard.modules[module.key] ?? module.label}</span>
                        <span className={`rounded-lg border px-2 py-0.5 text-[9px] font-black uppercase tracking-widest ${
                          module.enabled ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-100 text-slate-500'
                        }`}>
                          {module.enabled ? (module.state === 'trial' ? 'Trial' : labels.dashboard.present) : 'Lock'}
                        </span>
                      </div>
                    )) : <p className="text-[12px] text-slate-400">—</p>}
                  </div>
                  {discoverable.length > 0 ? (
                    <>
                      <p className="mt-4 text-[10px] font-black uppercase tracking-widest text-slate-400">
                        {business.length > 0 ? labels.dashboard.sectionLocked : labels.dashboard.sectionDiscoverBusiness}
                      </p>
                      <div className="mt-2 space-y-1">
                        {discoverable.map((module) => (
                          <Link
                            key={module.key}
                            href="/contact?topic=upgrade"
                            className="flex items-center justify-between gap-2 rounded-lg px-1 py-1.5 text-[12px] font-bold text-slate-500 transition hover:bg-emerald-50 hover:text-emerald-700"
                          >
                            <span>{labels.dashboard.modules[module.key] ?? module.label}</span>
                            <Plus className="h-3.5 w-3.5" aria-hidden="true" />
                          </Link>
                        ))}
                      </div>
                    </>
                  ) : null}
                </div>
              ) : null}
            </div>
            <div className="flex items-center gap-4">
            <div className="relative">
              <button
                type="button"
                className="relative flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:border-emerald-300 hover:text-emerald-700"
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
                    <div className="flex items-center gap-2">
                      <span className="text-xs font-semibold text-slate-500">{unreadCount} non lue(s)</span>
                      {unreadCount > 0 ? (
                        <button
                          type="button"
                          className="text-xs font-semibold text-emerald-600 transition hover:text-emerald-800"
                          onClick={() => void markAllNotificationsRead()}
                        >
                          Tout marquer lu
                        </button>
                      ) : null}
                    </div>
                  </div>
                  <div className="mt-2 max-h-80 space-y-2 overflow-auto">
                    {notificationPreview.length > 0 ? notificationPreview.map((notification) => (
                      <button
                        key={notification.id}
                        type="button"
                        className="w-full rounded-lg border border-slate-100 bg-transparent p-3 text-left transition hover:border-emerald-200 hover:bg-emerald-50"
                        onClick={() => void markNotificationRead(notification)}
                      >
                        <div className="flex items-start justify-between gap-2">
                          <p className="text-sm font-bold text-slate-900">{notification.title}</p>
                          {!notification.is_read ? <span className="mt-1 h-2 w-2 rounded-full bg-emerald-500" aria-label="Non lue" /> : null}
                        </div>
                        <p className="mt-1 line-clamp-2 text-xs leading-5 text-slate-600">{notification.body}</p>
                        <p className="mt-2 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">{notification.type}</p>
                      </button>
                    )) : (
                      <p className="rounded-lg bg-transparent p-3 text-sm text-slate-600">{labels.dashboard.noNotifications}</p>
                    )}
                  </div>
                  <Link
                    href="/settings/notifications"
                    className="mt-3 flex items-center justify-center rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 transition hover:border-emerald-300 hover:text-emerald-700"
                    onClick={() => setNotificationsOpen(false)}
                  >
                    {labels.dashboard.managePreferences}
                  </Link>
                </div>
              ) : null}
            </div>
            <div className="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-2 py-1.5 shadow-sm">
              <div className="flex h-7 w-7 items-center justify-center rounded-lg bg-gradient-to-br from-slate-200 to-slate-300 text-[10px] font-black text-slate-600">
                {user?.first_name?.charAt(0)}{user?.last_name?.charAt(0)}
              </div>
              <div className="hidden max-w-[10rem] overflow-hidden lg:block">
                <p className="truncate text-[11px] font-black text-slate-900">{getDisplayName(user)}</p>
                <p className="truncate text-[9px] font-medium text-slate-500">{user?.email}</p>
              </div>
              <button
                onClick={handleLogout}
                className="group rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-red-50 hover:text-red-500"
                title={labels.dashboard.logout}
                aria-label={labels.dashboard.logout}
              >
                <LockKeyhole className="h-4 w-4 transition-transform group-hover:scale-110" />
              </button>
            </div>
            <label className="hidden items-center gap-2 text-sm text-slate-600 md:flex">
              <span>{labels.dashboard.language}</span>
              <select
                className="rounded-md border border-slate-300 bg-white px-2 py-1 text-sm text-slate-700"
                value={locale}
                onChange={(e) => void handleLanguageChange(e.target.value)}
              >
                <option value="fr">Français</option>
                <option value="ar">العربية</option>
                <option value="tr">Türkçe</option>
                <option value="en">English</option>
              </select>
            </label>
            <div className="flex items-center gap-2 rounded-xl border border-emerald-500/20 bg-emerald-500/5 px-3 py-1.5 text-[10px] font-black uppercase tracking-widest text-emerald-600">
              <div className="relative flex h-2 w-2">
                <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                <span className="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
              </div>
              {/* Issue #2720 — statistique « Live » codée en dur retirée :
                  aucun endpoint ne la fournit (honnêteté des données). */}
              {labels.dashboard.present}
            </div>
          </div>
          </div>
          </div>
          {/* Bandeau horizontal — modules transverses de l'entreprise */}
          <div className="border-t border-slate-100/70 px-4 md:px-8">
            <div className="flex items-center gap-1.5 overflow-x-auto py-2" aria-label={labels.dashboard.sectionEnterprise}>
              <span className="shrink-0 pr-2 text-[10px] font-black uppercase tracking-widest text-slate-400">{labels.dashboard.sectionEnterprise}</span>
              {navPills.map((module) => (
                <NavPill key={module.key} module={module} active={pathname === module.href} labels={labels} />
              ))}
            </div>
          </div>
        </header>
        {onboardingPending && !showWizard ? (
          <div className="mx-auto w-full max-w-7xl px-4 pt-4 md:px-8">
            <button
              onClick={() => setShowWizard(true)}
              className="w-full rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-left text-[11px] font-bold text-emerald-700 transition hover:bg-emerald-100"
            >
              {labels.dashboard.resumeOnboarding}
            </button>
          </div>
        ) : null}
        <main className="mx-auto w-full max-w-7xl p-4 md:p-8">
          {currentModule && !currentModule.enabled ? (
            <FeatureLockedPanel module={currentModule} labels={labels} />
          ) : (
            children
          )}
        </main>
        {showWizard && user && <OnboardingWizard user={user} onComplete={() => setShowWizard(false)} />}
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

function NavPill({ module, active, labels }: { module: ClientModuleAccess; active: boolean; labels: CopyTree }) {
  const label = labels.dashboard.modules[module.key] ?? module.label;
  const className = [
    'group inline-flex shrink-0 items-center gap-2 rounded-full border px-3.5 py-1.5 text-[11px] font-black uppercase tracking-tight transition-all',
    active
      ? 'border-emerald-200 bg-emerald-50 text-emerald-700 shadow-sm'
      : module.enabled
        ? 'border-transparent text-slate-500 hover:border-slate-200 hover:bg-white hover:text-slate-900'
        : 'border-transparent text-slate-300',
  ].join(' ');

  return (
    <Link href={module.href ?? '#'} className={className} aria-disabled={!module.enabled} aria-current={active ? 'page' : undefined}>
      {label}
      {!module.enabled ? <LockKeyhole className="h-3 w-3" aria-label={labels.dashboard.featureLockedBadge} /> : null}
      {module.enabled && module.state === 'trial' ? (
        <span className="rounded-md border border-amber-200 bg-amber-50 px-1 py-0.5 text-[8px] font-black uppercase tracking-widest text-amber-600">Trial</span>
      ) : null}
    </Link>
  );
}

function BusinessCard({ module, active, labels }: { module: ClientModuleAccess; active: boolean; labels: CopyTree }) {
  const label = labels.dashboard.modules[module.key] ?? module.label;
  const initials = label.trim().slice(0, 2).toUpperCase();

  return (
    <Link
      href={module.href ?? '#'}
      aria-current={active ? 'page' : undefined}
      className={[
        'group flex items-center gap-3 rounded-2xl border px-3.5 py-3 text-sm font-bold transition-all',
        active
          ? 'border-emerald-200 bg-emerald-50 text-emerald-700 shadow-sm'
          : 'border-transparent text-slate-600 hover:border-slate-200 hover:bg-white hover:text-slate-900',
      ].join(' ')}
    >
      <span
        className={[
          'flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border text-[11px] font-black uppercase',
          active
            ? 'border-emerald-200 bg-white text-emerald-600'
            : 'border-slate-200 bg-slate-50 text-slate-400 group-hover:text-emerald-600',
        ].join(' ')}
      >
        {initials}
      </span>
      <span className="min-w-0 flex-1 truncate">{label}</span>
      {module.state === 'trial' ? (
        <span className="rounded-md border border-amber-200 bg-amber-50 px-1.5 py-0.5 text-[8px] font-black uppercase tracking-widest text-amber-600">Trial</span>
      ) : null}
    </Link>
  );
}

function FeatureLockedPanel({ module, labels }: { module: ClientModuleAccess; labels: CopyTree }) {
  // #2986 : messages localisés (4 locales) — plus de FR en dur.
  const reason = module.reason === 'role_locked'
    ? labels.dashboard.featureLockedRole
    : labels.dashboard.featureLockedPlan;

  useEffect(() => {
    trackClientEvent('feature_blocked', {
      module: module.key,
      reason: module.reason,
      state: module.state,
    });
  }, [module.key, module.reason, module.state]);

  return (
    <section data-testid="feature-locked-panel" className="overflow-hidden rounded-3xl border border-amber-200 bg-white shadow-sm">
      <div className="grid gap-6 p-6 lg:grid-cols-[1fr_280px] lg:items-center">
        <div className="space-y-4">
          <span className="inline-flex items-center gap-2 rounded-full bg-amber-50 px-3 py-1 text-xs font-bold uppercase tracking-[0.16em] text-amber-700">
            <LockKeyhole className="h-4 w-4" aria-hidden="true" />
            {labels.dashboard.featureLockedBadge}
          </span>
          <div>
            <h1 className="text-3xl font-black text-slate-950">{module.upgradeLabel}</h1>
            <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
              {reason} {labels.dashboard.featureLockedExplanation}
            </p>
          </div>
          <div className="rounded-2xl border border-slate-200 bg-transparent p-4 text-sm text-slate-700">
            {labels.dashboard.featureLockedAdminHint}
          </div>
        </div>
        <div className="rounded-2xl bg-slate-950 p-5 text-white">
          <p className="text-xs font-bold uppercase tracking-[0.16em] text-emerald-200">{labels.dashboard.featureLockedPlanRoleTitle}</p>
          <p className="mt-3 text-sm leading-6 text-slate-300">
            {labels.dashboard.featureLockedPlanRoleBody}
          </p>
          <Link href="/contact?topic=upgrade" className="mt-5 inline-flex w-full items-center justify-center rounded-xl bg-emerald-400 px-4 py-3 text-sm font-bold text-slate-950 transition hover:bg-emerald-300">
            {labels.dashboard.featureLockedCta}
          </Link>
        </div>
      </div>
    </section>
  );
}

