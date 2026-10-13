'use client';

/**
 * BC-15 Fuel — ventes & caisse (FUEL-007/008, #5801/#5802).
 *
 * Surface web de la caisse : sessions (`GET/POST /fuel-station/cash-sessions`,
 * approbation manager) et ventes (`GET /fuel-station/sales`).
 */
import { useCallback, useEffect, useState } from 'react';
import { Wallet } from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

type StationOption = { id: number; name: string };
type CashSession = {
  id: number;
  station_id: number | null;
  opened_at: string | null;
  opening_balance: number | string | null;
  closing_balance?: number | string | null;
  expected_balance?: number | string | null;
  variance?: number | string | null;
  status?: string | null;
};
type Sale = {
  id: number;
  station_id: number | null;
  product?: string | null;
  quantity?: number | string | null;
  total?: number | string | null;
  sale_time?: string | null;
  status?: string | null;
};

export default function FuelSalesPage() {
  const locale = getPreferredLocale();
  const [stations, setStations] = useState<StationOption[]>([]);
  const [sessions, setSessions] = useState<CashSession[]>([]);
  const [sales, setSales] = useState<Sale[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const [formStation, setFormStation] = useState('');
  const [formOpening, setFormOpening] = useState('');
  const [formError, setFormError] = useState('');
  const [saving, setSaving] = useState(false);
  const [approvingId, setApprovingId] = useState<number | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const [stationsRes, sessionsRes, salesRes] = await Promise.all([
        apiFetch('/fuel-station/stations?per_page=200'),
        apiFetch('/fuel-station/cash-sessions?per_page=100'),
        apiFetch('/fuel-station/sales?per_page=100'),
      ]);
      const stationsPayload = stationsRes.ok ? ((await stationsRes.json()) as { data?: StationOption[] }) : {};
      const sessionsPayload = sessionsRes.ok ? ((await sessionsRes.json()) as { data?: CashSession[] }) : {};
      const salesPayload = salesRes.ok ? ((await salesRes.json()) as { data?: Sale[] }) : {};
      setStations(Array.isArray(stationsPayload.data) ? stationsPayload.data : []);
      setSessions(Array.isArray(sessionsPayload.data) ? sessionsPayload.data : []);
      setSales(Array.isArray(salesPayload.data) ? salesPayload.data : []);
    } catch {
      setError(t(locale, 'fuel.sales.loadError', 'Impossible de charger les ventes et la caisse.'));
    } finally {
      setLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    void load();
  }, [load]);

  const openSession = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setFormError('');
    if (formStation === '') {
      setFormError(t(locale, 'fuel.sales.stationRequired', 'Sélectionnez une station.'));
      return;
    }
    setSaving(true);
    const body = JSON.stringify({
      station_id: Number(formStation),
      opening_balance: formOpening.trim() === '' ? null : Number(formOpening),
    });
    try {
      const res = await apiFetch('/fuel-station/cash-sessions', { method: 'POST', body });
      if (!res.ok) {
        const payload = (await res.json().catch(() => ({}))) as { message?: string };
        setFormError(payload.message ?? t(locale, 'fuel.sales.openFailed', "L'ouverture a échoué."));
        return;
      }
      setFormOpening('');
      await load();
    } catch {
      setFormError(t(locale, 'fuel.sales.openFailed', "L'ouverture a échoué."));
    } finally {
      setSaving(false);
    }
  };

  const approve = async (session: CashSession) => {
    setApprovingId(session.id);
    setError('');
    try {
      const res = await apiFetch(`/fuel-station/cash-sessions/${session.id}/approve`, { method: 'POST' });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      await load();
    } catch {
      setError(t(locale, 'fuel.sales.approveFailed', "L'approbation a échoué."));
    } finally {
      setApprovingId(null);
    }
  };

  const stationName = (id: number | null): string =>
    id === null ? '—' : stations.find((station) => station.id === id)?.name ?? `#${id}`;

  const inputClass =
    'w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-lime-500 focus:outline-none';

  return (
    <ModulePageShell
      icon={Wallet}
      title={t(locale, 'fuel.sales.title', 'Ventes & caisse')}
      description={t(locale, 'fuel.sales.subtitle', 'Sessions de caisse et ventes de vos stations.')}
    >
      {error ? (
        <p role="alert" className="rounded-lg bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
          {error}
        </p>
      ) : null}

      <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 className="text-sm font-bold uppercase tracking-wide text-slate-500">
          {t(locale, 'fuel.sales.openTitle', 'Ouvrir une session de caisse')}
        </h2>
        <form onSubmit={openSession} className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <label className="block text-sm">
            <span className="mb-1 block font-semibold text-slate-700">{t(locale, 'fuel.stock.station', 'Station')}</span>
            <select
              value={formStation}
              onChange={(e) => setFormStation(e.target.value)}
              className={inputClass}
              data-testid="session-station"
            >
              <option value="">{t(locale, 'fuel.team.selectPlaceholder', 'Sélectionner…')}</option>
              {stations.map((station) => (
                <option key={station.id} value={station.id}>
                  {station.name}
                </option>
              ))}
            </select>
          </label>
          <label className="block text-sm">
            <span className="mb-1 block font-semibold text-slate-700">
              {t(locale, 'fuel.sales.openingBalance', "Fond de caisse d'ouverture")}
            </span>
            <input
              type="number"
              min={0}
              step="0.01"
              value={formOpening}
              onChange={(e) => setFormOpening(e.target.value)}
              className={inputClass}
            />
          </label>
          <div className="flex items-end">
            <button
              type="submit"
              disabled={saving}
              data-testid="session-submit"
              className="rounded-xl bg-lime-600 px-4 py-2 text-sm font-black text-white transition hover:bg-lime-700 disabled:opacity-50"
            >
              {saving ? t(locale, 'fuel.sales.opening', 'Ouverture…') : t(locale, 'fuel.sales.open', 'Ouvrir')}
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
          {t(locale, 'fuel.sales.sessionsTitle', 'Sessions de caisse')}
        </h2>
        {loading ? (
          <p className="mt-3 text-sm text-slate-500">{t(locale, 'fuel.common.loading', 'Chargement…')}</p>
        ) : sessions.length === 0 ? (
          <p className="mt-3 text-sm text-slate-500" data-testid="sessions-empty">
            {t(locale, 'fuel.sales.sessionsEmpty', 'Aucune session de caisse.')}
          </p>
        ) : (
          <div className="mt-3 overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead>
                <tr className="border-b border-slate-200 text-xs font-bold uppercase tracking-wide text-slate-500">
                  <th className="px-3 py-2">{t(locale, 'fuel.stock.station', 'Station')}</th>
                  <th className="px-3 py-2">{t(locale, 'fuel.sales.openedAt', 'Ouverte le')}</th>
                  <th className="px-3 py-2">{t(locale, 'fuel.sales.openingBalance', "Fond d'ouverture")}</th>
                  <th className="px-3 py-2">{t(locale, 'fuel.team.status', 'Statut')}</th>
                  <th className="px-3 py-2" />
                </tr>
              </thead>
              <tbody>
                {sessions.map((session) => (
                  <tr key={session.id} className="border-b border-slate-100" data-testid={`session-row-${session.id}`}>
                    <td className="px-3 py-2 font-semibold text-slate-800">{stationName(session.station_id)}</td>
                    <td className="px-3 py-2 text-slate-600">{(session.opened_at ?? '—').slice(0, 10)}</td>
                    <td className="px-3 py-2 text-slate-600">{session.opening_balance ?? '—'}</td>
                    <td className="px-3 py-2 text-slate-600">{session.status ?? '—'}</td>
                    <td className="px-3 py-2 text-right">
                      {session.status !== 'approved' ? (
                        <button
                          type="button"
                          onClick={() => void approve(session)}
                          disabled={approvingId === session.id}
                          className="rounded-lg border border-emerald-200 px-3 py-1.5 text-xs font-bold text-emerald-700 transition hover:bg-emerald-50 disabled:opacity-50"
                        >
                          {t(locale, 'fuel.sales.approve', 'Approuver')}
                        </button>
                      ) : null}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>

      <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 className="text-sm font-bold uppercase tracking-wide text-slate-500">
          {t(locale, 'fuel.sales.listTitle', 'Ventes récentes')}
        </h2>
        {loading ? (
          <p className="mt-3 text-sm text-slate-500">{t(locale, 'fuel.common.loading', 'Chargement…')}</p>
        ) : sales.length === 0 ? (
          <p className="mt-3 text-sm text-slate-500" data-testid="sales-empty">
            {t(locale, 'fuel.sales.empty', 'Aucune vente enregistrée.')}
          </p>
        ) : (
          <div className="mt-3 overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead>
                <tr className="border-b border-slate-200 text-xs font-bold uppercase tracking-wide text-slate-500">
                  <th className="px-3 py-2">{t(locale, 'fuel.stock.station', 'Station')}</th>
                  <th className="px-3 py-2">{t(locale, 'fuel.stock.product', 'Produit')}</th>
                  <th className="px-3 py-2">{t(locale, 'fuel.sales.quantity', 'Quantité')}</th>
                  <th className="px-3 py-2">{t(locale, 'fuel.sales.amount', 'Montant')}</th>
                  <th className="px-3 py-2">{t(locale, 'fuel.stock.date', 'Date')}</th>
                </tr>
              </thead>
              <tbody>
                {sales.map((sale) => (
                  <tr key={sale.id} className="border-b border-slate-100" data-testid={`sale-row-${sale.id}`}>
                    <td className="px-3 py-2 text-slate-600">{stationName(sale.station_id)}</td>
                    <td className="px-3 py-2 font-semibold text-slate-800">{sale.product ?? '—'}</td>
                    <td className="px-3 py-2 text-slate-600">{sale.quantity ?? '—'}</td>
                    <td className="px-3 py-2 text-slate-600">{sale.total ?? '—'}</td>
                    <td className="px-3 py-2 text-slate-600">{(sale.sale_time ?? '—').slice(0, 10)}</td>
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
