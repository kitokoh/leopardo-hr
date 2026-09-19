'use client';

import Link from 'next/link';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { usePathname, useRouter } from 'next/navigation';
import { Bell, ChevronDown, Globe, KeyRound, LayoutGrid, LockKeyhole, LogOut, Menu, Plus, ShieldCheck, Sparkles, UserCircle, X } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { t as i18nT } from '@/lib/i18n/locale-catalog';
import { teamRolesT } from '@/lib/i18n/team-roles';
import { trackClientEvent } from '@/lib/client-analytics';
import { getClientModuleAccess, getModuleAccessForPath, getSidebarSections, isSelfActivable, mergeActivationSurface, sessionModuleSignature, type ClientModuleAccess, type ClientModuleKey } from '@/lib/client-features';
import { buildDashboardNav, isHrEntryActive, toNavModules, type DashboardNavEntry } from '@/lib/dashboard-nav';
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
// #7494 — la modale OnboardingWizard n'est plus le point d'entrée de la mise
// en route : l'entretien (#7493) puis la carte « Prochaines étapes » du
// dashboard la remplacent.
import { SetupInterview, shouldShowSetupInterview, type SetupInterviewCloseReason } from '@/modules/onboarding/components/SetupInterview';
import { WelcomeScreen, shouldShowFirstLoginWelcome, type WelcomeScreenAction } from '@/modules/onboarding/components/WelcomeScreen';

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
 * #7556 — Verrou de défilement partagé par les surfaces superposées (tiroir,
 * panneaux de la barre). Compté plutôt que posé à `hidden` en aveugle : deux
 * surfaces ouvertes en même temps ne doivent pas se rendre la main l'une à
 * l'autre un `overflow` intermédiaire (le dernier fermé restitue la valeur
 * d'origine du document).
 */
let overlayScrollLocks = 0;
let overflowBeforeFirstLock = '';

function lockDocumentScroll(): () => void {
  if (overlayScrollLocks === 0) {
    overflowBeforeFirstLock = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
  }
  overlayScrollLocks += 1;
  let released = false;

  return () => {
    if (released) {
      return;
    }
    released = true;
    overlayScrollLocks = Math.max(0, overlayScrollLocks - 1);
    if (overlayScrollLocks === 0) {
      document.body.style.overflow = overflowBeforeFirstLock;
    }
  };
}

/**
 * #7556 — tout panneau déroulant (tiroir de navigation, notifications,
 * modules, compte, sous-menu RH) se referme par Échap et verrouille le
 * défilement du document tant qu'il est ouvert.
 *
 * `onClose` est lu via une ref : l'effet ne se réabonne pas à chaque rendu.
 */
function usePanelDismiss(open: boolean, onClose: () => void): void {
  const closeRef = useRef(onClose);

  useEffect(() => {
    closeRef.current = onClose;
  }, [onClose]);

  useEffect(() => {
    if (!open) {
      return;
    }

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        closeRef.current();
      }
    };

    window.addEventListener('keydown', onKeyDown);
    const unlock = lockDocumentScroll();

    return () => {
      window.removeEventListener('keydown', onKeyDown);
      unlock();
    };
  }, [open]);
}

/** Classes de la liste de modules du shell (partagées tiroir / panneau `md`). */
const MODULES_NAV_PANEL = 'absolute end-0 top-12 z-30 max-h-[70vh] w-64 overflow-y-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-xl';

/** Classes d'un lien de module (état actif / repos). */
function modulesNavLinkClass(active: boolean): string {
  return [
    'flex items-center justify-between gap-2 rounded-lg px-3 py-2 text-[12px] font-bold transition',
    active ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900',
  ].join(' ');
}

/**
 * #7556 — liste de modules du shell (liens directs + sous-menu RH replié),
 * rendue à l'identique dans le tiroir mobile et dans le panneau `md`–`lg`.
 */
