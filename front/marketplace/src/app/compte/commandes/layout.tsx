import type { Metadata } from "next";
import type { ReactNode } from "react";

export const metadata: Metadata = {
  title: "Mes commandes",
  description:
    "Historique de vos commandes Leopardo Marché : statut de livraison, suivi et avis après livraison.",
  robots: { index: false },
};

export default function Layout({ children }: { children: ReactNode }) {
  return children;
}
