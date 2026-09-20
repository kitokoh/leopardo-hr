import { Store } from "lucide-react";
import Link from "next/link";

import { FavoriteButton } from "@/components/FavoriteButton";
import { Price } from "@/components/Price";
import { ProductImage } from "@/components/ProductImage";
import { RatingStars } from "@/components/RatingStars";
import type { PublicProduct } from "@/lib/api";

interface ProductCardProps {
  product: PublicProduct;
}

/** Carte produit — grille catalogue, nouveautés, vitrine boutique. */
export function ProductCard({ product }: ProductCardProps) {
  return (
    <article className="group relative flex flex-col overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md">
      <FavoriteButton
        productId={product.id}
        className="absolute right-2.5 top-2.5"
      />
      <ProductImage
        src={product.image_url}
        alt={product.name}
        className="aspect-square w-full"
      />
      <div className="flex flex-1 flex-col gap-1.5 p-4">
        <h3 className="line-clamp-2 text-sm font-medium text-stone-900">
          <Link
            href={`/produits/${product.id}`}
            className="after:absolute after:inset-0 after:content-['']"
          >
            {product.name}
          </Link>
        </h3>
        <p className="flex items-center gap-1 text-xs text-stone-500">
          <Store aria-hidden="true" className="h-3.5 w-3.5" />
          {product.seller.name}
          {product.seller.city ? ` · ${product.seller.city}` : ""}
        </p>
        {product.rating_count !== undefined && product.rating_count > 0 ? (
          <RatingStars rating={product.rating_avg ?? null} count={product.rating_count} />
        ) : null}
        <div className="mt-auto flex items-center justify-between pt-2">
          <Price
            priceMinor={product.price_minor}
            currency={product.currency}
            className="text-base font-semibold text-stone-900"
          />
          {product.available ? (
            <span className="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">
              Disponible
            </span>
          ) : (
            <span className="rounded-full bg-stone-100 px-2 py-0.5 text-xs font-medium text-stone-500">
              Épuisé
            </span>
          )}
        </div>
      </div>
    </article>
  );
}
