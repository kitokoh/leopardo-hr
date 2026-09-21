"use client";

import { Heart } from "lucide-react";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";

import { useBuyer } from "@/hooks/useBuyer";
import { addFavorite, fetchFavorites, removeFavorite } from "@/lib/api";

interface FavoriteButtonProps {
  productId: number;
  /** État initial connu (ex. page favoris) — évite un fetch par carte. */
  initialFavorite?: boolean;
  className?: string;
}

// Cache module-level des ids favoris : un seul GET par session de page,
// partagé entre toutes les cartes produit affichées.
let favoriteIdsPromise: Promise<Set<number>> | null = null;

function favoriteIds(): Promise<Set<number>> {
  if (favoriteIdsPromise === null) {
    favoriteIdsPromise = fetchFavorites()
      .then((products) => new Set(products.map((product) => product.id)))
      .catch(() => new Set<number>());
  }
  return favoriteIdsPromise;
}

function invalidateFavoriteIds(): void {
  favoriteIdsPromise = null;
}

/**
 * Bouton cœur favori (#7814) — sur les cartes produit et la fiche.
 * Sans session acheteur, redirige vers /compte (connexion).
 */
export function FavoriteButton({ productId, initialFavorite, className }: FavoriteButtonProps) {
  const router = useRouter();
  const { ready, session, handleUnauthorized } = useBuyer();
  const [favorite, setFavorite] = useState<boolean>(initialFavorite ?? false);
  const [pending, setPending] = useState(false);

  useEffect(() => {
    if (initialFavorite !== undefined || !session) return;
    let cancelled = false;
    void favoriteIds().then((ids) => {
      if (!cancelled) setFavorite(ids.has(productId));
    });
    return () => {
      cancelled = true;
    };
  }, [session, productId, initialFavorite]);

  if (!ready) return null;

  const toggle = async () => {
    if (!session) {
      router.push("/compte");
      return;
    }
    if (pending) return;
    setPending(true);
    const next = !favorite;
    setFavorite(next);
    try {
      if (next) {
        await addFavorite(productId);
      } else {
        await removeFavorite(productId);
      }
      invalidateFavoriteIds();
    } catch (error) {
      setFavorite(!next);
      if (handleUnauthorized(error)) router.push("/compte");
    } finally {
      setPending(false);
    }
  };

  return (
    <button
      type="button"
      onClick={() => void toggle()}
      disabled={pending}
      aria-pressed={favorite}
      aria-label={favorite ? "Retirer des favoris" : "Ajouter aux favoris"}
      className={`z-10 flex h-9 w-9 items-center justify-center rounded-full border border-stone-200 bg-white/90 text-stone-500 shadow-sm backdrop-blur transition hover:border-rose-200 hover:text-rose-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-500 disabled:opacity-60 ${className ?? ""}`}
    >
      <Heart
        aria-hidden="true"
        className={`h-4.5 w-4.5 ${favorite ? "fill-rose-500 text-rose-500" : ""}`}
      />
    </button>
  );
}
