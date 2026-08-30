'use client';

/**
 * RESTO-706 (#6219) — UI admin : écran stock & achats.
 * Niveaux de stock + alertes de seuil, bons de commande fournisseurs
 * (draft → send → receive) et réceptions — API `/restaurant/stock-levels`,
 * `/restaurant/stock/alerts`, `/restaurant/purchase-orders`,
 * `/restaurant/receivings`.
 */
import { useCallback, useEffect, useState } from 'react';
import { PackageSearch } from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

type StockLevel = { id: number; branch_id: number; ingredient_id: number; quantity: string; avg_cost_minor: number | null; alert_threshold: string | null; is_below_threshold: boolean };
type PurchaseOrder = { id: number; reference: string; supplier_id: number; status: string; total_minor: number | null; expected_at: string | null };

export default function RestaurantStockPage() {
  const locale = getPreferredLocale();
  const [tab, setTab] = useState<'levels' | 'purchaseOrders' | 'receivings'>('levels');
  const [levels, setLevels] = useState<StockLevel[]>([]);
  const [orders, setOrders] = useState<PurchaseOrder[]>([]);
  const [receivings, setReceivings] = useState<unknown[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [poForm, setPoForm] = useState({ branch_id: '', supplier_id: '', quantity: '', unit_price_minor: '', ingredient_id: '' });
  const [rcvForm, setRcvForm] = useState({ branch_id: '', ingredient_id: '', quantity: '', unit_price_minor: '' });

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const [l, o, r] = await Promise.all([
        apiFetch('/restaurant/stock-levels?per_page=200'),
        apiFetch('/restaurant/purchase-orders?per_page=100'),
        apiFetch('/restaurant/receivings?per_page=100'),
      ]);
      const parse = async (res: Response) => (res.ok ? ((await res.json()) as { data?: unknown[] }) : { data: [] });
      const [l2, o2, r2] = await Promise.all([parse(l), parse(o), parse(r)]);
      setLevels((l2.data ?? []) as StockLevel[]);
      setOrders((o2.data ?? []) as PurchaseOrder[]);
      setReceivings(r2.data ?? []);
    } catch {
      setError(t(locale, 'restaurant.stock.loadError', 'Impossible de charger les données de stock.'));
    } finally {
      setLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    void load();
  }, [load]);

  const createPo = async () => {
    setError('');
    try {
      const res = await apiFetch('/restaurant/purchase-orders', {
        method: 'POST',
        body: JSON.stringify({
          branch_id: Number(poForm.branch_id),
          supplier_id: Number(poForm.supplier_id),
          items: [{ ingredient_id: Number(poForm.ingredient_id), quantity: Number(poForm.quantity), unit_price_minor: Number(poForm.unit_price_minor) }],
        }),
      });
      if (!res.ok) {
        const payload = await res.json().catch(() => ({}));
        throw new Error((payload as { message?: string }).message ?? `HTTP ${res.status}`);
      }
      setPoForm({ branch_id: '', supplier_id: '', quantity: '', unit_price_minor: '', ingredient_id: '' });
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : t(locale, 'restaurant.stock.poError', 'Erreur de création du bon.'));
    }
  };

  const poAction = async (id: number, action: 'send' | 'receive') => {
    setError('');
    try {
      let body: string | undefined;
      if (action === 'receive') {
        // Réception complète : on reprend les lignes du bon (quantités identiques).
        const detail = await apiFetch(`/restaurant/purchase-orders/${id}`);
        if (!detail.ok) throw new Error(`HTTP ${detail.status}`);
        const payload = (await detail.json()) as { data?: { items?: { ingredient_id: number; quantity: string; unit_price_minor: number }[] } };
        const items = (payload.data?.items ?? []).map((it) => ({
          ingredient_id: it.ingredient_id,
          quantity: Number(it.quantity),
          unit_price_minor: it.unit_price_minor,
        }));
        if (items.length === 0) throw new Error('Aucune ligne à réceptionner.');
        body = JSON.stringify({ items });
      }
      const res = await apiFetch(`/restaurant/purchase-orders/${id}/${action}`, {
        method: 'POST',
        body,
      });
      if (!res.ok) {
        const payload = await res.json().catch(() => ({}));
        throw new Error((payload as { message?: string }).message ?? `HTTP ${res.status}`);
      }
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : t(locale, 'restaurant.stock.actionError', 'Action impossible sur le bon.'));
    }
  };

  const createReceiving = async () => {
    setError('');
    try {
      const res = await apiFetch('/restaurant/receivings', {
        method: 'POST',
        body: JSON.stringify({
          branch_id: Number(rcvForm.branch_id),
          items: [{ ingredient_id: Number(rcvForm.ingredient_id), quantity: Number(rcvForm.quantity), unit_price_minor: Number(rcvForm.unit_price_minor) }],
        }),
      });
      if (!res.ok) {
        const payload = await res.json().catch(() => ({}));
        throw new Error((payload as { message?: string }).message ?? `HTTP ${res.status}`);
      }
      setRcvForm({ branch_id: '', ingredient_id: '', quantity: '', unit_price_minor: '' });
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : t(locale, 'restaurant.stock.rcvError', 'Erreur de réception.'));
    }
  };

  const tabs = [
    { key: 'levels' as const, label: t(locale, 'restaurant.stock.tabLevels', 'Niveaux & alertes') },
    { key: 'purchaseOrders' as const, label: t(locale, 'restaurant.stock.tabPO', 'Bons de commande') },
    { key: 'receivings' as const, label: t(locale, 'restaurant.stock.tabReceivings', 'Réceptions') },
  ];

  return (
    <ModulePageShell icon={PackageSearch} title={t(locale, 'restaurant.stock.title', 'Stock & achats')} description={t(locale, 'restaurant.stock.subtitle', 'Niveaux, alertes, bons de commande, réceptions')}>
      {error ? <p className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{error}</p> : null}

      <div className="flex flex-wrap gap-2">
        {tabs.map((tb) => (
          <button key={tb.key} type="button" onClick={() => setTab(tb.key)} className={`rounded-lg px-3 py-1.5 text-sm font-medium ${tab === tb.key ? 'bg-emerald-600 text-white' : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50'}`}>
            {tb.label}
          </button>
        ))}
      </div>

      {tab === 'levels' ? (
        <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
          <table className="min-w-full divide-y divide-slate-200 text-sm">
            <thead className="bg-slate-50">
              <tr>
                <th className="px-4 py-3 text-left font-semibold text-slate-700">Branche</th>
                <th className="px-4 py-3 text-left font-semibold text-slate-700">Ingrédient</th>
                <th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'restaurant.stock.colQty', 'Quantité')}</th>
                <th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'restaurant.stock.colCost', 'Coût moyen')}</th>
                <th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'restaurant.stock.colThreshold', 'Seuil')}</th>
                <th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'restaurant.stock.colAlert', 'Alerte')}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {loading ? <tr><td colSpan={6} className="px-4 py-8 text-center text-slate-500">{t(locale, 'restaurant.stock.loading', 'Chargement...')}</td></tr>
                : levels.map((lv) => (
                  <tr key={lv.id} className="hover:bg-slate-50">
                    <td className="px-4 py-3">{lv.branch_id}</td>
                    <td className="px-4 py-3">{lv.ingredient_id}</td>
                    <td className="px-4 py-3">{lv.quantity}</td>
                    <td className="px-4 py-3">{lv.avg_cost_minor ?? '—'}</td>
                    <td className="px-4 py-3">{lv.alert_threshold ?? '—'}</td>
                    <td className="px-4 py-3">{lv.is_below_threshold ? <span className="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">{t(locale, 'restaurant.stock.alert', 'Seuil atteint')}</span> : <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">{t(locale, 'restaurant.stock.ok', 'OK')}</span>}</td>
                  </tr>
                ))}
            </tbody>
          </table>
        </div>
      ) : null}

      {tab === 'purchaseOrders' ? (
        <div className="space-y-4">
          <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 className="font-bold text-slate-900">{t(locale, 'restaurant.stock.newPO', 'Nouveau bon de commande')}</h3>
            <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-5">
              <input className="rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Branche ID" value={poForm.branch_id} onChange={(e) => setPoForm({ ...poForm, branch_id: e.target.value })} aria-label="Branche" />
              <input className="rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Fournisseur ID" value={poForm.supplier_id} onChange={(e) => setPoForm({ ...poForm, supplier_id: e.target.value })} aria-label="Fournisseur" />
              <input className="rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Ingrédient ID" value={poForm.ingredient_id} onChange={(e) => setPoForm({ ...poForm, ingredient_id: e.target.value })} aria-label="Ingrédient" />
              <input type="number" className="rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Quantité" value={poForm.quantity} onChange={(e) => setPoForm({ ...poForm, quantity: e.target.value })} aria-label="Quantité" />
              <input type="number" className="rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Prix unitaire (minor)" value={poForm.unit_price_minor} onChange={(e) => setPoForm({ ...poForm, unit_price_minor: e.target.value })} aria-label="Prix" />
            </div>
            <button type="button" onClick={() => void createPo()} className="mt-3 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
              {t(locale, 'restaurant.stock.createPO', 'Créer le bon')}
            </button>
          </div>

          <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table className="min-w-full divide-y divide-slate-200 text-sm">
              <thead className="bg-slate-50">
                <tr>
                  <th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'restaurant.stock.colRef', 'Référence')}</th>
                  <th className="px-4 py-3 text-left font-semibold text-slate-700">Fournisseur</th>
                  <th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'restaurant.stock.colStatus', 'Statut')}</th>
                  <th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'restaurant.stock.colTotal', 'Total')}</th>
                  <th className="px-4 py-3 text-right font-semibold text-slate-700" aria-label="Actions" />
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {orders.map((po) => (
                  <tr key={po.id} className="hover:bg-slate-50">
                    <td className="px-4 py-3 font-medium text-slate-900">{po.reference}</td>
                    <td className="px-4 py-3">{po.supplier_id}</td>
                    <td className="px-4 py-3">{po.status}</td>
                    <td className="px-4 py-3">{po.total_minor ?? '—'}</td>
                    <td className="px-4 py-3">
                      <div className="flex justify-end gap-2 text-xs font-medium">
                        {po.status === 'draft' ? <button className="text-blue-600 hover:underline" onClick={() => void poAction(po.id, 'send')}>{t(locale, 'restaurant.stock.send', 'Envoyer')}</button> : null}
                        {po.status === 'sent' ? <button className="text-emerald-600 hover:underline" onClick={() => void poAction(po.id, 'receive')}>{t(locale, 'restaurant.stock.receive', 'Réceptionner')}</button> : null}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      ) : null}

      {tab === 'receivings' ? (
        <div className="space-y-4">
          <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 className="font-bold text-slate-900">{t(locale, 'restaurant.stock.newReceiving', 'Nouvelle réception')}</h3>
            <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-4">
              <input className="rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Branche ID" value={rcvForm.branch_id} onChange={(e) => setRcvForm({ ...rcvForm, branch_id: e.target.value })} aria-label="Branche" />
              <input className="rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Ingrédient ID" value={rcvForm.ingredient_id} onChange={(e) => setRcvForm({ ...rcvForm, ingredient_id: e.target.value })} aria-label="Ingrédient" />
              <input type="number" className="rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Quantité" value={rcvForm.quantity} onChange={(e) => setRcvForm({ ...rcvForm, quantity: e.target.value })} aria-label="Quantité" />
              <input type="number" className="rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Prix unitaire (minor)" value={rcvForm.unit_price_minor} onChange={(e) => setRcvForm({ ...rcvForm, unit_price_minor: e.target.value })} aria-label="Prix" />
            </div>
            <button type="button" onClick={() => void createReceiving()} className="mt-3 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
              {t(locale, 'restaurant.stock.createReceiving', 'Réceptionner')}
            </button>
          </div>

          <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table className="min-w-full divide-y divide-slate-200 text-sm">
              <thead className="bg-slate-50">
                <tr>
                  <th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'restaurant.stock.colRef', 'Référence')}</th>
                  <th className="px-4 py-3 text-left font-semibold text-slate-700">Branche</th>
                  <th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'restaurant.stock.colDate', 'Date')}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {receivings.map((r) => {
                  const row = r as { id: number; reference: string; branch_id: number; received_at: string | null };
                  return (
                    <tr key={row.id} className="hover:bg-slate-50">
                      <td className="px-4 py-3 font-medium text-slate-900">{row.reference}</td>
                      <td className="px-4 py-3">{row.branch_id}</td>
                      <td className="px-4 py-3">{row.received_at ? new Date(row.received_at).toLocaleString(locale) : '—'}</td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </div>
      ) : null}
    </ModulePageShell>
  );
}
