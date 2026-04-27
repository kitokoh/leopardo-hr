import Link from 'next/link';

export default function DashboardLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <div className="flex min-h-screen bg-app-card">
      {/* Sidebar */}
      <aside className="w-64 bg-slate-900 text-white hidden md:flex flex-col">
        <div className="p-6">
          <h1 className="text-2xl font-bold tracking-tight">Leopardo RH</h1>
          <p className="text-xs text-slate-400 mt-1 uppercase tracking-widest font-semibold">Back-office Manager</p>
        </div>

        <nav className="flex-1 mt-4">
          <div className="px-4 mb-2">
            <p className="text-[10px] font-bold text-slate-500 uppercase tracking-widest px-2">Général</p>
          </div>
          <Link href="/dashboard" className="flex items-center gap-3 px-6 py-3 hover:bg-slate-800 transition-colors bg-slate-800/50 border-r-4 border-rh">
            <span className="w-2 h-2 rounded-full bg-rh"></span>
            Tableau de bord
          </Link>

          <div className="px-4 mt-6 mb-2">
            <p className="text-[10px] font-bold text-slate-500 uppercase tracking-widest px-2">Module RH</p>
          </div>
          <Link href="/employees" className="flex items-center gap-3 px-6 py-3 hover:bg-slate-800 transition-colors text-slate-300 hover:text-white">
            <span className="w-2 h-2 rounded-full bg-slate-600"></span>
            Employés
          </Link>
          <Link href="/attendance" className="flex items-center gap-3 px-6 py-3 hover:bg-slate-800 transition-colors text-slate-300 hover:text-white">
            <span className="w-2 h-2 rounded-full bg-slate-600"></span>
            Pointages
          </Link>
          <Link href="/absences" className="flex items-center gap-3 px-6 py-3 hover:bg-slate-800 transition-colors text-slate-300 hover:text-white">
            <span className="w-2 h-2 rounded-full bg-slate-600"></span>
            Absences
          </Link>

          <div className="px-4 mt-6 mb-2 opacity-40">
            <p className="text-[10px] font-bold text-slate-500 uppercase tracking-widest px-2">Phase 2</p>
          </div>
          <div className="flex items-center gap-3 px-6 py-3 text-slate-500 cursor-not-allowed italic text-sm">
            <span className="w-2 h-2 rounded-full bg-finance opacity-30"></span>
            Finance (Bientôt)
          </div>
          <div className="flex items-center gap-3 px-6 py-3 text-slate-500 cursor-not-allowed italic text-sm">
            <span className="w-2 h-2 rounded-full bg-security opacity-30"></span>
            Caméras (Bientôt)
          </div>
        </nav>

        {/* Leo AI Sidebar Prompt */}
        <div className="p-4 m-4 rounded-xl bg-ia/10 border border-ia/20">
          <div className="flex items-center gap-2 text-ia mb-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/></svg>
            <span className="text-xs font-bold uppercase tracking-wider">Leo IA</span>
          </div>
          <p className="text-[10px] text-slate-400 leading-relaxed">
            Leo arrive bientôt sur le web pour vous aider à analyser vos données RH par simple commande vocale.
          </p>
        </div>

        <div className="p-6 border-t border-slate-800">
          <div className="flex items-center gap-3 mb-4">
            <div className="w-8 h-8 rounded-full bg-rh flex items-center justify-center font-bold text-xs">MA</div>
            <div className="overflow-hidden">
              <p className="text-xs font-bold truncate">Manager Alpha</p>
              <p className="text-[10px] text-slate-500 truncate">Sidi Bel Abbès</p>
            </div>
          </div>
          <button className="w-full py-2 px-4 rounded-lg bg-slate-800 text-xs font-semibold hover:bg-red-900/20 hover:text-red-400 transition-all">
            Déconnexion
          </button>
        </div>
      </aside>

      {/* Main Content */}
      <div className="flex-1 flex flex-col">
        <header className="h-16 bg-white border-b border-app-border flex items-center justify-between px-8 sticky top-0 z-10">
          <h2 className="text-lg font-bold text-slate-800">Tableau de bord</h2>
          <div className="flex items-center gap-4">
            <div className="flex items-center gap-2 px-3 py-1.5 rounded-full bg-rh-light text-rh-dark text-[10px] font-bold uppercase tracking-wider">
              <span className="w-1.5 h-1.5 rounded-full bg-rh animate-pulse"></span>
              Live: 18 Présents
            </div>
          </div>
        </header>
        <main className="p-8 max-w-7xl mx-auto w-full">
          {children}
        </main>
      </div>
    </div>
  );
}
