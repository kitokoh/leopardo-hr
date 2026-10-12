'use client';

/**
 * RESTO-706 (#6219) — UI admin : écran livraison & fidélité.
 * Zones de livraison, livreurs, cycle de livraison, points fidélité et
 * promotions — API `/restaurant/delivery-zones`, `/restaurant/delivery-riders`,
 * `/restaurant/deliveries`, `/restaurant/loyalty-*`, `/restaurant/promotions`.
 */
import { useCallback, useEffect, useState } from 'react';
import { Bike } from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import { useRestaurantBranches } from '@/components/restaurant/BranchSelect';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

type Delivery = { id: number; order_id: number; rider_id: number | null; status: string; fee_minor: number };
type Rider = { id: number; name: string; phone: string | null; vehicle_code: string | null; is_active: boolean };
type OrderOption = { id: number; reference?: string | null; order_type?: string | null; status?: string | null };

export default function RestaurantDeliveryPage() {
  const locale = getPreferredLocale();
  // RESTO-904 (#7749) : noms de branches résolus via la liste partagée
  // (plus d'ID brut affiché), et suppression du champ branch_id mort du
  // formulaire de création (l'API livraisons ne l'accepte pas).
  const { branches } = useRestaurantBranches();
  const [tab, setTab] = useState<'zones' | 'riders' | 'deliveries' | 'loyalty' | 'promotions'>('zones');
  const [zones, setZones] = useState<unknown[]>([]);
  const [riders, setRiders] = useState<Rider[]>([]);
  const [deliveries, setDeliveries] = useState<Delivery[]>([]);
  const [loyalty, setLoyalty] = useState<unknown[]>([]);
  const [promotions, setPromotions] = useState<unknown[]>([]);
  // RESTO-ONBOARDING — plus de saisie d'identifiants techniques : la commande
  // et le livreur viennent de listes déroulantes alimentées par l'API.
  const [orders, setOrders] = useState<OrderOption[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [form, setForm] = useState({ order_id: '', fee_minor: '' });
  const [assigning, setAssigning] = useState<{ deliveryId: number; riderId: string } | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const res = await Promise.all([
        apiFetch('/restaurant/delivery-zones?per_page=100'),
        apiFetch('/restaurant/delivery-riders?per_page=100'),
        apiFetch('/restaurant/deliveries?per_page=100'),
        apiFetch('/restaurant/loyalty-customers?per_page=100'),
        apiFetch('/restaurant/promotions?per_page=100'),
        apiFetch('/restaurant/orders?per_page=200'),
      ]);
      const parse = async (r: Response) => (r.ok ? ((await r.json()) as { data?: unknown[] }) : { data: [] });
      const [z, ri, d, l, p, o] = await Promise.all(res.map(parse));
      setZones(z.data ?? []);
      setRiders((ri.data ?? []) as Rider[]);
      setDeliveries((d.data ?? []) as Delivery[]);
      setLoyalty(l.data ?? []);
      setPromotions(p.data ?? []);
      setOrders((o.data ?? []) as OrderOption[]);
    } catch {
      setError(t(locale, 'restaurant.del.loadError'));
    } finally {
      setLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    void load();
  }, [load]);

  const createDelivery = async () => {
    setError('');
    try {
      const res = await apiFetch('/restaurant/deliveries', {
        method: 'POST',
        body: JSON.stringify({ order_id: Number(form.order_id), fee_minor: Number(form.fee_minor) }),
      });
      if (!res.ok) {
        const payload = await res.json().catch(() => ({}));
        throw new Error((payload as { message?: string }).message ?? `HTTP ${res.status}`);
      }
      setForm({ order_id: '', fee_minor: '' });
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : t(locale, 'restaurant.del.createError'));
    }
  };

  const transition = async (id: number, action: string, riderId?: number) => {
    setError('');
    try {
      let body: string | undefined;
      if (action === 'assign') {
        if (!riderId) return;
        body = JSON.stringify({ rider_id: riderId });
      }
      const res = await apiFetch(`/restaurant/deliveries/${id}/${action}`, { method: 'POST', body });
      if (!res.ok) {
        const payload = await res.json().catch(() => ({}));
        throw new Error((payload as { message?: string }).message ?? `HTTP ${res.status}`);
      }
      setAssigning(null);
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : t(locale, 'restaurant.del.actionError', 'Action impossible.'));
    }
  };

  // RESTO-ONBOARDING — libellés lisibles (plus d'ID brut affiché).
  const riderName = (id: number | null) =>
    id === null ? '—' : riders.find((r) => r.id === id)?.name ?? `#${id}`;

  const orderLabel = (id: number) => {
    const order = orders.find((o) => o.id === id);
    if (!order) return `#${id}`;
    return order.reference ? `${order.reference} (#${id})` : `#${id}`;
  };

  const tabs = [
    { key: 'zones' as const, label: t(locale, 'restaurant.del.tabZones', 'Zones') },
    { key: 'riders' as const, label: t(locale, 'restaurant.del.tabRiders', 'Livreurs') },
    { key: 'deliveries' as const, label: t(locale, 'restaurant.del.tabDeliveries', 'Livraisons') },
    { key: 'loyalty' as const, label: t(locale, 'restaurant.del.tabLoyalty') },
    { key: 'promotions' as const, label: t(locale, 'restaurant.del.tabPromos', 'Promotions') },
  ];

  return (
    <ModulePageShell icon={Bike} title={t(locale, 'restaurant.del.title')} description={t(locale, 'restaurant.del.subtitle')}>
      {error ? <p className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{error}</p> : null}

      <div className="flex flex-wrap gap-2">
        {tabs.map((tb) => (
          <button key={tb.key} type="button" onClick={() => setTab(tb.key)} className={`rounded-lg px-3 py-1.5 text-sm font-medium ${tab === tb.key ? 'bg-emerald-600 text-white' : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50'}`}>
            {tb.label}
          </button>
        ))}
      </div>

      {tab === 'zones' ? (
        <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
          <table className="min-w-full divide-y divide-slate-200 text-sm">
            <thead className="bg-slate-50"><tr><th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'restaurant.del.colName', 'Nom')}</th><th className="px-4 py-3 text-left font-semibold text-slate-700">Branche</th><th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'restaurant.del.colFees', 'Frais & minimum')}</th></tr></thead>
            <tbody className="divide-y divide-slate-100">
              {zones.map((z) => {
                const row = z as { id: number; name: string; branch_id: number; fee_minor: number; min_order_minor: number | null; status: string };
                const branchName = branches.find((b) => b.id === row.branch_id)?.name ?? `#${row.branch_id}`;
                return (
                  <tr key={row.id} className="hover:bg-slate-50">
                    <td className="px-4 py-3 font-medium text-slate-900">{row.name}</td>
                    <td className="px-4 py-3">{branchName}</td>
                    <td className="px-4 py-3">{t(locale, 'restaurant.del.fee', 'Frais')}: {row.fee_minor} · min {row.min_order_minor ?? '—'} · {row.status}</td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      ) : null}

      {tab === 'riders' ? (
        <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
          <table className="min-w-full divide-y divide-slate-200 text-sm">
            <thead className="bg-slate-50"><tr><th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'restaurant.del.colName', 'Nom')}</th><th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'restaurant.del.colPhone')}</th><th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'restaurant.del.colVehicle')}</th><th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'restaurant.del.colActive', 'Actif')}</th></tr></thead>
            <tbody className="divide-y divide-slate-100">
              {riders.map((row) => {
                return (
                  <tr key={row.id} className="hover:bg-slate-50">
                    <td className="px-4 py-3 font-medium text-slate-900">{row.name}</td>
                    <td className="px-4 py-3">{row.phone ?? '—'}</td>
                    <td className="px-4 py-3">{row.vehicle_code ?? '—'}</td>
                    <td className="px-4 py-3">{row.is_active ? '✓' : '✗'}</td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      ) : null}

      {tab === 'deliveries' ? (
        <div className="space-y-4">
          <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 className="font-bold text-slate-900">{t(locale, 'restaurant.del.newDelivery', 'Nouvelle livraison')}</h3>
            <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
              <select
                className="rounded-lg border border-slate-200 px-3 py-2 text-sm"
                value={form.order_id}
                onChange={(e) => setForm({ ...form, order_id: e.target.value })}
                aria-label={t(locale, 'restaurant.del.colOrder', 'Commande')}
              >
                <option value="">{t(locale, 'restaurant.del.orderPlaceholder', 'Commande (type livraison)…')}</option>
                {orders.map((o) => (
                  <option key={o.id} value={o.id}>
                    {o.reference ? `${o.reference} — ` : ''}#{o.id}
                    {o.status ? ` (${o.status})` : ''}
                  </option>
                ))}
              </select>
              <input type="number" className="rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder={t(locale, 'restaurant.del.feePlaceholder', 'Frais (minor)')} value={form.fee_minor} onChange={(e) => setForm({ ...form, fee_minor: e.target.value })} aria-label={t(locale, 'restaurant.del.feePlaceholder', 'Frais (minor)')} />
              <button type="button" onClick={() => void createDelivery()} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                {t(locale, 'restaurant.del.create')}
              </button>
            </div>
          </div>

          <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table className="min-w-full divide-y divide-slate-200 text-sm">
              <thead className="bg-slate-50"><tr><th className="px-4 py-3 text-left font-semibold text-slate-700">ID</th><th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'restaurant.del.colOrder', 'Commande')}</th><th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'restaurant.del.colRider', 'Livreur')}</th><th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'restaurant.del.colStatus', 'Statut')}</th><th className="px-4 py-3 text-right font-semibold text-slate-700" aria-label="Actions" /></tr></thead>
              <tbody className="divide-y divide-slate-100">
                {deliveries.map((d) => (
                  <tr key={d.id} className="hover:bg-slate-50">
                    <td className="px-4 py-3">{d.id}</td>
                    <td className="px-4 py-3">{orderLabel(d.order_id)}</td>
                    <td className="px-4 py-3">
                      {assigning?.deliveryId === d.id ? (
                        <select
                          autoFocus
                          className="rounded-lg border border-slate-200 px-2 py-1 text-xs"
                          value={assigning.riderId}
                          onChange={(e) => setAssigning({ deliveryId: d.id, riderId: e.target.value })}
                          aria-label={t(locale, 'restaurant.del.colRider', 'Livreur')}
                        >
                          <option value="">{t(locale, 'restaurant.del.riderPlaceholder', 'Livreur…')}</option>
                          {riders.filter((r) => r.is_active).map((r) => (
                            <option key={r.id} value={r.id}>{r.name}</option>
                          ))}
                        </select>
                      ) : (
                        riderName(d.rider_id)
                      )}
                    </td>
                    <td className="px-4 py-3">{d.status}</td>
                    <td className="px-4 py-3">
                      <div className="flex justify-end gap-2 text-xs font-medium">
                        {d.status === 'pending' && assigning?.deliveryId !== d.id ? <button className="text-blue-600 hover:underline" onClick={() => setAssigning({ deliveryId: d.id, riderId: '' })}>{t(locale, 'restaurant.del.assign', 'Assigner')}</button> : null}
                        {d.status === 'pending' && assigning?.deliveryId === d.id ? (
                          <>
                            <button
                              className="text-emerald-700 hover:underline disabled:opacity-50"
                              disabled={assigning.riderId === ''}
                              onClick={() => void transition(d.id, 'assign', Number(assigning.riderId))}
                            >
                              {t(locale, 'restaurant.del.confirmAssign', 'Confirmer')}
                            </button>
                            <button className="text-slate-500 hover:underline" onClick={() => setAssigning(null)}>{t(locale, 'restaurant.del.cancelAssign', 'Annuler')}</button>
                          </>
                        ) : null}
                        {d.status === 'assigned' ? <button className="text-cyan-600 hover:underline" onClick={() => void transition(d.id, 'out-for-delivery')}>{t(locale, 'restaurant.del.out')}</button> : null}
                        {d.status === 'out_for_delivery' ? <button className="text-emerald-700 hover:underline" onClick={() => void transition(d.id, 'deliver')}>{t(locale, 'restaurant.del.deliver')}</button> : null}
                        {['pending', 'assigned', 'out_for_delivery'].includes(d.status) ? <button className="text-red-600 hover:underline" onClick={() => void transition(d.id, 'cancel')}>{t(locale, 'restaurant.del.cancel', 'Annuler')}</button> : null}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      ) : null}

      {tab === 'loyalty' ? (
        <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
          <table className="min-w-full divide-y divide-slate-200 text-sm">
            <thead className="bg-slate-50"><tr><th className="px-4 py-3 text-left font-semibold text-slate-700">Contact</th><th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'restaurant.del.colPoints', 'Points')}</th><th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'restaurant.del.colTier', 'Palier')}</th></tr></thead>
            <tbody className="divide-y divide-slate-100">
              {loyalty.map((l) => {
                const row = l as { id: number; customer_contact_id: number; points: number; tier_code: string | null };
                return (
                  <tr key={row.id} className="hover:bg-slate-50">
                    <td className="px-4 py-3">{row.customer_contact_id}</td>
                    <td className="px-4 py-3 font-medium text-slate-900">{row.points}</td>
                    <td className="px-4 py-3">{row.tier_code ?? '—'}</td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      ) : null}

      {tab === 'promotions' ? (
        <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
          <table className="min-w-full divide-y divide-slate-200 text-sm">
            <thead className="bg-slate-50"><tr><th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'restaurant.del.colCode', 'Code')}</th><th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'restaurant.del.colTitle', 'Titre')}</th><th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'restaurant.del.colType', 'Type')}</th><th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'restaurant.del.colValue', 'Valeur')}</th><th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'restaurant.del.colUses', 'Utilisations')}</th></tr></thead>
            <tbody className="divide-y divide-slate-100">
              {promotions.map((p) => {
                const row = p as { id: number; code: string; title: string; discount_type: string; value_minor: number; used_count: number; max_uses: number | null };
                return (
                  <tr key={row.id} className="hover:bg-slate-50">
                    <td className="px-4 py-3 font-medium text-slate-900">{row.code}</td>
                    <td className="px-4 py-3">{row.title}</td>
                    <td className="px-4 py-3">{row.discount_type}</td>
                    <td className="px-4 py-3">{row.value_minor}</td>
                    <td className="px-4 py-3">{row.used_count}/{row.max_uses ?? '∞'}</td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      ) : null}

      {loading ? <p className="text-sm text-slate-500">{t(locale, 'restaurant.del.loading', 'Chargement...')}</p> : null}
    </ModulePageShell>
  );
}
