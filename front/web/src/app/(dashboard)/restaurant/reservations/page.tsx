'use client';

/**
 * RESTO-706 (#6219) — UI admin : écran réservations.
 * Liste + création + transitions (confirm/check-in/no-show/cancel) sur
 * `/restaurant/reservations/*` + aperçu de disponibilité.
 */
import { useCallback, useEffect, useState } from 'react';
import { CalendarCheck } from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

type Reservation = {
  id: number;
  reference: string;
  contact_name: string;
  contact_phone: string;
  reserved_at: string | null;
  covers: number;
  table_id: number | null;
  status: string;
  deposit_minor: number | null;
};

const STATUS_STYLES: Record<string, string> = {
  pending: 'bg-amber-100 text-amber-800',
  confirmed: 'bg-blue-100 text-blue-800',
  seated: 'bg-emerald-100 text-emerald-800',
  completed: 'bg-slate-100 text-slate-700',
  no_show: 'bg-red-100 text-red-700',
  cancelled: 'bg-slate-200 text-slate-500',
};

export default function RestaurantReservationsPage() {
  const locale = getPreferredLocale();
  const [rows, setRows] = useState<Reservation[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [form, setForm] = useState({ branch_id: '', contact_name: '', contact_phone: '', reserved_at: '', covers: '2', table_id: '' });
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await apiFetch('/restaurant/reservations?per_page=100');
      const payload = await res.json();
      setRows(Array.isArray(payload?.data) ? payload.data : []);
    } catch {
      setError(t(locale, 'restaurant.res.loadError', 'Impossible de charger les réservations.'));
    } finally {
      setLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    void load();
  }, [load]);

  const create = async () => {
    setBusy(true);
    setError('');
    try {
      const res = await apiFetch('/restaurant/reservations', { method: 'POST', body: JSON.stringify({ ...form, covers: Number(form.covers), table_id: form.table_id ? Number(form.table_id) : null }) });
      if (!res.ok) {
        const payload = await res.json().catch(() => ({}));
        throw new Error((payload as { message?: string }).message ?? `HTTP ${res.status}`);
      }
      setForm({ branch_id: '', contact_name: '', contact_phone: '', reserved_at: '', covers: '2', table_id: '' });
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : t(locale, 'restaurant.res.createError', 'Erreur de création.'));
    } finally {
      setBusy(false);
    }
  };

  const transition = async (id: number, action: string) => {
    try {
      const res = await apiFetch(`/restaurant/reservations/${id}/${action}`, { method: 'POST' });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : t(locale, 'restaurant.res.actionError', 'Action impossible.'));
    }
  };

  return (
    <ModulePageShell
      icon={CalendarCheck}
      title={t(locale, 'restaurant.res.title', 'Réservations')}
      description={t(locale, 'restaurant.res.subtitle', 'Créneaux, check-in, no-show, dépôts')}
    >
      {error ? <p className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{error}</p> : null}

      <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h3 className="font-bold text-slate-900">{t(locale, 'restaurant.res.new', 'Nouvelle réservation')}</h3>
        <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
          <input className="rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Branche ID" value={form.branch_id} onChange={(e) => setForm({ ...form, branch_id: e.target.value })} aria-label="Branche ID" />
          <input className="rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder={t(locale, 'restaurant.res.name', 'Nom du client')} value={form.contact_name} onChange={(e) => setForm({ ...form, contact_name: e.target.value })} aria-label="Nom" />
          <input className="rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder={t(locale, 'restaurant.res.phone', 'Téléphone')} value={form.contact_phone} onChange={(e) => setForm({ ...form, contact_phone: e.target.value })} aria-label="Téléphone" />
          <input type="datetime-local" className="rounded-lg border border-slate-200 px-3 py-2 text-sm" value={form.reserved_at} onChange={(e) => setForm({ ...form, reserved_at: e.target.value })} aria-label="Date" />
          <input type="number" min={1} className="rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Couverts" value={form.covers} onChange={(e) => setForm({ ...form, covers: e.target.value })} aria-label="Couverts" />
          <button type="button" disabled={busy} onClick={() => void create()} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50">
            {t(locale, 'restaurant.res.create', 'Créer')}
          </button>
        </div>
      </div>

      <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table className="min-w-full divide-y divide-slate-200 text-sm">
          <thead className="bg-slate-50">
            <tr>
              <th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'restaurant.res.colRef', 'Référence')}</th>
              <th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'restaurant.res.colContact', 'Client')}</th>
              <th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'restaurant.res.colWhen', 'Quand')}</th>
              <th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'restaurant.res.colCovers', 'Couverts')}</th>
              <th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'restaurant.res.colStatus', 'Statut')}</th>
              <th className="px-4 py-3 text-right font-semibold text-slate-700" aria-label="Actions" />
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {loading ? (
              <tr><td colSpan={6} className="px-4 py-8 text-center text-slate-500">{t(locale, 'restaurant.res.loading', 'Chargement...')}</td></tr>
            ) : rows.length === 0 ? (
              <tr><td colSpan={6} className="px-4 py-8 text-center text-slate-500">{t(locale, 'restaurant.res.empty', 'Aucune réservation.')}</td></tr>
            ) : (
              rows.map((r) => (
                <tr key={r.id} className="hover:bg-slate-50">
                  <td className="px-4 py-3 font-medium text-slate-900">{r.reference}</td>
                  <td className="px-4 py-3">
                    {r.contact_name}
                    <span className="block text-xs text-slate-500">{r.contact_phone}</span>
                  </td>
                  <td className="px-4 py-3">{r.reserved_at ? new Date(r.reserved_at).toLocaleString(locale) : '—'}</td>
                  <td className="px-4 py-3">{r.covers}</td>
                  <td className="px-4 py-3">
                    <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_STYLES[r.status] ?? 'bg-slate-100 text-slate-600'}`}>{r.status}</span>
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex justify-end gap-2 text-xs font-medium">
                      {r.status === 'pending' ? (
                        <button className="text-blue-600 hover:underline" onClick={() => void transition(r.id, 'confirm')}>{t(locale, 'restaurant.res.confirm', 'Confirmer')}</button>
                      ) : null}
                      {r.status === 'confirmed' ? (
                        <button className="text-emerald-600 hover:underline" onClick={() => void transition(r.id, 'check-in')}>{t(locale, 'restaurant.res.checkin', 'Check-in')}</button>
                      ) : null}
                      {['pending', 'confirmed'].includes(r.status) ? (
                        <>
                          <button className="text-red-600 hover:underline" onClick={() => void transition(r.id, 'no-show')}>{t(locale, 'restaurant.res.noshow', 'No-show')}</button>
                          <button className="text-slate-600 hover:underline" onClick={() => void transition(r.id, 'cancel')}>{t(locale, 'restaurant.res.cancel', 'Annuler')}</button>
                        </>
                      ) : null}
                    </div>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </ModulePageShell>
  );
}
