interface LoadingGridProps {
  count?: number;
}

/** Squelettes de cartes produit pendant le chargement. */
export function LoadingGrid({ count = 8 }: LoadingGridProps) {
  return (
    <div
      role="status"
      aria-label="Chargement des produits"
      className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4"
    >
      {Array.from({ length: count }, (_, index) => (
        <div
          key={index}
          className="animate-pulse overflow-hidden rounded-2xl border border-stone-200 bg-white"
        >
          <div className="aspect-square w-full bg-stone-100" />
          <div className="space-y-2 p-4">
            <div className="h-3.5 w-3/4 rounded bg-stone-100" />
            <div className="h-3 w-1/2 rounded bg-stone-100" />
            <div className="h-4 w-1/3 rounded bg-stone-100" />
          </div>
        </div>
      ))}
      <span className="sr-only">Chargement…</span>
    </div>
  );
}
