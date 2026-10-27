'use client';

/**
 * HOSP-007 (#7949) — inventaire : types de chambres et unites physiques.
 *
 * Les deux sont rattaches a un ETABLISSEMENT, d'ou le selecteur. Le type de
 * chambre decrit ce qui est vendu (capacite, tarif), l'unite est l'objet
 * physique (chambre 101). Une reservation porte sur un TYPE, l'affectation
 * physique se fait a l'arrivee — c'est pourquoi les deux listes sont
 * presentees separement et non fusionnees.
 */
import { useCallback, useEffect, useState } from 'react';
import { Boxes, Loader2, AlertTriangle, Plus } from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

type Property = { id: number; name: string };
type RoomType = { id: number; name: string; capacity: number | null; base_price_minor: number | null };
type Unit = { id: number; name: string; room_type_id: number | null; floor: string | null };

export default function HospitalityInventoryPage() {
  const locale = getPreferredLocale();
  const [properties, setProperties] = useState<Property[]>([]);
  const [propertyId, setPropertyId] = useState<number | null>(null);
  const [roomTypes, setRoomTypes] = useState<RoomType[]>([]);
  const [units, setUnits] = useState<Unit[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [actionError, setActionError] = useState('');
  const [saving, setSaving] = useState(false);
  const [rtName, setRtName] = useState('');
  const [rtCapacity, setRtCapacity] = useState('2');
  const [rtPrice, setRtPrice] = useState('');
  const [unitName, setUnitName] = useState('');
  const [unitFloor, setUnitFloor] = useState('');

  const loadProperties = useCallback(async () => {
    try {
      const res = await apiFetch('/hospitality/properties');
      const payload = res.ok ? ((await res.json()) as { data?: Property[] }) : {};
      const list = Array.isArray(payload.data) ? payload.data : [];
      setProperties(list);
      if (list.length > 0) setPropertyId((current) => current ?? list[0].id);
    } catch {
      setError(t(locale, 'hospitality.properties.loadError', 'Impossible de charger les établissements.'));
    }
  }, [locale]);

  const loadInventory = useCallback(async () => {
    if (propertyId === null) return;
    setLoading(true);
    setError('');
    try {
      const [rtRes, unitRes] = await Promise.all([
        apiFetch(`/hospitality/properties/${propertyId}/room-types`),
        apiFetch(`/hospitality/properties/${propertyId}/units`),
      ]);
      const rtPayload = rtRes.ok ? ((await rtRes.json()) as { data?: RoomType[] }) : {};
      const unitPayload = unitRes.ok ? ((await unitRes.json()) as { data?: Unit[] }) : {};
      setRoomTypes(Array.isArray(rtPayload.data) ? rtPayload.data : []);
      setUnits(Array.isArray(unitPayload.data) ? unitPayload.data : []);
    } catch {
      setError(t(locale, 'hospitality.inventory.loadError', 'Impossible de charger l’inventaire.'));
    } finally {
      setLoading(false);
    }
  }, [locale, propertyId]);

  useEffect(() => {
    void loadProperties();
  }, [loadProperties]);

  useEffect(() => {
    void loadInventory();
  }, [loadInventory]);

  async function createRoomType(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (propertyId === null) return;
    setActionError('');
    if (rtName.trim() === '') {
      setActionError(t(locale, 'hospitality.inventory.nameRequired', 'Le nom du type de chambre est obligatoire.'));
      return;
    }
    setSaving(true);
    try {
      const res = await apiFetch(`/hospitality/properties/${propertyId}/room-types`, {
        method: 'POST',
        body: JSON.stringify({
          name: rtName.trim(),
          capacity: Number.parseInt(rtCapacity, 10) || null,
          base_price_minor: rtPrice.trim() === '' ? null : Math.round(Number.parseFloat(rtPrice) * 100),
        }),
      });
      if (!res.ok) {
        setActionError(t(locale, 'hospitality.inventory.createError', 'Création refusée par l’API.'));
        return;
      }
      setRtName('');
      setRtPrice('');
      await loadInventory();
    } finally {
      setSaving(false);
    }
  }

  async function createUnit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (propertyId === null) return;
    setActionError('');
    if (unitName.trim() === '') {
      setActionError(t(locale, 'hospitality.inventory.unitNameRequired', 'Le nom de l’unité est obligatoire.'));
      return;
    }
    setSaving(true);
    try {
      const res = await apiFetch(`/hospitality/properties/${propertyId}/units`, {
        method: 'POST',
        body: JSON.stringify({
          name: unitName.trim(),
          floor: unitFloor.trim() === '' ? null : unitFloor.trim(),
        }),
      });
      if (!res.ok) {
        setActionError(t(locale, 'hospitality.inventory.createError', 'Création refusée par l’API.'));
        return;
      }
      setUnitName('');
      setUnitFloor('');
      await loadInventory();
    } finally {
      setSaving(false);
    }
  }

  return (
    <ModulePageShell
      title={t(locale, 'hospitality.inventory.title', 'Inventaire')}
      subtitle={t(
        locale,
        'hospitality.inventory.subtitle',
        'Types de chambres (ce qui est vendu) et unités physiques (ce qui existe).',
      )}
      icon={Boxes}
    >
      {error !== '' ? (
        <div role="alert" className="flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
          <AlertTriangle className="h-4 w-4" />
          {error}
        </div>
      ) : null}

      <label className="flex flex-col gap-1 text-sm text-neutral-700">
        {t(locale, 'hospitality.common.property', 'Établissement')}
        <select
          value={propertyId ?? ''}
          onChange={(e) => setPropertyId(e.target.value === '' ? null : Number(e.target.value))}
          className="max-w-sm rounded-lg border border-neutral-300 px-3 py-2 text-sm"
        >
          {properties.length === 0 ? (
            <option value="">{t(locale, 'hospitality.common.noProperty', 'Aucun établissement')}</option>
          ) : null}
          {properties.map((p) => (
            <option key={p.id} value={p.id}>
              {p.name}
            </option>
          ))}
        </select>
      </label>

      {properties.length === 0 ? (
        <div className="rounded-xl border border-neutral-200 bg-white p-6 text-sm text-neutral-600">
          {t(
            locale,
            'hospitality.inventory.noPropertyBody',
            'Créez d’abord un établissement dans le référentiel.',
          )}
        </div>
      ) : null}

      {actionError !== '' ? (
        <div role="alert" className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
          {actionError}
        </div>
      ) : null}

      {loading ? (
        <div className="flex items-center gap-2 rounded-xl border border-neutral-200 bg-white p-4 text-sm text-neutral-600">
          <Loader2 className="h-4 w-4 animate-spin" />
          {t(locale, 'common.loading', 'Chargement…')}
        </div>
      ) : null}

      {propertyId !== null ? (
        <div className="grid gap-4 lg:grid-cols-2">
          <section className="rounded-xl border border-neutral-200 bg-white p-4">
            <h2 className="text-sm font-medium text-neutral-900">
              {t(locale, 'hospitality.inventory.roomTypes', 'Types de chambres')}
            </h2>
            <ul className="mt-3 space-y-2 text-sm">
              {roomTypes.length === 0 ? (
                <li className="text-neutral-500">
                  {t(locale, 'hospitality.inventory.emptyRoomTypes', 'Aucun type de chambre.')}
                </li>
              ) : null}
              {roomTypes.map((rt) => (
                <li key={rt.id} className="flex items-center justify-between border-b border-neutral-100 pb-2">
                  <span className="text-neutral-900">{rt.name}</span>
                  <span className="text-neutral-500">
                    {rt.capacity !== null ? `${rt.capacity} ${t(locale, 'hospitality.inventory.pax', 'pers.')}` : '—'}
                    {rt.base_price_minor !== null ? ` · ${(rt.base_price_minor / 100).toFixed(2)}` : ''}
                  </span>
                </li>
              ))}
            </ul>
            <form onSubmit={createRoomType} className="mt-4 grid gap-2 sm:grid-cols-3">
              <input
                value={rtName}
                onChange={(e) => setRtName(e.target.value)}
                placeholder={t(locale, 'hospitality.inventory.field.roomTypeName', 'Nom (ex. Double)')}
                className="rounded-lg border border-neutral-300 px-3 py-2 text-sm"
                required
              />
              <input
                value={rtCapacity}
                onChange={(e) => setRtCapacity(e.target.value)}
                type="number"
                min={1}
                placeholder={t(locale, 'hospitality.inventory.field.capacity', 'Capacité')}
                className="rounded-lg border border-neutral-300 px-3 py-2 text-sm"
              />
              <input
                value={rtPrice}
                onChange={(e) => setRtPrice(e.target.value)}
                type="number"
                min={0}
                step="0.01"
                placeholder={t(locale, 'hospitality.inventory.field.price', 'Tarif')}
                className="rounded-lg border border-neutral-300 px-3 py-2 text-sm"
              />
              <button
                type="submit"
                disabled={saving}
                className="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white disabled:opacity-60 sm:col-span-3"
              >
                <Plus className="h-4 w-4" />
                {t(locale, 'hospitality.inventory.addRoomType', 'Ajouter un type')}
              </button>
            </form>
          </section>

          <section className="rounded-xl border border-neutral-200 bg-white p-4">
            <h2 className="text-sm font-medium text-neutral-900">
              {t(locale, 'hospitality.inventory.units', 'Unités physiques')}
            </h2>
            <ul className="mt-3 space-y-2 text-sm">
              {units.length === 0 ? (
                <li className="text-neutral-500">
                  {t(locale, 'hospitality.inventory.emptyUnits', 'Aucune unité physique.')}
                </li>
              ) : null}
              {units.map((unit) => (
                <li key={unit.id} className="flex items-center justify-between border-b border-neutral-100 pb-2">
                  <span className="text-neutral-900">{unit.name}</span>
                  <span className="text-neutral-500">{unit.floor ?? '—'}</span>
                </li>
              ))}
            </ul>
            <form onSubmit={createUnit} className="mt-4 grid gap-2 sm:grid-cols-2">
              <input
                value={unitName}
                onChange={(e) => setUnitName(e.target.value)}
                placeholder={t(locale, 'hospitality.inventory.field.unitName', 'Nom (ex. 101)')}
                className="rounded-lg border border-neutral-300 px-3 py-2 text-sm"
                required
              />
              <input
                value={unitFloor}
                onChange={(e) => setUnitFloor(e.target.value)}
                placeholder={t(locale, 'hospitality.inventory.field.floor', 'Étage')}
                className="rounded-lg border border-neutral-300 px-3 py-2 text-sm"
              />
              <button
                type="submit"
                disabled={saving}
                className="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white disabled:opacity-60 sm:col-span-2"
              >
                <Plus className="h-4 w-4" />
                {t(locale, 'hospitality.inventory.addUnit', 'Ajouter une unité')}
              </button>
            </form>
          </section>
        </div>
      ) : null}
    </ModulePageShell>
  );
}
