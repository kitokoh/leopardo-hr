import { HandCoins, Search, ShieldCheck, Truck } from "lucide-react";
import Link from "next/link";

import { EmptyState } from "@/components/EmptyState";
import { ProductCard } from "@/components/ProductCard";
import { SellerCard } from "@/components/SellerCard";
import { fetchProducts, fetchSellers, type PublicProduct, type PublicSeller } from "@/lib/api";

export const dynamic = "force-dynamic";

async function loadHomeData(): Promise<{
  products: PublicProduct[];
  sellers: PublicSeller[];
  degraded: boolean;
}> {
  const [productsResult, sellersResult] = await Promise.allSettled([
    fetchProducts({ sort: "recent", per_page: 8 }),
    fetchSellers(),
  ]);
  return {
    products: productsResult.status === "fulfilled" ? productsResult.value.data : [],
    sellers: sellersResult.status === "fulfilled" ? sellersResult.value.slice(0, 6) : [],
    degraded: productsResult.status === "rejected" && sellersResult.status === "rejected",
  };
}

const PROMISES = [
  {
    icon: HandCoins,
    title: "Paiement à la livraison",
    description: "Aucune carte bancaire : vous payez en main propre, à réception.",
  },
  {
    icon: Truck,
    title: "Livraison par la boutique",
    description: "Chaque commande est préparée et livrée directement par le commerçant.",
  },
  {
    icon: ShieldCheck,
    title: "Suivi sécurisé, sans compte",
    description: "Une référence et un jeton privé suffisent pour suivre votre commande.",
  },
] as const;

export default async function HomePage() {
  const { products, sellers, degraded } = await loadHomeData();

  return (
    <div className="mx-auto max-w-6xl px-4">
      {/* Héros */}
      <section className="relative my-8 overflow-hidden rounded-3xl bg-gradient-to-br from-amber-500 via-amber-600 to-amber-700 px-6 py-16 text-white shadow-lg sm:px-12 sm:py-20">
        <div
          aria-hidden="true"
          className="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-white/10 blur-2xl"
        />
        <div className="relative max-w-2xl space-y-6">
          <p className="text-sm font-semibold uppercase tracking-widest text-amber-100">
            Le marché des boutiques locales
          </p>
          <h1 className="text-4xl font-bold leading-tight tracking-tight sm:text-5xl">
            Vos commerçants préférés, livrés chez vous.
          </h1>
          <p className="max-w-xl text-base text-amber-50 sm:text-lg">
            Parcourez les produits publiés par les boutiques Leopardo, commandez
            en quelques clics et payez à la livraison — sans créer de compte.
          </p>
          <form action="/produits" role="search" className="relative max-w-xl">
            <Search
              aria-hidden="true"
              className="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-stone-400"
            />
            <input
              type="search"
              name="q"
              placeholder="Que cherchez-vous ? Épicerie, mode, high-tech…"
              aria-label="Rechercher un produit"
              className="h-14 w-full rounded-full border-0 bg-white pl-12 pr-32 text-sm text-stone-900 shadow-lg placeholder:text-stone-400"
            />
            <button
              type="submit"
              className="absolute right-1.5 top-1/2 h-11 -translate-y-1/2 rounded-full bg-stone-900 px-5 text-sm font-semibold text-white transition hover:bg-stone-800"
            >
              Rechercher
            </button>
          </form>
        </div>
      </section>

      {/* Promesse de valeur */}
      <section aria-label="Nos engagements" className="grid gap-4 sm:grid-cols-3">
        {PROMISES.map(({ icon: Icon, title, description }) => (
          <div
            key={title}
            className="flex items-start gap-3 rounded-2xl border border-stone-200 bg-white p-5 shadow-sm"
          >
            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700">
              <Icon aria-hidden="true" className="h-5 w-5" />
            </span>
            <div>
              <h2 className="text-sm font-semibold text-stone-900">{title}</h2>
              <p className="mt-1 text-sm text-stone-500">{description}</p>
            </div>
          </div>
        ))}
      </section>

      {/* Nouveautés */}
      <section className="mt-14">
        <div className="mb-5 flex items-end justify-between">
          <h2 className="text-2xl font-bold tracking-tight text-stone-900">Nouveautés</h2>
          <Link
            href="/produits"
            className="text-sm font-semibold text-amber-700 transition hover:text-amber-800"
          >
            Voir tout le catalogue →
          </Link>
        </div>
        {products.length > 0 ? (
          <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            {products.map((product) => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
        ) : (
          <EmptyState
            title={degraded ? "Le marché est momentanément indisponible" : "Le marché ouvre bientôt"}
            description={
              degraded
                ? "Impossible de charger les produits pour le moment. Réessayez dans quelques instants."
                : "Les premières boutiques préparent leurs rayons. Revenez très vite !"
            }
          />
        )}
      </section>

      {/* Boutiques à découvrir */}
      {sellers.length > 0 ? (
        <section className="mt-14">
          <div className="mb-5 flex items-end justify-between">
            <h2 className="text-2xl font-bold tracking-tight text-stone-900">
              Boutiques à découvrir
            </h2>
            <Link
              href="/boutiques"
              className="text-sm font-semibold text-amber-700 transition hover:text-amber-800"
            >
              Toutes les boutiques →
            </Link>
          </div>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {sellers.map((seller) => (
              <SellerCard key={seller.slug} seller={seller} />
            ))}
          </div>
        </section>
      ) : null}
    </div>
  );
}
