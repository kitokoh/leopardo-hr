'use client';

import React, { useEffect, useState } from 'react';
import { apiFetch } from '@/lib/api-client';

export default function PartnerDashboard() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [status, setStatus] = useState('loading'); // 'not_applied', 'pending', 'approved', 'loading'

  const fetchData = async () => {
    setLoading(true);
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
      }
    } finally {
      setLoading(false);
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

  if (status === 'loading') return <div className="p-8 text-center text-slate-500 font-medium">Chargement de votre espace...</div>;

  if (status === 'not_applied') {
    return (
      <div className="p-8 max-w-2xl mx-auto">
        <div className="bg-white dark:bg-slate-800 rounded-3xl p-8 border border-teal-100 dark:border-teal-900/30 shadow-xl shadow-teal-500/5 text-center">
          <div className="w-20 h-20 bg-teal-100 dark:bg-teal-900/30 rounded-full flex items-center justify-center mx-auto mb-6">
            <svg className="w-10 h-10 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
          </div>
          <h1 className="text-3xl font-bold text-slate-900 dark:text-white mb-4">Devenir Partenaire</h1>
          <p className="text-slate-600 dark:text-slate-400 mb-8 leading-relaxed">
            Rejoignez l'écosystème Leopardo RH et gagnez des commissions sur chaque entreprise que vous parrainez.
            Jusqu'à 20% de commission récurrente.
          </p>
          <div className="flex flex-col gap-3">
            <button
              onClick={() => handleApply('individual')}
              className="bg-teal-600 text-white px-8 py-4 rounded-2xl font-bold hover:bg-teal-700 transition-all"
            >
              Postuler en tant qu'Individuel
            </button>
            <button
              onClick={() => handleApply('agency')}
              className="bg-white border border-teal-600 text-teal-600 px-8 py-4 rounded-2xl font-bold hover:bg-teal-50 transition-all"
            >
              Postuler en tant qu'Agence
            </button>
          </div>
        </div>
      </div>
    );
  }

  if (status === 'pending') {
    return (
      <div className="p-8 max-w-2xl mx-auto text-center">
        <div className="bg-white dark:bg-slate-800 rounded-3xl p-8 border border-amber-100 dark:border-amber-900/30 shadow-sm">
          <div className="w-16 h-16 bg-amber-100 dark:bg-amber-900/30 rounded-full flex items-center justify-center mx-auto mb-4">
             <svg className="w-8 h-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-12 0 9 9 0 0112 0z" /></svg>
          </div>
          <h2 className="text-2xl font-bold text-slate-900 dark:text-white mb-2">Candidature en cours</h2>
          <p className="text-slate-500">Votre demande est en cours de validation par notre équipe commerciale. Vous recevrez un email dès que votre accès sera activé.</p>
        </div>
      </div>
    );
  }

  return (
    <div className="p-6 text-slate-900 dark:text-white">
      <header className="mb-8 flex justify-between items-start">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">Dashboard Partenaire</h1>
          <p className="text-slate-500 mt-2">Suivez vos conversions et vos commissions Leopardo RH.</p>
        </div>
        <div className="bg-emerald-500/10 text-emerald-600 px-4 py-2 rounded-xl text-sm font-bold border border-emerald-500/20">
          Statut: Partenaire Actif
        </div>
      </header>

      <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <MetricCard label="Conversions" value={data?.stats.total_conversions} color="text-teal-600" />
        <MetricCard label="Gains Totaux" value={(data?.stats.total_earned / 100).toFixed(2) + ' €'} color="text-emerald-600" />
        <MetricCard label="En attente" value={(data?.stats.pending_approval / 100).toFixed(2) + ' €'} color="text-amber-600" />
        <MetricCard label="Solde Retirable" value={(data?.stats.approved_upcoming / 100).toFixed(2) + ' €'} color="text-blue-600" />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div className="lg:col-span-2">
          <section className="bg-white dark:bg-slate-800 rounded-3xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div className="p-6 border-b border-slate-200 dark:border-slate-700">
              <h2 className="text-lg font-semibold tracking-tight">Dernières Commissions</h2>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full text-left">
                <thead className="bg-slate-50 dark:bg-slate-900/50">
                  <tr>
                    <th className="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Tenant ID</th>
                    <th className="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Date</th>
                    <th className="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Statut</th>
                    <th className="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Montant</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-200 dark:divide-slate-700">
                  {data?.recent_commissions?.map(comm => (
                    <tr key={comm.id} className="hover:bg-slate-50 dark:hover:bg-slate-900/30 transition-colors">
                      <td className="px-6 py-4 text-sm font-medium font-mono">{comm.company_id.substring(0, 8)}...</td>
                      <td className="px-6 py-4 text-sm text-slate-500">{new Date(comm.created_at).toLocaleDateString()}</td>
                      <td className="px-6 py-4">
                        <span className={`px-2.5 py-1 text-xs font-medium rounded-full ${
                          comm.status === 'paid' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'
                        }`}>
                          {comm.status}
                        </span>
                      </td>
                      <td className="px-6 py-4 text-sm font-semibold">{(comm.amount / 100).toFixed(2)} €</td>
                    </tr>
                  ))}
                  {(!data?.recent_commissions || data.recent_commissions.length === 0) && (
                    <tr><td colSpan="4" className="px-6 py-8 text-center text-slate-500 italic">Aucune commission enregistrée.</td></tr>
                  )}
                </tbody>
              </table>
            </div>
          </section>
        </div>

        <div className="space-y-6">
          <section className="bg-white dark:bg-slate-800 p-6 rounded-3xl border border-slate-200 dark:border-slate-700 shadow-sm">
            <h3 className="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-widest mb-4">Paiement</h3>
            <div className="space-y-4">
              <p className="text-xs text-slate-500 leading-relaxed">
                Vos commissions sont payées une fois le seuil atteint.
                Vérifiez que vos coordonnées bancaires sont à jour.
              </p>
              <button
                onClick={() => alert("Fonctionnalité en cours de déploiement.")}
                className="w-full py-3 bg-slate-900 dark:bg-white dark:text-slate-900 text-white rounded-xl font-bold text-sm hover:opacity-90 transition-opacity"
              >
                Demander un virement
              </button>
            </div>
          </section>

          <section className="bg-teal-600 p-6 rounded-3xl text-white shadow-lg shadow-teal-500/20">
            <h3 className="text-sm font-bold uppercase tracking-widest mb-4 opacity-80">Lien de parrainage</h3>
            <div className="bg-white/10 rounded-xl p-3 mb-4 font-mono text-xs break-all">
              https://leopardo-rh.com/p/DEFAULT
            </div>
            <button className="w-full py-2 bg-white text-teal-700 rounded-xl font-bold text-sm hover:bg-teal-50 transition-colors">
              Copier mon lien
            </button>
          </section>
        </div>
      </div>
    </div>
  );
}

function MetricCard({ label, value, color }) {
  return (
    <div className="bg-white dark:bg-slate-800 p-6 rounded-3xl shadow-sm border border-slate-200 dark:border-slate-700">
      <h3 className="text-xs font-bold text-slate-500 uppercase tracking-widest">{label}</h3>
      <p className={`text-2xl font-black mt-2 ${color}`}>{value ?? '0'}</p>
    </div>
  );
}
