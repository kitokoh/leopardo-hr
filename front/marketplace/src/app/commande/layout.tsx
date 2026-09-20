import type { Metadata } from "next";
import type { ReactNode } from "react";

export const metadata: Metadata = {
  title: "Commande",
  description: "Checkout invité Leopardo Marché : coordonnées, livraison et paiement à la livraison.",
  robots: { index: false },
};

export default function Layout({ children }: { children: ReactNode }) {
  return children;
}
