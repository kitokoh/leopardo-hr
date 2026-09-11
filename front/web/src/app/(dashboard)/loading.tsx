/**
 * État de chargement du tableau de bord.
 *
 * Évite l'écran vide pendant la résolution d'un segment de route : la coquille
 * reste stable et l'utilisateur voit une progression au lieu d'un flash blanc.
 */
export default function DashboardLoading() {
  return (
    <div className="px-4 py-8 sm:px-6 lg:px-8" role="status" aria-live="polite" aria-busy="true">
      <span className="sr-only">Chargement du tableau de bord…</span>

      <div className="animate-pulse space-y-6">
        <div className="h-8 w-56 rounded-lg bg-slate-200 dark:bg-slate-800" />
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {Array.from({ length: 4 }).map((_, index) => (
            <div
              key={index}
              className="h-28 rounded-2xl border border-slate-200 bg-slate-100 dark:border-slate-800 dark:bg-slate-900"
            />
          ))}
        </div>
        <div className="h-64 rounded-2xl border border-slate-200 bg-slate-100 dark:border-slate-800 dark:bg-slate-900" />
      </div>
    </div>
  );
}
