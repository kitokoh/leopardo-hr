import type { Metadata } from "next";
import type { ReactNode } from "react";

import { Footer } from "@/components/Footer";
import { Header } from "@/components/Header";

import "./globals.css";

export const metadata: Metadata = {
  title: {
    default: "Leopardo Marché — le marché en ligne des boutiques locales",
    template: "%s · Leopardo Marché",
  },
  description:
    "Découvrez et commandez les produits des boutiques Leopardo : recherche, panier multi-boutiques, paiement à la livraison et suivi de commande.",
  openGraph: {
    siteName: "Leopardo Marché",
    type: "website",
    locale: "fr_FR",
    title: "Leopardo Marché — le marché en ligne des boutiques locales",
    description:
      "Découvrez et commandez les produits des boutiques Leopardo : paiement à la livraison, suivi de commande sécurisé.",
  },
};

/**
 * #8022 (tranche 1) — rendu dynamique de TOUTES les routes : la CSP à nonce
 * strict émise par `src/proxy.ts` exige que chaque page HTML soit rendue à
 * la requête pour que Next appose le nonce sur ses scripts inline
 * (bootstrap, flight data). Les pages catalogue étaient déjà
 * `force-dynamic` (données API en `no-store`) ; cette déclaration étend le
 * comportement aux pages clientes (panier, compte…) auparavant
 * pré-rendues — leur contenu utile arrivait déjà côté client, le coût de
 * rendu ne change donc pas en pratique.
 */
export const dynamic = "force-dynamic";

export default function RootLayout({ children }: { children: ReactNode }) {
  return (
    <html lang="fr">
      <body className="flex min-h-screen flex-col">
        <Header />
        <main className="flex-1">{children}</main>
        <Footer />
      </body>
    </html>
  );
}
