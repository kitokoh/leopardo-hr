"use client";

/**
 * Mes favoris — /compte/favoris (#7814).
 *
 * Liste des produits favoris du compte acheteur (DTO produits publics,
 * GET /public/market/account/favorites) avec retrait direct. Les produits
 * dépubliés sont filtrés côté serveur (fail-closed).
 */

import { Heart, Loader2 } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useCallback, useEffect, useState } from "react";

import { EmptyState } from "@/components/EmptyState";
import { Price } from "@/components/Price";
import { ProductImage } from "@/components/ProductImage";
import { RatingStars } from "@/components/RatingStars";
import { useBuyer } from "@/hooks/useBuyer";
import { fetchFavorites, removeFavorite, type PublicProduct } from "@/lib/api";

export default function AccountFavoritesPage() {
  const router = useRouter();
  const { ready, session, handleUnauthorized } = useBuyer();
  const [favorites, setFavorites] = useState<PublicProduct[] | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [removing, setRemoving] = useState<number | null>(null);

  const load = useCallback(
    async () => {
      setError(null);
      try {
        // #8096 — le cookie HttpOnly porte la session (plus de jeton).
        setFavorites(await fetchFavorites());
      } catch (err) {
        if (handleUnauthorized(err)) {
          router.push("/compte");
          return;
        }
        setError(err instanceof Error ? err.message : "Une erreur est survenue.");
      }
    },
    [handleUnauthorized, router],
  );

  useEffect(() => {
    if (!ready) return;
    if (!session) {
      router.push("/compte");
      return;
    }
    // Fetch-au-montage légitime (synchronisation avec l'API) : la règle
    // « React Compiler readiness » flaguerait le setError synchrone.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    void load();
  }, [ready, session, router, load]);

  const remove = async (productId: number) => {
    if (!session || removing !== null) return;
    setRemoving(productId);
    try {
      await removeFavorite(productId);
      setFavorites((current) =>
        current === null ? current : current.filter((product) => product.id !== productId),
      );
    } catch (err) {
      if (handleUnauthorized(err)) router.push("/compte");
    } finally {
      setRemoving(null);
    }
  };

  if (!ready || (session && favorites === null && error === null)) {
    return (
      <div className="mx-auto flex max-w-3xl items-center justify-center px-4 py-24" aria-busy="true">
        <Loader2 aria-hidden="true" className="h-6 w-6 animate-spin text-amber-600" />
        <span className="sr-only">Chargement de vos favoris…</span>
      </div>
    );
  }

  if (!session) return null;

  return (
    <div className="mx-auto max-w-3xl px-4 py-10">
      <nav aria-label="Fil d'Ariane" className="mb-6 text-sm text-stone-500">
        <Link href="/compte" className="transition hover:text-amber-700">
          Mon compte
        </Link>
        <span aria-hidden="true" className="mx-2">/</span>
        <span className="text-stone-900">Mes favoris</span>
      </nav>

      <h1 className="text-2xl font-bold tracking-tight text-stone-900">Mes favoris</h1>

      {error ? (
        <div className="mt-8">
          <EmptyState
            title="Impossible de charger vos favoris"
            description={error}
            action={{ href: "/compte/favoris", label: "Réessayer" }}
          />
        </div>
      ) : favorites !== null && favorites.length === 0 ? (
        <div className="mt-8">
          <EmptyState
            icon={Heart}
            title="Aucun favori pour l'instant"
            description="Touchez le cœur sur un produit pour le retrouver ici."
            action={{ href: "/produits", label: "Découvrir les produits" }}
          />
        </div>
      ) : (
        <ul className="mt-8 flex flex-col gap-3">
          {(favorites ?? []).map((product) => (
            <li
              key={product.id}
              className="flex items-center gap-4 rounded-2xl border border-stone-200 bg-white p-3 shadow-sm"
            >
              <ProductImage
                src={product.image_url}
                alt={product.name}
                className="h-20 w-20 shrink-0 rounded-xl"
              />
              <div className="min-w-0 flex-1">
                <Link
                  href={`/produits/${product.id}`}
                  className="line-clamp-2 text-sm font-semibold text-stone-900 transition hover:text-amber-700"
                >
                  {product.name}
                </Link>
                <p className="mt-0.5 text-xs text-stone-500">
                  {product.seller.name}
                  {product.seller.city ? ` · ${product.seller.city}` : ""}
                </p>
                <div className="mt-1 flex flex-wrap items-center gap-2">
                  <Price
                    priceMinor={product.price_minor}
                    currency={product.currency}
                    className="text-sm font-bold text-stone-900"
                  />
                  {product.rating_count !== undefined && product.rating_count > 0 ? (
                    <RatingStars rating={product.rating_avg ?? null} count={product.rating_count} />
                  ) : null}
                  {!product.available ? (
                    <span className="rounded-full bg-stone-100 px-2 py-0.5 text-xs font-medium text-stone-500">
                      Épuisé
                    </span>
                  ) : null}
                </div>
              </div>
              <button
                type="button"
                onClick={() => void remove(product.id)}
                disabled={removing === product.id}
                aria-label={`Retirer ${product.name} des favoris`}
                className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-rose-500 transition hover:bg-rose-50 disabled:opacity-50"
              >
                <Heart aria-hidden="true" className="h-5 w-5 fill-rose-500" />
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
