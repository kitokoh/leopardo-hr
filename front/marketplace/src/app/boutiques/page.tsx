import type { Metadata } from "next";

import { EmptyState } from "@/components/EmptyState";
import { SellerCard } from "@/components/SellerCard";
import { fetchSellers, type PublicSeller } from "@/lib/api";

export const dynamic = "force-dynamic";

export const metadata: Metadata = {
  title: "Boutiques",
  description:
    "Toutes les boutiques présentes sur Leopardo Marché : commerçants locaux, produits publiés en ligne, livraison à domicile.",
};

export default async function SellersPage() {
  let sellers: PublicSeller[] = [];
  let errorMessage: string | null = null;
  try {
    sellers = await fetchSellers();
  } catch (error) {
    errorMessage = error instanceof Error ? error.message : "Une erreur est survenue.";
  }

  return (
    <div className="mx-auto max-w-6xl px-4 py-8">
      <header className="mb-6">
        <h1 className="text-3xl font-bold tracking-tight text-stone-900">Nos boutiques</h1>
        <p className="mt-1 text-sm text-stone-500">
          Des commerçants locaux qui préparent et livrent eux-mêmes vos commandes.
        </p>
      </header>

      {errorMessage ? (
        <EmptyState
          title="Impossible de charger les boutiques"
          description={errorMessage}
          action={{ href: "/boutiques", label: "Réessayer" }}
        />
      ) : sellers.length > 0 ? (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {sellers.map((seller) => (
            <SellerCard key={seller.slug} seller={seller} />
          ))}
        </div>
      ) : (
        <EmptyState
          title="Aucune boutique pour le moment"
          description="Les premières boutiques arrivent bientôt sur Leopardo Marché."
          action={{ href: "/", label: "Retour à l'accueil" }}
        />
      )}
    </div>
  );
}
