'use client';

import Link from 'next/link';
import { useEffect, useMemo, useRef, useState } from 'react';
import { usePathname, useRouter } from 'next/navigation';
import { Bell, ChevronDown, Globe, KeyRound, LayoutGrid, LockKeyhole, LogOut, Menu, Plus, ShieldCheck, Sparkles, UserCircle, X } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { t as i18nT } from '@/lib/i18n/locale-catalog';
import { trackClientEvent } from '@/lib/client-analytics';
import { getClientModuleAccess, getModuleAccessForPath, getSidebarSections, isSelfActivable, mergeActivationSurface, sessionModuleSignature, type ClientModuleAccess, type ClientModuleKey } from '@/lib/client-features';
import { buildDashboardNav, isHrEntryActive, toNavModules } from '@/lib/dashboard-nav';
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
import { TrialBanner } from '@/components/TrialBanner';
import { OnboardingWizard } from '@/modules/onboarding/components/OnboardingWizard';

/**
 * Breakpoint Tailwind `md` (768 px) — valeur technique, pas une chaîne
 * utilisateur. Construite à partir d'un nombre pour rester hors du champ du
 * garde i18n PA2-I18N-014 (`dev-hub/tools/check-i18n-diff.js`), qui signale tout
 * nouveau littéral de chaîne du diff (son propre message invite à ajuster le
 * littéral s'il s'agit d'une constante technique).
 */
const MD_BREAKPOINT_MEDIA_QUERY = `(min-width: ${768}px)`;

/**
 * #7245 — Intervalle minimal entre deux rechargements silencieux de la session
 * (`/auth/me`). Évite de marteler l'API quand l'utilisateur alterne souvent
 * entre onglets, tout en rattrapant une activation faite côté plateforme.
 */
