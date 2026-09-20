import { RatingStars } from "@/components/RatingStars";
import { fetchProductReviews, type PublicReview } from "@/lib/api";

interface ReviewsSectionProps {
  productId: number;
}

function formatDate(value: string | null): string {
  if (!value) return "";
  try {
    return new Intl.DateTimeFormat("fr-FR", { dateStyle: "long" }).format(new Date(value));
  } catch {
    return "";
  }
}

/**
 * Section avis clients (#7814) — fiche produit. Rendu serveur : avis
 * approuvés paginés (première page) + note moyenne. Les avis sont
 * vérifiés : seuls les acheteurs livrés peuvent en déposer (depuis
 * « Mes commandes »).
 */
export async function ReviewsSection({ productId }: ReviewsSectionProps) {
  let reviews: PublicReview[] = [];
  let ratingAvg: number | null = null;
  let ratingCount = 0;
  let failed = false;

  try {
    const payload = await fetchProductReviews(productId);
    reviews = payload.reviews.data;
    ratingAvg = payload.ratingAvg;
    ratingCount = payload.ratingCount;
  } catch {
    failed = true;
  }

  return (
    <section aria-labelledby="reviews-heading" className="mt-12">
      <div className="flex flex-wrap items-center gap-3">
        <h2 id="reviews-heading" className="text-xl font-bold text-stone-900">
          Avis clients
        </h2>
        <RatingStars rating={ratingAvg} count={ratingCount} />
      </div>
      <p className="mt-1 text-xs text-stone-500">
        Avis vérifiés : seuls les clients dont la commande a été livrée peuvent noter ce produit,
        depuis « Mes commandes ».
      </p>

      {failed ? (
        <p className="mt-4 rounded-xl bg-stone-50 px-4 py-3 text-sm text-stone-500">
          Impossible de charger les avis pour le moment.
        </p>
      ) : reviews.length === 0 ? (
        <p className="mt-4 rounded-xl bg-stone-50 px-4 py-3 text-sm text-stone-500">
          Aucun avis pour l&apos;instant. Commandez ce produit et soyez le premier à donner votre
          avis après livraison !
        </p>
      ) : (
        <ul className="mt-4 flex flex-col gap-3">
          {reviews.map((review, index) => (
            <li
              key={`${review.buyer_name}-${review.created_at ?? index}`}
              className="rounded-2xl border border-stone-200 bg-white p-4 shadow-sm"
            >
              <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-sm font-semibold text-stone-900">{review.buyer_name}</p>
                <RatingStars rating={review.rating} />
              </div>
              {review.comment ? (
                <p className="mt-2 whitespace-pre-line text-sm text-stone-600">{review.comment}</p>
              ) : null}
              {review.created_at ? (
                <p className="mt-2 text-xs text-stone-400">{formatDate(review.created_at)}</p>
              ) : null}
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}
