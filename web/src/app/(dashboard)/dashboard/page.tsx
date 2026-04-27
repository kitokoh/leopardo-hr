export default function DashboardPage() {
  return (
    <div className="space-y-8">
      {/* Stats Cards */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div className="bg-white p-6 rounded-2xl shadow-sm border border-app-border group hover:border-rh/30 transition-all">
          <div className="flex items-center justify-between mb-4">
            <div className="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center text-slate-500 group-hover:bg-rh-light group-hover:text-rh transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <span className="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Total Équipe</span>
          </div>
          <p className="text-4xl font-black text-slate-900">24</p>
          <p className="text-xs text-slate-500 mt-2 font-medium">Employés actifs ce mois</p>
        </div>

        <div className="bg-white p-6 rounded-2xl shadow-sm border border-app-border group hover:border-success/30 transition-all">
          <div className="flex items-center justify-between mb-4">
            <div className="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center text-slate-500 group-hover:bg-rh-light group-hover:text-success transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <span className="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Présents</span>
          </div>
          <p className="text-4xl font-black text-success">18</p>
          <p className="text-xs text-slate-500 mt-2 font-medium">Pointés aujourd&apos;hui</p>
        </div>

        <div className="bg-white p-6 rounded-2xl shadow-sm border border-app-border group hover:border-warning/30 transition-all">
          <div className="flex items-center justify-between mb-4">
            <div className="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center text-slate-500 group-hover:bg-warning/10 group-hover:text-warning transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <span className="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Retards</span>
          </div>
          <p className="text-4xl font-black text-warning">2</p>
          <p className="text-xs text-slate-500 mt-2 font-medium">Minutes cumulées : 45m</p>
        </div>
      </div>

      {/* Main Content Area */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* Recent Activity */}
        <div className="lg:col-span-2 bg-white rounded-3xl shadow-sm border border-app-border overflow-hidden">
          <div className="p-6 border-b border-app-border flex items-center justify-between">
            <h3 className="text-sm font-bold text-slate-800 uppercase tracking-wider">Activité de pointage</h3>
            <button className="text-xs font-bold text-rh hover:underline">Voir tout</button>
          </div>
          <div className="divide-y divide-app-border">
            {[1, 2, 3, 4, 5].map((i) => (
              <div key={i} className="flex items-center justify-between p-6 hover:bg-slate-50 transition-colors">
                <div className="flex items-center gap-4">
                  <div className="w-12 h-12 rounded-2xl bg-slate-100 flex items-center justify-center font-black text-slate-400">
                    {String.fromCharCode(64 + i)}
                  </div>
                  <div>
                    <p className="text-sm font-bold text-slate-900">Collaborateur {i}</p>
                    <p className="text-[10px] text-slate-500 font-semibold uppercase tracking-tight">Check-in à 08:{30 + i} • Bureau Central</p>
                  </div>
                </div>
                <span className="text-[10px] font-black px-3 py-1.5 bg-rh-light text-rh-dark rounded-full uppercase tracking-widest">
                  Présent
                </span>
              </div>
            ))}
          </div>
        </div>

        {/* Leo IA Prompt / Right Column */}
        <div className="space-y-6">
          <div className="bg-ia/5 border border-ia/20 rounded-3xl p-6 relative overflow-hidden">
            <div className="absolute top-0 right-0 p-4 opacity-10">
              <svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="text-ia"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/></svg>
            </div>
            <h4 className="text-xs font-black text-ia uppercase tracking-[0.2em] mb-4">Conseil de Leo</h4>
            <p className="text-sm text-slate-700 leading-relaxed font-medium">
              &quot;Aujourd&apos;hui, vos retards sont en baisse de 15% par rapport à hier. Voulez-vous que j&apos;envoie un message de félicitations à l&apos;équipe ?&quot;
            </p>
            <div className="mt-6 flex gap-2">
              <button className="flex-1 py-2 rounded-xl bg-ia text-white text-[10px] font-bold uppercase tracking-widest hover:shadow-lg hover:shadow-ia/30 transition-all opacity-50 cursor-not-allowed">
                Oui, Leo
              </button>
              <button className="flex-1 py-2 rounded-xl bg-white border border-ia/20 text-ia text-[10px] font-bold uppercase tracking-widest hover:bg-ia/5 transition-all opacity-50 cursor-not-allowed">
                Plus tard
              </button>
            </div>
          </div>

          <div className="bg-white rounded-3xl p-6 border border-app-border shadow-sm">
            <h4 className="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4">Résumé financier (Est.)</h4>
            <div className="space-y-3">
              <div className="flex justify-between items-end">
                <span className="text-xs text-slate-500 font-medium">Dû ce mois</span>
                <span className="text-xl font-black text-slate-900">452.000 DA</span>
              </div>
              <div className="w-full h-1.5 bg-slate-100 rounded-full overflow-hidden">
                <div className="h-full bg-rh w-2/3"></div>
              </div>
              <p className="text-[10px] text-slate-400 font-medium italic">Basé sur 18 jours de pointage validés.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