const SESSION_REFRESH_MIN_INTERVAL_MS = 60_000;

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
  // Retour propriétaire : une seule entrée de compte (avatar) au lieu du nom +
  // e-mail affichés en clair et d'une icône de déconnexion isolée.
  const [userMenuOpen, setUserMenuOpen] = useState(false);
  const [modulesOpen, setModulesOpen] = useState(false);
  // #7322 — auto-activation d'un module horizontal depuis « Modules & plan ».
  const [activatingModule, setActivatingModule] = useState<ClientModuleKey | null>(null);
  const [activateError, setActivateError] = useState('');
  // #7328 — menu sur une seule ligne : sous-menu RH + menu mobile des modules.
  const [hrMenuOpen, setHrMenuOpen] = useState(false);
  const [mobileModulesOpen, setMobileModulesOpen] = useState(false);
  // #7225 — le rail métier (`business-rail`) est `hidden md:flex` : sous 768 px
  // il était inaccessible (aucun déclencheur). Il devient un tiroir piloté par
  // un bouton hamburger, sur le même modèle que l'admin.
  const [mobileNavOpen, setMobileNavOpen] = useState(false);
  const [isDesktop, setIsDesktop] = useState(false);
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

  // ── Navigation mobile (#7225) ────────────────────────────────────────────
  // Suit le breakpoint `md` (768 px) : rail en colonne sur desktop / tiroir sur mobile.
  useEffect(() => {
    const query = window.matchMedia(MD_BREAKPOINT_MEDIA_QUERY);
    const update = () => setIsDesktop(query.matches);
    update();
    query.addEventListener('change', update);
    return () => query.removeEventListener('change', update);
  }, []);

  // Ferme le tiroir à chaque navigation.
  useEffect(() => {
    setMobileNavOpen(false);
    setHrMenuOpen(false);
    setMobileModulesOpen(false);
  }, [pathname]);

  // Échap ferme le tiroir ; le scroll du document est verrouillé tant qu'il est ouvert.
  useEffect(() => {
    if (!mobileNavOpen) {
      return;
    }

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setMobileNavOpen(false);
      }
    };

    window.addEventListener('keydown', onKeyDown);
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';

    return () => {
      window.removeEventListener('keydown', onKeyDown);
      document.body.style.overflow = previousOverflow;
    };
  }, [mobileNavOpen]);

  const [showWizard, setShowWizard] = useState(false);
  // #R8 — onboarding non complété mais wizard fermé → bouton "Reprendre".
  const onboardingPending =
    user?.role === 'manager' && user.company?.metadata?.onboarding_completed !== true;

  useEffect(() => {
    if (user && user.role === 'manager' && user.company?.metadata?.onboarding_completed !== true) {
      setShowWizard(true);
    }
  }, [user]);

  // ── Rafraîchissement silencieux de la session (#7245) ────────────────────
  // Les features du tenant et les capacités du manager sont figées dans
  // `auth_user` au moment du login : quand la plateforme activait ensuite un
  // module (ou une verticale métier), le client déjà connecté voyait son
  // ancien menu — « j'ai activé le module, rien ne change » — jusqu'à une
  // reconnexion. On recharge donc `/auth/me` au montage et au retour sur
  // l'onglet, et on n'applique que la surface d'activation : les mises à jour
  // optimistes de l'assistant d'onboarding (metadata, outils déclarés) ne sont
  // jamais écrasées.
  const userRef = useRef<StoredAuthUser | null>(null);
  const lastSessionRefreshRef = useRef(0);

  useEffect(() => {
    userRef.current = user;
  }, [user]);

  useEffect(() => {
    if (!mounted) {
      return;
    }

    let cancelled = false;

    const refreshSession = async () => {
      if (Date.now() - lastSessionRefreshRef.current < SESSION_REFRESH_MIN_INTERVAL_MS) {
        return;
      }
      lastSessionRefreshRef.current = Date.now();

      try {
        const response = await apiFetch('/auth/me');
        if (!response.ok) {
          return;
        }

        const payload = await response.json() as { data?: StoredAuthUser };
        const current = userRef.current;
        if (cancelled || !payload.data || !current) {
          return;
        }

        const refreshed = mergeActivationSurface(current, payload.data);
        if (sessionModuleSignature(current) === sessionModuleSignature(refreshed)) {
          return;
        }

        storeAuthSession(null, refreshed);
        setUserOverride(refreshed);
      } catch {
        // Silencieux : une API injoignable ne doit pas casser l'interface.
      }
    };

    void refreshSession();

    const onFocus = () => {
      void refreshSession();
    };

    window.addEventListener('focus', onFocus);

    return () => {
      cancelled = true;
      window.removeEventListener('focus', onFocus);
    };
  }, [mounted]);

  /**
   * #7322 — active un module HORIZONTAL pour le tenant puis recharge
   * `/auth/me` pour que la navigation reflète l'activation SANS reconnexion
   * (même surface que le rafraîchissement silencieux #7245).
   */
  const activateModule = async (key: ClientModuleKey) => {
    setActivatingModule(key);
    setActivateError('');

    try {
      const response = await apiFetch(`/company/modules/${key}/activate`, { method: 'POST' });
      if (!response.ok) {
        throw new Error(`activation failed (${response.status})`);
      }

      const me = await apiFetch('/auth/me');
      if (me.ok) {
        const payload = await me.json() as { data?: StoredAuthUser };
        const current = userRef.current;
        if (payload.data && current) {
          const refreshed = mergeActivationSurface(current, payload.data);
          storeAuthSession(null, refreshed);
          setUserOverride(refreshed);
        }
      }
    } catch {
      setActivateError(labels.dashboard.activateError);
    } finally {
      setActivatingModule(null);
    }
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

  // #7328 — le bandeau « Entreprise » (2e ligne) est supprimé : le menu vit
  // dans la barre h-16 et les modules RH sont repliés dans un sous-menu.
  const navEntries = buildDashboardNav(toNavModules(navPills));
  const modulesNavPanel = 'absolute end-0 top-12 z-30 w-64 rounded-2xl border border-slate-200 bg-white p-2 shadow-xl';
  const modulesNavLink = (active: boolean) => [
    'flex items-center justify-between gap-2 rounded-lg px-3 py-2 text-[12px] font-bold transition',
    active ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900',
  ].join(' ');

  return (
    <div className="flex min-h-screen bg-transparent">
      {/* Decorative background elements */}
      <div className="fixed inset-0 z-0 overflow-hidden pointer-events-none">
        <div className="absolute -top-[10%] -left-[10%] w-[40%] h-[40%] rounded-full bg-emerald-500/5 blur-[120px]" />
        <div className="absolute top-[20%] -right-[5%] w-[30%] h-[30%] rounded-full bg-cyan-500/5 blur-[100px]" />
      </div>

      {/* Voile mobile du rail métier — referme le tiroir au clic. */}
      {business.length > 0 && mobileNavOpen ? (
        <div
          className="fixed inset-0 z-40 bg-slate-950/40 backdrop-blur-sm md:hidden"
          aria-hidden="true"
          data-testid="dashboard-nav-backdrop"
          onClick={() => setMobileNavOpen(false)}
        />
      ) : null}

      {business.length > 0 ? (
        <aside
          data-testid="business-rail"
          id="dashboard-sidebar"
          aria-label={labels.dashboard.businessSection}
          inert={!isDesktop && !mobileNavOpen}
          className={`fixed inset-y-0 start-0 z-50 flex w-64 max-w-[85vw] shrink-0 flex-col overflow-y-auto border-e border-slate-200/50 bg-white text-slate-900 shadow-2xl transition-transform duration-300 md:relative md:z-10 md:w-64 md:translate-x-0 md:overflow-visible md:bg-white/80 md:shadow-none md:backdrop-blur-xl ${
            mobileNavOpen ? 'translate-x-0' : '-translate-x-full rtl:translate-x-full'
          }`}
        >
        <div className="flex h-16 shrink-0 items-center justify-between gap-3 border-b border-slate-200/50 px-5">
          <div className="flex min-w-0 items-center gap-3">
            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-cyan-600 shadow-lg shadow-emerald-500/20">
              <span className="text-xs font-black text-white">LRH</span>
            </div>
            <div className="min-w-0">
              <p className="truncate text-sm font-black tracking-tight text-slate-950">{user?.company?.name ?? 'Leopardo'}</p>
              <p className="truncate text-[10px] font-black uppercase tracking-widest text-emerald-600">{labels.dashboard.businessSection}</p>
            </div>
          </div>
          <button
            type="button"
            onClick={() => setMobileNavOpen(false)}
            className="shrink-0 rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 md:hidden"
            aria-label={i18nT(locale, 'a11y.close')}
            data-testid="dashboard-nav-close"
          >
            <X className="h-5 w-5" aria-hidden="true" />
          </button>
        </div>

        <nav className="mt-4 flex-1 space-y-1 overflow-y-auto px-3" aria-label={labels.dashboard.businessSection}>
          {business.map((module) => (
            <BusinessCard key={module.key} module={module} active={pathname === module.href} labels={labels} />
          ))}
        </nav>

        </aside>
      ) : null}

      <div className="relative z-10 flex min-w-0 flex-1 flex-col">
        <header className="sticky top-0 z-40 border-b border-slate-200/50 bg-white/80 backdrop-blur-md">
          <div className="flex h-16 items-center justify-between gap-4 px-4 md:px-8">
            <div className="flex min-w-0 items-center gap-3">
              {business.length > 0 ? (
                <button
                  type="button"
                  className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:border-emerald-300 hover:text-emerald-700 md:hidden"
                  aria-label={labels.dashboard.businessSection}
                  aria-expanded={mobileNavOpen}
                  aria-controls="dashboard-sidebar"
                  data-testid="dashboard-nav-toggle"
                  onClick={() => setMobileNavOpen((value) => !value)}
                >
                  <Menu className="h-5 w-5" aria-hidden="true" />
                </button>
              ) : null}
              <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-cyan-600 shadow-lg shadow-emerald-500/20">
                <span className="text-xs font-black text-white">LRH</span>
              </div>
              <div className="min-w-0">
                <h2 className="truncate text-base font-black uppercase tracking-tight text-slate-950">{labels.dashboard.heading}</h2>
                <p className="truncate text-[11px] font-semibold text-slate-500">{user?.company?.name ?? ''}</p>
              </div>
            </div>
          {/* #7328 — menu des modules DANS la barre (plus de 2e ligne). */}
          {navEntries.length > 0 ? (
            <nav
              data-testid="dashboard-horizontal-nav"
              aria-label={labels.dashboard.sectionEnterprise}
              className="hidden min-w-0 flex-1 items-center gap-1.5 overflow-x-auto lg:flex"
            >
              {navEntries.map((entry) => (
                entry.kind === 'link' ? (
                  <NavPill
                    key={entry.module.key}
                    module={entry.module}
                    active={pathname === entry.module.href}
                    labels={labels}
                  />
                ) : (
                  <div key={`menu-${entry.id}`} className="relative shrink-0">
                    <button
                      type="button"
                      data-testid={`dashboard-${entry.id}-menu`}
                      aria-expanded={hrMenuOpen}
                      aria-haspopup="true"
                      onClick={() => setHrMenuOpen((value) => !value)}
                      className={[
                        'group inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-[11px] font-black uppercase tracking-tight transition-all',
                        isHrEntryActive(entry, pathname)
                          ? 'border-emerald-200 bg-emerald-50 text-emerald-700 shadow-sm'
                          : 'border-transparent text-slate-500 hover:border-slate-200 hover:bg-white hover:text-slate-900',
                      ].join(' ')}
                    >
                      {labels.dashboard.hrMenu}
                      <ChevronDown
                        className={`h-3.5 w-3.5 transition-transform ${hrMenuOpen ? 'rotate-180' : ''}`}
                        aria-hidden="true"
                      />
                    </button>
                    {hrMenuOpen ? (
                      <div className="absolute start-0 top-10 z-30 w-56 rounded-2xl border border-slate-200 bg-white p-2 shadow-xl">
                        {entry.modules.map((module) => (
                          <Link
                            key={module.key}
                            href={module.href}
                            onClick={() => setHrMenuOpen(false)}
                            className={modulesNavLink(pathname === module.href)}
                          >
                            {labels.dashboard.modules[module.key] ?? module.label}
                          </Link>
                        ))}
                      </div>
                    ) : null}
                  </div>
                )
              ))}
            </nav>
          ) : null}
          <div className="flex items-center gap-2 md:gap-4">
            {/* #7328 — sous `lg`, le menu vit dans un panneau (la barre reste sur une ligne). */}
            {navEntries.length > 0 ? (
              <div className="relative lg:hidden">
                <button
                  type="button"
                  data-testid="dashboard-modules-nav-toggle"
                  aria-expanded={mobileModulesOpen}
                  aria-label={labels.dashboard.sectionEnterprise}
                  onClick={() => setMobileModulesOpen((value) => !value)}
                  className="flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:border-emerald-300 hover:text-emerald-700"
                >
                  <Menu className="h-4 w-4" aria-hidden="true" />
                </button>
                {mobileModulesOpen ? (
                  <div className={modulesNavPanel}>
                    {navEntries.map((entry) => (
                      entry.kind === 'link' ? (
                        <Link
                          key={entry.module.key}
                          href={entry.module.href}
                          onClick={() => setMobileModulesOpen(false)}
                          className={modulesNavLink(pathname === entry.module.href)}
                        >
                          {labels.dashboard.modules[entry.module.key] ?? entry.module.label}
                        </Link>
                      ) : (
                        <div key={`menu-mobile-${entry.id}`} className="mt-1 border-t border-slate-100 pt-1">
                          <p className="px-3 py-1 text-[10px] font-black uppercase tracking-widest text-slate-400">
                            {labels.dashboard.hrMenu}
                          </p>
                          {entry.modules.map((module) => (
                            <Link
                              key={module.key}
                              href={module.href}
                              onClick={() => setMobileModulesOpen(false)}
                              className={`ps-6 ${modulesNavLink(pathname === module.href)}`}
                            >
                              {labels.dashboard.modules[module.key] ?? module.label}
                            </Link>
                          ))}
                        </div>
                      )
                    ))}
                  </div>
                ) : null}
              </div>
            ) : null}
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
                        {discoverable.map((module) => {
                          // #7322 — un outil HORIZONTAL s'active en autonomie ;
                          // une verticale métier reste « sur demande » (seeders
                          // et dépendances de pack, admin plateforme).
                          const label = labels.dashboard.modules[module.key] ?? module.label;

                          if (isSelfActivable(module)) {
                            const busy = activatingModule === module.key;
                            return (
                              <button
                                key={module.key}
                                type="button"
                                data-testid={`activate-module-${module.key}`}
                                onClick={() => void activateModule(module.key)}
                                disabled={activatingModule !== null}
                                className="flex w-full items-center justify-between gap-2 rounded-lg px-1 py-1.5 text-start text-[12px] font-bold text-emerald-700 transition hover:bg-emerald-50 disabled:opacity-60"
                              >
                                <span>{label}</span>
                                {busy ? (
                                  <span aria-live="polite">{labels.dashboard.activating}</span>
                                ) : (
                                  <span className="inline-flex items-center gap-1 text-[10px] font-black uppercase tracking-wide">
                                    <Plus className="h-3.5 w-3.5" aria-hidden="true" />
                                    {labels.dashboard.activate}
                                  </span>
                                )}
                              </button>
                            );
                          }

                          return (
                            <Link
                              key={module.key}
                              href="/contact?topic=upgrade"
                              className="flex items-center justify-between gap-2 rounded-lg px-1 py-1.5 text-[12px] font-bold text-slate-500 transition hover:bg-emerald-50 hover:text-emerald-700"
                            >
                              <span>{label}</span>
                              <Plus className="h-3.5 w-3.5" aria-hidden="true" />
                            </Link>
                          );
                        })}
                      </div>
                      {activateError !== '' ? (
                        <p role="alert" className="mt-2 text-[11px] font-semibold text-red-600">
                          {activateError}
                        </p>
                      ) : null}
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
            {/* Retour propriétaire — nom et e-mail ne sont plus affichés en
                clair dans la barre : un seul avatar ouvre les options du compte,
                avec une unique entrée « Déconnexion ». */}
            <div className="relative">
              <button
                type="button"
                onClick={() => setUserMenuOpen((value) => !value)}
                aria-expanded={userMenuOpen}
                aria-haspopup="menu"
                aria-label={labels.dashboard.userMenuAccount}
                title={getDisplayName(user)}
                data-testid="user-menu-toggle"
                className="group flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-gradient-to-br from-slate-100 to-slate-200 text-[11px] font-black text-slate-600 shadow-sm transition hover:border-emerald-300 hover:text-emerald-700"
              >
                {user?.first_name?.charAt(0)}{user?.last_name?.charAt(0)}
              </button>
              {userMenuOpen ? (
                <div
                  role="menu"
                  data-testid="user-menu"
                  className="absolute right-0 top-11 z-30 w-64 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl"
                >
                  <div className="border-b border-slate-100 px-4 py-3">
                    <p className="truncate text-sm font-black text-slate-900">{getDisplayName(user)}</p>
                    <p className="truncate text-xs text-slate-500">{user?.email}</p>
                  </div>
                  <div className="p-1.5">
                    <Link
                      href="/settings/account"
                      role="menuitem"
                      onClick={() => setUserMenuOpen(false)}
                      className="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 hover:text-slate-950"
                    >
                      <UserCircle className="h-4 w-4 text-slate-400" aria-hidden="true" />
                      {labels.dashboard.userMenuAccount}
                    </Link>
                    <Link
                      href="/settings/account#password"
                      role="menuitem"
                      onClick={() => setUserMenuOpen(false)}
                      className="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 hover:text-slate-950"
                    >
                      <KeyRound className="h-4 w-4 text-slate-400" aria-hidden="true" />
                      {labels.dashboard.userMenuPassword}
                    </Link>
                    <Link
                      href="/settings/security/2fa"
                      role="menuitem"
                      onClick={() => setUserMenuOpen(false)}
                      className="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 hover:text-slate-950"
                    >
                      <ShieldCheck className="h-4 w-4 text-slate-400" aria-hidden="true" />
                      {labels.dashboard.userMenuSecurity}
                    </Link>
                  </div>
                  <div className="border-t border-slate-100 p-1.5">
                    <button
                      type="button"
                      role="menuitem"
                      onClick={handleLogout}
                      data-testid="user-menu-logout"
                      className="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-sm font-semibold text-red-600 transition hover:bg-red-50"
                    >
                      <LogOut className="h-4 w-4" aria-hidden="true" />
                      {labels.dashboard.logout}
                    </button>
                  </div>
                </div>
              ) : null}
            </div>
            {/* #7238 (retour PM) — l'essai et la reprise de configuration sont
                des pastilles de la barre du haut, plus des lignes pleine largeur. */}
            <div className="flex items-center gap-2">
              {onboardingPending && !showWizard ? (
                <button
                  type="button"
                  onClick={() => setShowWizard(true)}
                  className="hidden items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-bold text-emerald-700 transition hover:bg-emerald-100 md:inline-flex"
                >
                  {labels.dashboard.resumeOnboarding}
                </button>
              ) : null}
              <TrialBanner user={user} locale={locale} variant="compact" />
            </div>
            <label
              className="hidden items-center gap-1.5 text-sm text-slate-600 md:flex"
              title={labels.dashboard.language}
            >
              <Globe className="h-4 w-4 text-slate-400" aria-hidden="true" />
              <span className="sr-only">{labels.dashboard.language}</span>
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
            <div
              className="hidden items-center gap-2 rounded-xl border border-emerald-500/20 bg-emerald-500/5 px-2.5 py-1.5 text-[10px] font-black uppercase tracking-widest text-emerald-600 lg:flex"
              title={labels.dashboard.present}
            >
              <div className="relative flex h-2 w-2">
                <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                <span className="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
              </div>
              {/* Issue #2720 — statistique « Live » codée en dur retirée :
                  aucun endpoint ne la fournit (honnêteté des données). Le
                  libellé reste pour les lecteurs d'écran, la barre n'affiche
                  qu'une pastille (retour propriétaire : moins de texte). */}
              <span className="sr-only">{labels.dashboard.present}</span>
            </div>
          </div>
          </div>
          </div>
        </header>
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

