'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { ChevronDown, CreditCard, LayoutGrid, Plug, X } from 'lucide-react';
import { t as i18nT } from '@/lib/i18n/locale-catalog';
import type { ClientModuleAccess } from '@/lib/client-features';
import {
  isNavEntryActive,
  type BusinessRailEntry,
  type DashboardNavEntry,
  type NavMenuGroupId,
  type NavModule,
} from '@/lib/dashboard-nav';
import type { AppLocale, CopyTree, StoredAuthUser } from '@/lib/i18n';
import { AccountMenu } from '@/components/layout/AccountMenu';

/**
 * #7908 — Sidebar gauche UNIFIÉE de l'espace client, TOUJOURS rendue
 * (desktop : colonne fixe w-64 ; sous `md` : tiroir).
 *
 * De haut en bas :
 *  - logo / nom de la compagnie ;
 *  - section « Mon métier » : cartes du rail métier (`buildBusinessRail`),
 *    inchangées fonctionnellement (testid `business-rail` conservé) ;
 *  - section « Entreprise » : les 4 groupes de l'ex-nav horizontale (hr,
 *    finance, growth, operations) en accordéons repliables — état persisté en
 *    localStorage, groupe actif auto-ouvert selon le pathname. Les testids
 *    `dashboard-<groupe>-menu(-panel)` sont conservés pour les parcours e2e ;
 *  - section « Plateforme » : Modules (/modules), Abonnement & factures
 *    (/billing), Intégrations (/settings/developer) ;
 *  - pied : bloc « Mon compte » (`AccountMenu`, menu vers le haut).
 */

/** Clé localStorage de l'état ouvert/fermé des accordéons Entreprise. */
export const SIDEBAR_GROUPS_STORAGE_KEY = 'dashboard_sidebar_groups';

type OpenGroups = Partial<Record<NavMenuGroupId, boolean>>;

function readStoredGroups(): OpenGroups {
  if (typeof window === 'undefined') {
    return {};
  }
  try {
    const raw = window.localStorage.getItem(SIDEBAR_GROUPS_STORAGE_KEY);
    if (!raw) {
      return {};
    }
    const parsed = JSON.parse(raw) as unknown;
    return parsed && typeof parsed === 'object' ? (parsed as OpenGroups) : {};
  } catch {
    return {};
  }
}

function persistGroups(groups: OpenGroups): void {
  try {
    window.localStorage.setItem(SIDEBAR_GROUPS_STORAGE_KEY, JSON.stringify(groups));
  } catch {
    // Stockage indisponible (navigation privée) : l'état reste en mémoire.
  }
}

/** Classes d'un lien de module de la sidebar (état actif / repos). */
function sidebarLinkClass(active: boolean): string {
  return [
    'flex items-center gap-2 rounded-lg px-3 py-2 text-[12px] font-bold transition',
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40',
    active ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900',
  ].join(' ');
}

function SidebarSectionTitle({ children }: { children: React.ReactNode }) {
  return (
    <p className="px-1 pb-1 text-[10px] font-black uppercase tracking-widest text-slate-500">
      {children}
    </p>
  );
}

function SidebarModuleLink({
  module,
  active,
  labels,
  onNavigate,
  nested = false,
}: {
  module: NavModule;
  active: boolean;
  labels: CopyTree;
  onNavigate: () => void;
  nested?: boolean;
}) {
  return (
    <Link
      href={module.href}
      onClick={onNavigate}
      aria-current={active ? 'page' : undefined}
      className={`${nested ? 'ps-6 ' : ''}${sidebarLinkClass(active)}`}
    >
      <span className="flex min-w-0 items-center gap-2">
        {module.icon ? <module.icon className="h-4 w-4 shrink-0 text-slate-400" aria-hidden="true" /> : null}
        <span className="truncate">{labels.dashboard.modules[module.key] ?? module.label}</span>
      </span>
      {/* #8028 — un module en essai doit rester VISIBLE comme tel dans la
          navigation : le refactor #7908 n'avait gardé le badge « Trial » que
          sur les cartes métier, si bien qu'un module cœur en essai (ex.
          « Rapports ») n'était plus signalé nulle part (régression verrouillée
          par `e2e/client-feature-gates.spec.ts`). */}
      <span className="ms-auto flex shrink-0 items-center gap-1.5">
        {module.state === 'trial' ? (
          <span
            data-testid="sidebar-module-trial-badge"
            className="rounded-md border border-amber-200 bg-amber-50 px-1 py-0.5 text-[8px] font-black uppercase tracking-widest text-amber-600"
          >
            Trial
          </span>
        ) : null}
        {/* Pastille d'item actif, teintée par le branding du tenant (#7860). */}
        {active ? (
          <span
            className="h-1.5 w-1.5 shrink-0 rounded-full bg-[var(--tenant-primary,#10b981)]"
            aria-hidden="true"
          />
        ) : null}
      </span>
    </Link>
  );
}

