import { MapPin, Store, Tag } from "lucide-react";
import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";

import { AddToCartButton } from "@/components/AddToCartButton";
import { FavoriteButton } from "@/components/FavoriteButton";
import { Price } from "@/components/Price";
import { ProductImage } from "@/components/ProductImage";
import { ReviewsSection } from "@/components/ReviewsSection";
import { ApiError, fetchProduct, type PublicProduct } from "@/lib/api";

export const dynamic = "force-dynamic";

type Params = Promise<{ id: string }>;

async function loadProduct(id: string): Promise<PublicProduct | null> {
  try {
    return await fetchProduct(id);
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) return null;
    throw error;
  }
}

export async function generateMetadata({ params }: { params: Params }): Promise<Metadata> {
  const { id } = await params;
  try {
    const product = await fetchProduct(id);
    const description =
      product.description ??
      `${product.name} — proposé par ${product.seller.name} sur Leopardo Marché.`;
    return {
      title: product.name,
      description,
      openGraph: {
        title: product.name,
        description,
        ...(product.image_url ? { images: [{ url: product.image_url }] } : {}),
      },
    };
  } catch {
    return { title: "Produit" };
  }
}

export default async function ProductPage({ params }: { params: Params }) {
  const { id } = await params;
  const product = await loadProduct(id);
  if (!product) notFound();

  return (
    <div className="mx-auto max-w-6xl px-4 py-8">
      <nav aria-label="Fil d'Ariane" className="mb-6 text-sm text-stone-500">
        <Link href="/produits" className="transition hover:text-amber-700">
          Produits
        </Link>
        <span aria-hidden="true" className="mx-2">/</span>
        <span className="text-stone-900">{product.name}</span>
      </nav>

      <div className="grid gap-8 lg:grid-cols-2">
        <ProductImage
          src={product.image_url}
          alt={product.name}
          className="aspect-square w-full rounded-3xl border border-stone-200 shadow-sm"
          sizes="(max-width: 1024px) 100vw, 50vw"
          priority
        />

        <div className="flex flex-col gap-5">
          <div className="flex flex-wrap items-center gap-2">
            {product.category ? (
              <span className="inline-flex items-center gap-1 rounded-full bg-stone-100 px-3 py-1 text-xs font-medium text-stone-600">
                <Tag aria-hidden="true" className="h-3.5 w-3.5" />
                {product.category.name}
              </span>
            ) : null}
            {product.available ? (
              <span className="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                Disponible
              </span>
            ) : (
              <span className="rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold text-stone-500">
                Épuisé
              </span>
            )}
          </div>

          <h1 className="text-3xl font-bold tracking-tight text-stone-900">{product.name}</h1>

          <Price
            priceMinor={product.price_minor}
            currency={product.currency}
            className="text-3xl font-bold text-amber-700"
          />

          {product.description ? (
            <p className="whitespace-pre-line text-stone-600">{product.description}</p>
          ) : null}

          <AddToCartButton product={product} />

          <FavoriteButton target={{ target_type: "product", product_id: product.id }} />

          <Link
            href={`/boutiques/${product.seller.slug}`}
            className="group mt-2 flex items-center gap-3 rounded-2xl border border-stone-200 bg-white p-4 shadow-sm transition hover:border-amber-300"
          >
            <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-100 text-amber-700">
              <Store aria-hidden="true" className="h-5 w-5" />
            </span>
            <span>
              <span className="block text-sm font-semibold text-stone-900 group-hover:text-amber-700">
                {product.seller.name}
              </span>
              {product.seller.city ? (
                <span className="flex items-center gap-1 text-xs text-stone-500">
                  <MapPin aria-hidden="true" className="h-3.5 w-3.5" />
                  {product.seller.city}
                </span>
              ) : (
                <span className="text-xs text-stone-500">Voir la boutique</span>
              )}
            </span>
          </Link>

          <p className="text-xs text-stone-400">
            Paiement à la livraison · Commande préparée et livrée par la boutique.
          </p>
        </div>
      </div>

      <ReviewsSection target={{ target_type: "product", product_id: product.id }} />
    </div>
  );
}
