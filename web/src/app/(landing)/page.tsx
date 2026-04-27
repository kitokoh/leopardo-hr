import Link from 'next/link';

export default function LandingPage() {
  return (
    <div className="flex flex-col min-h-screen">
      <header className="px-4 lg:px-6 h-14 flex items-center">
        <Link className="flex items-center justify-center" href="#">
          <span className="font-bold text-xl">Leopardo RH</span>
        </Link>
        <nav className="ml-auto flex gap-4 sm:gap-6">
          <Link className="text-sm font-medium hover:underline underline-offset-4" href="#features">
            Fonctionnalités
          </Link>
          <Link className="text-sm font-medium hover:underline underline-offset-4" href="#pricing">
            Tarifs
          </Link>
          <Link className="text-sm font-medium hover:underline underline-offset-4" href="/auth/login">
            Connexion
          </Link>
        </nav>
      </header>
      <main className="flex-1">
        <section className="w-full py-12 md:py-24 lg:py-32 xl:py-48 bg-slate-50">
          <div className="container px-4 md:px-6 mx-auto">
            <div className="flex flex-col items-center space-y-4 text-center">
              <div className="space-y-2">
                <h1 className="text-3xl font-bold tracking-tighter sm:text-4xl md:text-5xl lg:text-6xl/none">
                  Simplifiez vos RH, optimisez vos performances
                </h1>
                <p className="mx-auto max-w-[700px] text-gray-500 md:text-xl dark:text-gray-400">
                  La solution tout-en-un pour la gestion du personnel, du pointage à la paie.
                </p>
              </div>
              <div className="space-x-4">
                <Link
                  className="inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-white shadow transition-colors hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50"
                  href="/auth/login"
                >
                  Essai gratuit 14 jours
                </Link>
                <Link
                  className="inline-flex h-9 items-center justify-center rounded-md border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-900 shadow-sm transition-colors hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50"
                  href="#"
                >
                  Demander une démo
                </Link>
              </div>
            </div>
          </div>
        </section>
        <section id="features" className="w-full py-12 md:py-24 lg:py-32">
          <div className="container px-4 md:px-6 mx-auto">
            <div className="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
              <div className="flex flex-col items-center space-y-2 border-gray-800 p-4 rounded-lg">
                <h3 className="text-xl font-bold">Pointage</h3>
                <p className="text-sm text-gray-500 text-center">
                  Gestion précise des entrées et sorties, compatible avec ZKTeco.
                </p>
              </div>
              <div className="flex flex-col items-center space-y-2 border-gray-800 p-4 rounded-lg">
                <h3 className="text-xl font-bold">Absences</h3>
                <p className="text-sm text-gray-500 text-center">
                  Suivi simplifié des congés et des absences.
                </p>
              </div>
              <div className="flex flex-col items-center space-y-2 border-gray-800 p-4 rounded-lg">
                <h3 className="text-xl font-bold">Paie</h3>
                <p className="text-sm text-gray-500 text-center">
                  Calcul automatique de la paie selon les règles locales.
                </p>
              </div>
              <div className="flex flex-col items-center space-y-2 border-gray-800 p-4 rounded-lg">
                <h3 className="text-xl font-bold">Tâches</h3>
                <p className="text-sm text-gray-500 text-center">
                  Attribution et suivi des tâches pour vos employés.
                </p>
              </div>
            </div>
          </div>
        </section>
        <section id="pricing" className="w-full py-12 md:py-24 lg:py-32 bg-slate-50">
          <div className="container px-4 md:px-6 mx-auto">
            <h2 className="text-3xl font-bold tracking-tighter text-center mb-12">Nos Tarifs</h2>
            <div className="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
              <div className="flex flex-col p-6 bg-white shadow-lg rounded-lg justify-between border border-gray-100">
                <div>
                  <h3 className="text-2xl font-bold text-center">Starter</h3>
                  <div className="mt-4 text-center text-zinc-600">
                    <span className="text-4xl font-bold">29€</span>/mois
                  </div>
                  <ul className="mt-6 space-y-4">
                    <li className="flex items-center">✓ Jusqu&apos;à 10 employés</li>
                    <li className="flex items-center">✓ Pointage de base</li>
                    <li className="flex items-center">✓ Support email</li>
                  </ul>
                </div>
                <Link className="mt-8 block w-full bg-slate-900 text-white text-center py-2 rounded-md" href="/auth/login">
                  Choisir Starter
                </Link>
              </div>
              <div className="flex flex-col p-6 bg-white shadow-lg rounded-lg justify-between border-2 border-primary relative">
                <div className="absolute top-0 right-0 bg-primary text-white text-xs px-2 py-1 rounded-bl-lg rounded-tr-lg">Populaire</div>
                <div>
                  <h3 className="text-2xl font-bold text-center">Business</h3>
                  <div className="mt-4 text-center text-zinc-600">
                    <span className="text-4xl font-bold">79€</span>/mois
                  </div>
                  <ul className="mt-6 space-y-4">
                    <li className="flex items-center">✓ Jusqu&apos;à 50 employés</li>
                    <li className="flex items-center">✓ Paie et Absences</li>
                    <li className="flex items-center">✓ Support prioritaire</li>
                  </ul>
                </div>
                <Link className="mt-8 block w-full bg-primary text-white text-center py-2 rounded-md" href="/auth/login">
                  Choisir Business
                </Link>
              </div>
              <div className="flex flex-col p-6 bg-white shadow-lg rounded-lg justify-between border border-gray-100">
                <div>
                  <h3 className="text-2xl font-bold text-center">Enterprise</h3>
                  <div className="mt-4 text-center text-zinc-600">
                    <span className="text-2xl font-bold">Sur devis</span>
                  </div>
                  <ul className="mt-6 space-y-4">
                    <li className="flex items-center">✓ Employés illimités</li>
                    <li className="flex items-center">✓ Customisation complète</li>
                    <li className="flex items-center">✓ Gestionnaire dédié</li>
                  </ul>
                </div>
                <Link className="mt-8 block w-full bg-slate-900 text-white text-center py-2 rounded-md" href="#">
                  Nous contacter
                </Link>
              </div>
            </div>
          </div>
        </section>
      </main>
      <footer className="flex flex-col gap-2 sm:flex-row py-6 w-full shrink-0 items-center px-4 md:px-6 border-t">
        <p className="text-xs text-gray-500">© 2026 Leopardo RH. Tous droits réservés.</p>
        <nav className="sm:ml-auto flex gap-4 sm:gap-6">
          <Link className="text-xs hover:underline underline-offset-4" href="#">
            Conditions d&apos;utilisation
          </Link>
          <Link className="text-xs hover:underline underline-offset-4" href="#">
            Confidentialité
          </Link>
        </nav>
      </footer>
    </div>
  );
}
