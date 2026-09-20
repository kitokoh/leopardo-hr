'use client';

import Link from 'next/link';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { usePathname, useRouter } from 'next/navigation';
import { Bell, LockKeyhole, Menu } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { t as i18nT } from '@/lib/i18n/locale-catalog';
import { clearTenantBrandingCache, readCachedTenantBranding, storeTenantBranding, TENANT_BRANDING_EVENT, type TenantBranding } from '@/lib/tenant-branding';
import { trackClientEvent } from '@/lib/client-analytics';
import { getClientModuleAccess, getModuleAccessForPath, getSidebarSections, mergeActivationSurface, sessionModuleSignature, type ClientModuleAccess } from '@/lib/client-features';
import { buildBusinessRail, buildDashboardNav, toNavModules } from '@/lib/dashboard-nav';
import {
  applyDocumentLocale,
  clearAuthSession,
  getCopy,
  getStoredUser,
  normalizeLocale,
  storeAuthSession,
  type AppLocale,
  type CopyTree,
  type StoredAuthUser,
} from '@/lib/i18n';
import { TrialBanner } from '@/components/TrialBanner';
import { Sidebar } from '@/components/layout/Sidebar';
import { usePanelDismiss } from '@/components/layout/panel-dismiss';
// #7494 — la modale OnboardingWizard n'est plus le point d'entrée de la mise
// en route : l'entretien (#7493) puis la carte « Prochaines étapes » du
// dashboard la remplacent.
import { SetupInterview, shouldShowSetupInterview, type SetupInterviewCloseReason } from '@/modules/onboarding/components/SetupInterview';
import { WelcomeScreen, shouldShowFirstLoginWelcome, type WelcomeScreenAction } from '@/modules/onboarding/components/WelcomeScreen';
// #7866 — invite d'import du jeu de données de démonstration (API #7865),
// séquencée APRÈS l'écran de bienvenue et l'entretien de préparation.
import { DemoDataPrompt, shouldShowDemoDataPrompt, type DemoDataPromptCloseReason } from '@/modules/onboarding/components/DemoDataPrompt';

/**
 * #7908 — Refonte du shell client : la navigation vit dans une SIDEBAR gauche
 * unifiée (`@/components/layout/Sidebar`), toujours rendue (colonne fixe sur
 * desktop, tiroir sous `md`). La topbar est réduite à une ligne fine :
 * burger mobile + titre contextuel à gauche ; reprise d'onboarding, essai,
 * notifications et pastille de présence à droite. La nav horizontale, le
 * panneau « Modules & plan » (déménagé sur la page /modules), le select de
 * langue et le menu avatar (déménagés dans la sidebar) ont quitté la barre.
 */

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

/**
 * #7713/#7860 — image de marque du tenant consommée par le shell : couleurs
 * exposées en CSS custom properties, logo affiché à la place du badge LRH.
 * Le contrat `TenantBranding` et le cache localStorage vivent dans
 * `@/lib/tenant-branding` (hydratation instantanée + événement
 * `tenant-branding-updated`). Repli silencieux vers le thème par défaut si
 * l'appel échoue.
 */
