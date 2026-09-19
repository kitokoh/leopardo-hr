import { SlidersHorizontal } from "lucide-react";
import type { Metadata } from "next";

import { EmptyState } from "@/components/EmptyState";
import { Pagination } from "@/components/Pagination";
import { ProductCard } from "@/components/ProductCard";
import {
  fetchProducts,
  fetchSellers,
  type ProductSort,
  type PublicSeller,
} from "@/lib/api";

export const dynamic = "force-dynamic";

export const metadata: Metadata = {
  title: "Catalogue",
  description:
    "Recherchez parmi tous les produits des boutiques Leopardo : filtres par prix et boutique, tri par nouveauté ou par prix.",
};

type SearchParams = Record<string, string | string[] | undefined>;

function first(value: string | string[] | undefined): string | undefined {
  if (Array.isArray(value)) return value[0];
  return value;
}

function parseSort(value: string | undefined): ProductSort {
  return value === "price_asc" || value === "price_desc" ? value : "recent";
}

const SORT_OPTIONS: { value: ProductSort; label: string }[] = [
  { value: "recent", label: "Nouveautés" },
  { value: "price_asc", label: "Prix croissant" },
  { value: "price_desc", label: "Prix décroissant" },
];

export default async function ProductsPage({
  searchParams,
}: {
  searchParams: Promise<SearchParams>;
}) {
  const params = await searchParams;
  const q = first(params.q)?.trim() ?? "";
  const seller = first(params.seller)?.trim() ?? "";
  const minPrice = first(params.min_price)?.trim() ?? "";
  const maxPrice = first(params.max_price)?.trim() ?? "";
  const sort = parseSort(first(params.sort));
  const pageParam = Number(first(params.page));
  const page = Number.isFinite(pageParam) && pageParam >= 1 ? Math.floor(pageParam) : 1;

  let sellers: PublicSeller[] = [];
  let result: Awaited<ReturnType<typeof fetchProducts>> | null = null;
  let errorMessage: string | null = null;

  const [sellersSettled, productsSettled] = await Promise.allSettled([
    fetchSellers(),
    fetchProducts({
      q: q || undefined,
      seller: seller || undefined,
      min_price: minPrice || undefined,
      max_price: maxPrice || undefined,
      sort,
      page,
      per_page: 24,
    }),
  ]);
  if (sellersSettled.status === "fulfilled") sellers = sellersSettled.value;
  if (productsSettled.status === "fulfilled") {
    result = productsSettled.value;
  } else {
    errorMessage =
      productsSettled.reason instanceof Error
        ? productsSettled.reason.message
        : "Une erreur est survenue.";
  }

  const hrefFor = (targetPage: number) => {
    const query = new URLSearchParams();
    if (q) query.set("q", q);
    if (seller) query.set("seller", seller);
    if (minPrice) query.set("min_price", minPrice);
    if (maxPrice) query.set("max_price", maxPrice);
    if (sort !== "recent") query.set("sort", sort);
    if (targetPage > 1) query.set("page", `${targetPage}`);
    const suffix = query.toString();
    return `/produits${suffix ? `?${suffix}` : ""}`;
  };

  const inputClass =
    "h-10 rounded-full border border-stone-300 bg-white px-4 text-sm text-stone-900 placeholder:text-stone-400 focus:border-amber-500";

  return (
    <div className="mx-auto max-w-6xl px-4 py-8">
      <header className="mb-6">
        <h1 className="text-3xl font-bold tracking-tight text-stone-900">
          {q ? `Résultats pour « ${q} »` : "Tous les produits"}
        </h1>
        {result ? (
          <p className="mt-1 text-sm text-stone-500">
            {result.total} produit{result.total > 1 ? "s" : ""} trouvé
            {result.total > 1 ? "s" : ""}
          </p>
        ) : null}
      </header>

      {/* Filtres — formulaire GET, fonctionne sans JavaScript. */}
      <form
        method="get"
        action="/produits"
        aria-label="Filtres du catalogue"
        className="mb-8 flex flex-wrap items-end gap-3 rounded-2xl border border-stone-200 bg-white p-4 shadow-sm"
      >
        <div className="flex min-w-40 flex-1 flex-col gap-1">
          <label htmlFor="filter-q" className="text-xs font-medium text-stone-600">
            Recherche
          </label>
          <input
            id="filter-q"
            type="search"
            name="q"
            defaultValue={q}
            placeholder="Nom, description…"
            className={inputClass}
          />
        </div>
        <div className="flex flex-col gap-1">
          <label htmlFor="filter-seller" className="text-xs font-medium text-stone-600">
            Boutique
          </label>
          <select
            id="filter-seller"
            name="seller"
            defaultValue={seller}
            className={`${inputClass} pr-8`}
          >
            <option value="">Toutes les boutiques</option>
            {sellers.map((option) => (
              <option key={option.slug} value={option.slug}>
                {option.name}
              </option>
            ))}
          </select>
        </div>
        <div className="flex flex-col gap-1">
          <label htmlFor="filter-min" className="text-xs font-medium text-stone-600">
            Prix min.
          </label>
          <input
            id="filter-min"
            type="number"
            name="min_price"
            min={0}
            step="any"
            defaultValue={minPrice}
            placeholder="0"
            className={`${inputClass} w-28`}
          />
        </div>
        <div className="flex flex-col gap-1">
          <label htmlFor="filter-max" className="text-xs font-medium text-stone-600">
            Prix max.
          </label>
          <input
            id="filter-max"
            type="number"
            name="max_price"
            min={0}
            step="any"
            defaultValue={maxPrice}
            placeholder="—"
            className={`${inputClass} w-28`}
          />
        </div>
        <div className="flex flex-col gap-1">
          <label htmlFor="filter-sort" className="text-xs font-medium text-stone-600">
            Trier par
          </label>
          <select
            id="filter-sort"
            name="sort"
            defaultValue={sort}
            className={`${inputClass} pr-8`}
          >
            {SORT_OPTIONS.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </div>
        <button
          type="submit"
          className="inline-flex h-10 items-center gap-2 rounded-full bg-amber-600 px-5 text-sm font-semibold text-white transition hover:bg-amber-700"
        >
          <SlidersHorizontal aria-hidden="true" className="h-4 w-4" />
          Filtrer
        </button>
      </form>

      {errorMessage ? (
        <EmptyState
          title="Impossible de charger le catalogue"
          description={errorMessage}
          action={{ href: hrefFor(page), label: "Réessayer" }}
        />
      ) : result && result.data.length > 0 ? (
        <>
          <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            {result.data.map((product) => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
          <div className="mt-10">
            <Pagination page={result.page} lastPage={result.lastPage} hrefFor={hrefFor} />
          </div>
        </>
      ) : (
        <EmptyState
          title="Aucun produit ne correspond à votre recherche"
          description="Essayez d'élargir vos critères : moins de filtres, une autre orthographe ou une autre gamme de prix."
          action={{ href: "/produits", label: "Réinitialiser les filtres" }}
        />
      )}
    </div>
  );
}
