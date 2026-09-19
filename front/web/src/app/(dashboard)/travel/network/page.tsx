'use client';

/**
 * TravelManager (BC-24, #7634) — référentiel réseau de l'espace gérant :
 * gares/terminaux (`/travel/stations`), bureaux de vente (`/travel/offices`),
 * lignes ville→ville (`/travel/routes`) et arrêts ordonnés
 * (`/travel/routes/{r}/stops`). Les sélecteurs villes s'appuient sur
 * `GET /travel/cities` (référentiel seedé, filtrable par pays via
 * `GET /travel/countries`).
 *
 * Payloads alignés sur les FormRequests backend (Store/UpdateTravelStation,
 * Office, Route, RouteStop) : champs `nullable` envoyés à `null` quand vidés,
 * champs `sometimes` omis quand vides, ids numériques.
 */
import { useCallback, useEffect, useMemo, useState } from 'react';
import { Map as MapIcon, X } from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import {
  TravelCrudTable,
  buildCrudPayload,
  readApiError,
  type TravelCrudConfig,
  type TravelCrudField,
  type TravelCrudOption,
} from '@/components/travel/TravelCrudTable';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale, type AppLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

/** `TravelCountryResource` — référentiel pays du tenant. */
type TravelCountry = {
  id: number;
  iso2: string;
  iso3: string;
  name: string;
  phone_code: string | null;
  status: string;
};

/** `TravelCityResource` — référentiel villes du tenant. */
type TravelCity = {
  id: number;
  country_iso2: string;
  name: string;
  region: string | null;
  status: string;
};

/** `TravelRouteStopResource` — arrêt ordonné d'une ligne. */
type TravelRouteStop = {
  id: number;
  route_id: number;
  city_id: number;
  rank: number;
  is_stopover: boolean;
  min_duration_min: number | null;
};

type StopFormState = {
  city_id: string;
  rank: string;
  is_stopover: boolean;
  min_duration_min: string;
};

const EMPTY_STOP_FORM: StopFormState = { city_id: '', rank: '', is_stopover: false, min_duration_min: '' };

function statusOptions(locale: AppLocale): TravelCrudOption[] {
  return [
    { value: 'active', label: t(locale, 'travel.status.active', 'Actif') },
    { value: 'disabled', label: t(locale, 'travel.status.disabled', 'Désactivé') },
  ];
}

/**
 * Panneau modal de gestion des arrêts ordonnés d'une ligne
 * (`GET/POST /travel/routes/{r}/stops`, `PUT/DELETE .../stops/{s}`).
 */