function DashboardModuleLinks({
  entries,
  pathname,
  labels,
  onNavigate,
}: {
  entries: DashboardNavEntry[];
  pathname: string;
  labels: CopyTree;
  onNavigate: () => void;
}) {
  return (
    <>
      {entries.map((entry) => (
        entry.kind === 'link' ? (
          <Link
            key={entry.module.key}
            href={entry.module.href}
            onClick={onNavigate}
            className={modulesNavLinkClass(pathname === entry.module.href)}
          >
            {labels.dashboard.modules[entry.module.key] ?? entry.module.label}
          </Link>
        ) : (
          <div key={`menu-${entry.id}`} className="mt-1 border-t border-slate-100 pt-1">
            <p className="px-3 py-1 text-[10px] font-black uppercase tracking-widest text-slate-500">
              {labels.dashboard.hrMenu}
            </p>
            {entry.modules.map((module) => (
              <Link
                key={module.key}
                href={module.href}
                onClick={onNavigate}
                className={`ps-6 ${modulesNavLinkClass(pathname === module.href)}`}
              >
                {labels.dashboard.modules[module.key] ?? module.label}
              </Link>
            ))}
          </div>
        )
      ))}
    </>
  );
}

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
  // Retour propriétaire : le badge de présence ne garde que sa pastille, le
  // libellé « PRÉSENTS » passe en `sr-only` (il reste lu par les lecteurs
  // d'écran et sert de `title` au survol).
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
  // #7483 — le titre de la barre reflète la page courante : même résolution
  // que les pastilles de navigation (catalogue ROUTE_TO_MODULE → clé i18n
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

  /**
   * #7556 — un seul panneau déroulant ouvert à la fois dans la barre : ouvrir
   * un panneau referme les autres (et donc leur voile).
   */
  const closeHeaderPanels = useCallback(() => {
    setNotificationsOpen(false);
    setModulesOpen(false);
    setMobileModulesOpen(false);
    setUserMenuOpen(false);
    setHrMenuOpen(false);
  }, []);
  const headerPanelOpen = notificationsOpen || modulesOpen || mobileModulesOpen || userMenuOpen || hrMenuOpen;

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

  // Ferme le tiroir et les panneaux de la barre à chaque navigation.
  useEffect(() => {
    setMobileNavOpen(false);
    closeHeaderPanels();
  }, [closeHeaderPanels, pathname]);

  // Échap ferme le tiroir ; le scroll du document est verrouillé tant qu'il est ouvert.
  usePanelDismiss(mobileNavOpen, () => setMobileNavOpen(false));
  // #7556 — mêmes garanties pour les panneaux de la barre (notifications,
  // modules, plan, compte, sous-menu RH).
  usePanelDismiss(notificationsOpen, () => setNotificationsOpen(false));
  usePanelDismiss(modulesOpen, () => setModulesOpen(false));
  usePanelDismiss(mobileModulesOpen, () => setMobileModulesOpen(false));
  usePanelDismiss(userMenuOpen, () => setUserMenuOpen(false));
  usePanelDismiss(hrMenuOpen, () => setHrMenuOpen(false));

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
          role={isDesktop ? undefined : 'dialog'}
          aria-modal={!isDesktop && mobileNavOpen ? true : undefined}
          aria-label={isDesktop ? labels.dashboard.businessSection : labels.dashboard.navMenu}
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
              <p className="truncate text-[10px] font-black uppercase tracking-widest text-emerald-700">{labels.dashboard.businessSection}</p>
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

        {/* #7556 — sous `md`, le tiroir est le point d'entrée UNIQUE de
            navigation : il porte aussi les modules entreprise/horizontaux
            (masqués par `lg:flex` sous 1024 px) et les liens
            compte/paramètres, auparavant accessibles seulement par l'avatar. */}
        <div className="md:hidden">
          {navEntries.length > 0 ? (
            <section className="border-t border-slate-200/50 px-3 py-3" aria-label={labels.dashboard.sectionEnterprise}>
              <p className="px-1 pb-1 text-[10px] font-black uppercase tracking-widest text-slate-500">
                {labels.dashboard.sectionEnterprise}
              </p>
              <DashboardModuleLinks
                entries={navEntries}
                pathname={pathname}
                labels={labels}
                onNavigate={() => setMobileNavOpen(false)}
              />
            </section>
          ) : null}
          <section
            className="border-t border-slate-200/50 px-3 py-3"
            aria-label={labels.dashboard.accountSection}
            data-testid="dashboard-drawer-account"
          >
            <p className="px-1 pb-1 text-[10px] font-black uppercase tracking-widest text-slate-500">
              {labels.dashboard.accountSection}
            </p>
            <Link href="/settings/account" onClick={() => setMobileNavOpen(false)} className="flex items-center gap-3 rounded-lg px-3 py-2 text-[12px] font-bold text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
              <UserCircle className="h-4 w-4 text-slate-400" aria-hidden="true" />
              {labels.dashboard.userMenuAccount}
            </Link>
            {/* #7555 — gestion des collaborateurs et attribution des rôles. */}
            <Link href="/settings/team" onClick={() => setMobileNavOpen(false)} className="flex items-center gap-3 rounded-lg px-3 py-2 text-[12px] font-bold text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
              <UserCircle className="h-4 w-4 text-slate-400" aria-hidden="true" />
              {teamRolesT(locale, 'menuLabel')}
            </Link>
            <Link href="/settings/account#password" onClick={() => setMobileNavOpen(false)} className="flex items-center gap-3 rounded-lg px-3 py-2 text-[12px] font-bold text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
              <KeyRound className="h-4 w-4 text-slate-400" aria-hidden="true" />
              {labels.dashboard.userMenuPassword}
            </Link>
            <Link href="/settings/security/2fa" onClick={() => setMobileNavOpen(false)} className="flex items-center gap-3 rounded-lg px-3 py-2 text-[12px] font-bold text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
              <ShieldCheck className="h-4 w-4 text-slate-400" aria-hidden="true" />
              {labels.dashboard.userMenuSecurity}
            </Link>
            <Link href="/settings/notifications" onClick={() => setMobileNavOpen(false)} className="flex items-center gap-3 rounded-lg px-3 py-2 text-[12px] font-bold text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
              <Bell className="h-4 w-4 text-slate-400" aria-hidden="true" />
              {i18nT(locale, 'shell.notifications')}
            </Link>
            <button
              type="button"
              onClick={handleLogout}
              data-testid="dashboard-drawer-logout"
              className="mt-1 flex w-full items-center gap-3 rounded-lg border-t border-slate-100 px-3 py-2 text-[12px] font-bold text-red-600 transition hover:bg-red-50"
            >
              <LogOut className="h-4 w-4" aria-hidden="true" />
              {labels.dashboard.logout}
            </button>
          </section>
        </div>

        </aside>
      ) : null}

      <div className="relative z-10 flex min-w-0 flex-1 flex-col">
        {/* #7556 — voile des panneaux de la barre (notifications, modules, plan,
            compte, sous-menu RH) : il capte le clic extérieur. Placé DANS la
            colonne (au-dessus du contenu, sous la barre `z-40` et ses panneaux)
            car cette colonne est elle-même un contexte d'empilement `z-10` —
            un voile posé à la racine recouvrirait la barre et ses panneaux. */}
        {headerPanelOpen ? (
          <div
            className="fixed inset-0 z-30"
            aria-hidden="true"
            data-testid="dashboard-panel-backdrop"
            onClick={closeHeaderPanels}
          />
        ) : null}
        <header className="sticky top-0 z-40 border-b border-slate-200/50 bg-white/80 backdrop-blur-md">
          <div className="flex h-16 items-center justify-between gap-4 px-4 md:px-8">
            <div className="flex min-w-0 items-center gap-3">
              {business.length > 0 ? (
                <button
                  type="button"
                  className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:border-emerald-300 hover:text-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40 md:hidden"
                  aria-label={labels.dashboard.navMenu}
                  aria-expanded={mobileNavOpen}
                  aria-controls="dashboard-sidebar"
                  data-testid="dashboard-nav-toggle"
                  onClick={() => setMobileNavOpen((value) => !value)}
                >
                  <Menu className="h-5 w-5" aria-hidden="true" />
                </button>
              ) : null}
              {/* #7422 — repère de marque DÉDUPLIQUÉ : le rail métier porte déjà le
                  badge LRH (`business-rail`), donc la barre du haut ne le rend plus
                  au-dessus de `md` que lorsque le tenant n'a aucun rail métier.
                  Sous `md` le rail est un tiroir hors-écran : le badge reste. */}
              <div className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-cyan-600 shadow-lg shadow-emerald-500/20 ${business.length > 0 ? 'md:hidden' : ''}`}>
                <span className="text-xs font-black text-white">LRH</span>
              </div>
              <div className="min-w-0">
                <h2 className="truncate text-base font-black uppercase tracking-tight text-slate-950">{pageTitle}</h2>
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
                      aria-controls="dashboard-hr-menu-panel"
                      onClick={() => {
                        // Même correction que le menu de compte : fermer les
                        // autres panneaux puis basculer CELUI-CI sur une cible
                        // calculée avant (sinon il restait ouvert).
                        const next = !hrMenuOpen;
                        closeHeaderPanels();
                        setHrMenuOpen(next);
                      }}
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
                      <div
                        id="dashboard-hr-menu-panel"
                        data-testid="dashboard-hr-menu-panel"
                        className="absolute start-0 top-10 z-30 max-h-[70vh] w-56 overflow-y-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-xl"
                      >
                        {entry.modules.map((module) => (
                          <Link
                            key={module.key}
                            href={module.href}
                            onClick={() => setHrMenuOpen(false)}
                            className={modulesNavLinkClass(pathname === module.href)}
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
            {/* #7328 — sous `lg`, le menu vit dans un panneau (la barre reste sur une ligne).
                #7556 — ce panneau n'est nécessaire qu'entre `md` et `lg` (768–1024 px)
                où la nav horizontale `dashboard-horizontal-nav` est encore masquée ;
                sous `md`, c'est le tiroir (`dashboard-nav-toggle`) qui porte les
                modules. Un tenant SANS verticale n'a pas de tiroir : le panneau
                reste alors le seul accès aux modules sous `md`. */}
            {navEntries.length > 0 ? (
              <div className={`relative lg:hidden ${business.length > 0 ? 'hidden md:block' : ''}`}>
                <button
                  type="button"
                  data-testid="dashboard-modules-nav-toggle"
                  aria-expanded={mobileModulesOpen}
                  aria-haspopup="true"
                  aria-controls="dashboard-modules-panel"
                  aria-label={labels.dashboard.sectionEnterprise}
                  onClick={() => {
                    // #7584 — calculer la cible AVANT de fermer les autres panneaux :
                    // closeHeaderPanels() remet CE panneau à false, donc l'updater
                    // fonctionnel relisait `false` et le rouvrait aussitôt — le
                    // panneau ne se fermait jamais par son propre déclencheur.
                    const next = !mobileModulesOpen;
                    closeHeaderPanels();
                    setMobileModulesOpen(next);
                  }}
                  className="flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:border-emerald-300 hover:text-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40"
                >
                  <Menu className="h-4 w-4" aria-hidden="true" />
                </button>
                {mobileModulesOpen ? (
                  <div id="dashboard-modules-panel" data-testid="dashboard-modules-panel" className={MODULES_NAV_PANEL}>
                    <DashboardModuleLinks
                      entries={navEntries}
                      pathname={pathname}
                      labels={labels}
                      onNavigate={() => setMobileModulesOpen(false)}
                    />
                  </div>
                ) : null}
              </div>
            ) : null}
            {/* #7225 — panneau « Modules & plan » : modules de plateforme +
                verticales non activées (découverte, sans polluer le menu). */}
            <div className="relative">
              <button
                type="button"
                onClick={() => {
                  // #7584 — même correction que le menu de compte et le sous-menu RH
                  // (#7556) : cible calculée avant la fermeture des autres panneaux.
                  const next = !modulesOpen;
                  closeHeaderPanels();
                  setModulesOpen(next);
                }}
                aria-expanded={modulesOpen}
                aria-haspopup="true"
                aria-controls="dashboard-plan-panel"
                data-testid="dashboard-plan-toggle"
                className="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-600 shadow-sm transition hover:border-emerald-300 hover:text-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40"
              >
                <LayoutGrid className="h-4 w-4" aria-hidden="true" />
                {/* #7422 — icône seule : le libellé visible coûtait ~90 px à la
                    barre ; il reste en `sr-only` pour nommer le bouton. */}
                <span className="sr-only">{labels.dashboard.sectionModules}</span>
              </button>
              {modulesOpen ? (
                <div
                  id="dashboard-plan-panel"
                  data-testid="dashboard-plan-panel"
                  className="absolute right-0 top-12 z-30 max-h-[70vh] w-80 overflow-y-auto rounded-2xl border border-slate-200 bg-white p-4 shadow-xl"
                >
                  <p className="text-[10px] font-black uppercase tracking-widest text-slate-500">{labels.dashboard.sectionEnterprise}</p>
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
                      <p className="mt-4 text-[10px] font-black uppercase tracking-widest text-slate-500">
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
                className="relative flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:border-emerald-300 hover:text-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40"
                aria-label={i18nT(locale, 'shell.notifications')}
                aria-expanded={notificationsOpen}
                aria-haspopup="true"
                aria-controls="dashboard-notifications-panel"
                data-testid="dashboard-notifications-toggle"
                onClick={() => {
                  // #7584 — même correction : la cloche ne pouvait jamais refermer
                  // le panneau de notifications (closeHeaderPanels() le remettait
                  // à false, puis l'updater le relisait et le rouvrait).
                  const next = !notificationsOpen;
                  closeHeaderPanels();
                  setNotificationsOpen(next);
                }}
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
                  className="absolute right-0 top-12 z-30 max-h-[70vh] w-80 overflow-y-auto rounded-lg border border-slate-200 bg-white p-3 shadow-xl"
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
                          {!notification.is_read ? <span className="mt-1 h-2 w-2 rounded-full bg-emerald-500" aria-label="Non lue" /> : null}
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
            {/* Retour propriétaire — nom et e-mail ne sont plus affichés en
                clair dans la barre : un seul avatar ouvre les options du compte,
                avec une unique entrée « Déconnexion ». */}
            <div className="relative">
              <button
                type="button"
                onClick={() => {
                  // #7556 : `closeHeaderPanels()` remet CE panneau à false puis
                  // l'updater `!value` le rouvrait aussitôt (les deux mises à jour
                  // sont traitées dans le même lot) — le menu ne se refermait
                  // jamais au clic sur l'avatar. On calcule la cible AVANT de
                  // fermer les autres panneaux (régression vue par
                  // layout-header-menu.test.tsx « le menu du compte est refermable »).
                  const next = !userMenuOpen;
                  closeHeaderPanels();
                  setUserMenuOpen(next);
                }}
                aria-expanded={userMenuOpen}
                aria-haspopup="menu"
                aria-controls="dashboard-user-menu"
                aria-label={labels.dashboard.userMenuAccount}
                title={getDisplayName(user)}
                data-testid="user-menu-toggle"
                className="group flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-gradient-to-br from-slate-100 to-slate-200 text-[11px] font-black text-slate-600 shadow-sm transition hover:border-emerald-300 hover:text-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40"
              >
                {user?.first_name?.charAt(0)}{user?.last_name?.charAt(0)}
              </button>
              {userMenuOpen ? (
                <div
                  role="menu"
                  id="dashboard-user-menu"
                  data-testid="user-menu"
                  className="absolute right-0 top-11 z-30 max-h-[70vh] w-64 overflow-y-auto rounded-2xl border border-slate-200 bg-white shadow-xl"
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
                    {/* #7555 — gestion des collaborateurs et attribution des rôles. */}
                    <Link href="/settings/team" role="menuitem" onClick={() => setUserMenuOpen(false)} className="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 hover:text-slate-950">
                      <UserCircle className="h-4 w-4 text-slate-400" aria-hidden="true" />
                      {teamRolesT(locale, 'menuLabel')}
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
            <div className="hidden items-center gap-2 rounded-xl border border-emerald-500/20 bg-emerald-500/5 px-3 py-1.5 text-[10px] font-black uppercase tracking-widest text-emerald-700 xl:flex"
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
            ? 'border-emerald-200 bg-white text-emerald-700'
            : 'border-slate-200 bg-slate-50 text-slate-500 group-hover:text-emerald-700',
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