const HEX_COLOR_PATTERN = /^#[0-9A-Fa-f]{6}$/;

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
  // #7908 — la sidebar unifiée est TOUJOURS rendue : sous 768 px elle devient
  // un tiroir piloté par le bouton hamburger de la topbar.
  const [mobileNavOpen, setMobileNavOpen] = useState(false);
  const [isDesktop, setIsDesktop] = useState(false);
  // #7713 — branding du tenant (couleurs + logo), chargé après montage.
  const [tenantBranding, setTenantBranding] = useState<TenantBranding | null>(null);
  const user = userOverride ?? storedUser;
  const locale = localeOverride ?? normalizeLocale(user?.language);
  const labels = useMemo(() => getCopy(locale), [locale]);
  const modules = useMemo(() => getClientModuleAccess(user), [user]);
  const currentModule = useMemo(() => getModuleAccessForPath(pathname, user), [pathname, user]);
  // #7483 — le titre de la barre reflète la page courante : même résolution
  // que la navigation (catalogue ROUTE_TO_MODULE → clé i18n
  // `dashboard.modules`), au lieu d'être figé sur `dashboard.heading`
  // (« Tableau de bord » partout). Repli sur le titre générique pour les
  // routes hors catalogue (ex. /settings/account).
  const pageTitle = currentModule
    ? labels.dashboard.modules[currentModule.key] ?? currentModule.label
    : labels.dashboard.heading;

  useEffect(() => {
    setStoredUser(getStoredUser());
    setMounted(true);
  }, []);

  // #7713/#7860 — image de marque du tenant : hydratation INSTANTANÉE depuis
  // le cache localStorage (couleurs/logo dès la première frame après login),
  // puis refetch de `/company/branding` au montage pour rafraîchir le cache.
  // Toute erreur réseau est silencieuse : le shell garde son thème courant.
  useEffect(() => {
    if (!mounted || !user) {
      return;
    }

    const cached = readCachedTenantBranding();
    if (cached) {
      setTenantBranding(cached);
    }

    let cancelled = false;

    void (async () => {
      try {
        const response = await apiFetch('/company/branding');
        const payload = await response.json() as { data?: { branding?: TenantBranding } };
        if (!cancelled && payload.data?.branding) {
          setTenantBranding(payload.data.branding);
          // Réalimente le cache pour la prochaine connexion (et les autres
          // surfaces à l'écoute de `tenant-branding-updated`).
          storeTenantBranding(payload.data.branding);
        }
      } catch {
        // Repli silencieux : thème par défaut (ou cache déjà hydraté).
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [mounted, user]);

  // #7860 — mise à jour immédiate quand /settings/branding sauvegarde : la
  // page émet `tenant-branding-updated` via storeTenantBranding().
  useEffect(() => {
    const onBrandingUpdated = (event: Event) => {
      const detail = (event as CustomEvent<TenantBranding | null>).detail;
      setTenantBranding(detail ?? readCachedTenantBranding());
    };

    window.addEventListener(TENANT_BRANDING_EVENT, onBrandingUpdated);
    return () => window.removeEventListener(TENANT_BRANDING_EVENT, onBrandingUpdated);
  }, []);

  // Couleurs du tenant → CSS custom properties posées à la racine du shell.
  const tenantThemeStyle = useMemo(() => {
    if (!tenantBranding) {
      return undefined;
    }
    const style: Record<string, string> = {};
    if (HEX_COLOR_PATTERN.test(tenantBranding.primary_color ?? '')) {
      style['--tenant-primary'] = tenantBranding.primary_color;
    }
    if (HEX_COLOR_PATTERN.test(tenantBranding.accent_color ?? '')) {
      style['--tenant-accent'] = tenantBranding.accent_color;
    }
    return Object.keys(style).length > 0 ? (style as React.CSSProperties) : undefined;
  }, [tenantBranding]);

  const tenantLogoUrl = tenantBranding?.logo_url ?? null;

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

  const handleLogout = useCallback(() => {
    // #7860 — le thème du tenant ne doit pas fuiter vers la session suivante
    // (autre compte, autre entreprise) : cache purgé à la déconnexion.
    clearTenantBrandingCache();
    router.push('/auth/logout');
  }, [router]);

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

  // ── Navigation mobile (#7225/#7908) ─────────────────────────────────────
  // Suit le breakpoint `md` (768 px) : sidebar en colonne sur desktop / tiroir sur mobile.
  useEffect(() => {
    const query = window.matchMedia(MD_BREAKPOINT_MEDIA_QUERY);
    const update = () => setIsDesktop(query.matches);
    update();
    query.addEventListener('change', update);
    return () => query.removeEventListener('change', update);
  }, []);

  // Ferme le tiroir et le panneau de notifications à chaque navigation.
  useEffect(() => {
    setMobileNavOpen(false);
    setNotificationsOpen(false);
  }, [pathname]);

  // Échap ferme le tiroir / le panneau ; le scroll du document est verrouillé
  // tant qu'une de ces surfaces est ouverte (#7556).
  usePanelDismiss(mobileNavOpen, () => setMobileNavOpen(false));
  usePanelDismiss(notificationsOpen, () => setNotificationsOpen(false));

  const [showInterview, setShowInterview] = useState(false);
  // #7493 — relance douce : tant que l'entretien n'est pas complété (report
  // compris) et que l'onboarding n'est pas terminé, la pastille de la barre
  // « Terminer la configuration de mon espace » permet de le reprendre.
  const interviewMetadata = user?.company?.metadata as
    | { onboarding_completed?: boolean; setup_interview?: { status?: string } }
    | undefined;
  const onboardingPending =
    user?.role === 'manager' &&
    interviewMetadata?.onboarding_completed !== true &&
    interviewMetadata?.setup_interview?.status !== 'completed';

  useEffect(() => {
    // #7493 — première connexion : l'entretien de préparation s'affiche en
    // pleine page (après l'écran de bienvenue #7490). Un entretien reporté
    // (`dismissed`) ne se rouvre pas tout seul : relance douce uniquement.
    if (user && shouldShowSetupInterview(user)) {
      setShowInterview(true);
    }
  }, [user]);

  // #7604 (tranche du critère 2 de #7490) — écran de bienvenue de première
  // connexion. Il passe AVANT l'assistant d'accueil : l'utilisateur doit
  // d'abord comprendre ce qui vient de se passer (code validé, identifiants
  // par e-mail) avant de configurer quoi que ce soit. Non bloquant : les deux
  // issues de l'écran l'acquittent côté serveur.
  const welcomePending = shouldShowFirstLoginWelcome(user);

  const acknowledgeWelcome = useCallback(
    (seenAt: string, action: WelcomeScreenAction) => {
      const current = userRef.current;
      if (!current) {
        return;
      }

      // Mise à jour OPTIMISTE de la seule clé concernée : la source de vérité
      // reste le serveur (`/auth/me`), et `mergeActivationSurface` ne touche
      // pas à `metadata` — la clé optimiste n'est donc pas écrasée par le
      // rafraîchissement silencieux (#7245).
      const updated = {
        ...current,
        company: current.company
          ? {
              ...current.company,
              metadata: { ...(current.company.metadata ?? {}), welcome_seen_at: seenAt },
            }
          : current.company,
      };

      storeAuthSession(null, updated);
      setUserOverride(updated);

      // CTA principal (#7490) : « Définir mon mot de passe maintenant » — le
      // flux provisioning_token existant (page publique /auth/set-password,
      // token déjà détenu par le navigateur ou lien e-mail). L'écran est déjà
      // acquitté : au retour dans l'espace, il ne se réaffiche pas. (Le CTA
      // « start_setup » de main n'existe plus : l'entretien de préparation
      // #7493 s'ouvre via `shouldShowSetupInterview` après l'écran.)
      if (action === 'set_password') {
        router.push('/auth/set-password');
      }
    },
    [router],
  );

  // #7493 — fermeture de l'entretien de préparation : mise à jour OPTIMISTE
  // du statut (la source de vérité reste le serveur, `/auth/me`), puis — à la
  // complétion — rechargement de la surface d'activation pour que la
  // navigation reflète les modules activés SANS rechargement (#7245/#7322).
  const handleInterviewClose = useCallback(
    (reason: SetupInterviewCloseReason) => {
      setShowInterview(false);

      const current = userRef.current;
      if (current?.company && (reason === 'completed' || reason === 'dismissed')) {
        const metadata = (current.company.metadata ?? {}) as Record<string, unknown>;
        const interview = (metadata.setup_interview ?? {}) as Record<string, unknown>;
        const updated = {
          ...current,
          company: {
            ...current.company,
            metadata: {
              ...metadata,
              setup_interview: { ...interview, status: reason },
            },
          },
        };
        storeAuthSession(null, updated);
        setUserOverride(updated);
      }

      if (reason === 'completed') {
        void (async () => {
          try {
            const me = await apiFetch('/auth/me');
            if (!me.ok) return;
            const payload = (await me.json()) as { data?: StoredAuthUser };
            const latest = userRef.current;
            if (payload.data && latest) {
              const refreshed = mergeActivationSurface(latest, payload.data);
              storeAuthSession(null, refreshed);
              setUserOverride(refreshed);
            }
          } catch {
            // Non bloquant : la navigation se resynchronisera au prochain focus.
          }
        })();
      }
    },
    [],
  );

  // #7866 — invite d'import des données de démonstration : fermée pour LA
  // session quelle que soit l'issue (« Plus tard » ne persiste RIEN — ni
  // serveur, ni localStorage : l'invite reviendra à la prochaine entrée ;
  // « imported »/« dismissed » sont persistés côté serveur par le composant
  // dans `company.metadata.demo_data`, relus au prochain `/auth/me`). À
  // l'import, la surface d'activation est rechargée comme à la complétion de
  // l'entretien (#7245/#7322) pour refléter les données installées.
  const [demoPromptClosed, setDemoPromptClosed] = useState(false);
  const handleDemoPromptClose = useCallback(
    (reason: DemoDataPromptCloseReason) => {
      setDemoPromptClosed(true);

      if (reason === 'imported') {
        void (async () => {
          try {
            const me = await apiFetch('/auth/me');
            if (!me.ok) return;
            const payload = (await me.json()) as { data?: StoredAuthUser };
            const latest = userRef.current;
            if (payload.data && latest) {
              const refreshed = mergeActivationSurface(latest, payload.data);
              storeAuthSession(null, refreshed);
              setUserOverride(refreshed);
            }
          } catch {
            // Non bloquant : la navigation se resynchronisera au prochain focus.
          }
        })();
      }
    },
    [],
  );
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

  const handleLanguageChange = useCallback(async (value: string) => {
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
  }, []);

  if (!mounted) {
    return null;
  }

  // #7225 — deux axes : transverse (entreprise) vs métier (verticales du tenant).
  // Les modules métier non activés sont sortis du menu et restent découvrables
  // sur la page /modules (#7908, ex-panneau « Modules & plan »).
  const { core, business } = getSidebarSections(modules);

  // #7908 — la sidebar unifiée porte : le rail métier (verticales activées),
  // les groupes Entreprise (ex-nav horizontale) en accordéons, et la section
  // Plateforme (Modules, Abonnement & factures, Intégrations). Les modules non
  // activés restent découvrables sur la page /modules.
  const navPills = core.filter((module) => module.group !== 'platform' && module.href && module.enabled);
  const navEntries = buildDashboardNav(toNavModules(navPills));
  const businessRail = buildBusinessRail(business);

  return (
    <div className="flex min-h-screen bg-transparent" style={tenantThemeStyle}>
      {/* Decorative background elements */}
      <div className="fixed inset-0 z-0 overflow-hidden pointer-events-none">
        <div className="absolute -top-[10%] -left-[10%] w-[40%] h-[40%] rounded-full bg-emerald-500/5 blur-[120px]" />
        <div className="absolute top-[20%] -right-[5%] w-[30%] h-[30%] rounded-full bg-cyan-500/5 blur-[100px]" />
      </div>

      {/* Voile mobile de la sidebar — referme le tiroir au clic. */}
      {mobileNavOpen ? (
        <div
          className="fixed inset-0 z-40 bg-slate-950/40 backdrop-blur-sm md:hidden"
          aria-hidden="true"
          data-testid="dashboard-nav-backdrop"
          onClick={() => setMobileNavOpen(false)}
        />
      ) : null}

      {/* #7908 — sidebar unifiée, TOUJOURS rendue (même sans verticale métier). */}
      <Sidebar
        user={user}
        labels={labels}
        locale={locale}
        pathname={pathname}
        businessRail={businessRail}
        navEntries={navEntries}
        tenantLogoUrl={tenantLogoUrl}
        mobileNavOpen={mobileNavOpen}
        isDesktop={isDesktop}
        onClose={() => setMobileNavOpen(false)}
        onLanguageChange={handleLanguageChange}
        onLogout={handleLogout}
      />

      <div className="relative z-10 flex min-w-0 flex-1 flex-col">
        {/* #7556 — voile du panneau de notifications : il capte le clic
            extérieur. Placé DANS la colonne (au-dessus du contenu, sous la
            barre `z-40` et son panneau) car cette colonne est elle-même un
            contexte d'empilement `z-10`. */}
        {notificationsOpen ? (
          <div
            className="fixed inset-0 z-30"
            aria-hidden="true"
            data-testid="dashboard-panel-backdrop"
            onClick={() => setNotificationsOpen(false)}
          />
        ) : null}
        {/* #7908 — topbar réduite à une ligne fine. */}
        <header className="sticky top-0 z-40 border-b border-slate-200/50 bg-white/80 backdrop-blur-md">
          <div className="flex h-14 items-center justify-between gap-3 px-4 md:px-6">
            <div className="flex min-w-0 items-center gap-3">
              <button
                type="button"
                className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:border-emerald-300 hover:text-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40 md:hidden"
                aria-label={labels.dashboard.navMenu}
                aria-expanded={mobileNavOpen}
                aria-controls="dashboard-sidebar"
                data-testid="dashboard-nav-toggle"
                onClick={() => setMobileNavOpen((value) => !value)}
              >
                <Menu className="h-5 w-5" aria-hidden="true" />
              </button>
              <h2 className="truncate text-sm font-black uppercase tracking-tight text-slate-950">{pageTitle}</h2>
            </div>
            <div className="flex shrink-0 items-center gap-2 md:gap-3">
              {/* #7238 (retour PM) — l'essai et la reprise de configuration sont
                  des pastilles de la barre du haut, plus des lignes pleine largeur. */}
              {onboardingPending && !showInterview ? (
                <button
                  type="button"
                  onClick={() => setShowInterview(true)}
                  className="hidden items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-bold text-emerald-700 transition hover:bg-emerald-100 md:inline-flex"
                >
                  {labels.dashboard.resumeOnboarding}
                </button>
              ) : null}
              <TrialBanner user={user} locale={locale} variant="compact" />
              <div className="relative">
                <button
                  type="button"
                  className="relative flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:border-emerald-300 hover:text-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40"
                  aria-label={i18nT(locale, 'shell.notifications')}
                  aria-expanded={notificationsOpen}
                  aria-haspopup="true"
                  aria-controls="dashboard-notifications-panel"
                  data-testid="dashboard-notifications-toggle"
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
                  <div
                    id="dashboard-notifications-panel"
                    data-testid="dashboard-notifications-panel"
                    className="absolute right-0 top-11 z-30 max-h-[70vh] w-80 overflow-y-auto rounded-lg border border-slate-200 bg-white p-3 shadow-xl"
                  >
                    <div className="flex items-center justify-between border-b border-slate-100 pb-2">
                      <p className="text-sm font-bold text-slate-900">{i18nT(locale, 'shell.notifications')}</p>
                      <div className="flex items-center gap-2">
                        <span className="text-xs font-semibold text-slate-500">{unreadCount} non lue(s)</span>
                        {unreadCount > 0 ? (
                          <button
                            type="button"
                            className="text-xs font-semibold text-emerald-700 transition hover:text-emerald-800"
                            onClick={() => void markAllNotificationsRead()}
                          >
                            {i18nT(locale, 'notifMarkAllAsRead')}
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
                            {!notification.is_read ? <span className="mt-1 h-2 w-2 rounded-full bg-emerald-500" aria-label={labels.dashboard.notificationUnread} /> : null}
                          </div>
                          <p className="mt-1 line-clamp-2 text-xs leading-5 text-slate-600">{notification.body}</p>
                          <p className="mt-2 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-500">{notification.type}</p>
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
              <div className="hidden items-center gap-2 rounded-xl border border-emerald-500/20 bg-emerald-500/5 px-3 py-1.5 xl:flex"
                title={labels.dashboard.present}
              >
                <div className="relative flex h-2 w-2">
                  <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                  <span className="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                </div>
                {/* Issue #2720 — statistique « Live » codée en dur retirée :
                    aucun endpoint ne la fournit (honnêteté des données). */}
                <span className="sr-only">{labels.dashboard.present}</span>
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
        {welcomePending && user && (
          <WelcomeScreen locale={locale} onAcknowledged={acknowledgeWelcome} />
        )}
        {/* #7493 — l'entretien de préparation remplace la modale à 10 étapes
            (#7494) : pleine page, une question à la fois, jamais bloquant. */}
        {showInterview && user && !welcomePending && (
          <SetupInterview locale={locale} onClose={handleInterviewClose} />
        )}
        {/* #7866 — invite d'import du jeu de données de démonstration : après
            l’écran de bienvenue ET l’entretien de préparation (ni affiché, ni
            en attente). Le composant re-vérifie auprès du serveur
            (`GET /demo-data`) et ne rend rien sans kit proposable. */}
        {user &&
          !welcomePending &&
          !showInterview &&
          !shouldShowSetupInterview(user) &&
          !demoPromptClosed &&
          shouldShowDemoDataPrompt(user) && (
            <DemoDataPrompt locale={locale} onClose={handleDemoPromptClose} />
          )}
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
