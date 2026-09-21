import type { Metadata } from "next";
import { headers } from "next/headers";
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

export default async function RootLayout({ children }: { children: ReactNode }) {
  // #8022 — forcer le rendu DYNAMIQUE de toutes les routes HTML : le proxy
  // (`src/proxy.ts`) émet une CSP à nonce par requête et Next n'appose le
  // nonce sur ses scripts inline que si la route est rendue dynamiquement.
  // Lire `headers()` est le mécanisme canonique (même précédent que #3807
  // dans front/web et que #7841/`cookies()` dans front/travel-web). Le
  // nonce n'est pas consommé ici : aucun script inline maison n'existe.
  await headers();

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