function RouteStopsPanel({
  route,
  cityOptions,
  cityName,
  onClose,
}: {
  route: Record<string, unknown>;
  cityOptions: TravelCrudOption[];
  cityName: (cityId: unknown) => string;
  onClose: () => void;
}) {
  const locale = getPreferredLocale();
  const routeId = String(route.id);
  const [stops, setStops] = useState<TravelRouteStop[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [form, setForm] = useState<StopFormState>(EMPTY_STOP_FORM);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState('');

  const stopFields: TravelCrudField[] = useMemo(
    () => [
      { name: 'city_id', label: t(locale, 'travel.field.city', 'Ville'), type: 'select', numeric: true, required: true },
      { name: 'rank', label: t(locale, 'travel.field.rank', 'Rang'), type: 'number', min: 1 },
      { name: 'is_stopover', label: t(locale, 'travel.field.isStopover', 'Escale'), type: 'checkbox' },
      { name: 'min_duration_min', label: t(locale, 'travel.field.minDurationMin', 'Min (min)'), type: 'number', min: 1, nullable: true },
    ],
    [locale],
  );

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const res = await apiFetch(`/travel/routes/${routeId}/stops`);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const payload = (await res.json()) as { data?: TravelRouteStop[] };
      setStops(Array.isArray(payload.data) ? payload.data : []);
    } catch {
      setError(t(locale, 'travel.error.loadFailed', 'Impossible de charger les données.'));
    } finally {
      setLoading(false);
    }
  }, [routeId, locale]);

  useEffect(() => {
    void load();
  }, [load]);

  const resetForm = () => {
    setForm(EMPTY_STOP_FORM);
    setEditingId(null);
    setFormError('');
  };

  const submitStop = async () => {
    if (form.city_id === '') {
      setFormError(
        `${t(locale, 'travel.field.city', 'Ville')} — ${t(locale, 'travel.form.required', 'Ce champ est obligatoire.')}`,
      );
      return;
    }
    setSaving(true);
    setFormError('');
    try {
      const payload = buildCrudPayload(stopFields, form as unknown as Record<string, unknown>);
      const res = await apiFetch(
        editingId === null ? `/travel/routes/${routeId}/stops` : `/travel/routes/${routeId}/stops/${editingId}`,
        { method: editingId === null ? 'POST' : 'PUT', body: JSON.stringify(payload) },
      );
      if (!res.ok) {
        const msg = await readApiError(res);
        throw new Error(msg ?? t(locale, 'travel.error.saveFailed', "Échec de l'enregistrement."));
      }
      resetForm();
      await load();
    } catch (e) {
      setFormError(
        e instanceof Error && e.message
          ? e.message
          : t(locale, 'travel.error.saveFailed', "Échec de l'enregistrement."),
      );
    } finally {
      setSaving(false);
    }
  };

  const editStop = (stop: TravelRouteStop) => {
    setEditingId(stop.id);
    setForm({
      city_id: String(stop.city_id),
      rank: String(stop.rank),
      is_stopover: stop.is_stopover,
      min_duration_min: stop.min_duration_min === null ? '' : String(stop.min_duration_min),
    });
    setFormError('');
  };

  const removeStop = async (stop: TravelRouteStop) => {
    if (!window.confirm(t(locale, 'travel.confirm.deleteMessage', 'Supprimer définitivement cet élément ?'))) {
      return;
    }
    try {
      const res = await apiFetch(`/travel/routes/${routeId}/stops/${stop.id}`, { method: 'DELETE' });
      if (!res.ok && res.status !== 204) throw new Error(`HTTP ${res.status}`);
      await load();
    } catch {
      window.alert(t(locale, 'travel.error.deleteFailed', 'Échec de la suppression.'));
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" role="dialog" aria-modal="true">
      <div className="max-h-[85vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
        <div className="mb-4 flex items-start justify-between gap-4">
          <div>
            <h3 className="text-lg font-bold text-slate-900">
              {t(locale, 'travel.routes.stopsTitle', 'Étapes de la ligne')} — {String(route.code ?? route.id)}
            </h3>
            <p className="text-sm text-slate-500">
              {cityName(route.origin_city_id)} → {cityName(route.destination_city_id)}
            </p>
          </div>
          <button
            type="button"
            onClick={onClose}
            aria-label={t(locale, 'travel.action.cancel', 'Annuler')}
            className="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {error ? <p className="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p> : null}

        <div className="overflow-x-auto rounded-xl border border-slate-200">
          <table className="min-w-full divide-y divide-slate-200 text-sm">
            <thead className="bg-slate-50">
              <tr>
                <th className="px-4 py-2 text-start font-semibold text-slate-700">{t(locale, 'travel.field.rank', 'Rang')}</th>
                <th className="px-4 py-2 text-start font-semibold text-slate-700">{t(locale, 'travel.field.city', 'Ville')}</th>
                <th className="px-4 py-2 text-start font-semibold text-slate-700">{t(locale, 'travel.field.isStopover', 'Escale')}</th>
                <th className="px-4 py-2 text-start font-semibold text-slate-700">{t(locale, 'travel.field.minDurationMin', 'Min (min)')}</th>
                <th className="px-4 py-2 text-end font-semibold text-slate-700">{t(locale, 'travel.table.actions', 'Actions')}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {loading ? (
                <tr>
                  <td colSpan={5} className="px-4 py-6 text-center text-slate-500">
                    {t(locale, 'travel.loading', 'Chargement…')}
                  </td>
                </tr>
              ) : stops.length === 0 ? (
                <tr>
                  <td colSpan={5} className="px-4 py-6 text-center text-slate-500">
                    {t(locale, 'travel.table.emptyNested', 'Aucun élément.')}
                  </td>
                </tr>
              ) : (
                stops.map((stop) => (
                  <tr key={stop.id} className="hover:bg-slate-50">
                    <td className="px-4 py-2 text-slate-700">{stop.rank}</td>
                    <td className="px-4 py-2 text-slate-700">{cityName(stop.city_id)}</td>
                    <td className="px-4 py-2 text-slate-700">{stop.is_stopover ? '✓' : '✗'}</td>
                    <td className="px-4 py-2 text-slate-700">{stop.min_duration_min ?? '—'}</td>
                    <td className="px-4 py-2 text-end">
                      <div className="flex justify-end gap-3">
                        <button
                          type="button"
                          className="font-medium text-emerald-700 hover:text-emerald-800"
                          onClick={() => editStop(stop)}
                        >
                          {t(locale, 'travel.action.edit', 'Modifier')}
                        </button>
                        <button
                          type="button"
                          className="font-medium text-red-500 hover:text-red-700"
                          onClick={() => void removeStop(stop)}
                        >
                          {t(locale, 'travel.action.delete', 'Supprimer')}
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        <div className="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
          <h4 className="mb-3 text-sm font-bold text-slate-800">
            {editingId === null
              ? t(locale, 'travel.routes.addStop', 'Ajouter une étape')
              : t(locale, 'travel.action.editTitle', 'Modifier')}
          </h4>
          {formError ? <p className="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{formError}</p> : null}
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-4">
            <label className="block text-sm sm:col-span-2">
              <span className="mb-1 block font-medium text-slate-700">
                {t(locale, 'travel.field.city', 'Ville')} <span className="text-red-500">*</span>
              </span>
              <select
                value={form.city_id}
                onChange={(e) => setForm((prev) => ({ ...prev, city_id: e.target.value }))}
                className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
              >
                <option value="">{t(locale, 'travel.form.selectPlaceholder', '— Sélectionner —')}</option>
                {cityOptions.map((opt) => (
                  <option key={String(opt.value)} value={String(opt.value)}>
                    {opt.label}
                  </option>
                ))}
              </select>
            </label>
            <label className="block text-sm">
              <span className="mb-1 block font-medium text-slate-700">{t(locale, 'travel.field.rank', 'Rang')}</span>
              <input
                type="number"
                min={1}
                value={form.rank}
                onChange={(e) => setForm((prev) => ({ ...prev, rank: e.target.value }))}
                className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
              />
            </label>
            <label className="block text-sm">
              <span className="mb-1 block font-medium text-slate-700">
                {t(locale, 'travel.field.minDurationMin', 'Min (min)')}
              </span>
              <input
                type="number"
                min={1}
                value={form.min_duration_min}
                onChange={(e) => setForm((prev) => ({ ...prev, min_duration_min: e.target.value }))}
                className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
              />
            </label>
          </div>
          <label className="mt-3 inline-flex items-center gap-2 text-sm text-slate-700">
            <input
              type="checkbox"
              checked={form.is_stopover}
              onChange={(e) => setForm((prev) => ({ ...prev, is_stopover: e.target.checked }))}
              className="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
            />
            {t(locale, 'travel.field.isStopover', 'Escale')}
          </label>
          <div className="mt-4 flex justify-end gap-2">
            {editingId !== null ? (
              <button
                type="button"
                onClick={resetForm}
                className="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100"
              >
                {t(locale, 'travel.action.cancel', 'Annuler')}
              </button>
            ) : null}
            <button
              type="button"
              onClick={() => void submitStop()}
              disabled={saving}
              className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
            >
              {saving
                ? t(locale, 'travel.form.saving', 'Enregistrement…')
                : editingId === null
                  ? t(locale, 'travel.action.add', 'Ajouter')
                  : t(locale, 'travel.action.save', 'Enregistrer')}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

export default function TravelNetworkPage() {
  const locale = getPreferredLocale();
  const [tab, setTab] = useState<'stations' | 'offices' | 'routes'>('stations');
  const [cities, setCities] = useState<TravelCity[]>([]);
  const [countries, setCountries] = useState<TravelCountry[]>([]);
  const [citiesError, setCitiesError] = useState('');
  const [stopsRoute, setStopsRoute] = useState<Record<string, unknown> | null>(null);

  useEffect(() => {
    let cancelled = false;
    const loadReferential = async () => {
      try {
        const [citiesRes, countriesRes] = await Promise.all([
          apiFetch('/travel/cities?per_page=1000'),
          apiFetch('/travel/countries?per_page=1000'),
        ]);
        if (!citiesRes.ok || !countriesRes.ok) throw new Error('HTTP error');
        const citiesPayload = (await citiesRes.json()) as { data?: TravelCity[] };
        const countriesPayload = (await countriesRes.json()) as { data?: TravelCountry[] };
        if (!cancelled) {
          setCities(Array.isArray(citiesPayload.data) ? citiesPayload.data : []);
          setCountries(Array.isArray(countriesPayload.data) ? countriesPayload.data : []);
        }
      } catch {
        if (!cancelled) {
          setCitiesError(t(locale, 'travel.error.loadFailed', 'Impossible de charger les données.'));
        }
      }
    };
    void loadReferential();
    return () => {
      cancelled = true;
    };
  }, [locale]);

  const cityOptions = useMemo<TravelCrudOption[]>(() => {
    const countryByIso2 = new Map(countries.map((c) => [c.iso2, c.name]));
    return cities.map((c) => ({
      value: c.id,
      label: `${c.name} (${countryByIso2.get(c.country_iso2) ?? c.country_iso2})`,
    }));
  }, [cities, countries]);

  const cityName = useCallback(
    (cityId: unknown): string => {
      const city = cities.find((c) => c.id === Number(cityId));
      return city ? city.name : cityId === null || cityId === undefined ? '—' : `#${String(cityId)}`;
    },
    [cities],
  );

  const configs = useMemo<Record<'stations' | 'offices' | 'routes', TravelCrudConfig>>(() => {
    const STATUSES = statusOptions(locale);
    return {
      // Store/UpdateTravelStationRequest — code, name, city_id, address,
      // contact_phone, timezone, is_terminal, status.
      stations: {
        endpoint: '/travel/stations',
        listQuery: 'per_page=500',
        title: t(locale, 'travel.referentiel.stations', 'Stations / terminaux'),
        searchPlaceholder: t(locale, 'travel.search.station', 'Rechercher une station…'),
        searchKeys: ['code', 'name', 'address'],
        columns: [
          { key: 'code', label: t(locale, 'travel.field.code', 'Code') },
          { key: 'name', label: t(locale, 'travel.field.name', 'Nom') },
          { key: 'city_id', label: t(locale, 'travel.field.city', 'Ville'), render: (row) => cityName(row.city_id) },
          { key: 'contact_phone', label: t(locale, 'travel.field.contactPhone', 'Téléphone') },
          { key: 'is_terminal', label: t(locale, 'travel.field.isTerminal', 'Terminal (grande gare)') },
          { key: 'status', label: t(locale, 'travel.field.status', 'Statut') },
        ],
        fields: [
          { name: 'code', label: t(locale, 'travel.field.code', 'Code'), type: 'text', required: true, maxLength: 40 },
          { name: 'name', label: t(locale, 'travel.field.name', 'Nom'), type: 'text', required: true, maxLength: 120 },
          { name: 'city_id', label: t(locale, 'travel.field.city', 'Ville'), type: 'select', numeric: true, required: true, options: cityOptions },
          { name: 'address', label: t(locale, 'travel.field.address', 'Adresse'), type: 'text', nullable: true, maxLength: 255 },
          { name: 'contact_phone', label: t(locale, 'travel.field.contactPhone', 'Téléphone'), type: 'text', nullable: true, maxLength: 40 },
          { name: 'timezone', label: t(locale, 'travel.field.timezone', 'Fuseau'), type: 'text', hint: 'Africa/Lome' },
          { name: 'is_terminal', label: t(locale, 'travel.field.isTerminal', 'Terminal (grande gare)'), type: 'checkbox' },
          { name: 'status', label: t(locale, 'travel.field.status', 'Statut'), type: 'select', options: STATUSES },
        ],
      },
      // Store/UpdateTravelOfficeRequest — name, city_id, address,
      // contact_phone, status.
      offices: {
        endpoint: '/travel/offices',
        listQuery: 'per_page=500',
        title: t(locale, 'travel.referentiel.offices', 'Bureaux de vente'),
        searchPlaceholder: t(locale, 'travel.search.office', 'Rechercher un bureau…'),
        searchKeys: ['name', 'address', 'contact_phone'],
        columns: [
          { key: 'name', label: t(locale, 'travel.field.name', 'Nom') },
          { key: 'city_id', label: t(locale, 'travel.field.city', 'Ville'), render: (row) => cityName(row.city_id) },
          { key: 'address', label: t(locale, 'travel.field.address', 'Adresse') },
          { key: 'contact_phone', label: t(locale, 'travel.field.contactPhone', 'Téléphone') },
          { key: 'status', label: t(locale, 'travel.field.status', 'Statut') },
        ],
        fields: [
          { name: 'name', label: t(locale, 'travel.field.name', 'Nom'), type: 'text', required: true, maxLength: 120 },
          { name: 'city_id', label: t(locale, 'travel.field.city', 'Ville'), type: 'select', numeric: true, required: true, options: cityOptions },
          { name: 'address', label: t(locale, 'travel.field.address', 'Adresse'), type: 'text', nullable: true, maxLength: 255 },
          { name: 'contact_phone', label: t(locale, 'travel.field.contactPhone', 'Téléphone'), type: 'text', nullable: true, maxLength: 40 },
          { name: 'status', label: t(locale, 'travel.field.status', 'Statut'), type: 'select', options: STATUSES },
        ],
      },
      // Store/UpdateTravelRouteRequest — code, origin_city_id,
      // destination_city_id (différentes), distance_km, duration_min, status.
      routes: {
        endpoint: '/travel/routes',
        listQuery: 'per_page=500',
        title: t(locale, 'travel.routes.title', 'Lignes & itinéraires'),
        searchPlaceholder: t(locale, 'travel.search.route', 'Rechercher une ligne…'),
        searchKeys: ['code'],
        columns: [
          { key: 'code', label: t(locale, 'travel.field.code', 'Code') },
          { key: 'origin_city_id', label: t(locale, 'travel.field.origin', 'Départ'), render: (row) => cityName(row.origin_city_id) },
          { key: 'destination_city_id', label: t(locale, 'travel.field.destination', 'Arrivée'), render: (row) => cityName(row.destination_city_id) },
          { key: 'distance_km', label: t(locale, 'travel.field.distanceKm', 'Distance (km)') },
          { key: 'duration_min', label: t(locale, 'travel.field.durationMin', 'Durée (min)') },
          { key: 'status', label: t(locale, 'travel.field.status', 'Statut') },
        ],
        fields: [
          { name: 'code', label: t(locale, 'travel.field.code', 'Code'), type: 'text', required: true, maxLength: 40 },
          { name: 'origin_city_id', label: t(locale, 'travel.field.origin', 'Départ'), type: 'select', numeric: true, required: true, options: cityOptions },
          { name: 'destination_city_id', label: t(locale, 'travel.field.destination', 'Arrivée'), type: 'select', numeric: true, required: true, options: cityOptions },
          { name: 'distance_km', label: t(locale, 'travel.field.distanceKm', 'Distance (km)'), type: 'number', min: 0, max: 20000, nullable: true },
          { name: 'duration_min', label: t(locale, 'travel.field.durationMin', 'Durée (min)'), type: 'number', min: 1, max: 100000, nullable: true },
          { name: 'status', label: t(locale, 'travel.field.status', 'Statut'), type: 'select', options: STATUSES },
        ],
        rowActions: [
          {
            key: 'stops',
            label: t(locale, 'travel.routes.stops', 'Étapes'),
            onClick: (row) => setStopsRoute(row),
          },
        ],
      },
    };
  }, [locale, cityOptions, cityName]);

  const tabs = [
    { key: 'stations' as const, label: t(locale, 'travel.referentiel.tabStations', 'Stations') },
    { key: 'offices' as const, label: t(locale, 'travel.referentiel.tabOffices', 'Bureaux') },
    { key: 'routes' as const, label: t(locale, 'travel.tab.routes', 'Routes & Trajets') },
  ];

  return (
    <ModulePageShell
      icon={MapIcon}
      title={t(locale, 'travel.home.network', 'Réseau')}
      description={t(locale, 'travel.home.networkDesc', 'Gares, bureaux, lignes et arrêts ordonnés.')}
    >
      {citiesError ? (
        <p className="rounded-lg bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{citiesError}</p>
      ) : null}

      <div className="flex flex-wrap gap-2">
        {tabs.map((tb) => (
          <button
            key={tb.key}
            type="button"
            onClick={() => setTab(tb.key)}
            className={`rounded-lg px-3 py-1.5 text-sm font-medium ${
              tab === tb.key
                ? 'bg-emerald-600 text-white'
                : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50'
            }`}
          >
            {tb.label}
          </button>
        ))}
      </div>

      <TravelCrudTable key={tab} config={configs[tab]} />

      {stopsRoute ? (
        <RouteStopsPanel
          route={stopsRoute}
          cityOptions={cityOptions}
          cityName={cityName}
          onClose={() => setStopsRoute(null)}
        />
      ) : null}
    </ModulePageShell>
  );
}