/**
 * Accordéon d'un groupe Entreprise (hr, finance, growth, operations).
 * Le panneau n'est monté qu'ouvert — même contrat que l'ex-`NavGroupMenu`.
 */
function SidebarGroupAccordion({
  entry,
  open,
  pathname,
  labels,
  onToggle,
  onNavigate,
}: {
  entry: Extract<DashboardNavEntry, { kind: 'menu' }>;
  open: boolean;
  pathname: string;
  labels: CopyTree;
  onToggle: () => void;
  onNavigate: () => void;
}) {
  const active = isNavEntryActive(entry, pathname);

  return (
    <div>
      <button
        type="button"
        data-testid={`dashboard-${entry.id}-menu`}
        aria-expanded={open}
        aria-controls={`dashboard-${entry.id}-menu-panel`}
        onClick={onToggle}
        className={[
          'flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2 text-[11px] font-black uppercase tracking-tight transition',
          'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40',
          active ? 'text-emerald-700' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900',
        ].join(' ')}
      >
        {labels.dashboard.navGroups[entry.id] ?? labels.dashboard.hrMenu}
        <ChevronDown
          className={`h-3.5 w-3.5 shrink-0 transition-transform ${open ? 'rotate-180' : ''}`}
          aria-hidden="true"
        />
      </button>
      {open ? (
        <div
          id={`dashboard-${entry.id}-menu-panel`}
          data-testid={`dashboard-${entry.id}-menu-panel`}
          className="mt-0.5 space-y-0.5"
        >
          {entry.modules.map((module) => (
            <SidebarModuleLink
              key={module.key}
              module={module}
              active={pathname === module.href}
              labels={labels}
              onNavigate={onNavigate}
              nested
            />
          ))}
        </div>
      ) : null}
    </div>
  );
}

function BusinessCard({
  module,
  active,
  labels,
  onNavigate,
  compact = false,
}: {
  module: ClientModuleAccess;
  active: boolean;
  labels: CopyTree;
  onNavigate: () => void;
  compact?: boolean;
}) {
  const label = labels.dashboard.modules[module.key] ?? module.label;
  const initials = label.trim().slice(0, 2).toUpperCase();
  const Icon = module.icon;

  return (
    <Link
      href={module.href ?? '#'}
      onClick={onNavigate}
      aria-current={active ? 'page' : undefined}
      className={[
        'group flex items-center gap-3 rounded-2xl border text-sm font-bold transition-all',
        compact ? 'px-3 py-2' : 'px-3.5 py-3',
        active
          ? 'border-emerald-200 bg-emerald-50 text-emerald-700 shadow-sm'
          : 'border-transparent text-slate-600 hover:border-slate-200 hover:bg-white hover:text-slate-900',
      ].join(' ')}
    >
      <span
        className={[
          'flex shrink-0 items-center justify-center rounded-xl border text-[11px] font-black uppercase',
          compact ? 'h-7 w-7' : 'h-9 w-9',
          active
            ? 'border-emerald-200 bg-white text-emerald-700'
            : 'border-slate-200 bg-slate-50 text-slate-500 group-hover:text-emerald-700',
        ].join(' ')}
      >
        {/* #7724 — icône de module sur les cartes métier (repli : initiales). */}
        {Icon ? <Icon className={compact ? 'h-3.5 w-3.5' : 'h-4 w-4'} aria-hidden="true" /> : initials}
      </span>
      <span className="min-w-0 flex-1 truncate">{label}</span>
      {module.state === 'trial' ? (
        <span className="rounded-md border border-amber-200 bg-amber-50 px-1.5 py-0.5 text-[8px] font-black uppercase tracking-widest text-amber-600">Trial</span>
      ) : null}
    </Link>
  );
}

