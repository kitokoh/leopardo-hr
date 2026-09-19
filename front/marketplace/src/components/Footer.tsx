import { PawPrint } from "lucide-react";
import Link from "next/link";

/** Pied de page complet : navigation, engagements, mentions. */
export function Footer() {
  return (
    <footer className="mt-16 border-t border-stone-200 bg-white">
      <div className="mx-auto grid max-w-6xl gap-10 px-4 py-12 sm:grid-cols-2 lg:grid-cols-4">
        <div className="space-y-3">
          <p className="flex items-center gap-2 text-lg font-bold text-stone-900">
            <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-500 text-white">
              <PawPrint aria-hidden="true" className="h-4 w-4" />
            </span>
            Leopardo <span className="text-amber-600">Marché</span>
          </p>
          <p className="text-sm text-stone-500">
            La vitrine en ligne des boutiques Leopardo : des commerçants
            locaux, un seul panier, la livraison à domicile.
          </p>
        </div>

        <nav aria-label="Acheter" className="space-y-3">
          <h2 className="text-sm font-semibold uppercase tracking-wide text-stone-900">Acheter</h2>
          <ul className="space-y-2 text-sm text-stone-600">
            <li><Link href="/produits" className="transition hover:text-amber-700">Tous les produits</Link></li>
            <li><Link href="/boutiques" className="transition hover:text-amber-700">Toutes les boutiques</Link></li>
            <li><Link href="/panier" className="transition hover:text-amber-700">Mon panier</Link></li>
          </ul>
        </nav>

        <nav aria-label="Commandes" className="space-y-3">
          <h2 className="text-sm font-semibold uppercase tracking-wide text-stone-900">Commandes</h2>
          <ul className="space-y-2 text-sm text-stone-600">
            <li><Link href="/suivi" className="transition hover:text-amber-700">Suivre ma commande</Link></li>
            <li><Link href="/confirmation" className="transition hover:text-amber-700">Mes commandes récentes</Link></li>
          </ul>
        </nav>

        <div className="space-y-3">
          <h2 className="text-sm font-semibold uppercase tracking-wide text-stone-900">Nos engagements</h2>
          <ul className="space-y-2 text-sm text-stone-600">
            <li>Paiement à la livraison, sans compte</li>
            <li>Suivi de commande par jeton sécurisé</li>
            <li>Données personnelles minimales</li>
          </ul>
        </div>
      </div>
      <div className="border-t border-stone-100 py-5">
        <p className="mx-auto max-w-6xl px-4 text-xs text-stone-400">
          © {new Date().getFullYear()} Leopardo Marché — un service Leopardo. Les
          commandes sont préparées et livrées par chaque boutique.
        </p>
      </div>
    </footer>
  );
}
