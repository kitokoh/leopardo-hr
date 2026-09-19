import { MapPin, Store } from "lucide-react";
import type { Metadata } from "next";
import { notFound } from "next/navigation";

import { EmptyState } from "@/components/EmptyState";
import { FavoriteButton } from "@/components/FavoriteButton";
import { Pagination } from "@/components/Pagination";
import { ProductCard } from "@/components/ProductCard";
import { ReviewsSection } from "@/components/ReviewsSection";
import { ApiError, fetchProducts, fetchSeller, type PublicSeller } from "@/lib/api";

export const dynamic = "force-dynamic";

type Params = Promise<{ slug: string }>;
type SearchParams = Promise<Record<string, string | string[] | undefined>>;

async function loadSeller(slug: string): Promise<PublicSeller | null> {
  try {
    return await fetchSeller(slug);
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) return null;
    throw error;
  }
}

export async function generateMetadata({ params }: { params: Params }): Promise<Metadata> {
  const { slug } = await params;
  try {
    const seller = await fetchSeller(slug);
    const description =
      seller.description ??
      `Découvrez les produits de ${seller.name}${seller.city ? ` (${seller.city})` : ""} sur Leopardo Marché.`;
    return {
      title: seller.name,
      description,
      openGraph: { title: seller.name, description },
    };
  } catch {
    return { title: "Boutique" };
  }
}

export default async function SellerPage({
  params,
  searchParams,
}: {
  params: Params;
  searchParams: SearchParams;
}) {
  const { slug } = await params;
  const query = await searchParams;
  const rawPage = Array.isArray(query.page) ? query.page[0] : query.page;
  const parsedPage = Number(rawPage);
  const page = Number.isFinite(parsedPage) && parsedPage >= 1 ? Math.floor(parsedPage) : 1;

  const seller = await loadSeller(slug);
  if (!seller) notFound();

  let products: Awaited<ReturnType<typeof fetchProducts>> | null = null;
  let errorMessage: string | null = null;
  try {
    products = await fetchProducts({ seller: slug, sort: "recent", page, per_page: 24 });
  } catch (error) {
    errorMessage = error instanceof Error ? error.message : "Une erreur est survenue.";
  }

  return (
    <div className="mx-auto max-w-6xl px-4 py-8">
      {/* Vitrine */}
      <header className="mb-8 flex flex-col gap-4 rounded-3xl border border-stone-200 bg-white p-6 shadow-sm sm:flex-row sm:items-center sm:gap-6 sm:p-8">
        <span className="flex h-16 w-16 shrink-0 items-center justify-center rounded-3xl bg-amber-100 text-amber-700">
          <Store aria-hidden="true" className="h-8 w-8" />
        </span>
        <div>
          <h1 className="text-3xl font-bold tracking-tight text-stone-900">{seller.name}</h1>
          <div className="mt-1 flex flex-wrap items-center gap-3 text-sm text-stone-500">
            {seller.city ? (
              <span className="flex items-center gap-1">
                <MapPin aria-hidden="true" className="h-4 w-4" />
                {seller.city}
              </span>
            ) : null}
            <span>
              {seller.products_count} produit{seller.products_count > 1 ? "s" : ""} en ligne
            </span>
          </div>
          {seller.description ? (
            <p className="mt-3 max-w-2xl text-sm text-stone-600">{seller.description}</p>
          ) : null}
          <FavoriteButton
            target={{ target_type: "seller", seller: slug }}
            className="mt-3"
          />
        </div>
      </header>

      <h2 className="mb-5 text-xl font-bold tracking-tight text-stone-900">
        Les produits de la boutique
      </h2>

      {errorMessage ? (
        <EmptyState
          title="Impossible de charger les produits"
          description={errorMessage}
          action={{ href: `/boutiques/${slug}`, label: "Réessayer" }}
        />
      ) : products && products.data.length > 0 ? (
        <>
          <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            {products.data.map((product) => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
          <div className="mt-10">
            <Pagination
              page={products.page}
              lastPage={products.lastPage}
              hrefFor={(target) =>
                `/boutiques/${slug}${target > 1 ? `?page=${target}` : ""}`
              }
            />
          </div>
        </>
      ) : (
        <EmptyState
          title="Cette boutique n'a pas encore de produits en ligne"
          description="Revenez bientôt, ou découvrez les autres boutiques du marché."
          action={{ href: "/boutiques", label: "Voir les autres boutiques" }}
        />
      )}

      <ReviewsSection target={{ target_type: "seller", seller: slug }} />
    </div>
  );
}
