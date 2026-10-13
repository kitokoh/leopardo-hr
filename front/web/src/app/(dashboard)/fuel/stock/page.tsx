'use client';

/**
 * BC-15 Fuel — stock & carburants (FUEL-009, #5803).
 *
 * Surface web du stock : niveau de cuve par produit
 * (`GET /fuel-station/stock/level?station_id=&product_code=`), entrées de
 * stock (`GET/POST /fuel-station/stock-entries`) et dernières entrées.
 */
import { useCallback, useEffect, useState } from 'react';
import { PackageSearch } from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

type StationOption = { id: number; name: string };
type StockEntry = {
  id: number;
  station_id: number | null;
  product_code: string;
  quantity: number | string;
  unit_cost?: number | string | null;
  entry_type?: string | null;
  reference?: string | null;
  entry_date?: string | null;
};

function uuid(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID();
  }
  return `fuel-stock-${Date.now()}-${Math.random().toString(36).slice(2, 12)}`;
}

export default function FuelStockPage() {
  const locale = getPreferredLocale();
  const [stations, setStations] = useState<StationOption[]>([]);
  const [entries, setEntries] = useState<StockEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const [levelStation, setLevelStation] = useState('');
  const [levelProduct, setLevelProduct] = useState('diesel');
  const [level, setLevel] = useState<string | null>(null);

  const [formStation, setFormStation] = useState('');
  const [formProduct, setFormProduct] = useState('');
  const [formQuantity, setFormQuantity] = useState('');
  const [formUnitCost, setFormUnitCost] = useState('');
  const [formEntryType, setFormEntryType] = useState('purchase');
  const [formReason, setFormReason] = useState('');
  const [formError, setFormError] = useState('');
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const [stationsRes, entriesRes] = await Promise.all([
        apiFetch('/fuel-station/stations?per_page=200'),
        apiFetch('/fuel-station/stock-entries?per_page=100'),
      ]);
      const stationsPayload = stationsRes.ok ? ((await stationsRes.json()) as { data?: StationOption[] }) : {};
      const entriesPayload = entriesRes.ok ? ((await entriesRes.json()) as { data?: StockEntry[] }) : {};
      setStations(Array.isArray(stationsPayload.data) ? stationsPayload.data : []);
      setEntries(Array.isArray(entriesPayload.data) ? entriesPayload.data : []);
    } catch {
      setError(t(locale, 'fuel.stock.loadError', 'Impossible de charger le stock.'));
    } finally {
      setLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    void load();
  }, [load]);

  const lookupLevel = async () => {
    if (levelProduct.trim() === '') {
      setError(t(locale, 'fuel.stock.productRequired', 'Renseignez un code produit pour consulter le niveau.'));
      return;
    }
    setError('');
    setLevel(null);
    try {
      const params = new URLSearchParams({ product_code: levelProduct.trim() });
      if (levelStation !== '') {
        params.set('station_id', levelStation);
      }
      const res = await apiFetch(`/fuel-station/stock/level?${params.toString()}`);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const payload = (await res.json()) as { data?: { level_litres?: number | string } };
      setLevel(String(payload.data?.level_litres ?? 0));
    } catch {
      setError(t(locale, 'fuel.stock.levelError', 'Impossible de récupérer le niveau de cuve.'));
    }
  };

  const submitEntry = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setFormError('');
    if (formProduct.trim() === '' || formQuantity.trim() === '') {
      setFormError(t(locale, 'fuel.stock.formIncomplete', 'Renseignez le produit et la quantité.'));
      return;
    }
    if (formEntryType === 'adjustment' && formReason.trim() === '') {
      setFormError(t(locale, 'fuel.stock.reasonRequired', 'Un ajustement exige un motif.'));
      return;
    }
    setSaving(true);
    const body = JSON.stringify({
      station_id: formStation === '' ? null : Number(formStation),
      product_code: formProduct.trim(),
      quantity: Number(formQuantity),
      unit_cost: formUnitCost.trim() === '' ? null : Number(formUnitCost),
      entry_type: formEntryType,
      reason: formReason.trim() || null,
      idempotency_key: uuid(),
    });
    try {
      const res = await apiFetch('/fuel-station/stock-entries', { method: 'POST', body });
      if (!res.ok) {
        const payload = (await res.json().catch(() => ({}))) as { message?: string };
        setFormError(payload.message ?? t(locale, 'fuel.stock.saveFailed', "L'enregistrement a échoué."));
        return;
      }
      setFormProduct('');
      setFormQuantity('');
      setFormUnitCost('');
      setFormReason('');
      await load();
    } catch {
      setFormError(t(locale, 'fuel.stock.saveFailed', "L'enregistrement a échoué."));
    } finally {
      setSaving(false);
    }
  };

  const stationName = (id: number | null): string =>
    id === null ? '—' : stations.find((station) => station.id === id)?.name ?? `#${id}`;

  const inputClass =
    'w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-emerald-400 focus:outline-none';

  return (
    <ModulePageShell
      icon={PackageSearch}
      title={t(locale, 'fuel.stock.title', 'Stock & carburants')}
      description={t(locale, 'fuel.stock.subtitle', 'Niveaux de cuve, entrées de stock et mouvements.')}
    >
      {error ? (
        <p role="alert" className="rounded-lg bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
          {error}
        </p>
      ) : null}

      <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 className="text-sm font-bold uppercase tracking-wide text-slate-500">
          {t(locale, 'fuel.stock.levelTitle', 'Niveau de cuve')}
        </h2>
        <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <label className="block text-sm">
            <span className="mb-1 block font-semibold text-slate-700">{t(locale, 'fuel.stock.station', 'Station')}</span>
            <select value={levelStation} onChange={(e) => setLevelStation(e.target.value)} className={inputClass}>
              <option value="">{t(locale, 'fuel.rotations.stationAny', 'Toutes')}</option>
              {stations.map((station) => (
                <option key={station.id} value={station.id}>
                  {station.name}
                </option>
              ))}
            </select>
          </label>
          <label className="block text-sm">
            <span className="mb-1 block font-semibold text-slate-700">{t(locale, 'fuel.stock.product', 'Produit')}</span>
            <input
              type="text"
              value={levelProduct}
              onChange={(e) => setLevelProduct(e.target.value)}
              placeholder="diesel"
              className={inputClass}
            />
          </label>
          <div className="flex items-end">
            <button
              type="button"
              onClick={() => void lookupLevel()}
              className="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-black text-white transition hover:bg-emerald-700"
            >
              {t(locale, 'fuel.stock.consult', 'Consulter')}
            </button>
          </div>
          <div className="flex items-end">
            <p className="text-sm text-slate-700" data-testid="stock-level">
              {level === null ? '—' : `${level} L`}
            </p>
          </div>
        </div>
      </section>

      <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 className="text-sm font-bold uppercase tracking-wide text-slate-500">
          {t(locale, 'fuel.stock.createTitle', 'Entrée de stock')}
        </h2>
        <form onSubmit={submitEntry} className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <label className="block text-sm">
            <span className="mb-1 block font-semibold text-slate-700">{t(locale, 'fuel.stock.station', 'Station')}</span>
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
            <span className="mb-1 block font-semibold text-slate-700">{t(locale, 'fuel.stock.product', 'Produit')}</span>
            <input
              type="text"
              value={formProduct}
              onChange={(e) => setFormProduct(e.target.value)}
              placeholder="diesel"
              className={inputClass}
              data-testid="stock-product"
            />
          </label>
          <label className="block text-sm">
            <span className="mb-1 block font-semibold text-slate-700">{t(locale, 'fuel.stock.quantity', 'Quantité (L)')}</span>
            <input
              type="number"
              min={0}
              step="0.001"
              value={formQuantity}
              onChange={(e) => setFormQuantity(e.target.value)}
              className={inputClass}
              data-testid="stock-quantity"
            />
          </label>
          <label className="block text-sm">
            <span className="mb-1 block font-semibold text-slate-700">{t(locale, 'fuel.stock.unitCost', 'Coût unitaire')}</span>
            <input
              type="number"
              min={0}
              step="0.01"
              value={formUnitCost}
              onChange={(e) => setFormUnitCost(e.target.value)}
              className={inputClass}
            />
          </label>
          <label className="block text-sm">
            <span className="mb-1 block font-semibold text-slate-700">{t(locale, 'fuel.stock.entryType', 'Type')}</span>
            <select value={formEntryType} onChange={(e) => setFormEntryType(e.target.value)} className={inputClass}>
              <option value="purchase">{t(locale, 'fuel.stock.typePurchase', 'Achat')}</option>
              <option value="delivery">{t(locale, 'fuel.stock.typeDelivery', 'Livraison')}</option>
              <option value="adjustment">{t(locale, 'fuel.stock.typeAdjustment', 'Ajustement')}</option>
            </select>
          </label>
          <label className="block text-sm">
            <span className="mb-1 block font-semibold text-slate-700">{t(locale, 'fuel.stock.reason', 'Motif')}</span>
            <input
              type="text"
              value={formReason}
              onChange={(e) => setFormReason(e.target.value)}
              maxLength={255}
              className={inputClass}
            />
          </label>
          <div className="flex items-end sm:col-span-2 lg:col-span-3">
            <button
              type="submit"
              disabled={saving}
              data-testid="stock-submit"
              className="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-black text-white transition hover:bg-emerald-700 disabled:opacity-50"
            >
              {saving ? t(locale, 'fuel.stock.saving', 'Enregistrement…') : t(locale, 'fuel.stock.save', 'Enregistrer')}
            </button>
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
          {t(locale, 'fuel.stock.listTitle', 'Dernières entrées')}
        </h2>
        {loading ? (
          <p className="mt-3 text-sm text-slate-500">{t(locale, 'fuel.common.loading', 'Chargement…')}</p>
        ) : entries.length === 0 ? (
          <p className="mt-3 text-sm text-slate-500" data-testid="stock-empty">
            {t(locale, 'fuel.stock.empty', 'Aucune entrée de stock pour le moment.')}
          </p>
        ) : (
          <div className="mt-3 overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead>
                <tr className="border-b border-slate-200 text-xs font-bold uppercase tracking-wide text-slate-500">
                  <th className="px-3 py-2">{t(locale, 'fuel.stock.product', 'Produit')}</th>
                  <th className="px-3 py-2">{t(locale, 'fuel.stock.station', 'Station')}</th>
                  <th className="px-3 py-2">{t(locale, 'fuel.stock.quantity', 'Quantité (L)')}</th>
                  <th className="px-3 py-2">{t(locale, 'fuel.stock.entryType', 'Type')}</th>
                  <th className="px-3 py-2">{t(locale, 'fuel.stock.date', 'Date')}</th>
                </tr>
              </thead>
              <tbody>
                {entries.map((entry) => (
                  <tr key={entry.id} className="border-b border-slate-100" data-testid={`stock-row-${entry.id}`}>
                    <td className="px-3 py-2 font-semibold text-slate-800">{entry.product_code}</td>
                    <td className="px-3 py-2 text-slate-600">{stationName(entry.station_id)}</td>
                    <td className="px-3 py-2 text-slate-600">{entry.quantity}</td>
                    <td className="px-3 py-2 text-slate-600">{entry.entry_type ?? '—'}</td>
                    <td className="px-3 py-2 text-slate-600">{entry.entry_date ?? '—'}</td>
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
