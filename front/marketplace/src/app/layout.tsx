import type { Metadata } from "next";
import type { ReactNode } from "react";

import { Footer } from "@/components/Footer";
import { Header } from "@/components/Header";
import { SITE_URL } from "@/lib/site";

import "./globals.css";

export const metadata: Metadata = {
  metadataBase: new URL(SITE_URL),
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
