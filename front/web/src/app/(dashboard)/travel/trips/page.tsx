'use client';

/**
 * TravelManager (BC-24, #7635) — gestion des voyages de l'espace gérant :
 * liste + recherche multi-filtres (`GET /travel/trips`,
 * `GET /travel/trips/search`), création/édition d'un trajet daté
 * (payload `StoreTravelTripRequest` : code, route_id, carrier_id?,
 * vehicle_id?, departure/arrival date+time, means_of_transport,
 * total_seats), tarifs par classe (`/travel/trips/{t}/prices`),
 * publication/annulation (`POST /travel/trips/{t}/publish|cancel`, motif
 * obligatoire 3..500) et manifeste passagers (`GET /travel/trips/{t}/manifest`)
 * en panneau modal.
 */
import { useCallback, useEffect, useMemo, useState } from 'react';
import { CalendarClock, X } from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import { readApiError, type TravelCrudOption } from '@/components/travel/TravelCrudTable';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale, type AppLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

/** `TravelTripPriceResource` — tarif par classe (unités mineures). */
type TravelTripPrice = {
  id: number;
  trip_id: number;
  class_id: number;
  adult_price_minor: number;
  child_price_minor: number | null;
  currency: string;
};

/** `TravelTripResource` — trajet daté (prices/route chargés en liste). */
type TravelTrip = {
  id: number;
  code: string;
  route_id: number;
  carrier_id: number | null;
  vehicle_id: number | null;
  departure_date: string;
  departure_time: string;
  arrival_date: string;
  arrival_time: string;
  means_of_transport: string;
  total_seats: number;
  status: string;
  published_at: string | null;
  prices?: TravelTripPrice[];
};

/** `TravelPassengerResource` — ligne du manifeste (RGPD : pas de n° de pièce). */
type TravelPassenger = {
  id: number;
  booking_id: number;
  full_name: string;
  birth_date: string | null;
  age_category: string;
  class_id: number | null;
  seat_number: string | null;
  unit_price_minor: number;
};

type TravelRouteRef = { id: number; code: string; origin_city_id: number; destination_city_id: number };
type TravelCarrierRef = { id: number; name: string };
type TravelVehicleRef = { id: number; code: string; registration_number: string | null };
type TravelClassRef = { id: number; code: string; label: string };
type TravelCityRef = { id: number; name: string };

type TripFormState = {
  code: string;
  route_id: string;
  carrier_id: string;
  vehicle_id: string;
  departure_date: string;
  departure_time: string;
  arrival_date: string;
  arrival_time: string;
  means_of_transport: string;
  total_seats: string;
};

const EMPTY_TRIP_FORM: TripFormState = {
  code: '',
  route_id: '',
  carrier_id: '',
  vehicle_id: '',
  departure_date: '',
  departure_time: '',
  arrival_date: '',
  arrival_time: '',
  means_of_transport: 'bus',
  total_seats: '',
};

type PriceFormState = { class_id: string; adult_price_minor: string; child_price_minor: string; currency: string };

const EMPTY_PRICE_FORM: PriceFormState = { class_id: '', adult_price_minor: '', child_price_minor: '', currency: 'XOF' };

const MEANS = ['bus', 'train', 'plane', 'boat'] as const;
const TRIP_STATUSES = ['draft', 'scheduled', 'published', 'cancelled'] as const;

/** Normalise `HH:MM[:SS]` vers le format `H:i` attendu par le backend. */
function toHourMinute(value: string): string {
  return value.length > 5 ? value.slice(0, 5) : value;
}

function meansLabel(locale: AppLocale, means: string): string {
  return t(locale, `travel.means.${means}`, means);
}

function statusLabel(locale: AppLocale, status: string): string {
  return t(locale, `travel.tripStatus.${status}`, status);
}

function statusBadgeClass(status: string): string {
  switch (status) {
    case 'published':
      return 'bg-emerald-100 text-emerald-800';
    case 'scheduled':
      return 'bg-cyan-100 text-cyan-800';
    case 'cancelled':
      return 'bg-red-100 text-red-700';
    default:
      return 'bg-slate-100 text-slate-700';
  }
}

function ModalShell({
  title,
  subtitle,
  onClose,
  children,
  wide,
}: {
  title: string;
  subtitle?: string;
  onClose: () => void;
  children: React.ReactNode;
  wide?: boolean;
}) {
  const locale = getPreferredLocale();
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" role="dialog" aria-modal="true">
      <div className={`max-h-[85vh] w-full ${wide ? 'max-w-2xl' : 'max-w-lg'} overflow-y-auto rounded-2xl bg-white p-6 shadow-xl`}>
        <div className="mb-4 flex items-start justify-between gap-4">
          <div>
            <h3 className="text-lg font-bold text-slate-900">{title}</h3>
            {subtitle ? <p className="text-sm text-slate-500">{subtitle}</p> : null}
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
        {children}
      </div>
    </div>
  );
}

