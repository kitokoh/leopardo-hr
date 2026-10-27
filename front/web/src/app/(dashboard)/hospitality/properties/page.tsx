'use client';

/**
 * HOSP-007 (#7949) — referentiel des etablissements (BC-32).
 *
 * CRUD sur `/hospitality/properties`. La **publication** (`publish` /
 * `unpublish`) est exposee separement parce qu'elle change qui voit
 * l'etablissement : `is_public = true` le fait apparaitre sur la vitrine
 * `/stay` (HOSP-006), ce qui n'est pas un simple champ de formulaire.
 */
import { useCallback, useEffect, useState } from 'react';
import { Building2, Loader2, AlertTriangle, Plus, Trash2, Eye, EyeOff } from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

type Property = {
  id: number;
  name: string;
  slug: string | null;
  city: string | null;
  address: string | null;
  is_public: boolean;
  published_at: string | null;
};

export default function HospitalityPropertiesPage() {
  const locale = getPreferredLocale();
  const [properties, setProperties] = useState<Property[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [actionError, setActionError] = useState('');
  const [saving, setSaving] = useState(false);
  const [busyId, setBusyId] = useState<number | null>(null);
  const [formName, setFormName] = useState('');
  const [formCity, setFormCity] = useState('');
  const [formAddress, setFormAddress] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const res = await apiFetch('/hospitality/properties');
      if (!res.ok) {
        setError(t(locale, 'hospitality.properties.loadError'));
        return;
      }
      const payload = (await res.json()) as { data?: Property[] };
      setProperties(Array.isArray(payload.data) ? payload.data : []);
    } catch {
      setError(t(locale, 'hospitality.properties.loadError'));
    } finally {
      setLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    void load();
  }, [load]);

  async function create(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setActionError('');
    if (formName.trim() === '') {
      setActionError(t(locale, 'hospitality.properties.nameRequired'));
      return;
    }
    setSaving(true);
    try {
      const res = await apiFetch('/hospitality/properties', {
        method: 'POST',
        body: JSON.stringify({
          name: formName.trim(),
          city: formCity.trim() === '' ? null : formCity.trim(),
          address: formAddress.trim() === '' ? null : formAddress.trim(),
        }),
      });
      if (!res.ok) {
        setActionError(t(locale, 'hospitality.properties.createError'));
        return;
      }
      setFormName('');
      setFormCity('');
      setFormAddress('');
      await load();
    } catch {
      setActionError(t(locale, 'hospitality.properties.createError'));
    } finally {
      setSaving(false);
    }
  }

  async function togglePublication(property: Property) {
    setActionError('');
    setBusyId(property.id);
    try {
      const action = property.is_public ? 'unpublish' : 'publish';
      const res = await apiFetch(`/hospitality/properties/${property.id}/${action}`, { method: 'POST' });
      if (!res.ok) {
        // Le refus usuel : publier sans slug public ni unite disponible.
        // On remonte le message de l'API plutot qu'un « erreur » muet.
        setActionError(
          t(locale, 'hospitality.properties.publishError'),
        );
        return;
      }
      await load();
    } catch {
      setActionError(t(locale, 'hospitality.properties.publishError'));
    } finally {
      setBusyId(null);
    }
  }

  async function remove(property: Property) {
    const confirmed = window.confirm(
      t(locale, 'hospitality.properties.confirmDelete'),
    );
    if (!confirmed) return;
    setActionError('');
    setBusyId(property.id);
    try {
      const res = await apiFetch(`/hospitality/properties/${property.id}`, { method: 'DELETE' });
      if (!res.ok) {
        setActionError(t(locale, 'hospitality.properties.deleteError'));
        return;
      }
      await load();
    } catch {
      setActionError(t(locale, 'hospitality.properties.deleteError'));
    } finally {
      setBusyId(null);
    }
  }

  return (
    <ModulePageShell
      title={t(locale, 'hospitality.properties.title')}
      subtitle={t(locale, 'hospitality.properties.subtitle')}
      icon={Building2}
    >
      {error !== '' ? (
        <div role="alert" className="flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
          <AlertTriangle className="h-4 w-4" />
          {error}
        </div>
      ) : null}

      {actionError !== '' ? (
        <div role="alert" className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
          {actionError}
        </div>
      ) : null}

      <form onSubmit={create} className="rounded-xl border border-neutral-200 bg-white p-4">
        <p className="mb-3 text-sm font-medium text-neutral-900">
          {t(locale, 'hospitality.properties.create')}
        </p>
        <div className="grid gap-3 sm:grid-cols-3">
          <input
            value={formName}
            onChange={(e) => setFormName(e.target.value)}
            placeholder={t(locale, 'hospitality.properties.field.name')}
            className="rounded-lg border border-neutral-300 px-3 py-2 text-sm"
            required
          />
          <input
            value={formCity}
            onChange={(e) => setFormCity(e.target.value)}
            placeholder={t(locale, 'hospitality.properties.field.city')}
            className="rounded-lg border border-neutral-300 px-3 py-2 text-sm"
          />
          <input
            value={formAddress}
            onChange={(e) => setFormAddress(e.target.value)}
            placeholder={t(locale, 'hospitality.properties.field.address')}
            className="rounded-lg border border-neutral-300 px-3 py-2 text-sm"
          />
        </div>
        <button
          type="submit"
          disabled={saving}
          className="mt-3 inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white disabled:opacity-60"
        >
          {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Plus className="h-4 w-4" />}
          {t(locale, 'hospitality.properties.submit')}
        </button>
      </form>

      {loading ? (
        <div className="flex items-center gap-2 rounded-xl border border-neutral-200 bg-white p-4 text-sm text-neutral-600">
          <Loader2 className="h-4 w-4 animate-spin" />
          {t(locale, 'common.loading')}
        </div>
      ) : null}

      {!loading && properties.length === 0 ? (
        <div className="rounded-xl border border-neutral-200 bg-white p-6 text-sm text-neutral-600">
          {t(locale, 'hospitality.properties.empty')}
        </div>
      ) : null}

      {properties.length > 0 ? (
        <div className="overflow-hidden rounded-xl border border-neutral-200 bg-white">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 text-left text-neutral-600">
              <tr>
                <th className="px-4 py-2 font-medium">{t(locale, 'hospitality.properties.col.name')}</th>
                <th className="px-4 py-2 font-medium">{t(locale, 'hospitality.properties.col.city')}</th>
                <th className="px-4 py-2 font-medium">{t(locale, 'hospitality.properties.col.visibility')}</th>
                <th className="px-4 py-2" />
              </tr>
            </thead>
            <tbody>
              {properties.map((property) => (
                <tr key={property.id} className="border-t border-neutral-100">
                  <td className="px-4 py-3 font-medium text-neutral-900">{property.name}</td>
                  <td className="px-4 py-3 text-neutral-600">{property.city ?? '—'}</td>
                  <td className="px-4 py-3">
                    <span
                      className={
                        property.is_public
                          ? 'rounded-full bg-emerald-50 px-2 py-1 text-xs text-emerald-700'
                          : 'rounded-full bg-neutral-100 px-2 py-1 text-xs text-neutral-600'
                      }
                    >
                      {property.is_public
                        ? t(locale, 'hospitality.properties.public')
                        : t(locale, 'hospitality.properties.private')}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex items-center justify-end gap-2">
                      <button
                        type="button"
                        onClick={() => void togglePublication(property)}
                        disabled={busyId === property.id}
                        className="inline-flex items-center gap-1 rounded-lg border border-neutral-300 px-2 py-1 text-xs text-neutral-700 disabled:opacity-50"
                      >
                        {property.is_public ? <EyeOff className="h-3 w-3" /> : <Eye className="h-3 w-3" />}
                        {property.is_public
                          ? t(locale, 'hospitality.properties.unpublish')
                          : t(locale, 'hospitality.properties.publish')}
                      </button>
                      <button
                        type="button"
                        onClick={() => void remove(property)}
                        disabled={busyId === property.id}
                        className="inline-flex items-center gap-1 rounded-lg border border-red-200 px-2 py-1 text-xs text-red-700 disabled:opacity-50"
                      >
                        <Trash2 className="h-3 w-3" />
                        {t(locale, 'common.delete')}
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}
    </ModulePageShell>
  );
}
