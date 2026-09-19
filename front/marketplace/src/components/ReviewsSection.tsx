"use client";

import { Star } from "lucide-react";
import Link from "next/link";
import { useEffect, useState, type FormEvent } from "react";

import { useAccount } from "@/hooks/useAccount";
import { ApiError } from "@/lib/api";
import {
  fetchProductReviews,
  fetchSellerReviews,
  submitReview,
  type ReviewList,
} from "@/lib/account";
import { formatDate } from "@/lib/format";

type ReviewTarget =
  | { target_type: "product"; product_id: number }
  | { target_type: "seller"; seller: string };

/**
 * Avis & notations (#7814) — liste publique des avis APPROUVÉS (note
 * moyenne + prénom), et dépôt d'avis vérifié pour l'acheteur connecté
 * (commande livrée exigée côté serveur, modération avant publication).
 */
export function ReviewsSection({ target }: { target: ReviewTarget }) {
  const { session } = useAccount();
  const [reviews, setReviews] = useState<ReviewList | null>(null);
  const [loadError, setLoadError] = useState(false);
  const [rating, setRating] = useState(5);
  const [comment, setComment] = useState("");
  const [formState, setFormState] = useState<"idle" | "busy" | "sent">("idle");
  const [formError, setFormError] = useState<string | null>(null);

  const targetKey =
    target.target_type === "product" ? `p:${target.product_id}` : `s:${target.seller}`;

  useEffect(() => {
    let cancelled = false;
    const promise =
      target.target_type === "product"
        ? fetchProductReviews(target.product_id)
        : fetchSellerReviews(target.seller);
    promise
      .then((data) => {
        if (!cancelled) {
          setReviews(data);
          setLoadError(false);
        }
      })
      .catch(() => {
        if (!cancelled) setLoadError(true);
      });
    return () => {
      cancelled = true;
    };
    // La cible est entièrement décrite par targetKey (discriminant stable).
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [targetKey]);

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setFormError(null);
    setFormState("busy");
    try {
      await submitReview({
        ...target,
        rating,
        ...(comment.trim() ? { comment: comment.trim() } : {}),
      });
      setFormState("sent");
    } catch (err) {
      setFormState("idle");
      setFormError(err instanceof ApiError ? err.message : "Une erreur est survenue.");
    }
  };

  return (
    <section aria-labelledby="reviews-title" className="mt-12">
      <div className="flex flex-wrap items-center gap-3">
        <h2 id="reviews-title" className="text-lg font-semibold text-stone-900">
          Avis {target.target_type === "product" ? "sur ce produit" : "sur cette boutique"}
        </h2>
        {reviews && reviews.rating.count > 0 ? (
          <span className="inline-flex items-center gap-1 rounded-full bg-amber-50 px-3 py-1 text-sm font-semibold text-amber-800">
            <Star aria-hidden="true" className="h-4 w-4 fill-amber-500 text-amber-500" />
            {reviews.rating.average?.toLocaleString("fr-FR") ?? "—"} / 5 ·{" "}
            {reviews.rating.count} avis
          </span>
        ) : null}
      </div>

      {loadError ? (
        <p className="mt-4 text-sm text-stone-500">Les avis sont momentanément indisponibles.</p>
      ) : reviews === null ? (
        <div className="mt-4 h-20 animate-pulse rounded-2xl bg-stone-100" aria-hidden="true" />
      ) : reviews.data.length === 0 ? (
        <p className="mt-4 text-sm text-stone-500">
          Aucun avis publié pour le moment. Les avis proviennent d&apos;acheteurs livrés et sont
          modérés par la boutique.
        </p>
      ) : (
        <ul className="mt-4 space-y-3">
          {reviews.data.map((review) => (
            <li key={review.id} className="rounded-2xl border border-stone-200 bg-white p-4">
              <div className="flex items-center gap-2">
                <span
                  className="inline-flex items-center gap-0.5"
                  role="img"
                  aria-label={`${review.rating} sur 5`}
                >
                  {[1, 2, 3, 4, 5].map((value) => (
                    <Star
                      key={value}
                      aria-hidden="true"
                      className={`h-4 w-4 ${
                        value <= review.rating
                          ? "fill-amber-500 text-amber-500"
                          : "text-stone-300"
                      }`}
                    />
                  ))}
                </span>
                <span className="text-sm font-semibold text-stone-900">
                  {review.author ?? "Acheteur vérifié"}
                </span>
                {review.created_at ? (
                  <span className="text-xs text-stone-400">{formatDate(review.created_at)}</span>
                ) : null}
              </div>
              {review.comment ? (
                <p className="mt-2 text-sm text-stone-600">{review.comment}</p>
              ) : null}
            </li>
          ))}
        </ul>
      )}

      <div className="mt-6 rounded-2xl border border-stone-200 bg-stone-50 p-4 sm:p-5">
        {!session ? (
          <p className="text-sm text-stone-600">
            <Link href="/compte/connexion" className="font-semibold text-amber-700 transition hover:text-amber-800">
              Connectez-vous
            </Link>{" "}
            pour donner votre avis — réservé aux acheteurs livrés.
          </p>
        ) : formState === "sent" ? (
          <p role="status" className="text-sm font-medium text-emerald-700">
            Merci ! Votre avis a été envoyé et sera publié après modération par la boutique.
          </p>
        ) : (
          <form onSubmit={submit} className="space-y-3">
            <p className="text-sm font-semibold text-stone-900">Donner votre avis</p>
            <div className="flex items-center gap-1" role="radiogroup" aria-label="Note sur 5">
              {[1, 2, 3, 4, 5].map((value) => (
                <button
                  key={value}
                  type="button"
                  role="radio"
                  aria-checked={rating === value}
                  aria-label={`${value} étoile${value > 1 ? "s" : ""}`}
                  onClick={() => setRating(value)}
                  className="rounded-full p-1 transition hover:bg-amber-100"
                >
                  <Star
                    aria-hidden="true"
                    className={`h-6 w-6 ${
                      value <= rating ? "fill-amber-500 text-amber-500" : "text-stone-300"
                    }`}
                  />
                </button>
              ))}
            </div>
            <textarea
              value={comment}
              onChange={(event) => setComment(event.target.value)}
              maxLength={2000}
              rows={3}
              placeholder="Votre commentaire (optionnel)…"
              aria-label="Commentaire"
              className="w-full rounded-xl border border-stone-300 bg-white px-3.5 py-2.5 text-sm text-stone-900 transition focus:border-amber-500"
            />
            {formError ? (
              <p role="alert" className="rounded-xl bg-red-50 px-3.5 py-2.5 text-sm text-red-700">
                {formError}
              </p>
            ) : null}
            <button
              type="submit"
              disabled={formState === "busy"}
              className="rounded-full bg-amber-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-60"
            >
              {formState === "busy" ? "Envoi…" : "Envoyer mon avis"}
            </button>
          </form>
        )}
      </div>
    </section>
  );
}
