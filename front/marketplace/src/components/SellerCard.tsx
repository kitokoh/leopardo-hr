import { MapPin, Store } from "lucide-react";
import Link from "next/link";

import type { PublicSeller } from "@/lib/api";

interface SellerCardProps {
  seller: PublicSeller;
}

/** Carte boutique — annuaire /boutiques et section accueil. */
export function SellerCard({ seller }: SellerCardProps) {
  return (
    <article className="group relative flex flex-col gap-3 rounded-2xl border border-stone-200 bg-white p-5 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md">
      <div className="flex items-center gap-3">
        <span className="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-100 text-amber-700">
          <Store aria-hidden="true" className="h-6 w-6" />
        </span>
        <div>
          <h3 className="font-semibold text-stone-900">
            <Link
              href={`/boutiques/${seller.slug}`}
              className="after:absolute after:inset-0 after:content-['']"
            >
              {seller.name}
            </Link>
          </h3>
          {seller.city ? (
            <p className="flex items-center gap-1 text-xs text-stone-500">
              <MapPin aria-hidden="true" className="h-3.5 w-3.5" />
              {seller.city}
            </p>
          ) : null}
        </div>
      </div>
      {seller.description ? (
        <p className="line-clamp-2 text-sm text-stone-600">{seller.description}</p>
      ) : null}
      <p className="mt-auto text-xs font-medium text-amber-700">
        {seller.products_count} produit{seller.products_count > 1 ? "s" : ""} en ligne
      </p>
    </article>
  );
}