export function Sidebar({
  user,
  labels,
  locale,
  pathname,
  businessRail,
  navEntries,
  tenantLogoUrl,
  mobileNavOpen,
  isDesktop,
  onClose,
  onLanguageChange,
  onLogout,
}: {
  user: StoredAuthUser | null;
  labels: CopyTree;
  locale: AppLocale;
  pathname: string;
  businessRail: BusinessRailEntry[];
  navEntries: DashboardNavEntry[];
  tenantLogoUrl: string | null;
  mobileNavOpen: boolean;
  isDesktop: boolean;
  onClose: () => void;
  onLanguageChange: (value: string) => Promise<void> | void;
  onLogout: () => void;
}) {
  // État ouvert/fermé des accordéons Entreprise, persisté en localStorage.
  // Lecture paresseuse : le layout ne rend la sidebar qu'après montage
  // (`mounted`), il n'y a donc pas de désaccord SSR/hydratation.
  const [openGroups, setOpenGroups] = useState<OpenGroups>(readStoredGroups);

  const toggleGroup = useCallback((id: NavMenuGroupId) => {
    setOpenGroups((current) => {
      const next = { ...current, [id]: !current[id] };
      persistGroups(next);
      return next;
    });
  }, []);

  // Groupe actif auto-ouvert selon le pathname (sans refermer les autres).
  useEffect(() => {
    const activeEntry = navEntries.find(
      (entry): entry is Extract<DashboardNavEntry, { kind: 'menu' }> =>
        entry.kind === 'menu' && isNavEntryActive(entry, pathname),
    );
    if (!activeEntry) {
      return;
    }
    setOpenGroups((current) => {
      if (current[activeEntry.id]) {
        return current;
      }
      const next = { ...current, [activeEntry.id]: true };
      persistGroups(next);
      return next;
    });
  }, [navEntries, pathname]);

  const handleNavigate = useCallback(() => {
    if (!isDesktop) {
      onClose();
    }
  }, [isDesktop, onClose]);

  return (
    <aside
      data-testid="dashboard-sidebar"
      id="dashboard-sidebar"
      role={isDesktop ? undefined : 'dialog'}
      aria-modal={!isDesktop && mobileNavOpen ? true : undefined}
      aria-label={labels.dashboard.navMenu}
      inert={!isDesktop && !mobileNavOpen}
      className={`fixed inset-y-0 start-0 z-50 flex w-64 max-w-[85vw] shrink-0 flex-col border-e border-slate-200/50 bg-white text-slate-900 shadow-2xl transition-transform duration-300 md:relative md:z-10 md:w-64 md:translate-x-0 md:bg-white/80 md:shadow-none md:backdrop-blur-xl ${
        mobileNavOpen ? 'translate-x-0' : '-translate-x-full rtl:translate-x-full'
      }`}
    >
      <div className="flex h-14 shrink-0 items-center justify-between gap-3 border-b border-slate-200/50 px-4">
        <div className="flex min-w-0 items-center gap-3">
          {tenantLogoUrl ? (
            // eslint-disable-next-line @next/next/no-img-element -- logo du tenant servi par l'API (URL par entreprise, hors allowlist next/image)
            <img src={tenantLogoUrl} alt="" data-testid="tenant-logo" className="h-9 w-9 shrink-0 rounded-xl border border-slate-200 bg-white object-contain shadow-sm" />
          ) : (
            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-[var(--tenant-primary,#10b981)] to-[var(--tenant-accent,#0891b2)] shadow-lg shadow-emerald-500/20">
              <span className="text-xs font-black text-white">LRH</span>
            </div>
          )}
          <p className="min-w-0 truncate text-sm font-black tracking-tight text-slate-950">
            {user?.company?.name ?? 'Leopardo'}
          </p>
        </div>
        <button
          type="button"
          onClick={onClose}
          className="shrink-0 rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 md:hidden"
          aria-label={i18nT(locale, 'a11y.close')}
          data-testid="dashboard-nav-close"
        >
          <X className="h-5 w-5" aria-hidden="true" />
        </button>
      </div>

      <div className="flex-1 space-y-5 overflow-y-auto px-3 py-4">
        {/* ── Section Métier (#7225/#7724, inchangée fonctionnellement) ── */}
        {businessRail.length > 0 ? (
          <nav data-testid="business-rail" aria-label={labels.dashboard.businessSection} className="space-y-1">
            <SidebarSectionTitle>{labels.dashboard.businessSection}</SidebarSectionTitle>
            {businessRail.map(({ module, children }) => (
              <div key={module.key}>
                <BusinessCard module={module} active={pathname === module.href} labels={labels} onNavigate={handleNavigate} />
                {children.length > 0 ? (
                  <div className="ms-6 mt-1 space-y-1 border-s border-slate-200 ps-3">
                    {children.map((child) => (
                      <BusinessCard key={child.key} module={child} active={pathname === child.href} labels={labels} onNavigate={handleNavigate} compact />
                    ))}
                  </div>
                ) : null}
              </div>
            ))}
          </nav>
        ) : null}

        {/* ── Section Entreprise : ex-nav horizontale en accordéons (#7908) ── */}
        {navEntries.length > 0 ? (
          <nav data-testid="dashboard-enterprise-nav" aria-label={labels.dashboard.sectionEnterprise} className="space-y-0.5">
            <SidebarSectionTitle>{labels.dashboard.sectionEnterprise}</SidebarSectionTitle>
            {navEntries.map((entry) => (
              entry.kind === 'link' ? (
                <SidebarModuleLink
                  key={entry.module.key}
                  module={entry.module}
                  active={pathname === entry.module.href}
                  labels={labels}
                  onNavigate={handleNavigate}
                />
              ) : (
                <SidebarGroupAccordion
                  key={`menu-${entry.id}`}
                  entry={entry}
                  open={Boolean(openGroups[entry.id])}
                  pathname={pathname}
                  labels={labels}
                  onToggle={() => toggleGroup(entry.id)}
                  onNavigate={handleNavigate}
                />
              )
            ))}
          </nav>
        ) : null}

        {/* ── Section Plateforme (#7908) ── */}
        <nav data-testid="dashboard-platform-nav" aria-label={labels.dashboard.sectionPlatform} className="space-y-0.5">
          <SidebarSectionTitle>{labels.dashboard.sectionPlatform}</SidebarSectionTitle>
          <Link
            href="/modules"
            onClick={handleNavigate}
            data-testid="sidebar-modules-link"
            aria-current={pathname === '/modules' ? 'page' : undefined}
            className={sidebarLinkClass(pathname === '/modules')}
          >
            <LayoutGrid className="h-4 w-4 shrink-0 text-slate-400" aria-hidden="true" />
            <span className="truncate">{labels.dashboard.platformModules}</span>
          </Link>
          <Link
            href="/billing"
            onClick={handleNavigate}
            data-testid="sidebar-billing-link"
            aria-current={pathname === '/billing' ? 'page' : undefined}
            className={sidebarLinkClass(pathname === '/billing')}
          >
            <CreditCard className="h-4 w-4 shrink-0 text-slate-400" aria-hidden="true" />
            <span className="truncate">{labels.dashboard.userMenuBilling}</span>
          </Link>
          <Link
            href="/settings/developer"
            onClick={handleNavigate}
            data-testid="sidebar-integrations-link"
            aria-current={pathname === '/settings/developer' ? 'page' : undefined}
            className={sidebarLinkClass(pathname === '/settings/developer')}
          >
            <Plug className="h-4 w-4 shrink-0 text-slate-400" aria-hidden="true" />
            <span className="truncate">{labels.dashboard.modules.integrations}</span>
          </Link>
        </nav>
      </div>

      {/* ── Pied : bloc « Mon compte » (menu vers le haut, #7908) ── */}
      <AccountMenu
        user={user}
        labels={labels}
        locale={locale}
        onLanguageChange={onLanguageChange}
        onLogout={onLogout}
        onNavigate={handleNavigate}
      />
    </aside>
  );
}
