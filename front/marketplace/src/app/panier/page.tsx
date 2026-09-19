"use client";

import { ShoppingBag, Store, Trash2 } from "lucide-react";
import Link from "next/link";

import { EmptyState } from "@/components/EmptyState";
import { Price } from "@/components/Price";
import { ProductImage } from "@/components/ProductImage";
import { QuantityInput } from "@/components/QuantityInput";
import { useCart } from "@/hooks/useCart";

/**
 * Panier — localStorage (`leopardo_marche_cart`), groupé PAR boutique :
 * côté API, 1 commande = 1 vendeur, le checkout crée donc une commande par
 * groupe affiché ici.
 */
export default function CartPage() {
  const { ready, groups, count, setQuantity, remove, removeSeller } = useCart();

  if (!ready) {
    return (
      <div className="mx-auto max-w-4xl px-4 py-8">
        <div className="h-9 w-48 animate-pulse rounded-lg bg-stone-200" />
        <div className="mt-6 h-40 animate-pulse rounded-2xl bg-stone-100" />
      </div>
    );
  }

  if (groups.length === 0) {
    return (
      <div className="mx-auto max-w-4xl px-4 py-8">
        <h1 className="mb-6 text-3xl font-bold tracking-tight text-stone-900">Mon panier</h1>
        <EmptyState
          icon={ShoppingBag}
          title="Votre panier est vide"
          description="Parcourez le catalogue et ajoutez vos premiers articles : aucun compte n'est nécessaire."
          action={{ href: "/produits", label: "Découvrir les produits" }}
        />
      </div>
    );
  }

  const grandTotals = new Map<string, number>();
  for (const group of groups) {
    grandTotals.set(
      group.currency,
      (grandTotals.get(group.currency) ?? 0) + group.subtotalMinor,
    );
  }

  return (
    <div className="mx-auto max-w-4xl px-4 py-8">
      <h1 className="mb-1 text-3xl font-bold tracking-tight text-stone-900">Mon panier</h1>
      <p className="mb-6 text-sm text-stone-500">
        {count} article{count > 1 ? "s" : ""} · {groups.length} boutique
        {groups.length > 1 ? "s" : ""}
        {groups.length > 1 ? " — une commande sera créée par boutique." : ""}
      </p>

      <div className="space-y-6">
        {groups.map((group) => (
          <section
            key={group.seller.slug}
            aria-label={`Articles de ${group.seller.name}`}
            className="overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm"
          >
            <header className="flex items-center justify-between gap-3 border-b border-stone-100 bg-stone-50/60 px-5 py-3">
              <Link
                href={`/boutiques/${group.seller.slug}`}
                className="flex items-center gap-2 text-sm font-semibold text-stone-900 transition hover:text-amber-700"
              >
                <Store aria-hidden="true" className="h-4 w-4 text-amber-600" />
                {group.seller.name}
                {group.seller.city ? (
                  <span className="font-normal text-stone-400">· {group.seller.city}</span>
                ) : null}
              </Link>
              <button
                type="button"
                onClick={() => removeSeller(group.seller.slug)}
                className="text-xs font-medium text-stone-400 transition hover:text-red-600"
              >
                Vider cette boutique
              </button>
            </header>

            <ul className="divide-y divide-stone-100">
              {group.items.map((item) => (
                <li key={item.productId} className="flex gap-4 p-5">
                  <ProductImage
                    src={item.imageUrl}
                    alt={item.name}
                    className="h-20 w-20 shrink-0 rounded-xl"
                    sizes="80px"
                  />
                  <div className="flex min-w-0 flex-1 flex-col gap-2">
                    <div className="flex items-start justify-between gap-3">
                      <Link
                        href={`/produits/${item.productId}`}
                        className="line-clamp-2 text-sm font-medium text-stone-900 transition hover:text-amber-700"
                      >
                        {item.name}
                      </Link>
                      <button
                        type="button"
                        onClick={() => remove(item.productId)}
                        aria-label={`Retirer ${item.name} du panier`}
                        className="rounded-full p-1.5 text-stone-400 transition hover:bg-red-50 hover:text-red-600"
                      >
                        <Trash2 aria-hidden="true" className="h-4 w-4" />
                      </button>
                    </div>
                    <div className="mt-auto flex flex-wrap items-center justify-between gap-3">
                      <QuantityInput
                        compact
                        value={item.quantity}
                        onChange={(quantity) => setQuantity(item.productId, quantity)}
                        label={`Quantité pour ${item.name}`}
                      />
                      <Price
                        priceMinor={item.priceMinor * item.quantity}
                        currency={item.currency}
                        className="text-sm font-semibold text-stone-900"
                      />
                    </div>
                  </div>
                </li>
              ))}
            </ul>

            <footer className="flex items-center justify-between border-t border-stone-100 px-5 py-3 text-sm">
              <span className="text-stone-500">Sous-total {group.seller.name}</span>
              <Price
                priceMinor={group.subtotalMinor}
                currency={group.currency}
                className="font-semibold text-stone-900"
              />
            </footer>
          </section>
        ))}
      </div>

      <div className="mt-8 rounded-2xl border border-amber-200 bg-amber-50 p-6">
        <div className="flex flex-wrap items-center justify-between gap-4">
          <div>
            <p className="text-sm font-medium text-stone-600">Total du panier</p>
            <p className="text-2xl font-bold text-stone-900">
              {[...grandTotals.entries()].map(([currency, total], index) => (
                <span key={currency}>
                  {index > 0 ? " + " : ""}
                  <Price priceMinor={total} currency={currency} className="" />
                </span>
              ))}
            </p>
            <p className="mt-1 text-xs text-stone-500">Paiement à la livraison, en espèces.</p>
          </div>
          <Link
            href="/commande"
            className="inline-flex h-12 items-center rounded-full bg-amber-600 px-8 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-700"
          >
            Passer la commande
          </Link>
        </div>
      </div>
    </div>
  );
}
