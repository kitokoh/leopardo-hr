'use client';

/**
 * BranchSelect (RESTO-904, #7749) — sélecteur de succursale partagé du module
 * Restaurant. Remplace les saisies « Branche ID » brutes : la liste vient de
 * `GET /restaurant/branches` (via le hook `useRestaurantBranches`) et le
 * composant rend un `<select>` avec le nom des branches.
 */
import { useCallback, useEffect, useState } from 'react';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

export type RestaurantBranch = {
  id: number;
  code?: string;
  name: string;
  city?: string | null;
  establishment_type?: string | null;
};

/** Charge la liste des succursales du tenant (pattern kitchen/page.tsx). */
export function useRestaurantBranches(): {
  branches: RestaurantBranch[];
  loading: boolean;
  error: string;
  reload: () => Promise<void>;
} {
  const locale = getPreferredLocale();
  const [branches, setBranches] = useState<RestaurantBranch[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const reload = useCallback(async () => {
    setLoading(true);
    try {
      const res = await apiFetch('/restaurant/branches?per_page=200');
      const payload = (await res.json()) as { data?: RestaurantBranch[] };
      setBranches(Array.isArray(payload?.data) ? payload.data : []);
      setError('');
    } catch {
      setError(t(locale, 'restaurant.branchSelect.loadError'));
    } finally {
      setLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    void reload();
  }, [reload]);

  return { branches, loading, error, reload };
}

export function BranchSelect({
  branches,
  value,
  onChange,
  allowEmpty = false,
  className,
}: {
  branches: RestaurantBranch[];
  value: number | null;
  onChange: (branchId: number | null) => void;
  /** Affiche une option vide (ex. « toutes les branches » / non choisie). */
  allowEmpty?: boolean;
  className?: string;
}) {
  const locale = getPreferredLocale();

  return (
    <select
      value={value ?? ''}
      onChange={(event) => onChange(event.target.value === '' ? null : Number(event.target.value))}
      aria-label={t(locale, 'restaurant.branch')}
      className={className ?? 'rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none'}
    >
      {allowEmpty ? <option value="">{t(locale, 'restaurant.branchSelect.placeholder')}</option> : null}
      {branches.map((branch) => (
        <option key={branch.id} value={branch.id}>
          {branch.name}
          {branch.city ? ` — ${branch.city}` : ''}
        </option>
      ))}
    </select>
  );
}
