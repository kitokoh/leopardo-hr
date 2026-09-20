'use client';

/**
 * #7862 — panneau « Modules délégués » d'un collaborateur, extrait de
 * l'ancienne page /settings/team (#7762) lors de sa fusion dans /employees.
 *
 * Contrat API inchangé :
 *   GET /employees/{id}/module-grants  → composition courante
 *   PUT /employees/{id}/module-grants  → jeu COMPLET (ce qui n'est pas coché
 *                                        est révoqué)
 *
 * Réservé au manager principal (policy `manageModuleGrants`) : le parent ne
 * monte ce panneau que pour un viewer principal, sur une ligne ≠ soi.
 */

import { useCallback, useEffect, useState } from 'react';

import { apiFetch } from '@/lib/api-client';
import { MODULE_GRANT_KEYS, type ModuleGrantKey } from '@/lib/client-features';
import type { AppLocale } from '@/lib/i18n';
import { teamRolesErrorMessage, teamRolesT, type TeamRolesKey } from '@/lib/i18n/team-roles';

/**
 * Libellés des modules délégables (registre fermé ModuleKey, miroir de
 * `MODULE_GRANT_KEYS` — la parité est vérifiée par le test de la page).
 */
const MODULE_GRANT_LABEL_KEYS: Record<ModuleGrantKey, TeamRolesKey> = {
  marketing: 'moduleMarketing',
  accounting: 'moduleAccounting',
  support: 'moduleSupport',
  crm: 'moduleCrm',
  showcase: 'moduleShowcase',
  hr: 'moduleHr',
  billing_view: 'moduleBillingView',
};

type ModuleGrantsPayload = { data?: { module_keys?: string[] } };

type Props = {
  employeeId: number;
  locale: AppLocale;
  /** Notifie le parent d'un succès d'enregistrement (bandeau global). */
  onSaved?: (message: string) => void;
};

export function ModuleGrantsPanel({ employeeId, locale, onSaved }: Props) {
  const [draft, setDraft] = useState<string[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);

    try {
      const response = await apiFetch(`/employees/${employeeId}/module-grants`, { _cacheBust: true });
      const payload = (await response.json()) as ModuleGrantsPayload;
      setDraft(Array.isArray(payload.data?.module_keys) ? payload.data.module_keys : []);
    } catch (err) {
      setError(teamRolesErrorMessage(locale, err, 'modulesLoadError'));
    } finally {
      setLoading(false);
    }
  }, [employeeId, locale]);

  useEffect(() => {
    void load();
  }, [load]);

  const toggle = (key: string) => {
    setDraft((current) =>
      current.includes(key) ? current.filter((entry) => entry !== key) : [...current, key],
    );
  };

  /** PUT du jeu COMPLET (ce qui n'est pas coché est révoqué). */
  const save = async () => {
    setError(null);
    setSaving(true);

    try {
      await apiFetch(`/employees/${employeeId}/module-grants`, {
        method: 'PUT',
        body: JSON.stringify({ module_keys: draft }),
      });
      onSaved?.(teamRolesT(locale, 'modulesSaved'));
    } catch (err) {
      setError(teamRolesErrorMessage(locale, err));
    } finally {
      setSaving(false);
    }
  };

  return (
    <div
      data-testid={`team-grants-panel-${employeeId}`}
      className="rounded-2xl border border-app-border bg-white p-4"
    >
      <p className="text-[11px] font-bold uppercase tracking-wider text-slate-600">
        {teamRolesT(locale, 'modulesTitle')}
      </p>
      <p className="mt-1 text-[11px] text-slate-500">{teamRolesT(locale, 'modulesHint')}</p>

      {error ? <p className="mt-3 text-xs font-medium text-red-600">{error}</p> : null}

      {loading ? (
        <p className="mt-3 text-xs text-slate-500">{teamRolesT(locale, 'loading')}</p>
      ) : (
        <>
          <div className="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
            {MODULE_GRANT_KEYS.map((moduleKey) => (
              <label
                key={moduleKey}
                className="flex items-center gap-2 rounded-xl border border-app-border bg-slate-50 px-3 py-2 text-xs font-medium text-slate-700"
              >
                <input
                  type="checkbox"
                  checked={draft.includes(moduleKey)}
                  onChange={() => toggle(moduleKey)}
                  data-testid={`team-grant-${employeeId}-${moduleKey}`}
                  className="h-4 w-4 rounded border-app-border text-brand-700 focus:ring-brand-500"
                />
                {teamRolesT(locale, MODULE_GRANT_LABEL_KEYS[moduleKey])}
              </label>
            ))}
          </div>
          <div className="mt-3">
            <button
              type="button"
              onClick={() => void save()}
              disabled={saving}
              data-testid={`team-grants-save-${employeeId}`}
              className="rounded-xl bg-brand-700 px-4 py-2 text-xs font-bold text-white transition hover:bg-brand-800 disabled:opacity-60"
            >
              {teamRolesT(locale, 'modulesSave')}
            </button>
          </div>
        </>
      )}
    </div>
  );
}
