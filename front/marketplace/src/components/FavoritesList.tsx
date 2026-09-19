"use client";

import { Heart, Store, Trash2 } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";

import { EmptyState } from "@/components/EmptyState";
import { Price } from "@/components/Price";
import { ProductImage } from "@/components/ProductImage";
import { useAccount } from "@/hooks/useAccount";
import { ApiError } from "@/lib/api";
import { fetchFavorites, removeFavorite, type Favorite } from "@/lib/account";

/**
 * Favoris de l'acheteur (#7814) — produits et boutiques, cibles résolues
 * publiquement (une cible retirée de la vente est signalée sans donnée
 * interne). Retrait borné au compte.
 */
export function FavoritesList() {
  const router = useRouter();
  const { ready, session } = useAccount();
  const [favorites, setFavorites] = useState<Favorite[] | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!ready) return;
    if (!session) {
      router.replace("/compte/connexion");
      return;
    }
    let cancelled = false;
    fetchFavorites()
      .then((data) => {
        if (!cancelled) setFavorites(data);
      })
      .catch((err) => {
        if (!cancelled) {
          setError(err instanceof ApiError ? err.message : "Une erreur est survenue.");
        }
      });
    return () => {
      cancelled = true;
    };
  }, [ready, session, router]);

  const remove = async (id: number) => {
    try {
      await removeFavorite(id);
      setFavorites((current) => (current ?? []).filter((favorite) => favorite.id !== id));
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Une erreur est survenue.");
    }
  };

  if (!ready || !session) {
    return (
      <div className="mx-auto max-w-4xl px-4 py-12">
        <div className="h-40 animate-pulse rounded-2xl bg-stone-100" aria-hidden="true" />
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-4xl px-4 py-8">
      <nav aria-label="Fil d'Ariane" className="mb-6 text-sm text-stone-500">
        <Link href="/compte" className="transition hover:text-amber-700">
          Mon compte
        </Link>
        <span aria-hidden="true" className="mx-2">/</span>
        <span className="text-stone-900">Favoris</span>
      </nav>

      <h1 className="flex items-center gap-2 text-2xl font-bold tracking-tight text-stone-900">
        <Heart aria-hidden="true" className="h-6 w-6 text-amber-600" />
        Mes favoris
      </h1>

      {error ? (
        <p role="alert" className="mt-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">
          {error}
        </p>
      ) : null}

      {favorites === null ? (
        <div className="mt-6 space-y-3" aria-busy="true" aria-label="Chargement des favoris">
          {[0, 1, 2].map((i) => (
            <div key={i} className="h-20 animate-pulse rounded-2xl bg-stone-100" />
          ))}
        </div>
      ) : favorites.length === 0 ? (
        <div className="mt-6">
          <EmptyState
            icon={Heart}
            title="Aucun favori pour le moment"
            description="Ajoutez des produits ou des boutiques en favoris pour les retrouver ici."
            action={{ href: "/produits", label: "Découvrir les produits" }}
          />
        </div>
      ) : (
        <ul className="mt-6 space-y-3">
          {favorites.map((favorite) => (
            <li
              key={favorite.id}
              className="flex items-center gap-4 rounded-2xl border border-stone-200 bg-white p-4 shadow-sm"
            >
              {favorite.target_type === "product" ? (
                favorite.product ? (
                  <>
                    <div className="h-16 w-16 shrink-0 overflow-hidden rounded-xl bg-stone-100">
                      <ProductImage
                        src={favorite.product.image_url}
                        alt={favorite.product.name}
                        className="h-full w-full object-cover"
                      />
                    </div>
                    <div className="min-w-0 flex-1">
                      <Link
                        href={`/produits/${favorite.product.id}`}
                        className="block truncate font-semibold text-stone-900 transition hover:text-amber-700"
                      >
                        {favorite.product.name}
                      </Link>
                      <p className="truncate text-sm text-stone-500">
                        {favorite.product.seller.name ?? "Boutique"}
                      </p>
                      <Price
                        priceMinor={favorite.product.price_minor}
                        currency={favorite.product.currency}
                        className="text-sm font-semibold text-stone-900"
                      />
                    </div>
                  </>
                ) : (
                  <p className="flex-1 text-sm text-stone-500">
                    Ce produit n&apos;est plus disponible à la vente.
                  </p>
                )
              ) : favorite.seller ? (
                <>
                  <span className="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                    <Store aria-hidden="true" className="h-7 w-7" />
                  </span>
                  <div className="min-w-0 flex-1">
                    {favorite.seller.slug ? (
                      <Link
                        href={`/boutiques/${encodeURIComponent(favorite.seller.slug)}`}
                        className="block truncate font-semibold text-stone-900 transition hover:text-amber-700"
                      >
                        {favorite.seller.name ?? "Boutique"}
                      </Link>
                    ) : (
                      <p className="truncate font-semibold text-stone-900">
                        {favorite.seller.name ?? "Boutique"}
                      </p>
                    )}
                    {favorite.seller.city ? (
                      <p className="text-sm text-stone-500">{favorite.seller.city}</p>
                    ) : null}
                  </div>
                </>
              ) : (
                <p className="flex-1 text-sm text-stone-500">
                  Cette boutique n&apos;est plus disponible.
                </p>
              )}

              <button
                type="button"
                onClick={() => remove(favorite.id)}
                aria-label="Retirer ce favori"
                className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-stone-400 transition hover:bg-red-50 hover:text-red-600"
              >
                <Trash2 aria-hidden="true" className="h-4 w-4" />
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
