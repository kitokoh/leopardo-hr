'use client';

import React, { useEffect, useState } from 'react';
import { apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { Clock3, Landmark, Link2, TrendingUp, Users, Wallet, Coins } from 'lucide-react';

type Commission = {
  id: string;
  company_id: string;
  created_at: string;
  status: string;
  amount: number;
};

type PartnerData = {
  stats: {
    total_conversions: number;
    total_earned: number;
    pending_approval: number;
    approved_upcoming: number;
  };
  recent_commissions: Commission[];
};

export default function PartnerDashboard() {
  const [data, setData] = useState<PartnerData | null>(null);
  const [status, setStatus] = useState('loading'); // 'not_applied', 'pending', 'approved', 'loading'

  const fetchData = async () => {
    try {
      const response = await apiFetch('/partner/stats');
      const payload = await response.json();

      // If the API returns success, we are an approved partner
      setData(payload);
      setStatus('approved');
    } catch (error: any) {
      if (error?.code === 'NOT_A_PARTNER' || error?.response?.data?.code === 'NOT_A_PARTNER') {
        setStatus('not_applied');
      } else {
        console.error("Failed to fetch partner stats", error);
        // Show not applied instead of hanging forever on error
        setStatus('not_applied');
      }
    }
  };

  useEffect(() => {
    fetchData();
  }, []);

  const handleApply = async (type: string) => {
    try {
      await apiFetch('/partner/apply', {
        method: 'POST',
        body: JSON.stringify({ type })
      });
      setStatus('pending');
    } catch (error) {
      alert("Erreur lors de la candidature : " + (error as Error).message);
    }
  };

  if (status === 'loading') return <div className="p-8 text-center text-sm font-medium text-slate-500">Chargement de votre espace...</div>;

  if (status === 'not_applied') {
    return (
      <div className="mx-auto max-w-2xl">
        <div className="rounded-3xl border border-brand-100 bg-white p-8 text-center shadow-premium">
          <div className="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-brand-50">
            <Users className="h-10 w-10 text-brand-600" aria-hidden="true" />
          </div>
          <h1 className="mb-4 text-3xl font-black text-slate-950">Devenir Partenaire</h1>
          <p className="mb-8 leading-relaxed text-slate-600">
            Rejoignez l&apos;ecosysteme Leopardo RH et gagnez des commissions sur chaque entreprise que vous parrainez.
            Jusqu&apos;a 20% de commission recurrente.
          </p>
          <div className="flex flex-col gap-3">
            <button
              onClick={() => handleApply('individual')}
              className="rounded-2xl bg-brand-600 px-8 py-4 font-bold text-white transition-all hover:bg-brand-700"
            >
              Postuler en tant qu&apos;Individuel
            </button>
            <button
              onClick={() => handleApply('agency')}
              className="rounded-2xl border border-brand-600 px-8 py-4 font-bold text-brand-600 transition-all hover:bg-brand-50"
            >
              Postuler en tant qu&apos;Agence
            </button>
          </div>
        </div>
      </div>
    );
  }

  if (status === 'pending') {
    return (
      <div className="mx-auto max-w-2xl text-center">
        <div className="rounded-3xl border border-amber-100 bg-white p-8 shadow-sm">
          <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-amber-50">
            <Clock3 className="h-8 w-8 text-amber-600" aria-hidden="true" />
          </div>
          <h2 className="mb-2 text-2xl font-black text-slate-950">Candidature en cours</h2>
          <p className="text-slate-500">Votre demande est en cours de validation par notre equipe commerciale. Vous recevrez un email des que votre acces sera active.</p>
        </div>
      </div>
    );
  }

  return (
    <ModulePageShell
      title="Dashboard Partenaire"
      subtitle="Suivez vos conversions et vos commissions Leopardo RH — statut partenaire actif."
      accentClassName="bg-gradient-to-br from-brand-500/10 via-white to-white"
    >
      <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
        <MetricCard label="Conversions" value={data?.stats?.total_conversions || 0} icon={TrendingUp} accent="text-brand-600 bg-brand-50" />
        <MetricCard label="Gains Totaux" value={((data?.stats?.total_earned || 0) / 100).toFixed(2) + ' \u20ac'} icon={Coins} accent="text-emerald-600 bg-emerald-50" />
        <MetricCard label="En attente" value={((data?.stats?.pending_approval || 0) / 100).toFixed(2) + ' \u20ac'} icon={Clock3} accent="text-amber-600 bg-amber-50" />
        <MetricCard label="Solde Retirable" value={((data?.stats?.approved_upcoming || 0) / 100).toFixed(2) + ' \u20ac'} icon={Wallet} accent="text-blue-600 bg-blue-50" />
      </div>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <section className="overflow-hidden rounded-3xl border border-app-border bg-white shadow-sm lg:col-span-2">
          <div className="border-b border-app-border px-6 py-4">
            <h2 className="text-sm font-bold uppercase tracking-wider text-slate-800">Dernieres Commissions</h2>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-app-border bg-slate-50/50">
                  <th className="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Tenant ID</th>
                  <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Date</th>
                  <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Statut</th>
                  <th className="px-6 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500">Montant</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-app-border">
                {data?.recent_commissions?.map(comm => (
                  <tr key={comm.id} className="transition-colors hover:bg-slate-50/60">
                    <td className="px-6 py-4 font-mono text-sm font-bold text-slate-950">{comm.company_id.substring(0, 8)}...</td>
                    <td className="px-4 py-4 text-sm text-slate-500">{new Date(comm.created_at).toLocaleDateString()}</td>
                    <td className="px-4 py-4">
                      <span className={`inline-flex rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider ${
                        comm.status === 'paid' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'
                      }`}>
                        {comm.status}
                      </span>
                    </td>
                    <td className="px-6 py-4 text-right text-sm font-bold text-slate-950">{(comm.amount / 100).toFixed(2)} \u20ac</td>
                  </tr>
                ))}
                {(!data?.recent_commissions || data.recent_commissions.length === 0) && (
                  <tr><td colSpan={4} className="px-6 py-10 text-center text-sm italic text-slate-500">Aucune commission enregistree.</td></tr>
                )}
              </tbody>
            </table>
          </div>
        </section>

        <div className="space-y-6">
          <section className="rounded-3xl border border-app-border bg-white p-6 shadow-sm">
            <div className="mb-4 flex items-center gap-3">
              <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                <Landmark className="h-5 w-5" aria-hidden="true" />
              </div>
              <h3 className="text-xs font-bold uppercase tracking-widest text-slate-800">Paiement</h3>
            </div>
            <div className="space-y-4">
              <p className="text-xs leading-relaxed text-slate-500">
                Vos commissions sont payees une fois le seuil atteint.
                Verifiez que vos coordonnees bancaires sont a jour.
              </p>
              <button
                onClick={() => alert("Fonctionnalite en cours de deploiement.")}
                className="w-full rounded-xl bg-slate-950 py-3 text-sm font-bold text-white transition-opacity hover:opacity-90"
              >
                Demander un virement
              </button>
            </div>
          </section>

          <section className="rounded-3xl bg-brand-600 p-6 text-white shadow-lg shadow-brand-500/20">
            <div className="mb-4 flex items-center gap-2 opacity-90">
              <Link2 className="h-4 w-4" aria-hidden="true" />
              <h3 className="text-xs font-bold uppercase tracking-widest">Lien de parrainage</h3>
            </div>
            <div className="mb-4 break-all rounded-xl bg-white/10 p-3 font-mono text-xs">
              https://leopardo-rh.com/p/DEFAULT
            </div>
            <button className="w-full rounded-xl bg-white py-2 text-sm font-bold text-brand-700 transition-colors hover:bg-brand-50">
              Copier mon lien
            </button>
          </section>
        </div>
      </div>
    </ModulePageShell>
  );
}

function MetricCard({ label, value, icon: Icon, accent }: { label: string; value: string | number | undefined; icon: typeof TrendingUp; accent: string }) {
  return (
    <div className="rounded-2xl border border-app-border bg-white p-5 shadow-sm">
      <div className={`mb-3 inline-flex h-10 w-10 items-center justify-center rounded-xl ${accent}`}>
        <Icon className="h-5 w-5" aria-hidden="true" />
      </div>
      <p className="text-2xl font-black text-slate-950">{value ?? '0'}</p>
      <p className="text-xs font-bold uppercase tracking-widest text-slate-400">{label}</p>
    </div>
  );
}
