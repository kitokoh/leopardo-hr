'use client';

import { useEffect, useMemo, useSyncExternalStore } from 'react';
import { getCopy, getPreferredLocale, type AppLocale } from '@/lib/i18n';

const emptySubscribe = () => () => {};

export default function DashboardPage() {
  const locale = useSyncExternalStore<AppLocale>(emptySubscribe, getPreferredLocale, () => 'fr');
  const labels = useMemo(() => getCopy(locale), [locale]);

  useEffect(() => {
    document.documentElement.lang = locale;
  }, [locale]);

  return (
    <div className="space-y-8">
      <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
        <div className="group rounded-2xl border border-app-border bg-white p-6 shadow-sm transition-all hover:border-rh/30">
          <div className="mb-4 flex items-center justify-between">
            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-500 transition-colors group-hover:bg-rh-light group-hover:text-rh">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <span className="text-[10px] font-bold uppercase tracking-widest text-slate-400">{labels.dashboard.employees}</span>
          </div>
          <p className="text-4xl font-black text-slate-900">24</p>
          <p className="mt-2 text-xs font-medium text-slate-500">Employes actifs ce mois</p>
        </div>

        <div className="group rounded-2xl border border-app-border bg-white p-6 shadow-sm transition-all hover:border-success/30">
          <div className="mb-4 flex items-center justify-between">
            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-500 transition-colors group-hover:bg-rh-light group-hover:text-success">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <span className="text-[10px] font-bold uppercase tracking-widest text-slate-400">{labels.dashboard.present}</span>
          </div>
          <p className="text-4xl font-black text-success">18</p>
          <p className="mt-2 text-xs font-medium text-slate-500">Pointes aujourd&apos;hui</p>
        </div>

        <div className="group rounded-2xl border border-app-border bg-white p-6 shadow-sm transition-all hover:border-warning/30">
          <div className="mb-4 flex items-center justify-between">
            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-500 transition-colors group-hover:bg-warning/10 group-hover:text-warning">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <span className="text-[10px] font-bold uppercase tracking-widest text-slate-400">{labels.dashboard.late}</span>
          </div>
          <p className="text-4xl font-black text-warning">2</p>
          <p className="mt-2 text-xs font-medium text-slate-500">Minutes cumulees : 45m</p>
        </div>
      </div>

      <div className="grid grid-cols-1 gap-8 lg:grid-cols-3">
        <div className="overflow-hidden rounded-3xl border border-app-border bg-white shadow-sm lg:col-span-2">
          <div className="flex items-center justify-between border-b border-app-border p-6">
            <h3 className="text-sm font-bold uppercase tracking-wider text-slate-800">{labels.dashboard.activity}</h3>
            <button className="text-xs font-bold text-rh hover:underline">Voir tout</button>
          </div>
          <div className="divide-y divide-app-border">
            {[1, 2, 3, 4, 5].map((i) => (
              <div key={i} className="flex items-center justify-between p-6 transition-colors hover:bg-slate-50">
                <div className="flex items-center gap-4">
                  <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 font-black text-slate-400">
                    {String.fromCharCode(64 + i)}
                  </div>
                  <div>
                    <p className="text-sm font-bold text-slate-900">{labels.dashboard.employeeLabel} {i}</p>
                    <p className="text-[10px] font-semibold uppercase tracking-tight text-slate-500">{labels.dashboard.checkInAt} 08:{30 + i} • Bureau Central</p>
                  </div>
                </div>
                <span className="rounded-full bg-rh-light px-3 py-1.5 text-[10px] font-black uppercase tracking-widest text-rh-dark">
                  {labels.dashboard.presentBadge}
                </span>
              </div>
            ))}
          </div>
        </div>

        <div className="space-y-6">
          <div className="relative overflow-hidden rounded-3xl border border-ia/20 bg-ia/5 p-6">
            <div className="absolute right-0 top-0 p-4 opacity-10">
              <svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="text-ia"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/></svg>
            </div>
            <h4 className="mb-4 text-xs font-black uppercase tracking-[0.2em] text-ia">Conseil de Leo</h4>
            <p className="text-sm font-medium leading-relaxed text-slate-700">
              &quot;Aujourd&apos;hui, vos retards sont en baisse de 15% par rapport a hier. Voulez-vous que j&apos;envoie un message de felicitations a l&apos;equipe ?&quot;
            </p>
            <div className="mt-6 flex gap-2">
              <button className="flex-1 cursor-not-allowed rounded-xl bg-ia py-2 text-[10px] font-bold uppercase tracking-widest text-white opacity-50 transition-all hover:shadow-lg hover:shadow-ia/30">
                Oui, Leo
              </button>
              <button className="flex-1 cursor-not-allowed rounded-xl border border-ia/20 bg-white py-2 text-[10px] font-bold uppercase tracking-widest text-ia opacity-50 transition-all hover:bg-ia/5">
                Plus tard
              </button>
            </div>
          </div>

          <div className="rounded-3xl border border-app-border bg-white p-6 shadow-sm">
            <h4 className="mb-4 text-[10px] font-black uppercase tracking-widest text-slate-400">Resume financier (Est.)</h4>
            <div className="space-y-3">
              <div className="flex items-end justify-between">
                <span className="text-xs font-medium text-slate-500">Du ce mois</span>
                <span className="text-xl font-black text-slate-900">452.000 DA</span>
              </div>
              <div className="h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                <div className="h-full w-2/3 bg-rh"></div>
              </div>
              <p className="text-[10px] font-medium italic text-slate-400">Base sur 18 jours de pointage valides.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
