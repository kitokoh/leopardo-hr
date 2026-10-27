'use client';

/**
 * HOSP-007 (#7949) — reservations au guichet et transitions.
 *
 * Les transitions ne sont **pas** libres : le modele impose
 * `pending -> confirmed -> checked_in -> checked_out`, avec `cancelled` et
 * `no_show` en sorties laterales. L'API refuse toute autre transition
 * (HOSP-004), donc le front n'affiche que celles qui sont legalement
 * possibles depuis l'etat courant — proposer un bouton qui echoue en 422
 * serait une fausse promesse.
 */
import { useCallback, useEffect, useState } from 'react';
import { CalendarCheck, Loader2, AlertTriangle } from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

type Reservation = {
  id: number;
  reference: string | null;
  status: string;
  check_in_date: string | null;
  check_out_date: string | null;
  guest_name: string | null;
  total_minor: number | null;
};

/**
 * Transitions ouvertes depuis chaque etat — miroir exact de
 * `HospitalityReservation::ALLOWED_TRANSITIONS` cote API.
 */
const TRANSITIONS: Record<string, { action: string; labelKey: string }[]> = {
  pending: [
    { action: 'confirm', labelKey: 'hospitality.reservations.action.confirm' },
    { action: 'cancel', labelKey: 'hospitality.reservations.action.cancel' },
  ],
  confirmed: [
    { action: 'check-in', labelKey: 'hospitality.reservations.action.checkIn' },
    { action: 'no-show', labelKey: 'hospitality.reservations.action.noShow' },
    { action: 'cancel', labelKey: 'hospitality.reservations.action.cancel' },
  ],
  checked_in: [
    { action: 'check-out', labelKey: 'hospitality.reservations.action.checkOut' },
  ],
  checked_out: [],
  cancelled: [],
  no_show: [],
};

const STATUS_LABELS: Record<string, { key: string }> = {
  pending: { key: 'hospitality.reservations.status.pending' },
  confirmed: { key: 'hospitality.reservations.status.confirmed' },
  checked_in: { key: 'hospitality.reservations.status.checkedIn' },
  checked_out: { key: 'hospitality.reservations.status.checkedOut' },
  cancelled: { key: 'hospitality.reservations.status.cancelled' },
  no_show: { key: 'hospitality.reservations.status.noShow' },
};

export default function HospitalityReservationsPage() {
  const locale = getPreferredLocale();
  const [reservations, setReservations] = useState<Reservation[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [actionError, setActionError] = useState('');
  const [busyId, setBusyId] = useState<number | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const res = await apiFetch('/hospitality/reservations');
      if (!res.ok) {
        setError(t(locale, 'hospitality.reservations.loadError'));
        return;
      }
      const payload = (await res.json()) as { data?: Reservation[] };
      setReservations(Array.isArray(payload.data) ? payload.data : []);
    } catch {
      setError(t(locale, 'hospitality.reservations.loadError'));
    } finally {
      setLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    void load();
  }, [load]);

  async function transition(reservation: Reservation, action: string) {
    setActionError('');
    setBusyId(reservation.id);
    try {
      const res = await apiFetch(`/hospitality/reservations/${reservation.id}/${action}`, { method: 'POST' });
      if (!res.ok) {
        // 422 ici signifie que l'etat a bouge entre l'affichage et le clic
        // (double clic, autre poste). On recharge pour resynchroniser au
        // lieu de laisser l'utilisateur devant un bouton mort.
        setActionError(
          t(locale, 'hospitality.reservations.transitionError'),
        );
        await load();
        return;
      }
      await load();
    } catch {
      setActionError(t(locale, 'hospitality.reservations.transitionError'));
    } finally {
      setBusyId(null);
    }
  }

  return (
    <ModulePageShell
      title={t(locale, 'hospitality.reservations.title')}
      subtitle={t(locale, 'hospitality.reservations.subtitle')}
      icon={CalendarCheck}
    >
      {error !== '' ? (
        <div role="alert" className="flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
          <AlertTriangle className="h-4 w-4" />
          {error}
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
          {t(locale, 'common.loading')}
        </div>
      ) : null}

      {!loading && reservations.length === 0 ? (
        <div className="rounded-xl border border-neutral-200 bg-white p-6 text-sm text-neutral-600">
          {t(locale, 'hospitality.reservations.empty')}
        </div>
      ) : null}

      {reservations.length > 0 ? (
        <div className="overflow-x-auto rounded-xl border border-neutral-200 bg-white">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 text-left text-neutral-600">
              <tr>
                <th className="px-4 py-2 font-medium">{t(locale, 'hospitality.reservations.col.reference')}</th>
                <th className="px-4 py-2 font-medium">{t(locale, 'hospitality.reservations.col.guest')}</th>
                <th className="px-4 py-2 font-medium">{t(locale, 'hospitality.reservations.col.dates')}</th>
                <th className="px-4 py-2 font-medium">{t(locale, 'hospitality.reservations.col.status')}</th>
                <th className="px-4 py-2" />
              </tr>
            </thead>
            <tbody>
              {reservations.map((reservation) => {
                const statusKey = STATUS_LABELS[reservation.status]?.key;
                const available = TRANSITIONS[reservation.status] ?? [];

                return (
                  <tr key={reservation.id} className="border-t border-neutral-100 align-top">
                    <td className="px-4 py-3 font-medium text-neutral-900">{reservation.reference ?? `#${reservation.id}`}</td>
                    <td className="px-4 py-3 text-neutral-700">{reservation.guest_name ?? '—'}</td>
                    <td className="px-4 py-3 text-neutral-600">
                      {reservation.check_in_date ?? '—'} → {reservation.check_out_date ?? '—'}
                    </td>
                    <td className="px-4 py-3">
                      <span className="rounded-full bg-neutral-100 px-2 py-1 text-xs text-neutral-700">
                        {statusKey ? t(locale, statusKey) : reservation.status}
                      </span>
                    </td>
                    <td className="px-4 py-3">
                      <div className="flex flex-wrap justify-end gap-2">
                        {available.map((tr) => (
                          <button
                            key={tr.action}
                            type="button"
                            onClick={() => void transition(reservation, tr.action)}
                            disabled={busyId === reservation.id}
                            className="rounded-lg border border-neutral-300 px-2 py-1 text-xs text-neutral-700 disabled:opacity-50"
                          >
                            {t(locale, tr.labelKey)}
                          </button>
                        ))}
                        {available.length === 0 ? (
                          <span className="text-xs text-neutral-400">
                            {t(locale, 'hospitality.reservations.noAction')}
                          </span>
                        ) : null}
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      ) : null}
    </ModulePageShell>
  );
}
