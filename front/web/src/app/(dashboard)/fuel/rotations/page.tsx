'use client';

/**
 * BC-15 Fuel — rotations/plannings des équipes de station (FUEL-005, #5799).
 *
 * L'API existait déjà (`/fuel-station/shifts`, FuelShiftController — CRUD des
 * shifts) mais AUCUNE surface web ne l'exposait : un responsable créait ses
 * employés sans pouvoir planifier leurs rotations. Cette page branche le CRUD
 * des shifts (nom, station, horaires, statut) sur l'API réelle.
 */
import { useCallback, useEffect, useState } from 'react';
import { CalendarClock } from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

type Shift = {
  id: number;
  name: string;
  station_id: number | null;
  start_time: string | null;
  end_time: string | null;
  status?: string;
  assignments_count?: number | null;
};

type StationOption = { id: number; name: string };

export default function FuelRotationsPage() {
  const locale = getPreferredLocale();
  const [shifts, setShifts] = useState<Shift[]>([]);
  const [stations, setStations] = useState<StationOption[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [formId, setFormId] = useState('');
  const [formName, setFormName] = useState('');
  const [formStation, setFormStation] = useState('');
  const [formStart, setFormStart] = useState('06:00');
  const [formEnd, setFormEnd] = useState('14:00');
  const [formStatus, setFormStatus] = useState('active');
  const [formError, setFormError] = useState('');
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const res = await apiFetch('/fuel-station/shifts?per_page=200');
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const payload = (await res.json()) as { data?: Shift[] };
      setShifts(Array.isArray(payload.data) ? payload.data : []);
    } catch {
      setError(t(locale, 'fuel.rotations.loadError', 'Impossible de charger les rotations.'));
    } finally {
      setLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    let cancelled = false;
    void (async () => {
      try {
        const res = await apiFetch('/fuel-station/stations?per_page=200');
        if (!res.ok) return;
        const payload = (await res.json()) as { data?: { id: number; name: string }[] };
        if (cancelled) return;
        setStations(Array.isArray(payload.data) ? payload.data : []);
      } catch {
        // référentiel stations optionnel : la liste des shifts prime.
      }
    })();
    return () => {
      cancelled = true;
    };
  }, []);

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
    setFormName('');
    setFormStation('');
    setFormStart('06:00');
    setFormEnd('14:00');
    setFormStatus('active');
    setFormError('');
  };

  const submit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setFormError('');
    if (formName.trim() === '' || formStart === '' || formEnd === '') {
      setFormError(t(locale, 'fuel.rotations.formIncomplete', 'Renseignez un nom et des horaires valides.'));
      return;
    }
    setSaving(true);
    const body = JSON.stringify({
      name: formName.trim(),
      station_id: formStation === '' ? null : Number(formStation),
      start_time: formStart,
      end_time: formEnd,
      status: formStatus,
    });
    try {
      const res =
        formId === ''
          ? await apiFetch('/fuel-station/shifts', { method: 'POST', body })
          : await apiFetch(`/fuel-station/shifts/${formId}`, { method: 'PUT', body });
      if (!res.ok) {
        setFormError((await readApiError(res)) ?? t(locale, 'fuel.rotations.saveFailed', "L'enregistrement a échoué."));
        return;
      }
      resetForm();
      await load();
    } catch {
      setFormError(t(locale, 'fuel.rotations.saveFailed', "L'enregistrement a échoué."));
    } finally {
      setSaving(false);
    }
  };

  const startEdit = (shift: Shift) => {
    setFormId(String(shift.id));
    setFormName(shift.name ?? '');
    setFormStation(shift.station_id === null ? '' : String(shift.station_id));
    setFormStart((shift.start_time ?? '').slice(0, 5));
    setFormEnd((shift.end_time ?? '').slice(0, 5));
    setFormStatus(shift.status ?? 'active');
    setFormError('');
  };

  const remove = async (shift: Shift) => {
    if (!window.confirm(t(locale, 'fuel.rotations.confirmRemove', 'Supprimer cette rotation ?'))) {
      return;
    }
    setError('');
    try {
      const res = await apiFetch(`/fuel-station/shifts/${shift.id}`, { method: 'DELETE' });
      if (!res.ok && res.status !== 204) throw new Error(`HTTP ${res.status}`);
      await load();
    } catch {
      setError(t(locale, 'fuel.rotations.removeFailed', 'La suppression a échoué.'));
    }
  };

  const stationName = (id: number | null): string =>
    id === null ? '—' : stations.find((station) => station.id === id)?.name ?? `#${id}`;

  const inputClass =
    'w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-sky-400 focus:outline-none';

  return (
    <ModulePageShell
      icon={CalendarClock}
      title={t(locale, 'fuel.rotations.title', 'Rotations')}
      description={t(locale, 'fuel.rotations.subtitle', 'Définissez les shifts de vos stations (horaires et pause).')}
    >
      {error ? (
        <p role="alert" className="rounded-lg bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
          {error}
        </p>
      ) : null}

      <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 className="text-sm font-bold uppercase tracking-wide text-slate-500">
          {formId === ''
            ? t(locale, 'fuel.rotations.createTitle', 'Nouvelle rotation')
            : t(locale, 'fuel.rotations.editTitle', 'Modifier la rotation')}
        </h2>
        <form onSubmit={submit} className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
          <label className="block text-sm">
            <span className="mb-1 block font-semibold text-slate-700">{t(locale, 'fuel.rotations.name', 'Nom')}</span>
            <input
              type="text"
              value={formName}
              onChange={(e) => setFormName(e.target.value)}
              placeholder={t(locale, 'fuel.rotations.namePlaceholder', 'Matin, soir, nuit…')}
              maxLength={120}
              className={inputClass}
              data-testid="rotation-name"
            />
          </label>
          <label className="block text-sm">
            <span className="mb-1 block font-semibold text-slate-700">{t(locale, 'fuel.rotations.station', 'Station')}</span>
            <select value={formStation} onChange={(e) => setFormStation(e.target.value)} className={inputClass}>
              <option value="">{t(locale, 'fuel.rotations.stationAny', 'Toutes')}</option>
              {stations.map((station) => (
                <option key={station.id} value={station.id}>
                  {station.name}
                </option>
              ))}
            </select>
          </label>
          <label className="block text-sm">
            <span className="mb-1 block font-semibold text-slate-700">{t(locale, 'fuel.rotations.startTime', 'Début')}</span>
            <input
              type="time"
              value={formStart}
              onChange={(e) => setFormStart(e.target.value)}
              className={inputClass}
              data-testid="rotation-start"
            />
          </label>
          <label className="block text-sm">
            <span className="mb-1 block font-semibold text-slate-700">{t(locale, 'fuel.rotations.endTime', 'Fin')}</span>
            <input
              type="time"
              value={formEnd}
              onChange={(e) => setFormEnd(e.target.value)}
              className={inputClass}
              data-testid="rotation-end"
            />
          </label>
          <label className="block text-sm">
            <span className="mb-1 block font-semibold text-slate-700">{t(locale, 'fuel.rotations.status', 'Statut')}</span>
            <select value={formStatus} onChange={(e) => setFormStatus(e.target.value)} className={inputClass}>
              <option value="active">{t(locale, 'fuel.stations.statusActive', 'Active')}</option>
              <option value="inactive">{t(locale, 'fuel.stations.statusInactive', 'Inactive')}</option>
            </select>
          </label>
          <div className="flex items-end gap-2 sm:col-span-2 lg:col-span-5">
            <button
              type="submit"
              disabled={saving}
              data-testid="rotation-submit"
              className="rounded-xl bg-sky-600 px-4 py-2 text-sm font-black text-white transition hover:bg-sky-700 disabled:opacity-50"
            >
              {saving
                ? t(locale, 'fuel.rotations.saving', 'Enregistrement…')
                : formId === ''
                  ? t(locale, 'fuel.rotations.create', 'Créer')
                  : t(locale, 'fuel.rotations.save', 'Enregistrer')}
            </button>
            {formId !== '' ? (
              <button
                type="button"
                onClick={resetForm}
                className="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600 transition hover:bg-slate-50"
              >
                {t(locale, 'fuel.rotations.cancel', 'Annuler')}
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
          {t(locale, 'fuel.rotations.listTitle', 'Rotations existantes')}
        </h2>
        {loading ? (
          <p className="mt-3 text-sm text-slate-500">{t(locale, 'fuel.common.loading', 'Chargement…')}</p>
        ) : shifts.length === 0 ? (
          <p className="mt-3 text-sm text-slate-500" data-testid="rotation-empty">
            {t(locale, 'fuel.rotations.empty', 'Aucune rotation pour le moment.')}
          </p>
        ) : (
          <div className="mt-3 overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead>
                <tr className="border-b border-slate-200 text-xs font-bold uppercase tracking-wide text-slate-500">
                  <th className="px-3 py-2">{t(locale, 'fuel.rotations.name', 'Nom')}</th>
                  <th className="px-3 py-2">{t(locale, 'fuel.rotations.station', 'Station')}</th>
                  <th className="px-3 py-2">{t(locale, 'fuel.rotations.startTime', 'Début')}</th>
                  <th className="px-3 py-2">{t(locale, 'fuel.rotations.endTime', 'Fin')}</th>
                  <th className="px-3 py-2">{t(locale, 'fuel.rotations.assignments', 'Affectations')}</th>
                  <th className="px-3 py-2" />
                </tr>
              </thead>
              <tbody>
                {shifts.map((shift) => (
                  <tr key={shift.id} className="border-b border-slate-100" data-testid={`rotation-row-${shift.id}`}>
                    <td className="px-3 py-2 font-semibold text-slate-800">{shift.name}</td>
                    <td className="px-3 py-2 text-slate-600">{stationName(shift.station_id)}</td>
                    <td className="px-3 py-2 text-slate-600">{(shift.start_time ?? '—').slice(0, 5)}</td>
                    <td className="px-3 py-2 text-slate-600">{(shift.end_time ?? '—').slice(0, 5)}</td>
                    <td className="px-3 py-2 text-slate-600">{shift.assignments_count ?? 0}</td>
                    <td className="px-3 py-2">
                      <div className="flex justify-end gap-2">
                        <button
                          type="button"
                          onClick={() => startEdit(shift)}
                          className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:bg-slate-50"
                        >
                          {t(locale, 'fuel.common.edit', 'Modifier')}
                        </button>
                        <button
                          type="button"
                          onClick={() => void remove(shift)}
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
