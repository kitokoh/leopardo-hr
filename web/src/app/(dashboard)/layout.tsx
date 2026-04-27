import Link from 'next/link';

export default function DashboardLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <div className="flex min-h-screen bg-slate-100">
      {/* Sidebar */}
      <aside className="w-64 bg-slate-900 text-white hidden md:block">
        <div className="p-6">
          <h1 className="text-2xl font-bold">Leopardo RH</h1>
        </div>
        <nav className="mt-6">
          <Link href="/dashboard" className="block px-6 py-3 hover:bg-slate-800 bg-slate-800 border-l-4 border-primary">
            Tableau de bord
          </Link>
          <Link href="/employees" className="block px-6 py-3 hover:bg-slate-800">
            Employés
          </Link>
          <Link href="/attendance" className="block px-6 py-3 hover:bg-slate-800">
            Pointages
          </Link>
          <Link href="/payroll" className="block px-6 py-3 hover:bg-slate-800">
            Paie
          </Link>
          <Link href="/settings" className="block px-6 py-3 hover:bg-slate-800">
            Paramètres
          </Link>
        </nav>
      </aside>

      {/* Main Content */}
      <div className="flex-1 flex flex-col">
        <header className="h-16 bg-white shadow-sm flex items-center justify-between px-8">
          <h2 className="text-xl font-semibold text-gray-800">Tableau de bord</h2>
          <div className="flex items-center gap-4">
            <span className="text-sm text-gray-600">Manager Alpha</span>
            <button className="text-sm text-red-600 hover:underline">Déconnexion</button>
          </div>
        </header>
        <main className="p-8">
          {children}
        </main>
      </div>
    </div>
  );
}
