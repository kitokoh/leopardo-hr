'use client';

/**
 * HOSP-007 (#7949) — locatif : baux et echeances de loyer.
 *
 * Le locatif est une seconde activite du module, distincte de l'hotellerie :
 * un bail court sur des mois et produit des echeances, la ou une reservation
 * est un sejour. Les deux sont donc sur des ecrans differents — les melanger
 * ferait apparaitre des loyers dans un planning de chambres.
 *
 * `generate-periods` est expose parce qu'un bail sans echeance ne peut pas
 * etre encaisse : c'est l'action qui rend le bail exploitable.
 */
import { useCallback, useEffect, useState } from 'react';
import { KeyRound, Loader2, AlertTriangle, CalendarPlus, Ban } from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

type Property = { id: number; name: string };
type Lease = {
  id: number;
  property_id: number;
  tenant_name: string | null;
  status: string;
  start_date: string | null;
  end_date: string | null;
  rent_minor: number | null;
  currency: string | null;
};
type RentPayment = {
  id: number;
  lease_id: number;
  due_date: string | null;
  amount_minor: number;
  status: string;
};

export default function HospitalityRentalsPage() {
  const locale = getPreferredLocale();
  const [properties, setProperties] = useState<Property[]>([]);
  const [propertyId, setPropertyId] = useState<number | null>(null);
  const [leases, setLeases] = useState<Lease[]>([]);
  const [payments, setPayments] = useState<RentPayment[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [actionError, setActionError] = useState('');
  const [busyId, setBusyId] = useState<number | null>(null);

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

  const load = useCallback(async () => {
    if (propertyId === null) return;
    setLoading(true);
    setError('');
    try {
      const [leaseRes, paymentRes] = await Promise.all([
        apiFetch(`/hospitality/properties/${propertyId}/leases`),
        apiFetch('/hospitality/rent-payments'),
      ]);
      const leasePayload = leaseRes.ok ? ((await leaseRes.json()) as { data?: Lease[] }) : {};
      const paymentPayload = paymentRes.ok ? ((await paymentRes.json()) as { data?: RentPayment[] }) : {};
      setLeases(Array.isArray(leasePayload.data) ? leasePayload.data : []);
      setPayments(Array.isArray(paymentPayload.data) ? paymentPayload.data : []);
    } catch {
      setError(t(locale, 'hospitality.rentals.loadError', 'Impossible de charger le locatif.'));
    } finally {
      setLoading(false);
    }
  }, [locale, propertyId]);

  useEffect(() => {
    void loadProperties();
  }, [loadProperties]);

  useEffect(() => {
    void load();
  }, [load]);

  async function generatePeriods(lease: Lease) {
    setActionError('');
    setBusyId(lease.id);
    try {
      const res = await apiFetch(`/hospitality/leases/${lease.id}/generate-periods`, { method: 'POST' });
      if (!res.ok) {
        // Cas usuel : echeances deja generees pour la periode, ou bail sans
        // date de fin. On dit lequel plutot qu'un « erreur » generique.
        setActionError(
          t(
            locale,
            'hospitality.rentals.generateError',
            'Génération refusée : les échéances existent déjà ou le bail n’a pas de date de fin.',
          ),
        );
        return;
      }
      await load();
    } catch {
      setActionError(t(locale, 'hospitality.rentals.generateError', 'Génération refusée.'));
    } finally {
      setBusyId(null);
    }
  }

  async function terminate(lease: Lease) {
    const confirmed = window.confirm(
      t(locale, 'hospitality.rentals.confirmTerminate', 'Résilier ce bail ? Les échéances non réglées restent dues.'),
    );
    if (!confirmed) return;
    setActionError('');
    setBusyId(lease.id);
    try {
      const res = await apiFetch(`/hospitality/leases/${lease.id}/terminate`, { method: 'POST' });
      if (!res.ok) {
        setActionError(t(locale, 'hospitality.rentals.terminateError', 'Résiliation refusée par l’API.'));
        return;
      }
      await load();
    } finally {
      setBusyId(null);
    }
  }

  const paymentsFor = (leaseId: number): RentPayment[] => payments.filter((p) => p.lease_id === leaseId);

  return (
    <ModulePageShell
      title={t(locale, 'hospitality.rentals.title', 'Locatif')}
      subtitle={t(
        locale,
        'hospitality.rentals.subtitle',
        'Baux, échéances et encaissements des logements en location.',
      )}
      icon={KeyRound}
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

      {propertyId !== null && !loading && leases.length === 0 ? (
        <div className="rounded-xl border border-neutral-200 bg-white p-6 text-sm text-neutral-600">
          {t(locale, 'hospitality.rentals.empty', 'Aucun bail pour cet établissement.')}
        </div>
      ) : null}

      {leases.length > 0 ? (
        <div className="space-y-3">
          {leases.map((lease) => {
            const leasePayments = paymentsFor(lease.id);
            const unpaid = leasePayments.filter((p) => p.status !== 'paid');

            return (
              <section key={lease.id} className="rounded-xl border border-neutral-200 bg-white p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <p className="font-medium text-neutral-900">{lease.tenant_name ?? `Bail #${lease.id}`}</p>
                    <p className="mt-1 text-sm text-neutral-600">
                      {lease.start_date ?? '—'} → {lease.end_date ?? '—'}
                      {lease.rent_minor !== null
                        ? ` · ${(lease.rent_minor / 100).toFixed(2)} ${lease.currency ?? ''}`.trimEnd()
                        : ''}
                    </p>
                  </div>
                  <div className="flex flex-wrap items-center gap-2">
                    <span className="rounded-full bg-neutral-100 px-2 py-1 text-xs text-neutral-700">{lease.status}</span>
                    <button
                      type="button"
                      onClick={() => void generatePeriods(lease)}
                      disabled={busyId === lease.id}
                      className="inline-flex items-center gap-1 rounded-lg border border-neutral-300 px-2 py-1 text-xs text-neutral-700 disabled:opacity-50"
                    >
                      <CalendarPlus className="h-3 w-3" />
                      {t(locale, 'hospitality.rentals.generate', 'Générer les échéances')}
                    </button>
                    <button
                      type="button"
                      onClick={() => void terminate(lease)}
                      disabled={busyId === lease.id}
                      className="inline-flex items-center gap-1 rounded-lg border border-red-200 px-2 py-1 text-xs text-red-700 disabled:opacity-50"
                    >
                      <Ban className="h-3 w-3" />
                      {t(locale, 'hospitality.rentals.terminate', 'Résilier')}
                    </button>
                  </div>
                </div>

                {leasePayments.length > 0 ? (
                  <div className="mt-3 border-t border-neutral-100 pt-3">
                    <p className="text-xs font-medium uppercase tracking-wide text-neutral-500">
                      {t(locale, 'hospitality.rentals.payments', 'Échéances')}
                      {unpaid.length > 0 ? ` · ${unpaid.length} ${t(locale, 'hospitality.rentals.unpaid', 'impayée(s)')}` : ''}
                    </p>
                    <ul className="mt-2 space-y-1 text-sm">
                      {leasePayments.map((payment) => (
                        <li key={payment.id} className="flex items-center justify-between">
                          <span className="text-neutral-700">{payment.due_date ?? '—'}</span>
                          <span className={payment.status === 'paid' ? 'text-emerald-700' : 'text-amber-700'}>
                            {(payment.amount_minor / 100).toFixed(2)} · {payment.status}
                          </span>
                        </li>
                      ))}
                    </ul>
                  </div>
                ) : (
                  <p className="mt-3 border-t border-neutral-100 pt-3 text-sm text-neutral-500">
                    {t(
                      locale,
                      'hospitality.rentals.noPayments',
                      'Aucune échéance : générez-les pour pouvoir encaisser le loyer.',
                    )}
                  </p>
                )}
              </section>
            );
          })}
        </div>
      ) : null}
    </ModulePageShell>
  );
}
