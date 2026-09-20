import { Star } from "lucide-react";

interface RatingStarsProps {
  /** Moyenne 0..5 (null = aucun avis). */
  rating: number | null;
  count?: number;
  className?: string;
}

/**
 * Note moyenne — 5 étoiles remplies proportionnellement + compteur d'avis.
 * Accessible : la valeur est portée par un libellé texte (aria-label).
 */
export function RatingStars({ rating, count, className }: RatingStarsProps) {
  if (rating === null || (count !== undefined && count === 0)) {
    return (
      <span className={`text-xs text-stone-400 ${className ?? ""}`}>Aucun avis</span>
    );
  }

  const label = `Note ${rating.toLocaleString("fr-FR")} sur 5${
    count !== undefined ? ` (${count} avis)` : ""
  }`;

  return (
    <span
      role="img"
      aria-label={label}
      className={`inline-flex items-center gap-1 ${className ?? ""}`}
    >
      <span aria-hidden="true" className="flex items-center gap-0.5">
        {[1, 2, 3, 4, 5].map((position) => (
          <Star
            key={position}
            className={`h-4 w-4 ${
              rating >= position - 0.25
                ? "fill-amber-400 text-amber-400"
                : "fill-stone-200 text-stone-200"
            }`}
          />
        ))}
      </span>
      <span aria-hidden="true" className="text-xs font-medium text-stone-600">
        {rating.toLocaleString("fr-FR")}
        {count !== undefined ? ` (${count})` : ""}
      </span>
    </span>
  );
}
