"use client";

import { PawPrint, Search, ShoppingBag, UserRound } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState, type FormEvent } from "react";

import { useCart } from "@/hooks/useCart";

/**
 * En-tête sticky : logo, recherche (route vers /produits?q=), navigation
 * Boutiques / Suivi, panier avec badge compteur synchronisé localStorage.
 */
export function Header() {
  const router = useRouter();
  const { ready, count } = useCart();
  const [query, setQuery] = useState("");

  const submitSearch = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const trimmed = query.trim();
    router.push(trimmed ? `/produits?q=${encodeURIComponent(trimmed)}` : "/produits");
  };

  return (
    <header className="sticky top-0 z-40 border-b border-stone-200/80 bg-white/90 backdrop-blur">
      <div className="mx-auto flex h-16 max-w-6xl items-center gap-3 px-4 sm:gap-6">
        <Link
          href="/"
          className="flex shrink-0 items-center gap-2 text-lg font-bold tracking-tight text-stone-900"
        >
          <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-500 text-white shadow-sm">
            <PawPrint aria-hidden="true" className="h-5 w-5" />
          </span>
          <span className="hidden sm:inline">
            Leopardo <span className="text-amber-600">Marché</span>
          </span>
        </Link>

        <form
          onSubmit={submitSearch}
          role="search"
          className="relative min-w-0 flex-1"
        >
          <Search
            aria-hidden="true"
            className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-stone-400"
          />
          <input
            type="search"
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            placeholder="Rechercher un produit…"
            aria-label="Rechercher un produit"
            className="h-10 w-full rounded-full border border-stone-300 bg-stone-50 pl-10 pr-4 text-sm text-stone-900 placeholder:text-stone-400 transition focus:border-amber-500 focus:bg-white"
          />
        </form>

        <nav aria-label="Navigation principale" className="flex shrink-0 items-center gap-1 sm:gap-2">
          <Link
            href="/boutiques"
            className="hidden rounded-full px-3 py-2 text-sm font-medium text-stone-700 transition hover:bg-stone-100 hover:text-stone-900 md:inline-block"
          >
            Boutiques
          </Link>
          <Link
            href="/suivi"
            className="hidden rounded-full px-3 py-2 text-sm font-medium text-stone-700 transition hover:bg-stone-100 hover:text-stone-900 md:inline-block"
          >
            Suivi de commande
          </Link>
          <Link
            href="/compte"
            aria-label="Mon compte"
            className="flex h-10 w-10 items-center justify-center rounded-full text-stone-700 transition hover:bg-amber-50 hover:text-amber-700"
          >
            <UserRound aria-hidden="true" className="h-5 w-5" />
          </Link>
          <Link
            href="/panier"
            aria-label={`Panier${ready && count > 0 ? ` (${count} article${count > 1 ? "s" : ""})` : ""}`}
            className="relative flex h-10 w-10 items-center justify-center rounded-full text-stone-700 transition hover:bg-amber-50 hover:text-amber-700"
          >
            <ShoppingBag aria-hidden="true" className="h-5 w-5" />
            {ready && count > 0 ? (
              <span className="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-amber-600 px-1 text-[11px] font-bold text-white">
                {count > 99 ? "99+" : count}
              </span>
            ) : null}
          </Link>
        </nav>
      </div>
    </header>
  );
}
