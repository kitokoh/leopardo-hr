'use client';

import React, { useEffect, useState } from 'react';

export default function PartnerDashboard() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    // Dans un environnement réel, nous utiliserions un fetch authentifié
    // Ici, on simule l'appel à l'API /api/v1/partner/stats
    const fetchData = async () => {
      try {
        // simulation
        const response = {
          stats: {
            total_conversions: 15,
            total_earned: 67500, // en centimes
            pending_approval: 12000,
            approved_upcoming: 4500,
          },
          recent_companies: [
            { id: '1', name: 'Atlas Digital', created_at: '2026-06-12', status: 'active', commission: 4500 },
            { id: '2', name: 'Tech Solutions', created_at: '2026-06-10', status: 'trial', commission: 0 },
          ]
        };
        setData(response);
      } catch (error) {
        console.error("Failed to fetch partner stats", error);
      } finally {
        setLoading(false);
      }
    };

    fetchData();
  }, []);

  if (loading) return <div className="p-8">Chargement...</div>;

  return (
    <div className="p-6 text-slate-900 dark:text-white">
      <header className="mb-8">
        <h1 className="text-3xl font-bold">Dashboard Partenaire</h1>
        <p className="text-slate-500 mt-2">Suivez vos conversions et vos commissions Leopardo RH.</p>
      </header>

      <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <MetricCard label="Conversions" value={data?.stats.total_conversions} color="text-teal-600" />
        <MetricCard label="Gains Totaux" value={(data?.stats.total_earned / 100).toFixed(2) + ' €'} color="text-emerald-600" />
        <MetricCard label="En attente" value={(data?.stats.pending_approval / 100).toFixed(2) + ' €'} color="text-amber-600" />
        <MetricCard label="Approuvé" value={(data?.stats.approved_upcoming / 100).toFixed(2) + ' €'} color="text-blue-600" />
      </div>

      <section className="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div className="p-6 border-b border-slate-200 dark:border-slate-700 flex justify-between items-center">
          <h2 className="text-lg font-semibold">Entreprises Référées Récentes</h2>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full text-left">
            <thead className="bg-slate-50 dark:bg-slate-900/50">
              <tr>
                <th className="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Entreprise</th>
                <th className="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Date</th>
                <th className="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Statut</th>
                <th className="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Commission</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-200 dark:divide-slate-700">
              {data?.recent_companies.map(company => (
                <tr key={company.id} className="hover:bg-slate-50 dark:hover:bg-slate-900/30 transition-colors">
                  <td className="px-6 py-4 text-sm font-medium">{company.name}</td>
                  <td className="px-6 py-4 text-sm text-slate-500">{new Date(company.created_at).toLocaleDateString()}</td>
                  <td className="px-6 py-4">
                    <span className="px-2.5 py-1 text-xs font-medium rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">
                      {company.status}
                    </span>
                  </td>
                  <td className="px-6 py-4 text-sm font-semibold">{(company.commission / 100).toFixed(2)} €</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>
    </div>
  );
}

function MetricCard({ label, value, color }) {
  return (
    <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700">
      <h3 className="text-sm font-medium text-slate-500 uppercase tracking-wider">{label}</h3>
      <p className={`text-2xl font-bold mt-1 ${color}`}>{value}</p>
    </div>
  );
}
