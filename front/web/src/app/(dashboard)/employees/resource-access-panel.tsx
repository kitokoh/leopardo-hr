'use client';

/**
 * Issue #7599 (R2 de l'épique #7597) — onglet « Accès & ressources » d'un
 * collaborateur : matrice ressources × niveau (aucun / lecture / opérer /
 * gérer), branchée sur l'API R1 :
 *
 *   GET /employees/{id}/resource-assignments  (existant)
 *   PUT /employees/{id}/resource-assignments  (remplacement complet)
 *   GET /resources/{type}                     (catalogue assignable)
 *
 * Le PUT est un remplacement complet TOUS TYPES CONFONDUS : le panneau
 * conserve donc les assignations des autres types telles que chargées et ne
 * réécrit que celles du type affiché.
 */

import { useCallback, useEffect, useMemo, useState } from 'react';
import { apiFetch } from '@/lib/api-client';
import type { AppLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

const RESOURCE_TYPES = ['restaurant_branch', 'site', 'vehicle', 'camera'] as const;
const ACCESS_LEVELS = ['view', 'operate', 'manage'] as const;

type ResourceType = (typeof RESOURCE_TYPES)[number];
type AccessLevel = (typeof ACCESS_LEVELS)[number];

type Assignment = {
  resource_type: string;
  resource_id: number;
  access_level: string;
  resource_label?: string;
};

type CatalogEntry = {
  id: number;
  label: string;
};

type Props = {
  employeeId: number;
  locale: AppLocale;
};

export function ResourceAccessPanel({ employeeId, locale }: Props) {
  const [resourceType, setResourceType] = useState<ResourceType>('restaurant_branch');
  const [assignments, setAssignments] = useState<Assignment[]>([]);
  const [catalog, setCatalog] = useState<CatalogEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    setSaved(false);
    try {
      const [assignmentsRes, catalogRes] = await Promise.all([
        apiFetch(`/employees/${employeeId}/resource-assignments`, { _cacheBust: true }),
        apiFetch(`/resources/${resourceType}`, { _cacheBust: true }),
      ]);
      if (!assignmentsRes.ok || !catalogRes.ok) {
        setError(t(locale, 'employees.resourceAccess.loadError'));
        return;
      }
      const assignmentsPayload = (await assignmentsRes.json()) as { data?: Assignment[] };
      const catalogPayload = (await catalogRes.json()) as { data?: CatalogEntry[] };
      setAssignments(Array.isArray(assignmentsPayload.data) ? assignmentsPayload.data : []);
      setCatalog(Array.isArray(catalogPayload.data) ? catalogPayload.data : []);
    } catch {
      setError(t(locale, 'employees.resourceAccess.loadError'));
    } finally {
      setLoading(false);
    }
  }, [employeeId, resourceType, locale]);

  useEffect(() => {
    void load();
  }, [load]);

  const levelByResourceId = useMemo(() => {
    const map = new Map<number, string>();
    for (const assignment of assignments) {
      if (assignment.resource_type === resourceType) {
        map.set(assignment.resource_id, assignment.access_level);
      }
    }
    return map;
  }, [assignments, resourceType]);

  const setLevel = (resourceId: number, level: AccessLevel | null) => {
    setSaved(false);
    setAssignments((current) => {
      const others = current.filter(
        (entry) => entry.resource_type !== resourceType || entry.resource_id !== resourceId,
      );
      if (level === null) {
        return others;
      }
      return [...others, { resource_type: resourceType, resource_id: resourceId, access_level: level }];
    });
  };

  const save = async () => {
    setSaving(true);
    setError(null);
    setSaved(false);
    try {
      const response = await apiFetch(`/employees/${employeeId}/resource-assignments`, {
        method: 'PUT',
        body: JSON.stringify({
          assignments: assignments.map((entry) => ({
            resource_type: entry.resource_type,
            resource_id: entry.resource_id,
            access_level: entry.access_level,
          })),
        }),
      });
      if (!response.ok) {
        setError(t(locale, 'employees.resourceAccess.saveError'));
        return;
      }
      const payload = (await response.json()) as { data?: Assignment[] };
      setAssignments(Array.isArray(payload.data) ? payload.data : []);
      setSaved(true);
    } catch {
      setError(t(locale, 'employees.resourceAccess.saveError'));
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="rounded-2xl border border-app-border bg-slate-50 p-4" data-testid="resource-access-panel">
      <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h3 className="text-sm font-bold text-slate-900">
            {t(locale, 'employees.resourceAccess.title')}
          </h3>
          <p className="text-xs text-slate-500">{t(locale, 'employees.resourceAccess.subtitle')}</p>
        </div>
        <select
          value={resourceType}
          onChange={(event) => setResourceType(event.target.value as ResourceType)}
          aria-label={t(locale, 'employees.resourceAccess.title')}
          className="rounded-xl border border-app-border bg-white px-3 py-2 text-sm"
        >
          {RESOURCE_TYPES.map((type) => (
            <option key={type} value={type}>
              {t(locale, `employees.resourceAccess.types.${type}`, type)}
            </option>
          ))}
        </select>
      </div>

      {error ? <p className="mt-3 text-sm text-red-600">{error}</p> : null}
      {saved ? (
        <p className="mt-3 text-sm text-emerald-700">{t(locale, 'employees.resourceAccess.saved')}</p>
      ) : null}

      {loading ? (
        <p className="mt-3 text-sm text-slate-500">{t(locale, 'employees.resourceAccess.loading')}</p>
      ) : catalog.length === 0 ? (
        <p className="mt-3 text-sm text-slate-500">{t(locale, 'employees.resourceAccess.empty')}</p>
      ) : (
        <div className="mt-3 space-y-2">
          {catalog.map((resource) => {
            const currentLevel = levelByResourceId.get(resource.id) ?? null;
            return (
              <div
                key={resource.id}
                className="flex flex-col gap-2 rounded-xl border border-app-border bg-white px-3 py-2 sm:flex-row sm:items-center sm:justify-between"
              >
                <span className="text-sm font-medium text-slate-800">{resource.label}</span>
                <div className="flex flex-wrap gap-1">
                  {[null, ...ACCESS_LEVELS].map((level) => {
                    const key = level ?? 'none';
                    const label = t(
                      locale,
                      level === null
                        ? 'employees.resourceAccess.levelNone'
                        : `employees.resourceAccess.level${level.charAt(0).toUpperCase()}${level.slice(1)}`,
                    );
                    const active = currentLevel === level || (level === null && currentLevel === null);
                    return (
                      <button
                        key={key}
                        type="button"
                        onClick={() => setLevel(resource.id, level)}
                        className={
                          active
                            ? 'rounded-lg bg-slate-900 px-3 py-1 text-xs font-bold text-white'
                            : 'rounded-lg border border-app-border px-3 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-100'
                        }
                      >
                        {label}
                      </button>
                    );
                  })}
                </div>
              </div>
            );
          })}
        </div>
      )}

      <div className="mt-4">
        <button
          type="button"
          onClick={() => void save()}
          disabled={saving || loading}
          className="inline-flex items-center gap-2 rounded-xl bg-brand-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-800 disabled:opacity-60"
        >
          {t(locale, 'employees.resourceAccess.save')}
        </button>
      </div>
    </div>
  );
}
