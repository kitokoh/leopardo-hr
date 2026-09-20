'use client';

import Link from 'next/link';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Plus } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import {
  getClientModuleAccess,
  getSidebarSections,
  isSelfActivable,
  mergeActivationSurface,
  type ClientModuleKey,
} from '@/lib/client-features';
import {
  getCopy,
  getStoredUser,
  normalizeLocale,
  storeAuthSession,
  type StoredAuthUser,
} from '@/lib/i18n';

/**
 * #7908 — page « Modules » (/modules) : reprend le contenu de l'ex-panneau
 * « Modules & plan » de la topbar (`dashboard-plan-toggle`/`dashboard-plan-panel`).
 *
 *  - statut des modules de plateforme (Trial / Présent / Lock) ;
 *  - auto-activation des outils HORIZONTAUX (#7322 : POST
 *    /company/modules/{key}/activate puis rechargement de `/auth/me`,
 *    boutons `activate-module-<key>` conservés) ;
 *  - les verticales métier restent « sur demande » : lien
 *    /contact?topic=upgrade.
 *
 * Le préfixe /modules est ajouté à `protected-prefixes.ts`, au matcher de
 * `proxy.ts` et à `public/sw.js` (garde Jest `protected-prefixes.test.ts`).
 */
export default function ModulesPage() {
  const [mounted, setMounted] = useState(false);
  const [user, setUser] = useState<StoredAuthUser | null>(null);
  // #7322 — auto-activation d'un module horizontal.
  const [activatingModule, setActivatingModule] = useState<ClientModuleKey | null>(null);
  const [activateError, setActivateError] = useState('');

  const userRef = useRef<StoredAuthUser | null>(null);
  useEffect(() => {
    userRef.current = user;
  }, [user]);

  useEffect(() => {
    setUser(getStoredUser());
    setMounted(true);
  }, []);

  const locale = normalizeLocale(user?.language);
  const labels = useMemo(() => getCopy(locale), [locale]);
  const modules = useMemo(() => getClientModuleAccess(user), [user]);

  if (!mounted) {
    return null;
  }

  const { core, business, lockedBusiness } = getSidebarSections(modules);
  const platformModules = core.filter((module) => module.group === 'platform');
  const lockedCore = core.filter((module) => module.group !== 'platform' && module.href && !module.enabled);
  const discoverable = [...lockedCore, ...lockedBusiness];

  /**
   * #7322 — active un module HORIZONTAL pour le tenant puis recharge
   * `/auth/me` pour que la navigation reflète l'activation SANS reconnexion
   * (même surface que le rafraîchissement silencieux #7245 du layout).
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
          setUser(refreshed);
        }
      }
    } catch {
      setActivateError(labels.dashboard.activateError);
    } finally {
      setActivatingModule(null);
    }
  };

  return (
    <div className="space-y-6" data-testid="modules-page">
      <header>
        <h1 className="text-2xl font-black tracking-tight text-slate-950">{labels.dashboard.sectionModules}</h1>
        <p className="mt-1 max-w-2xl text-sm leading-6 text-slate-600">{labels.dashboard.modulesPageSubtitle}</p>
      </header>

      {/* ── Modules de plateforme : statut Trial / Présent / Lock ── */}
      <section
        data-testid="modules-platform-section"
        aria-label={labels.dashboard.sectionEnterprise}
        className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm md:p-6"
      >
        <p className="text-[10px] font-black uppercase tracking-widest text-slate-500">{labels.dashboard.sectionEnterprise}</p>
        <div className="mt-3 space-y-2">
          {platformModules.length > 0 ? platformModules.map((module) => (
            <div key={module.key} className="flex items-center justify-between gap-2 text-sm font-bold text-slate-600">
              <span>{labels.dashboard.modules[module.key] ?? module.label}</span>
              <span className={`rounded-lg border px-2 py-0.5 text-[9px] font-black uppercase tracking-widest ${
                module.enabled ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-100 text-slate-500'
              }`}>
                {module.enabled ? (module.state === 'trial' ? 'Trial' : labels.dashboard.present) : 'Lock'}
              </span>
            </div>
          )) : <p className="text-sm text-slate-400">—</p>}
        </div>
      </section>

      {/* ── Modules découvrables : auto-activation ou demande ── */}
      {discoverable.length > 0 ? (
        <section
          data-testid="modules-discover-section"
          aria-label={business.length > 0 ? labels.dashboard.sectionLocked : labels.dashboard.sectionDiscoverBusiness}
          className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm md:p-6"
        >
          <p className="text-[10px] font-black uppercase tracking-widest text-slate-500">
            {business.length > 0 ? labels.dashboard.sectionLocked : labels.dashboard.sectionDiscoverBusiness}
          </p>
          <div className="mt-3 space-y-1">
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
                    className="flex w-full items-center justify-between gap-2 rounded-xl px-2 py-2 text-start text-sm font-bold text-emerald-700 transition hover:bg-emerald-50 disabled:opacity-60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40"
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
                  className="flex items-center justify-between gap-2 rounded-xl px-2 py-2 text-sm font-bold text-slate-500 transition hover:bg-emerald-50 hover:text-emerald-700"
                >
                  <span>{label}</span>
                  <Plus className="h-3.5 w-3.5" aria-hidden="true" />
                </Link>
              );
            })}
          </div>
          {activateError !== '' ? (
            <p role="alert" className="mt-3 text-xs font-semibold text-red-600">
              {activateError}
            </p>
          ) : null}
        </section>
      ) : null}
    </div>
  );
}
