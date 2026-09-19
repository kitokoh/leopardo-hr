import { ChevronLeft, ChevronRight } from "lucide-react";
import Link from "next/link";

interface PaginationProps {
  page: number;
  lastPage: number;
  /** Construit l'URL d'une page (query préservée par l'appelant). */
  hrefFor: (page: number) => string;
}

/** Pagination par liens (fonctionne sans JavaScript). */
export function Pagination({ page, lastPage, hrefFor }: PaginationProps) {
  if (lastPage <= 1) return null;

  const linkClass =
    "inline-flex h-10 items-center gap-1 rounded-full border border-stone-300 bg-white px-4 text-sm font-medium text-stone-700 transition hover:border-amber-400 hover:text-amber-700";
  const disabledClass =
    "inline-flex h-10 items-center gap-1 rounded-full border border-stone-200 bg-stone-50 px-4 text-sm font-medium text-stone-300";

  return (
    <nav aria-label="Pagination" className="flex items-center justify-center gap-3">
      {page > 1 ? (
        <Link href={hrefFor(page - 1)} rel="prev" className={linkClass}>
          <ChevronLeft aria-hidden="true" className="h-4 w-4" />
          Précédent
        </Link>
      ) : (
        <span aria-disabled="true" className={disabledClass}>
          <ChevronLeft aria-hidden="true" className="h-4 w-4" />
          Précédent
        </span>
      )}
      <span className="text-sm text-stone-500" aria-current="page">
        Page {page} sur {lastPage}
      </span>
      {page < lastPage ? (
        <Link href={hrefFor(page + 1)} rel="next" className={linkClass}>
          Suivant
          <ChevronRight aria-hidden="true" className="h-4 w-4" />
        </Link>
      ) : (
        <span aria-disabled="true" className={disabledClass}>
          Suivant
          <ChevronRight aria-hidden="true" className="h-4 w-4" />
        </span>
      )}
    </nav>
  );
}
