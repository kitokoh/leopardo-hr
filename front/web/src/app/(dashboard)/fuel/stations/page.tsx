'use client';

/**
 * BC-15 Fuel — gestion des stations-service (FUEL-011, #5805).
 *
 * Branche le CRUD de `/fuel-station/stations` (FuelStationController) sur une
 * surface web : un responsable de station crée ses stations (code, nom,
 * adresse, devise, statut), puis les modifie ou les supprime.
 */
import { useCallback, useEffect, useState } from 'react';
import { Building2 } from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

type Station = {
  id: number;
  code: string;
  name: string;
  address?: string | null;
  phone?: string | null;
  currency?: string | null;
  status?: string;
};

export default function FuelStationsPage() {
  const locale = getPreferredLocale();
  const [stations, setStations] = useState<Station[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [formId, setFormId] = useState('');
  const [formCode, setFormCode] = useState('');
  const [formName, setFormName] = useState('');
  const [formAddress, setFormAddress] = useState('');
  const [formCurrency, setFormCurrency] = useState('XOF');
  const [formStatus, setFormStatus] = useState('active');
  const [formError, setFormError] = useState('');
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const res = await apiFetch('/fuel-station/stations?per_page=200');
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const payload = (await res.json()) as { data?: Station[] };
      setStations(Array.isArray(payload.data) ? payload.data : []);
    } catch {
      setError(t(locale, 'fuel.stations.loadError', 'Impossible de charger les stations.'));
    } finally {
      setLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    void load();
  }, [load]);

  const readApiError = async (res: Response): Promise<string | null> => {
    try {
      const payload = (await res.json()) as { message?: string };
      return typeof payload.message === 'string' && payload.message !== '' ? payload.message : null;
    } catch {
      return null;
    }
  };

  const resetForm = () => {
    setFormId('');
    setFormCode('');
    setFormName('');
    setFormAddress('');
    setFormCurrency('XOF');
    setFormStatus('active');
    setFormError('');
  };

  const submit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setFormError('');
    if (formCode.trim() === '' || formName.trim() === '') {
      setFormError(t(locale, 'fuel.stations.formIncomplete', 'Renseignez un code et un nom.'));
      return;
    }
    setSaving(true);
    const body = JSON.stringify({
      code: formCode.trim(),
      name: formName.trim(),
      address: formAddress.trim() || null,
      currency: formCurrency.trim() || null,
      status: formStatus,
    });
    try {
      const res =
        formId === ''
          ? await apiFetch('/fuel-station/stations', { method: 'POST', body })
          : await apiFetch(`/fuel-station/stations/${formId}`, { method: 'PUT', body });
      if (!res.ok) {
        setFormError((await readApiError(res)) ?? t(locale, 'fuel.stations.saveFailed', "L'enregistrement a échoué."));
        return;
      }
      resetForm();
      await load();
    } catch {
      setFormError(t(locale, 'fuel.stations.saveFailed', "L'enregistrement a échoué."));
    } finally {
      setSaving(false);
    }
  };

  const startEdit = (station: Station) => {
    setFormId(String(station.id));
    setFormCode(station.code ?? '');
    setFormName(station.name ?? '');
    setFormAddress(station.address ?? '');
    setFormCurrency(station.currency ?? 'XOF');
    setFormStatus(station.status ?? 'active');
    setFormError('');
  };

  const remove = async (station: Station) => {
    if (!window.confirm(t(locale, 'fuel.stations.confirmRemove', 'Supprimer cette station ?'))) {
      return;
    }
    setError('');
    try {
      const res = await apiFetch(`/fuel-station/stations/${station.id}`, { method: 'DELETE' });
      if (!res.ok && res.status !== 204) throw new Error(`HTTP ${res.status}`);
      await load();
    } catch {
      setError(t(locale, 'fuel.stations.removeFailed', 'La suppression a échoué.'));
    }
  };

  const inputClass =
    'w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-amber-400 focus:outline-none';

  return (
    <ModulePageShell
      icon={Building2}
      title={t(locale, 'fuel.stations.title', 'Stations')}
      description={t(locale, 'fuel.stations.subtitle', 'Créez et gérez vos stations-service.')}
    >
      {error ? (
        <p role="alert" className="rounded-lg bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
          {error}
        </p>
      ) : null}

      <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 className="text-sm font-bold uppercase tracking-wide text-slate-500">
          {formId === ''
            ? t(locale, 'fuel.stations.createTitle', 'Nouvelle station')
            : t(locale, 'fuel.stations.editTitle', 'Modifier la station')}
        </h2>
        <form onSubmit={submit} className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <label className="block text-sm">
            <span className="mb-1 block font-semibold text-slate-700">{t(locale, 'fuel.stations.code', 'Code')}</span>
            <input
              type="text"
              value={formCode}
              onChange={(e) => setFormCode(e.target.value)}
              maxLength={40}
              className={inputClass}
              data-testid="station-code"
            />
          </label>
          <label className="block text-sm">
            <span className="mb-1 block font-semibold text-slate-700">{t(locale, 'fuel.stations.name', 'Nom')}</span>
            <input
              type="text"
              value={formName}
              onChange={(e) => setFormName(e.target.value)}
              maxLength={150}
              className={inputClass}
              data-testid="station-name"
            />
          </label>
          <label className="block text-sm">
            <span className="mb-1 block font-semibold text-slate-700">{t(locale, 'fuel.stations.address', 'Adresse')}</span>
            <input
              type="text"
              value={formAddress}
              onChange={(e) => setFormAddress(e.target.value)}
              maxLength={255}
              className={inputClass}
            />
          </label>
          <label className="block text-sm">
            <span className="mb-1 block font-semibold text-slate-700">{t(locale, 'fuel.stations.currency', 'Devise')}</span>
            <input
              type="text"
              value={formCurrency}
              onChange={(e) => setFormCurrency(e.target.value)}
              maxLength={10}
              className={inputClass}
            />
          </label>
          <label className="block text-sm">
            <span className="mb-1 block font-semibold text-slate-700">{t(locale, 'fuel.stations.status', 'Statut')}</span>
            <select value={formStatus} onChange={(e) => setFormStatus(e.target.value)} className={inputClass}>
              <option value="active">{t(locale, 'fuel.stations.statusActive', 'Active')}</option>
              <option value="inactive">{t(locale, 'fuel.stations.statusInactive', 'Inactive')}</option>
              <option value="archived">{t(locale, 'fuel.stations.statusArchived', 'Archivée')}</option>
            </select>
          </label>
          <div className="flex items-end gap-2">
            <button
              type="submit"
              disabled={saving}
              data-testid="station-submit"
              className="rounded-xl bg-amber-600 px-4 py-2 text-sm font-black text-white transition hover:bg-amber-700 disabled:opacity-50"
            >
              {saving
                ? t(locale, 'fuel.stations.saving', 'Enregistrement…')
                : formId === ''
                  ? t(locale, 'fuel.stations.create', 'Créer')
                  : t(locale, 'fuel.stations.save', 'Enregistrer')}
            </button>
            {formId !== '' ? (
              <button
                type="button"
                onClick={resetForm}
                className="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600 transition hover:bg-slate-50"
              >
                {t(locale, 'fuel.stations.cancel', 'Annuler')}
              </button>
            ) : null}
          </div>
        </form>
        {formError ? (
          <p role="alert" className="mt-3 text-sm font-semibold text-red-600">
            {formError}
          </p>
        ) : null}
      </section>

      <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 className="text-sm font-bold uppercase tracking-wide text-slate-500">
          {t(locale, 'fuel.stations.listTitle', 'Stations existantes')}
        </h2>
        {loading ? (
          <p className="mt-3 text-sm text-slate-500">{t(locale, 'fuel.common.loading', 'Chargement…')}</p>
        ) : stations.length === 0 ? (
          <p className="mt-3 text-sm text-slate-500" data-testid="station-empty">
            {t(locale, 'fuel.stations.empty', 'Aucune station pour le moment.')}
          </p>
        ) : (
          <div className="mt-3 overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead>
                <tr className="border-b border-slate-200 text-xs font-bold uppercase tracking-wide text-slate-500">
                  <th className="px-3 py-2">{t(locale, 'fuel.stations.code', 'Code')}</th>
                  <th className="px-3 py-2">{t(locale, 'fuel.stations.name', 'Nom')}</th>
                  <th className="px-3 py-2">{t(locale, 'fuel.stations.address', 'Adresse')}</th>
                  <th className="px-3 py-2">{t(locale, 'fuel.stations.status', 'Statut')}</th>
                  <th className="px-3 py-2" />
                </tr>
              </thead>
              <tbody>
                {stations.map((station) => (
                  <tr key={station.id} className="border-b border-slate-100" data-testid={`station-row-${station.id}`}>
                    <td className="px-3 py-2 font-mono text-xs font-bold text-slate-500">{station.code}</td>
                    <td className="px-3 py-2 font-semibold text-slate-800">{station.name}</td>
                    <td className="px-3 py-2 text-slate-600">{station.address ?? '—'}</td>
                    <td className="px-3 py-2 text-slate-600">{station.status ?? '—'}</td>
                    <td className="px-3 py-2">
                      <div className="flex justify-end gap-2">
                        <button
                          type="button"
                          onClick={() => startEdit(station)}
                          className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:bg-slate-50"
                        >
                          {t(locale, 'fuel.common.edit', 'Modifier')}
                        </button>
                        <button
                          type="button"
                          onClick={() => void remove(station)}
                          className="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-bold text-red-700 transition hover:bg-red-50"
                        >
                          {t(locale, 'fuel.common.remove', 'Supprimer')}
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>
    </ModulePageShell>
  );
}
