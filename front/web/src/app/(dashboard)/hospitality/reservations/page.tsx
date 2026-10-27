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
const TRANSITIONS: Record<string, { action: string; labelKey: string; fallback: string }[]> = {
  pending: [
    { action: 'confirm', labelKey: 'hospitality.reservations.action.confirm', fallback: 'Confirmer' },
    { action: 'cancel', labelKey: 'hospitality.reservations.action.cancel', fallback: 'Annuler' },
  ],
  confirmed: [
    { action: 'check-in', labelKey: 'hospitality.reservations.action.checkIn', fallback: 'Arrivée' },
    { action: 'no-show', labelKey: 'hospitality.reservations.action.noShow', fallback: 'Non présenté' },
    { action: 'cancel', labelKey: 'hospitality.reservations.action.cancel', fallback: 'Annuler' },
  ],
  checked_in: [
    { action: 'check-out', labelKey: 'hospitality.reservations.action.checkOut', fallback: 'Départ' },
  ],
  checked_out: [],
  cancelled: [],
  no_show: [],
};

const STATUS_LABELS: Record<string, { key: string; fallback: string }> = {
  pending: { key: 'hospitality.reservations.status.pending', fallback: 'En attente' },
  confirmed: { key: 'hospitality.reservations.status.confirmed', fallback: 'Confirmée' },
  checked_in: { key: 'hospitality.reservations.status.checkedIn', fallback: 'En séjour' },
  checked_out: { key: 'hospitality.reservations.status.checkedOut', fallback: 'Terminée' },
  cancelled: { key: 'hospitality.reservations.status.cancelled', fallback: 'Annulée' },
  no_show: { key: 'hospitality.reservations.status.noShow', fallback: 'Non présentée' },
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
        setError(t(locale, 'hospitality.reservations.loadError', 'Impossible de charger les réservations.'));
        return;
      }
      const payload = (await res.json()) as { data?: Reservation[] };
      setReservations(Array.isArray(payload.data) ? payload.data : []);
    } catch {
      setError(t(locale, 'hospitality.reservations.loadError', 'Impossible de charger les réservations.'));
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
          t(
            locale,
            'hospitality.reservations.transitionError',
            'Transition refusée : la réservation a peut-être changé d’état. Liste rechargée.',
          ),
        );
        await load();
        return;
      }
      await load();
    } catch {
      setActionError(t(locale, 'hospitality.reservations.transitionError', 'Transition refusée.'));
    } finally {
      setBusyId(null);
    }
  }

  return (
    <ModulePageShell
      title={t(locale, 'hospitality.reservations.title', 'Réservations')}
      subtitle={t(
        locale,
        'hospitality.reservations.subtitle',
        'Séjours au guichet et transitions d’état.',
      )}
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
          {t(locale, 'common.loading', 'Chargement…')}
        </div>
      ) : null}

      {!loading && reservations.length === 0 ? (
        <div className="rounded-xl border border-neutral-200 bg-white p-6 text-sm text-neutral-600">
          {t(locale, 'hospitality.reservations.empty', 'Aucune réservation enregistrée.')}
        </div>
      ) : null}

      {reservations.length > 0 ? (
        <div className="overflow-x-auto rounded-xl border border-neutral-200 bg-white">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 text-left text-neutral-600">
              <tr>
                <th className="px-4 py-2 font-medium">{t(locale, 'hospitality.reservations.col.reference', 'Référence')}</th>
                <th className="px-4 py-2 font-medium">{t(locale, 'hospitality.reservations.col.guest', 'Client')}</th>
                <th className="px-4 py-2 font-medium">{t(locale, 'hospitality.reservations.col.dates', 'Séjour')}</th>
                <th className="px-4 py-2 font-medium">{t(locale, 'hospitality.reservations.col.status', 'État')}</th>
                <th className="px-4 py-2" />
              </tr>
            </thead>
            <tbody>
              {reservations.map((reservation) => {
                const statusLabel = STATUS_LABELS[reservation.status];
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
                        {statusLabel ? t(locale, statusLabel.key, statusLabel.fallback) : reservation.status}
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
                            {t(locale, tr.labelKey, tr.fallback)}
                          </button>
                        ))}
                        {available.length === 0 ? (
                          <span className="text-xs text-neutral-400">
                            {t(locale, 'hospitality.reservations.noAction', 'Aucune action')}
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
