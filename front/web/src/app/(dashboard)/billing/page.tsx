'use client';

import { useCallback, useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import { ApiError, apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { CreditCard, Download, ExternalLink, FileText, Loader2, ShieldCheck, XCircle } from 'lucide-react';

type Subscription = {
  id: number | string;
  plan: string;
  status: string;
  payment_method?: string | null;
  current_period_start?: string | null;
  current_period_end?: string | null;
  cancelled_at?: string | null;
};

type Invoice = {
  id: number;
  number?: string | null;
  amount: number;
  currency: string;
  total: number;
  status: string;
  due_date?: string | null;
  paid_at?: string | null;
};

const PLAN_LABELS: Record<string, string> = {
  starter: 'Starter',
  business: 'Business',
  enterprise: 'Enterprise',
};

const STATUS_LABELS: Record<string, { label: string; className: string }> = {
  active: { label: 'Actif', className: 'bg-emerald-100 text-emerald-700' },
  cancelled: { label: 'Annule', className: 'bg-red-100 text-red-700' },
  past_due: { label: 'Impaye', className: 'bg-amber-100 text-amber-700' },
  paid: { label: 'Payee', className: 'bg-emerald-100 text-emerald-700' },
  pending: { label: 'En attente', className: 'bg-amber-100 text-amber-700' },
};

export default function BillingPage() {
  const [subscription, setSubscription] = useState<Subscription | null>(null);
  const [invoices, setInvoices] = useState<Invoice[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actionLoading, setActionLoading] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [subRes, invRes] = await Promise.all([
        apiFetch('/billing/subscription').catch((err) => {
          if (err instanceof ApiError && err.status === 404) return null;
          throw err;
        }),
        apiFetch('/billing/invoices'),
      ]);

      if (subRes) {
        const subData = await subRes.json() as { data?: Subscription | null };
        setSubscription(subData.data ?? null);
      } else {
        setSubscription(null);
      }

      const invData = await invRes.json() as { data?: Invoice[] };
      setInvoices(Array.isArray(invData.data) ? invData.data : []);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Impossible de charger les informations de facturation.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { void load(); }, [load]);

  const formatCurrency = (val: number, currency = 'EUR') =>
    new Intl.NumberFormat('fr-FR', { style: 'currency', currency }).format(val || 0);

  const handleUpgrade = async (plan: 'starter' | 'business' | 'enterprise') => {
    setActionLoading(`upgrade-${plan}`);
    setError(null);
    try {
      await apiFetch('/billing/subscription/upgrade', {
        method: 'POST',
        body: JSON.stringify({ plan, payment_method: 'manual' }),
      });
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Impossible de changer de plan.');
    } finally {
      setActionLoading(null);
    }
  };

  const handleCancel = async () => {
    if (!confirm('Annuler votre abonnement ? Vous perdrez l\'acces aux modules premium a la fin de la periode en cours.')) return;
    setActionLoading('cancel');
    setError(null);
    try {
      await apiFetch('/billing/subscription/cancel', {
        method: 'POST',
        body: JSON.stringify({}),
      });
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Impossible d\'annuler l\'abonnement.');
    } finally {
      setActionLoading(null);
    }
  };

  const handleRenew = async () => {
    setActionLoading('renew');
    setError(null);
    try {
      await apiFetch('/billing/subscription/renew', { method: 'POST' });
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Impossible de reactiver l\'abonnement.');
    } finally {
      setActionLoading(null);
    }
  };

  const handleCheckout = async (plan: 'starter' | 'business' | 'enterprise') => {
    setActionLoading(`checkout-${plan}`);
    setError(null);
    try {
      const origin = window.location.origin;
      const res = await apiFetch('/billing/checkout', {
        method: 'POST',
        body: JSON.stringify({
          plan,
          success_url: `${origin}/billing?checkout=success`,
          cancel_url: `${origin}/billing?checkout=cancelled`,
        }),
      });
      const data = await res.json() as { data?: { checkout_url?: string } };
      if (data.data?.checkout_url) {
        window.location.href = data.data.checkout_url;
      }
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Le paiement en ligne n\'est pas encore configure pour ce compte.');
    } finally {
      setActionLoading(null);
    }
  };

  const handlePortal = async () => {
    setActionLoading('portal');
    setError(null);
    try {
      const origin = window.location.origin;
      const returnUrl = encodeURIComponent(`${origin}/billing`);
      const res = await apiFetch(`/billing/portal?return_url=${returnUrl}`);
      const data = await res.json() as { data?: { portal_url?: string } };
      if (data.data?.portal_url) {
        window.location.href = data.data.portal_url;
      }
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Aucun compte de paiement associe. Souscrivez d\'abord a un plan.');
    } finally {
      setActionLoading(null);
    }
  };

  const downloadInvoicePdf = async (id: number) => {
    try {
      const res = await apiFetch(`/billing/invoices/${id}/pdf`);
      const blob = await res.blob();
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `facture-${id}.pdf`;
      a.click();
      URL.revokeObjectURL(url);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Impossible de telecharger la facture.');
    }
  };

  return (
    <ModulePageShell
      title="Facturation"
      subtitle="Gerez votre abonnement, vos moyens de paiement et vos factures directement depuis votre espace client."
      accentClassName="bg-gradient-to-br from-emerald-500/10 via-white to-white"
    >
      {error ? (
        <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>
      ) : null}

      {loading ? (
        <div className="py-16 text-center text-slate-400">Chargement...</div>
      ) : (
        <>
          <div className="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-6 shadow-sm">
            <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
              <div>
                <p className="text-xs font-bold uppercase tracking-widest text-slate-400">Abonnement actuel</p>
                {subscription ? (
                  <>
                    <h2 className="mt-2 text-2xl font-black text-slate-950 dark:text-white">
                      {PLAN_LABELS[subscription.plan] ?? subscription.plan}
                    </h2>
                    <p className="mt-1 text-sm text-slate-500">
                      {subscription.current_period_start && subscription.current_period_end
                        ? `Periode: ${new Date(subscription.current_period_start).toLocaleDateString('fr-FR')} au ${new Date(subscription.current_period_end).toLocaleDateString('fr-FR')}`
                        : 'Aucune periode active'}
                    </p>
                  </>
                ) : (
                  <h2 className="mt-2 text-xl font-bold text-slate-500">Aucun abonnement actif</h2>
                )}
              </div>
              {subscription ? (
                <span className={`inline-flex h-fit items-center gap-1 rounded-full px-3 py-1.5 text-xs font-bold uppercase ${STATUS_LABELS[subscription.status]?.className ?? 'bg-slate-100 text-slate-600'}`}>
                  <ShieldCheck className="h-3.5 w-3.5" />
                  {STATUS_LABELS[subscription.status]?.label ?? subscription.status}
                </span>
              ) : null}
            </div>

            <div className="mt-5 flex flex-wrap gap-3">
              {(['starter', 'business', 'enterprise'] as const).map((plan) => (
                <button
                  key={plan}
                  onClick={() => handleUpgrade(plan)}
                  disabled={subscription?.plan === plan || actionLoading === `upgrade-${plan}`}
                  className="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 px-4 py-2 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 disabled:opacity-40"
                >
                  {actionLoading === `upgrade-${plan}` ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
                  {subscription?.plan === plan ? `${PLAN_LABELS[plan]} (actuel)` : `Passer a ${PLAN_LABELS[plan]}`}
                </button>
              ))}
              {subscription?.status === 'active' ? (
                <button
                  onClick={handleCancel}
                  disabled={actionLoading === 'cancel'}
                  className="inline-flex items-center gap-2 rounded-xl border border-red-200 px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50 disabled:opacity-40"
                >
                  <XCircle className="h-4 w-4" /> Annuler l&apos;abonnement
                </button>
              ) : subscription?.status === 'cancelled' ? (
                <button
                  onClick={handleRenew}
                  disabled={actionLoading === 'renew'}
                  className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-40"
                >
                  Reactiver l&apos;abonnement
                </button>
              ) : null}
            </div>

            <div className="mt-4 flex flex-wrap gap-3 border-t border-slate-100 dark:border-slate-700 pt-4">
              {(['starter', 'business', 'enterprise'] as const).map((plan) => (
                <button
                  key={`checkout-${plan}`}
                  onClick={() => handleCheckout(plan)}
                  disabled={actionLoading === `checkout-${plan}`}
                  className="inline-flex items-center gap-2 rounded-xl bg-slate-900 dark:bg-white px-4 py-2 text-sm font-semibold text-white dark:text-slate-900 hover:bg-slate-800 dark:hover:bg-slate-100 disabled:opacity-40"
                >
                  <CreditCard className="h-4 w-4" /> Payer en ligne — {PLAN_LABELS[plan]}
                </button>
              ))}
              <button
                onClick={handlePortal}
                disabled={actionLoading === 'portal'}
                className="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 px-4 py-2 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 disabled:opacity-40"
              >
                <ExternalLink className="h-4 w-4" /> Portail de paiement
              </button>
            </div>
          </div>

          <div className="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 overflow-hidden">
            <div className="border-b border-slate-100 dark:border-slate-700 px-6 py-4">
              <h2 className="text-sm font-bold uppercase tracking-wider text-slate-800 dark:text-slate-200">Factures</h2>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900">
                    <th className="px-4 py-3 text-left font-medium text-slate-500">Numero</th>
                    <th className="px-4 py-3 text-right font-medium text-slate-500">Montant</th>
                    <th className="px-4 py-3 text-center font-medium text-slate-500">Statut</th>
                    <th className="px-4 py-3 text-left font-medium text-slate-500">Echeance</th>
                    <th className="px-4 py-3 text-center font-medium text-slate-500">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {invoices.length === 0 ? (
                    <tr><td colSpan={5} className="px-4 py-12 text-center text-slate-400">Aucune facture pour le moment</td></tr>
                  ) : invoices.map((invoice) => (
                    <tr key={invoice.id} className="border-b border-slate-100 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50">
                      <td className="px-4 py-3 font-medium text-slate-900 dark:text-white">
                        <span className="flex items-center gap-2"><FileText className="h-4 w-4 text-slate-400" />{invoice.number ?? `#${invoice.id}`}</span>
                      </td>
                      <td className="px-4 py-3 text-right text-slate-900 dark:text-white tabular-nums">{formatCurrency(invoice.total ?? invoice.amount, invoice.currency)}</td>
                      <td className="px-4 py-3 text-center">
                        <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-semibold ${STATUS_LABELS[invoice.status]?.className ?? 'bg-slate-100 text-slate-600'}`}>
                          {STATUS_LABELS[invoice.status]?.label ?? invoice.status}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-slate-600 dark:text-slate-400">
                        {invoice.due_date ? new Date(invoice.due_date).toLocaleDateString('fr-FR') : '—'}
                      </td>
                      <td className="px-4 py-3 text-center">
                        <button onClick={() => downloadInvoicePdf(invoice.id)} className="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400 hover:text-emerald-600" title="Telecharger PDF">
                          <Download className="h-4 w-4" />
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </>
      )}
    </ModulePageShell>
  );
}