export default function TravelTripsPage() {
  const locale = getPreferredLocale();

  // Référentiels (sélecteurs du formulaire et de la recherche).
  const [routes, setRoutes] = useState<TravelRouteRef[]>([]);
  const [carriers, setCarriers] = useState<TravelCarrierRef[]>([]);
  const [vehicles, setVehicles] = useState<TravelVehicleRef[]>([]);
  const [classes, setClasses] = useState<TravelClassRef[]>([]);
  const [cities, setCities] = useState<TravelCityRef[]>([]);

  // Liste + filtres de recherche (`GET /travel/trips/search`).
  const [trips, setTrips] = useState<TravelTrip[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [filters, setFilters] = useState({ origin_city_id: '', destination_city_id: '', departure_date: '', status: '' });

  const [toast, setToast] = useState('');

  // Modales.
  const [showForm, setShowForm] = useState(false);
  const [editing, setEditing] = useState<TravelTrip | null>(null);
  const [form, setForm] = useState<TripFormState>(EMPTY_TRIP_FORM);
  const [formError, setFormError] = useState('');
  const [saving, setSaving] = useState(false);

  const [pricesTrip, setPricesTrip] = useState<TravelTrip | null>(null);
  const [cancelTrip, setCancelTrip] = useState<TravelTrip | null>(null);
  const [manifestTrip, setManifestTrip] = useState<TravelTrip | null>(null);

  const notify = useCallback((message: string) => {
    setToast(message);
    window.setTimeout(() => setToast(''), 4000);
  }, []);

  useEffect(() => {
    let cancelled = false;
    const loadReferentials = async () => {
      try {
        const [routesRes, carriersRes, vehiclesRes, classesRes, citiesRes] = await Promise.all([
          apiFetch('/travel/routes?per_page=1000'),
          apiFetch('/travel/carriers?per_page=1000'),
          apiFetch('/travel/vehicles?per_page=1000'),
          apiFetch('/travel/classes?per_page=1000'),
          apiFetch('/travel/cities?per_page=1000'),
        ]);
        if (cancelled) return;
        if (routesRes.ok) setRoutes(((await routesRes.json()) as { data?: TravelRouteRef[] }).data ?? []);
        if (carriersRes.ok) setCarriers(((await carriersRes.json()) as { data?: TravelCarrierRef[] }).data ?? []);
        if (vehiclesRes.ok) setVehicles(((await vehiclesRes.json()) as { data?: TravelVehicleRef[] }).data ?? []);
        if (classesRes.ok) setClasses(((await classesRes.json()) as { data?: TravelClassRef[] }).data ?? []);
        if (citiesRes.ok) setCities(((await citiesRes.json()) as { data?: TravelCityRef[] }).data ?? []);
      } catch {
        // Les sélecteurs restent vides ; la liste des trajets signale déjà
        // les erreurs réseau.
      }
    };
    void loadReferentials();
    return () => {
      cancelled = true;
    };
  }, []);

  const cityName = useCallback(
    (cityId: number): string => cities.find((c) => c.id === cityId)?.name ?? `#${cityId}`,
    [cities],
  );

  const routeLabel = useCallback(
    (routeId: number): string => {
      const route = routes.find((r) => r.id === routeId);
      if (!route) return `#${routeId}`;
      return `${route.code} — ${cityName(route.origin_city_id)} → ${cityName(route.destination_city_id)}`;
    },
    [routes, cityName],
  );

  const classLabel = useCallback(
    (classId: number | null): string => {
      if (classId === null) return '—';
      return classes.find((c) => c.id === classId)?.label ?? `#${classId}`;
    },
    [classes],
  );

  const hasFilters = Object.values(filters).some((v) => v !== '');

  const loadTrips = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const params = new URLSearchParams({ per_page: '200' });
      for (const [key, value] of Object.entries(filters)) {
        if (value !== '') params.set(key, value);
      }
      const endpoint = hasFilters ? '/travel/trips/search' : '/travel/trips';
      const res = await apiFetch(`${endpoint}?${params.toString()}`);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const payload = (await res.json()) as { data?: TravelTrip[] };
      setTrips(Array.isArray(payload.data) ? payload.data : []);
    } catch {
      setError(t(locale, 'travel.error.loadFailed', 'Impossible de charger les données.'));
    } finally {
      setLoading(false);
    }
  }, [filters, hasFilters, locale]);

  useEffect(() => {
    void loadTrips();
  }, [loadTrips]);

  const cityOptions = useMemo<TravelCrudOption[]>(
    () => cities.map((c) => ({ value: c.id, label: c.name })),
    [cities],
  );

  const openCreate = () => {
    setEditing(null);
    setForm(EMPTY_TRIP_FORM);
    setFormError('');
    setShowForm(true);
  };

  const openEdit = (trip: TravelTrip) => {
    setEditing(trip);
    setForm({
      code: trip.code,
      route_id: String(trip.route_id),
      carrier_id: trip.carrier_id === null ? '' : String(trip.carrier_id),
      vehicle_id: trip.vehicle_id === null ? '' : String(trip.vehicle_id),
      departure_date: trip.departure_date,
      departure_time: toHourMinute(trip.departure_time),
      arrival_date: trip.arrival_date,
      arrival_time: toHourMinute(trip.arrival_time),
      means_of_transport: trip.means_of_transport,
      total_seats: String(trip.total_seats),
    });
    setFormError('');
    setShowForm(true);
  };

  const submitTrip = async () => {
    const required: { key: keyof TripFormState; label: string }[] = [
      { key: 'code', label: t(locale, 'travel.field.code', 'Code') },
      { key: 'route_id', label: t(locale, 'travel.field.route', 'Ligne') },
      { key: 'departure_date', label: t(locale, 'travel.field.departureDate', 'Départ') },
      { key: 'departure_time', label: t(locale, 'travel.field.departureTime', 'Heure') },
      { key: 'arrival_date', label: t(locale, 'travel.field.arrivalDate', 'Arrivée') },
      { key: 'arrival_time', label: t(locale, 'travel.field.arrivalTime', "Heure d'arrivée") },
      { key: 'total_seats', label: t(locale, 'travel.field.totalSeats', 'Places') },
    ];
    const missing = required.find(({ key }) => form[key].trim() === '');
    if (missing) {
      setFormError(`${missing.label} — ${t(locale, 'travel.form.required', 'Ce champ est obligatoire.')}`);
      return;
    }
    setSaving(true);
    setFormError('');
    try {
      // Payload aligné sur Store/UpdateTravelTripRequest : ids numériques,
      // heures `H:i`, transporteur/véhicule `null` quand non renseignés.
      const payload: Record<string, unknown> = {
        code: form.code.trim(),
        route_id: Number(form.route_id),
        carrier_id: form.carrier_id === '' ? null : Number(form.carrier_id),
        vehicle_id: form.vehicle_id === '' ? null : Number(form.vehicle_id),
        departure_date: form.departure_date,
        departure_time: toHourMinute(form.departure_time),
        arrival_date: form.arrival_date,
        arrival_time: toHourMinute(form.arrival_time),
        means_of_transport: form.means_of_transport,
        total_seats: Number(form.total_seats),
      };
      const res = await apiFetch(editing ? `/travel/trips/${editing.id}` : '/travel/trips', {
        method: editing ? 'PUT' : 'POST',
        body: JSON.stringify(payload),
      });
      if (!res.ok) {
        const msg = await readApiError(res);
        throw new Error(msg ?? t(locale, 'travel.error.saveFailed', "Échec de l'enregistrement."));
      }
      setShowForm(false);
      notify(t(locale, 'travel.toast.saved', 'Enregistré.'));
      await loadTrips();
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

  const publishTrip = async (trip: TravelTrip) => {
    if (!window.confirm(t(locale, 'travel.confirm.publishTrip', 'Publier ce trajet ? Il deviendra visible à la vente.'))) {
      return;
    }
    try {
      const res = await apiFetch(`/travel/trips/${trip.id}/publish`, { method: 'POST' });
      if (!res.ok) {
        const msg = await readApiError(res);
        throw new Error(msg ?? t(locale, 'travel.error.actionFailed', "L'action a échoué."));
      }
      notify(t(locale, 'travel.toast.published', 'Trajet publié.'));
      await loadTrips();
    } catch (e) {
      window.alert(
        e instanceof Error && e.message ? e.message : t(locale, 'travel.error.actionFailed', "L'action a échoué."),
      );
    }
  };

  const deleteTrip = async (trip: TravelTrip) => {
    if (!window.confirm(t(locale, 'travel.confirm.deleteMessage', 'Supprimer définitivement cet élément ?'))) {
      return;
    }
    try {
      const res = await apiFetch(`/travel/trips/${trip.id}`, { method: 'DELETE' });
      if (!res.ok && res.status !== 204) throw new Error(`HTTP ${res.status}`);
      notify(t(locale, 'travel.toast.deleted', 'Supprimé.'));
      await loadTrips();
    } catch {
      window.alert(t(locale, 'travel.error.deleteFailed', 'Échec de la suppression.'));
    }
  };

  return (
    <ModulePageShell
      icon={CalendarClock}
      title={t(locale, 'travel.trips.title', 'Trajets datés')}
      description={t(locale, 'travel.trips.subtitle', 'Départs planifiés, tarifs par classe, publication.')}
    >
      {toast ? (
        <p className="rounded-lg bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700" role="status">
          {toast}
        </p>
      ) : null}
      {error ? (
        <div className="flex items-center justify-between gap-4 rounded-lg bg-red-50 px-4 py-3">
          <p className="text-sm font-semibold text-red-700">{error}</p>
          <button
            type="button"
            onClick={() => void loadTrips()}
            className="shrink-0 rounded-lg border border-red-200 bg-white px-3 py-1.5 text-sm font-semibold text-red-700 hover:bg-red-100"
          >
            {t(locale, 'travel.gate.retry', 'Réessayer')}
          </button>
        </div>
      ) : null}

      {/* Recherche multi-filtres (GET /travel/trips/search). */}
      <div className="flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <label className="block text-sm">
          <span className="mb-1 block font-medium text-slate-700">{t(locale, 'travel.field.origin', 'Départ')}</span>
          <select
            value={filters.origin_city_id}
            onChange={(e) => setFilters((prev) => ({ ...prev, origin_city_id: e.target.value }))}
            className="rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
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
          <span className="mb-1 block font-medium text-slate-700">{t(locale, 'travel.field.destination', 'Arrivée')}</span>
          <select
            value={filters.destination_city_id}
            onChange={(e) => setFilters((prev) => ({ ...prev, destination_city_id: e.target.value }))}
            className="rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
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
          <span className="mb-1 block font-medium text-slate-700">
            {t(locale, 'travel.field.departureDate', 'Départ')}
          </span>
          <input
            type="date"
            value={filters.departure_date}
            onChange={(e) => setFilters((prev) => ({ ...prev, departure_date: e.target.value }))}
            className="rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
          />
        </label>
        <label className="block text-sm">
          <span className="mb-1 block font-medium text-slate-700">{t(locale, 'travel.field.status', 'Statut')}</span>
          <select
            value={filters.status}
            onChange={(e) => setFilters((prev) => ({ ...prev, status: e.target.value }))}
            className="rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
          >
            <option value="">{t(locale, 'travel.form.selectPlaceholder', '— Sélectionner —')}</option>
            {TRIP_STATUSES.map((status) => (
              <option key={status} value={status}>
                {statusLabel(locale, status)}
              </option>
            ))}
          </select>
        </label>
        {hasFilters ? (
          <button
            type="button"
            onClick={() => setFilters({ origin_city_id: '', destination_city_id: '', departure_date: '', status: '' })}
            className="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
          >
            {t(locale, 'travel.bookings.reset', 'Réinitialiser')}
          </button>
        ) : null}
        <div className="ms-auto">
          <button
            type="button"
            onClick={openCreate}
            className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
          >
            {t(locale, 'travel.action.create', 'Créer')}
          </button>
        </div>
      </div>

      <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table className="min-w-full divide-y divide-slate-200 text-sm">
          <thead className="bg-slate-50">
            <tr>
              <th className="px-4 py-3 text-start font-semibold text-slate-700">{t(locale, 'travel.field.code', 'Code')}</th>
              <th className="px-4 py-3 text-start font-semibold text-slate-700">{t(locale, 'travel.field.route', 'Ligne')}</th>
              <th className="px-4 py-3 text-start font-semibold text-slate-700">{t(locale, 'travel.field.departureDate', 'Départ')}</th>
              <th className="px-4 py-3 text-start font-semibold text-slate-700">{t(locale, 'travel.field.arrivalDate', 'Arrivée')}</th>
              <th className="px-4 py-3 text-start font-semibold text-slate-700">{t(locale, 'travel.field.means', 'Moyen')}</th>
              <th className="px-4 py-3 text-start font-semibold text-slate-700">{t(locale, 'travel.field.totalSeats', 'Places')}</th>
              <th className="px-4 py-3 text-start font-semibold text-slate-700">{t(locale, 'travel.field.status', 'Statut')}</th>
              <th className="px-4 py-3 text-end font-semibold text-slate-700">{t(locale, 'travel.table.actions', 'Actions')}</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {loading ? (
              <tr>
                <td colSpan={8} className="px-4 py-8 text-center text-slate-500">
                  {t(locale, 'travel.loading', 'Chargement…')}
                </td>
              </tr>
            ) : trips.length === 0 ? (
              <tr>
                <td colSpan={8} className="px-4 py-8 text-center text-slate-500">
                  {t(locale, 'travel.table.emptyNested', 'Aucun élément.')}
                </td>
              </tr>
            ) : (
              trips.map((trip) => (
                <tr key={trip.id} className="hover:bg-slate-50">
                  <td className="px-4 py-3 font-medium text-slate-900">{trip.code}</td>
                  <td className="px-4 py-3 text-slate-700">{routeLabel(trip.route_id)}</td>
                  <td className="px-4 py-3 text-slate-700">
                    {trip.departure_date} {toHourMinute(trip.departure_time)}
                  </td>
                  <td className="px-4 py-3 text-slate-700">
                    {trip.arrival_date} {toHourMinute(trip.arrival_time)}
                  </td>
                  <td className="px-4 py-3 text-slate-700">{meansLabel(locale, trip.means_of_transport)}</td>
                  <td className="px-4 py-3 text-slate-700">{trip.total_seats}</td>
                  <td className="px-4 py-3">
                    <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${statusBadgeClass(trip.status)}`}>
                      {statusLabel(locale, trip.status)}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-end">
                    <div className="flex flex-wrap justify-end gap-3">
                      <button
                        type="button"
                        className="font-medium text-cyan-700 hover:text-cyan-800"
                        onClick={() => setPricesTrip(trip)}
                      >
                        {t(locale, 'travel.trips.prices', 'Tarifs')}
                      </button>
                      <button
                        type="button"
                        className="font-medium text-cyan-700 hover:text-cyan-800"
                        onClick={() => setManifestTrip(trip)}
                      >
                        {t(locale, 'travel.trips.manifest', 'Manifeste')}
                      </button>
                      {trip.status === 'draft' || trip.status === 'scheduled' ? (
                        <button
                          type="button"
                          className="font-medium text-emerald-700 hover:text-emerald-800"
                          onClick={() => void publishTrip(trip)}
                        >
                          {t(locale, 'travel.action.publish', 'Publier')}
                        </button>
                      ) : null}
                      {trip.status !== 'cancelled' && trip.status !== 'published' ? (
                        <button
                          type="button"
                          className="font-medium text-emerald-700 hover:text-emerald-800"
                          onClick={() => openEdit(trip)}
                        >
                          {t(locale, 'travel.action.edit', 'Modifier')}
                        </button>
                      ) : null}
                      {trip.status !== 'cancelled' ? (
                        <button
                          type="button"
                          className="font-medium text-amber-700 hover:text-amber-800"
                          onClick={() => setCancelTrip(trip)}
                        >
                          {t(locale, 'travel.action.cancelTrip', 'Annuler le trajet')}
                        </button>
                      ) : null}
                      {trip.status !== 'published' ? (
                        <button
                          type="button"
                          className="font-medium text-red-500 hover:text-red-700"
                          onClick={() => void deleteTrip(trip)}
                        >
                          {t(locale, 'travel.action.delete', 'Supprimer')}
                        </button>
                      ) : null}
                    </div>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {showForm ? (
        <ModalShell
          title={
            editing
              ? `${t(locale, 'travel.action.editTitle', 'Modifier')} — ${editing.code}`
              : t(locale, 'travel.action.createTitle', 'Créer un élément')
          }
          onClose={() => setShowForm(false)}
        >
          {formError ? <p className="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{formError}</p> : null}
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <label className="block text-sm">
              <span className="mb-1 block font-medium text-slate-700">
                {t(locale, 'travel.field.code', 'Code')} <span className="text-red-500">*</span>
              </span>
              <input
                type="text"
                maxLength={40}
                value={form.code}
                onChange={(e) => setForm((prev) => ({ ...prev, code: e.target.value }))}
                className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
              />
            </label>
            <label className="block text-sm">
              <span className="mb-1 block font-medium text-slate-700">
                {t(locale, 'travel.field.route', 'Ligne')} <span className="text-red-500">*</span>
              </span>
              <select
                value={form.route_id}
                onChange={(e) => setForm((prev) => ({ ...prev, route_id: e.target.value }))}
                className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
              >
                <option value="">{t(locale, 'travel.form.selectPlaceholder', '— Sélectionner —')}</option>
                {routes.map((route) => (
                  <option key={route.id} value={route.id}>
                    {routeLabel(route.id)}
                  </option>
                ))}
              </select>
            </label>
            <label className="block text-sm">
              <span className="mb-1 block font-medium text-slate-700">{t(locale, 'travel.field.carrier', 'Compagnie')}</span>
              <select
                value={form.carrier_id}
                onChange={(e) => setForm((prev) => ({ ...prev, carrier_id: e.target.value }))}
                className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
              >
                <option value="">{t(locale, 'travel.form.selectPlaceholder', '— Sélectionner —')}</option>
                {carriers.map((carrier) => (
                  <option key={carrier.id} value={carrier.id}>
                    {carrier.name}
                  </option>
                ))}
              </select>
            </label>
            <label className="block text-sm">
              <span className="mb-1 block font-medium text-slate-700">{t(locale, 'travel.field.vehicle', 'Véhicule')}</span>
              <select
                value={form.vehicle_id}
                onChange={(e) => setForm((prev) => ({ ...prev, vehicle_id: e.target.value }))}
                className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
              >
                <option value="">{t(locale, 'travel.form.selectPlaceholder', '— Sélectionner —')}</option>
                {vehicles.map((vehicle) => (
                  <option key={vehicle.id} value={vehicle.id}>
                    {vehicle.code}
                    {vehicle.registration_number ? ` (${vehicle.registration_number})` : ''}
                  </option>
                ))}
              </select>
            </label>
            <label className="block text-sm">
              <span className="mb-1 block font-medium text-slate-700">
                {t(locale, 'travel.field.departureDate', 'Départ')} <span className="text-red-500">*</span>
              </span>
              <input
                type="date"
                value={form.departure_date}
                onChange={(e) => setForm((prev) => ({ ...prev, departure_date: e.target.value }))}
                className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
              />
            </label>
            <label className="block text-sm">
              <span className="mb-1 block font-medium text-slate-700">
                {t(locale, 'travel.field.departureTime', 'Heure')} <span className="text-red-500">*</span>
              </span>
              <input
                type="time"
                value={form.departure_time}
                onChange={(e) => setForm((prev) => ({ ...prev, departure_time: e.target.value }))}
                className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
              />
            </label>
            <label className="block text-sm">
              <span className="mb-1 block font-medium text-slate-700">
                {t(locale, 'travel.field.arrivalDate', 'Arrivée')} <span className="text-red-500">*</span>
              </span>
              <input
                type="date"
                value={form.arrival_date}
                onChange={(e) => setForm((prev) => ({ ...prev, arrival_date: e.target.value }))}
                className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
              />
            </label>
            <label className="block text-sm">
              <span className="mb-1 block font-medium text-slate-700">
                {t(locale, 'travel.field.arrivalTime', "Heure d'arrivée")} <span className="text-red-500">*</span>
              </span>
              <input
                type="time"
                value={form.arrival_time}
                onChange={(e) => setForm((prev) => ({ ...prev, arrival_time: e.target.value }))}
                className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
              />
            </label>
            <label className="block text-sm">
              <span className="mb-1 block font-medium text-slate-700">{t(locale, 'travel.field.means', 'Moyen')}</span>
              <select
                value={form.means_of_transport}
                onChange={(e) => setForm((prev) => ({ ...prev, means_of_transport: e.target.value }))}
                className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
              >
                {MEANS.map((means) => (
                  <option key={means} value={means}>
                    {meansLabel(locale, means)}
                  </option>
                ))}
              </select>
            </label>
            <label className="block text-sm">
              <span className="mb-1 block font-medium text-slate-700">
                {t(locale, 'travel.field.totalSeats', 'Places')} <span className="text-red-500">*</span>
              </span>
              <input
                type="number"
                min={1}
                max={200}
                value={form.total_seats}
                onChange={(e) => setForm((prev) => ({ ...prev, total_seats: e.target.value }))}
                className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
              />
            </label>
          </div>
          <div className="mt-5 flex justify-end gap-2">
            <button
              type="button"
              onClick={() => setShowForm(false)}
              className="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100"
            >
              {t(locale, 'travel.action.cancel', 'Annuler')}
            </button>
            <button
              type="button"
              onClick={() => void submitTrip()}
              disabled={saving}
              className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
            >
              {saving
                ? t(locale, 'travel.form.saving', 'Enregistrement…')
                : t(locale, 'travel.action.save', 'Enregistrer')}
            </button>
          </div>
        </ModalShell>
      ) : null}

      {pricesTrip ? (
        <TripPricesPanel
          trip={pricesTrip}
          classes={classes}
          classLabel={classLabel}
          onClose={() => setPricesTrip(null)}
        />
      ) : null}

      {cancelTrip ? (
        <TripCancelPanel
          trip={cancelTrip}
          onClose={() => setCancelTrip(null)}
          onCancelled={async () => {
            setCancelTrip(null);
            notify(t(locale, 'travel.toast.tripCancelled', 'Trajet annulé.'));
            await loadTrips();
          }}
        />
      ) : null}

      {manifestTrip ? (
        <TripManifestPanel trip={manifestTrip} classLabel={classLabel} onClose={() => setManifestTrip(null)} />
      ) : null}
    </ModulePageShell>
  );
}

/**
 * Panneau modal des tarifs par classe d'un trajet
 * (`GET/POST /travel/trips/{t}/prices`, `PUT/DELETE .../prices/{p}` —
 * payload `StoreTravelTripPriceRequest` : montants en unités mineures ≥ 1,
 * devise sur 3 caractères, unicité (trajet, classe) → 409 backend).
 */
function TripPricesPanel({
  trip,
  classes,
  classLabel,
  onClose,
}: {
  trip: TravelTrip;
  classes: TravelClassRef[];
  classLabel: (classId: number | null) => string;
  onClose: () => void;
}) {
  const locale = getPreferredLocale();
  const [prices, setPrices] = useState<TravelTripPrice[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [form, setForm] = useState<PriceFormState>(EMPTY_PRICE_FORM);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const res = await apiFetch(`/travel/trips/${trip.id}/prices`);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const payload = (await res.json()) as { data?: TravelTripPrice[] };
      setPrices(Array.isArray(payload.data) ? payload.data : []);
    } catch {
      setError(t(locale, 'travel.error.loadFailed', 'Impossible de charger les données.'));
    } finally {
      setLoading(false);
    }
  }, [trip.id, locale]);

  useEffect(() => {
    void load();
  }, [load]);

  const resetForm = () => {
    setForm(EMPTY_PRICE_FORM);
    setEditingId(null);
    setFormError('');
  };

  const submitPrice = async () => {
    if (form.class_id === '' || form.adult_price_minor === '' || form.currency.trim().length !== 3) {
      setFormError(t(locale, 'travel.form.required', 'Ce champ est obligatoire.'));
      return;
    }
    setSaving(true);
    setFormError('');
    try {
      const payload: Record<string, unknown> = {
        class_id: Number(form.class_id),
        adult_price_minor: Number(form.adult_price_minor),
        child_price_minor: form.child_price_minor === '' ? null : Number(form.child_price_minor),
        currency: form.currency.trim().toUpperCase(),
      };
      const res = await apiFetch(
        editingId === null ? `/travel/trips/${trip.id}/prices` : `/travel/trips/${trip.id}/prices/${editingId}`,
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

  const editPrice = (price: TravelTripPrice) => {
    setEditingId(price.id);
    setForm({
      class_id: String(price.class_id),
      adult_price_minor: String(price.adult_price_minor),
      child_price_minor: price.child_price_minor === null ? '' : String(price.child_price_minor),
      currency: price.currency,
    });
    setFormError('');
  };

  const removePrice = async (price: TravelTripPrice) => {
    if (!window.confirm(t(locale, 'travel.confirm.deleteMessage', 'Supprimer définitivement cet élément ?'))) {
      return;
    }
    try {
      const res = await apiFetch(`/travel/trips/${trip.id}/prices/${price.id}`, { method: 'DELETE' });
      if (!res.ok && res.status !== 204) throw new Error(`HTTP ${res.status}`);
      await load();
    } catch {
      window.alert(t(locale, 'travel.error.deleteFailed', 'Échec de la suppression.'));
    }
  };

  return (
    <ModalShell
      title={`${t(locale, 'travel.trips.pricesTitle', 'Tarifs par classe')} — ${trip.code}`}
      onClose={onClose}
      wide
    >
      {error ? <p className="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p> : null}
      <div className="overflow-x-auto rounded-xl border border-slate-200">
        <table className="min-w-full divide-y divide-slate-200 text-sm">
          <thead className="bg-slate-50">
            <tr>
              <th className="px-4 py-2 text-start font-semibold text-slate-700">{t(locale, 'travel.field.class', 'Classe')}</th>
              <th className="px-4 py-2 text-start font-semibold text-slate-700">{t(locale, 'travel.field.adultPrice', 'Adulte')}</th>
              <th className="px-4 py-2 text-start font-semibold text-slate-700">{t(locale, 'travel.field.childPrice', 'Enfant')}</th>
              <th className="px-4 py-2 text-start font-semibold text-slate-700">{t(locale, 'travel.field.currency', 'Devise')}</th>
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
            ) : prices.length === 0 ? (
              <tr>
                <td colSpan={5} className="px-4 py-6 text-center text-slate-500">
                  {t(locale, 'travel.table.emptyNested', 'Aucun élément.')}
                </td>
              </tr>
            ) : (
              prices.map((price) => (
                <tr key={price.id} className="hover:bg-slate-50">
                  <td className="px-4 py-2 text-slate-700">{classLabel(price.class_id)}</td>
                  <td className="px-4 py-2 text-slate-700">{price.adult_price_minor.toLocaleString(locale)}</td>
                  <td className="px-4 py-2 text-slate-700">
                    {price.child_price_minor === null ? '—' : price.child_price_minor.toLocaleString(locale)}
                  </td>
                  <td className="px-4 py-2 text-slate-700">{price.currency}</td>
                  <td className="px-4 py-2 text-end">
                    <div className="flex justify-end gap-3">
                      <button
                        type="button"
                        className="font-medium text-emerald-700 hover:text-emerald-800"
                        onClick={() => editPrice(price)}
                      >
                        {t(locale, 'travel.action.edit', 'Modifier')}
                      </button>
                      <button
                        type="button"
                        className="font-medium text-red-500 hover:text-red-700"
                        onClick={() => void removePrice(price)}
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
            ? t(locale, 'travel.action.add', 'Ajouter')
            : t(locale, 'travel.action.editTitle', 'Modifier')}
        </h4>
        {formError ? <p className="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{formError}</p> : null}
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-4">
          <label className="block text-sm">
            <span className="mb-1 block font-medium text-slate-700">
              {t(locale, 'travel.field.class', 'Classe')} <span className="text-red-500">*</span>
            </span>
            <select
              value={form.class_id}
              onChange={(e) => setForm((prev) => ({ ...prev, class_id: e.target.value }))}
              className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
            >
              <option value="">{t(locale, 'travel.form.selectPlaceholder', '— Sélectionner —')}</option>
              {classes.map((cls) => (
                <option key={cls.id} value={cls.id}>
                  {cls.label}
                </option>
              ))}
            </select>
          </label>
          <label className="block text-sm">
            <span className="mb-1 block font-medium text-slate-700">
              {t(locale, 'travel.field.adultPrice', 'Adulte')} <span className="text-red-500">*</span>
            </span>
            <input
              type="number"
              min={1}
              value={form.adult_price_minor}
              onChange={(e) => setForm((prev) => ({ ...prev, adult_price_minor: e.target.value }))}
              className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
            />
          </label>
          <label className="block text-sm">
            <span className="mb-1 block font-medium text-slate-700">{t(locale, 'travel.field.childPrice', 'Enfant')}</span>
            <input
              type="number"
              min={1}
              value={form.child_price_minor}
              onChange={(e) => setForm((prev) => ({ ...prev, child_price_minor: e.target.value }))}
              className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
            />
          </label>
          <label className="block text-sm">
            <span className="mb-1 block font-medium text-slate-700">
              {t(locale, 'travel.field.currency', 'Devise')} <span className="text-red-500">*</span>
            </span>
            <input
              type="text"
              maxLength={3}
              value={form.currency}
              onChange={(e) => setForm((prev) => ({ ...prev, currency: e.target.value }))}
              className="w-full rounded-lg border border-slate-200 px-3 py-2 uppercase focus:border-emerald-500 focus:outline-none"
            />
          </label>
        </div>
        <p className="mt-2 text-xs text-slate-500">
          {t(locale, 'travel.form.moneyMinor', 'Montant en devises (1 = 100 minor)')}
        </p>
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
            onClick={() => void submitPrice()}
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
    </ModalShell>
  );
}

/**
 * Panneau modal d'annulation d'un trajet — `CancelTravelTripRequest` :
 * motif obligatoire (3..500 caractères, jamais de PII, audité en outbox).
 */
function TripCancelPanel({
  trip,
  onClose,
  onCancelled,
}: {
  trip: TravelTrip;
  onClose: () => void;
  onCancelled: () => Promise<void>;
}) {
  const locale = getPreferredLocale();
  const [reason, setReason] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');

  const submit = async () => {
    if (reason.trim().length < 3) {
      setError(t(locale, 'travel.bookings.reasonRequired', 'Le motif est obligatoire.'));
      return;
    }
    setSubmitting(true);
    setError('');
    try {
      const res = await apiFetch(`/travel/trips/${trip.id}/cancel`, {
        method: 'POST',
        body: JSON.stringify({ reason: reason.trim() }),
      });
      if (!res.ok) {
        const msg = await readApiError(res);
        throw new Error(msg ?? t(locale, 'travel.error.actionFailed', "L'action a échoué."));
      }
      await onCancelled();
    } catch (e) {
      setError(
        e instanceof Error && e.message ? e.message : t(locale, 'travel.error.actionFailed', "L'action a échoué."),
      );
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <ModalShell
      title={`${t(locale, 'travel.trips.cancelReasonTitle', "Motif d'annulation du trajet")} — ${trip.code}`}
      subtitle={t(locale, 'travel.confirm.cancelTrip', 'Annuler ce trajet ? Les réservations seront traitées selon les règles métier.')}
      onClose={onClose}
    >
      {error ? <p className="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p> : null}
      <label className="block text-sm">
        <span className="mb-1 block font-medium text-slate-700">
          {t(locale, 'travel.bookings.reason', 'Motif')} <span className="text-red-500">*</span>
        </span>
        <textarea
          value={reason}
          onChange={(e) => setReason(e.target.value)}
          rows={3}
          maxLength={500}
          className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
        />
      </label>
      <div className="mt-5 flex justify-end gap-2">
        <button
          type="button"
          onClick={onClose}
          className="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100"
        >
          {t(locale, 'travel.action.cancel', 'Annuler')}
        </button>
        <button
          type="button"
          onClick={() => void submit()}
          disabled={submitting}
          className="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-50"
        >
          {submitting
            ? t(locale, 'travel.form.saving', 'Enregistrement…')
            : t(locale, 'travel.action.cancelTrip', 'Annuler le trajet')}
        </button>
      </div>
    </ModalShell>
  );
}

/**
 * Panneau modal du manifeste passagers (`GET /travel/trips/{t}/manifest`) —
 * passagers triés par siège, sans donnée de pièce d'identité (RGPD).
 */
function TripManifestPanel({
  trip,
  classLabel,
  onClose,
}: {
  trip: TravelTrip;
  classLabel: (classId: number | null) => string;
  onClose: () => void;
}) {
  const locale = getPreferredLocale();
  const [passengers, setPassengers] = useState<TravelPassenger[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    let cancelled = false;
    const load = async () => {
      setLoading(true);
      setError('');
      try {
        const res = await apiFetch(`/travel/trips/${trip.id}/manifest`);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const payload = (await res.json()) as { data?: TravelPassenger[] };
        if (!cancelled) setPassengers(Array.isArray(payload.data) ? payload.data : []);
      } catch {
        if (!cancelled) setError(t(locale, 'travel.error.loadFailed', 'Impossible de charger les données.'));
      } finally {
        if (!cancelled) setLoading(false);
      }
    };
    void load();
    return () => {
      cancelled = true;
    };
  }, [trip.id, locale]);

  return (
    <ModalShell
      title={`${t(locale, 'travel.trips.manifestTitle', 'Manifeste des passagers')} — ${trip.code}`}
      subtitle={`${trip.departure_date} ${toHourMinute(trip.departure_time)}`}
      onClose={onClose}
      wide
    >
      {error ? <p className="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p> : null}
      <div className="overflow-x-auto rounded-xl border border-slate-200">
        <table className="min-w-full divide-y divide-slate-200 text-sm">
          <thead className="bg-slate-50">
            <tr>
              <th className="px-4 py-2 text-start font-semibold text-slate-700">{t(locale, 'travel.field.seat', 'Siège')}</th>
              <th className="px-4 py-2 text-start font-semibold text-slate-700">{t(locale, 'travel.field.fullName', 'Nom complet')}</th>
              <th className="px-4 py-2 text-start font-semibold text-slate-700">{t(locale, 'travel.field.ageCategory', 'Catégorie')}</th>
              <th className="px-4 py-2 text-start font-semibold text-slate-700">{t(locale, 'travel.field.class', 'Classe')}</th>
              <th className="px-4 py-2 text-start font-semibold text-slate-700">{t(locale, 'travel.field.unitPrice', 'Prix unitaire')}</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {loading ? (
              <tr>
                <td colSpan={5} className="px-4 py-6 text-center text-slate-500">
                  {t(locale, 'travel.loading', 'Chargement…')}
                </td>
              </tr>
            ) : passengers.length === 0 ? (
              <tr>
                <td colSpan={5} className="px-4 py-6 text-center text-slate-500">
                  {t(locale, 'travel.checkin.emptyManifest', 'Aucun passager sur ce trajet.')}
                </td>
              </tr>
            ) : (
              passengers.map((passenger) => (
                <tr key={passenger.id} className="hover:bg-slate-50">
                  <td className="px-4 py-2 text-slate-700">{passenger.seat_number ?? '—'}</td>
                  <td className="px-4 py-2 font-medium text-slate-900">{passenger.full_name}</td>
                  <td className="px-4 py-2 text-slate-700">
                    {t(locale, `travel.ageCategory.${passenger.age_category}`, passenger.age_category)}
                  </td>
                  <td className="px-4 py-2 text-slate-700">{classLabel(passenger.class_id)}</td>
                  <td className="px-4 py-2 text-slate-700">{passenger.unit_price_minor.toLocaleString(locale)}</td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </ModalShell>
  );
}
