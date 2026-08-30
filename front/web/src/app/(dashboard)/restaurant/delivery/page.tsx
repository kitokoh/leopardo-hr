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
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

type Delivery = { id: number; order_id: number; rider_id: number | null; status: string; fee_minor: number };

export default function RestaurantDeliveryPage() {
  const locale = getPreferredLocale();
  const [tab, setTab] = useState<'zones' | 'riders' | 'deliveries' | 'loyalty' | 'promotions'>('zones');
  const [zones, setZones] = useState<unknown[]>([]);
  const [riders, setRiders] = useState<unknown[]>([]);
  const [deliveries, setDeliveries] = useState<Delivery[]>([]);
  const [loyalty, setLoyalty] = useState<unknown[]>([]);
  const [promotions, setPromotions] = useState<unknown[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [form, setForm] = useState({ branch_id: '', order_id: '', fee_minor: '' });

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
      ]);
      const parse = async (r: Response) => (r.ok ? ((await r.json()) as { data?: unknown[] }) : { data: [] });
      const [z, ri, d, l, p] = await Promise.all(res.map(parse));
      setZones(z.data ?? []);
      setRiders(ri.data ?? []);
      setDeliveries((d.data ?? []) as Delivery[]);
      setLoyalty(l.data ?? []);
      setPromotions(p.data ?? []);
    } catch {
      setError(t(locale, 'restaurant.del.loadError', 'Impossible de charger les données livraison/fidélité.'));
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
      setForm({ branch_id: '', order_id: '', fee_minor: '' });
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : t(locale, 'restaurant.del.createError', 'Erreur de création de la livraison.'));
    }
  };

  const transition = async (id: number, action: string) => {
    setError('');
    try {
      const res = await apiFetch(`/restaurant/deliveries/${id}/${action}`, { method: 'POST' });
      if (!res.ok) {
        const payload = await res.json().catch(() => ({}));
        throw new Error((payload as { message?: string }).message ?? `HTTP ${res.status}`);
      }
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : t(locale, 'restaurant.del.actionError', 'Action impossible.'));
    }
  };

  const tabs = [
    { key: 'zones' as const, label: t(locale, 'restaurant.del.tabZones', 'Zones') },
    { key: 'riders' as const, label: t(locale, 'restaurant.del.tabRiders', 'Livreurs') },
    { key: 'deliveries' as const, label: t(locale, 'restaurant.del.tabDeliveries', 'Livraisons') },
    { key: 'loyalty' as const, label: t(locale, 'restaurant.del.tabLoyalty', 'Fidélité') },
    { key: 'promotions' as const, label: t(locale, 'restaurant.del.tabPromos', 'Promotions') },
  ];

  return (
    <ModulePageShell icon={Bike} title={t(locale, 'restaurant.del.title', 'Livraison & fidélité')} description={t(locale, 'restaurant.del.subtitle', 'Zones, livreurs, tournées, points, promotions')}>
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
                return (
                  <tr key={row.id} className="hover:bg-slate-50">
                    <td className="px-4 py-3 font-medium text-slate-900">{row.name}</td>
                    <td className="px-4 py-3">{row.branch_id}</td>
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
            <thead className="bg-slate-50"><tr><th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'restaurant.del.colName', 'Nom')}</th><th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'restaurant.del.colPhone', 'Téléphone')}</th><th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'restaurant.del.colVehicle', 'Véhicule')}</th><th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'restaurant.del.colActive', 'Actif')}</th></tr></thead>
            <tbody className="divide-y divide-slate-100">
              {riders.map((r) => {
                const row = r as { id: number; name: string; phone: string | null; vehicle_code: string | null; is_active: boolean };
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
              <input className="rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Commande ID (type delivery)" value={form.order_id} onChange={(e) => setForm({ ...form, order_id: e.target.value })} aria-label="Commande" />
              <input type="number" className="rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Frais (minor)" value={form.fee_minor} onChange={(e) => setForm({ ...form, fee_minor: e.target.value })} aria-label="Frais" />
              <button type="button" onClick={() => void createDelivery()} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                {t(locale, 'restaurant.del.create', 'Créer')}
              </button>
            </div>
          </div>

          <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table className="min-w-full divide-y divide-slate-200 text-sm">
              <thead className="bg-slate-50"><tr><th className="px-4 py-3 text-left font-semibold text-slate-700">ID</th><th className="px-4 py-3 text-left font-semibold text-slate-700">Commande</th><th className="px-4 py-3 text-left font-semibold text-slate-700">Livreur</th><th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'restaurant.del.colStatus', 'Statut')}</th><th className="px-4 py-3 text-right font-semibold text-slate-700" aria-label="Actions" /></tr></thead>
              <tbody className="divide-y divide-slate-100">
                {deliveries.map((d) => (
                  <tr key={d.id} className="hover:bg-slate-50">
                    <td className="px-4 py-3">{d.id}</td>
                    <td className="px-4 py-3">{d.order_id}</td>
                    <td className="px-4 py-3">{d.rider_id ?? '—'}</td>
                    <td className="px-4 py-3">{d.status}</td>
                    <td className="px-4 py-3">
                      <div className="flex justify-end gap-2 text-xs font-medium">
                        {d.status === 'pending' ? <button className="text-blue-600 hover:underline" onClick={() => void transition(d.id, 'assign')}>{t(locale, 'restaurant.del.assign', 'Assigner')}</button> : null}
                        {d.status === 'assigned' ? <button className="text-cyan-600 hover:underline" onClick={() => void transition(d.id, 'out-for-delivery')}>{t(locale, 'restaurant.del.out', 'En tournée')}</button> : null}
                        {d.status === 'out_for_delivery' ? <button className="text-emerald-600 hover:underline" onClick={() => void transition(d.id, 'deliver')}>{t(locale, 'restaurant.del.deliver', 'Livrée')}</button> : null}
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
