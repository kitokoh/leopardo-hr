"use client";

import { Star } from "lucide-react";
import { useId, useState } from "react";

import { ApiError, submitReview } from "@/lib/api";

interface ReviewFormProps {
  token: string;
  orderReference: string;
  productId: number;
  productName: string;
  onDone: () => void;
  onUnauthorized: (error: unknown) => boolean;
}

const ERROR_MESSAGES: Record<string, string> = {
  ORDER_NOT_DELIVERED: "Cette commande n'est pas encore livrée.",
  PRODUCT_NOT_IN_ORDER: "Ce produit ne fait pas partie de cette commande.",
  ALREADY_REVIEWED: "Vous avez déjà laissé un avis pour ce produit sur cette commande.",
};

function messageFor(error: unknown): string {
  if (error instanceof ApiError && typeof error.payload === "object" && error.payload !== null) {
    const payload = error.payload as { errors?: Record<string, unknown> };
    for (const value of Object.values(payload.errors ?? {})) {
      if (Array.isArray(value) && typeof value[0] === "string" && ERROR_MESSAGES[value[0]]) {
        return ERROR_MESSAGES[value[0]];
      }
    }
  }
  if (error instanceof ApiError) return error.message;
  return "Impossible d'envoyer l'avis. Veuillez réessayer.";
}

/**
 * Formulaire d'avis vérifié (#7814) — accessible depuis l'historique des
 * commandes quand la commande est livrée. Note 1..5 obligatoire,
 * commentaire optionnel (≤ 1000 caractères).
 */
export function ReviewForm({
  token,
  orderReference,
  productId,
  productName,
  onDone,
  onUnauthorized,
}: ReviewFormProps) {
  const headingId = useId();
  const [rating, setRating] = useState(0);
  const [comment, setComment] = useState("");
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [sent, setSent] = useState(false);

  const submit = async () => {
    if (rating < 1 || pending) return;
    setPending(true);
    setError(null);
    try {
      await submitReview(token, {
        order_reference: orderReference,
        product_id: productId,
        rating,
        ...(comment.trim().length > 0 ? { comment: comment.trim() } : {}),
      });
      setSent(true);
      onDone();
    } catch (err) {
      if (onUnauthorized(err)) return;
      setError(messageFor(err));
    } finally {
      setPending(false);
    }
  };

  if (sent) {
    return (
      <p role="status" className="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
        Merci ! Votre avis sur « {productName} » a bien été publié.
      </p>
    );
  }

  return (
    <form
      aria-labelledby={headingId}
      onSubmit={(event) => {
        event.preventDefault();
        void submit();
      }}
      className="flex flex-col gap-3 rounded-xl border border-stone-200 bg-stone-50 p-4"
    >
      <p id={headingId} className="text-sm font-semibold text-stone-900">
        Votre avis sur « {productName} »
      </p>

      <div role="radiogroup" aria-label="Note sur 5" className="flex items-center gap-1">
        {[1, 2, 3, 4, 5].map((value) => (
          <button
            key={value}
            type="button"
            role="radio"
            aria-checked={rating === value}
            aria-label={`${value} étoile${value > 1 ? "s" : ""}`}
            onClick={() => setRating(value)}
            className="rounded-md p-1 transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500"
          >
            <Star
              aria-hidden="true"
              className={`h-6 w-6 ${
                rating >= value ? "fill-amber-400 text-amber-400" : "fill-stone-200 text-stone-200"
              }`}
            />
          </button>
        ))}
      </div>

      <label className="flex flex-col gap-1 text-sm text-stone-700">
        Commentaire (optionnel)
        <textarea
          value={comment}
          onChange={(event) => setComment(event.target.value)}
          maxLength={1000}
          rows={3}
          placeholder="Qualité, livraison, conformité…"
          className="rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm text-stone-900 placeholder:text-stone-400 focus:border-amber-500 focus:outline-none"
        />
      </label>

      {error ? (
        <p role="alert" className="text-sm text-rose-600">
          {error}
        </p>
      ) : null}

      <button
        type="submit"
        disabled={rating < 1 || pending}
        className="self-start rounded-full bg-amber-600 px-5 py-2 text-sm font-semibold text-white transition hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-50"
      >
        {pending ? "Envoi…" : "Publier l'avis"}
      </button>
    </form>
  );
}
