'use client';

/**
 * #EVAL — panneau « Permissions métier » d'un collaborateur.
 *
 * Constat de recette : `travel.agent` (vente au guichet) n'était attribuable à
 * personne. Le panneau « Modules délégués » ne pouvait pas le proposer — son
 * registre (`ModuleKey`) est fermé et HORIZONTAL, et l'API refuse toute clé
 * hors registre en 422. Le grant n'existait donc que posé automatiquement pour
 * le `principal` à l'activation de la verticale : un responsable d'agence ne
 * pouvait pas nommer un vendeur.
 *
 * Contrat API (surface DISTINCTE de `module-grants`) :
 *   GET /employees/{id}/vertical-permissions
 *     → { data: { permission_keys: string[], available: Record<code, label> } }
 *   PUT /employees/{id}/vertical-permissions
 *     → { permission_keys: string[] }  (jeu COMPLET : non coché = révoqué)
 *
 * Le catalogue `available` vient du SERVEUR : il ne contient que les
 * permissions des solutions réellement actives du tenant. Le front ne décide
 * donc jamais quelles permissions existent (fail-closed côté API).
 *
 * Réservé au manager principal (policy `manageModuleGrants`) : le parent ne
 * monte ce panneau que pour un viewer principal, sur une ligne ≠ soi.
 */

import { useCallback, useEffect, useState } from 'react';

import { apiFetch } from '@/lib/api-client';
import type { AppLocale } from '@/lib/i18n';
import { teamRolesErrorMessage, teamRolesT } from '@/lib/i18n/team-roles';

type VerticalPermissionsPayload = {
  data?: {
    permission_keys?: string[];
    available?: Record<string, string>;
  };
};

type Props = {
  employeeId: number;
  locale: AppLocale;
  /** Notifie le parent d'un succès d'enregistrement (bandeau global). */
  onSaved?: (message: string) => void;
};

export function VerticalPermissionsPanel({ employeeId, locale, onSaved }: Props) {
  const [draft, setDraft] = useState<string[]>([]);
  const [available, setAvailable] = useState<Record<string, string>>({});
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);

    try {
      const response = await apiFetch(`/employees/${employeeId}/vertical-permissions`, {
        _cacheBust: true,
      });
      const payload = (await response.json()) as VerticalPermissionsPayload;

      setDraft(Array.isArray(payload.data?.permission_keys) ? payload.data.permission_keys : []);
      setAvailable(payload.data?.available ?? {});
    } catch (err) {
      setError(teamRolesErrorMessage(locale, err, 'verticalLoadError'));
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
      await apiFetch(`/employees/${employeeId}/vertical-permissions`, {
        method: 'PUT',
        body: JSON.stringify({ permission_keys: draft }),
      });
      onSaved?.(teamRolesT(locale, 'verticalSaved'));
    } catch (err) {
      setError(teamRolesErrorMessage(locale, err));
    } finally {
      setSaving(false);
    }
  };

  const codes = Object.keys(available).sort();

  return (
    <div
      data-testid={`vertical-permissions-panel-${employeeId}`}
      className="rounded-2xl border border-app-border bg-white p-4"
    >
      <p className="text-xs font-bold uppercase tracking-wider text-slate-600">
        {teamRolesT(locale, 'verticalTitle')}
      </p>
      <p className="mt-1 text-xs text-slate-500">{teamRolesT(locale, 'verticalHint')}</p>

      {error ? <p className="mt-3 text-xs font-medium text-red-600">{error}</p> : null}

      {loading ? (
        <p className="mt-3 text-xs text-slate-500">{teamRolesT(locale, 'loading')}</p>
      ) : codes.length === 0 ? (
        <p className="mt-3 text-xs text-slate-500">{teamRolesT(locale, 'verticalEmpty')}</p>
      ) : (
        <>
          <div className="mt-3 grid gap-2 sm:grid-cols-2">
            {codes.map((code) => (
              <label
                key={code}
                className="flex items-start gap-2 rounded-xl border border-app-border bg-slate-50 px-3 py-2 text-xs font-medium text-slate-700"
              >
                <input
                  type="checkbox"
                  checked={draft.includes(code)}
                  onChange={() => toggle(code)}
                  data-testid={`vertical-permission-${employeeId}-${code}`}
                  className="mt-0.5 h-4 w-4 rounded border-app-border text-brand-700 focus:ring-brand-500"
                />
                <span>
                  <span className="block">{available[code]}</span>
                  <span className="block font-mono text-[10px] text-slate-400">{code}</span>
                </span>
              </label>
            ))}
          </div>
          <div className="mt-3">
            <button
              type="button"
              onClick={() => void save()}
              disabled={saving}
              data-testid={`vertical-permissions-save-${employeeId}`}
              className="rounded-xl bg-brand-700 px-4 py-2 text-xs font-bold text-white transition hover:bg-brand-800 disabled:opacity-60"
            >
              {teamRolesT(locale, 'verticalSave')}
            </button>
          </div>
        </>
      )}
    </div>
  );
}
