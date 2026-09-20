"use client";

import { Check, ShoppingBag } from "lucide-react";
import { useEffect, useRef, useState } from "react";

import { QuantityInput } from "@/components/QuantityInput";
import type { PublicProduct } from "@/lib/api";
import { addToCart } from "@/lib/cart";

interface AddToCartButtonProps {
  product: PublicProduct;
}

/** Sélecteur de quantité + bouton d'ajout au panier avec feedback visuel. */
export function AddToCartButton({ product }: AddToCartButtonProps) {
  const [quantity, setQuantity] = useState(1);
  const [added, setAdded] = useState(false);
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    return () => {
      if (timer.current) clearTimeout(timer.current);
    };
  }, []);

  if (!product.available) {
    return (
      <p className="rounded-2xl bg-stone-100 px-4 py-3 text-sm font-medium text-stone-500">
        Ce produit est momentanément épuisé.
      </p>
    );
  }

  const handleAdd = () => {
    addToCart(product, quantity);
    setAdded(true);
    if (timer.current) clearTimeout(timer.current);
    timer.current = setTimeout(() => setAdded(false), 2000);
  };

  return (
    <div className="flex flex-wrap items-center gap-4">
      <QuantityInput value={quantity} onChange={setQuantity} />
      <button
        type="button"
        onClick={handleAdd}
        className={`inline-flex h-12 items-center gap-2 rounded-full px-6 text-sm font-semibold text-white shadow-sm transition ${
          added ? "bg-emerald-600" : "bg-amber-600 hover:bg-amber-700 active:scale-[0.98]"
        }`}
      >
        {added ? (
          <>
            <Check aria-hidden="true" className="h-5 w-5" />
            Ajouté au panier
          </>
        ) : (
          <>
            <ShoppingBag aria-hidden="true" className="h-5 w-5" />
            Ajouter au panier
          </>
        )}
      </button>
      <span role="status" aria-live="polite" className="sr-only">
        {added ? "Produit ajouté au panier" : ""}
      </span>
    </div>
  );
}
