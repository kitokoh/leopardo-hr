/**
 * loading.tsx — état de chargement racine (App Router).
 *
 * Audit UX 2026-11 : seuls 2 `loading.tsx` existaient pour ~180 pages ; les
 * segments publics (auth, boutiques, travel, vitrine…) n'avaient aucun état
 * de chargement et affichaient un écran blanc pendant la navigation.
 *
 * Squelette neutre, sans dépendance à un composant client : il s'affiche
 * immédiatement pendant le streaming du segment.
 */
export default function Loading() {
  return (
    <div
      role="status"
      aria-busy="true"
      aria-live="polite"
      className="flex min-h-[60vh] w-full items-center justify-center p-6"
    >
      <span className="sr-only">Chargement…</span>
      <div className="flex flex-col items-center gap-4">
        <div
          aria-hidden="true"
          className="h-10 w-10 animate-spin rounded-full border-2 border-slate-200 border-t-emerald-600"
        />
        <div aria-hidden="true" className="w-56 space-y-2">
          <div className="h-3 w-full animate-pulse rounded-full bg-slate-200" />
          <div className="h-3 w-2/3 animate-pulse rounded-full bg-slate-200" />
        </div>
      </div>
    </div>
  );
}
